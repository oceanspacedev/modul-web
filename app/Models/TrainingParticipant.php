<?php

namespace App\Models;

use App\Enums\TrainingStatus;
use App\Support\StorageUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TrainingParticipant extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attended_at' => 'datetime',
            'wa_sent_at' => 'datetime',
            'zoom_off_cam_at' => 'datetime',
            'is_off_cam' => 'boolean',
            'off_cam_count' => 'integer',
        ];
    }

    /**
     * Daftarkan user sebagai peserta baru dengan token link portal sendiri.
     */
    public static function createFor(Training $training, int $userId): self
    {
        return $training->participants()->create([
            'user_id' => $userId,
            'token' => Str::random(40).'_'.time(),
            'attendance_status' => 'pending',
        ]);
    }

    /**
     * Keluarkan peserta dari pelatihan beserta hasil pretest/posttest dan evaluasinya. Link portalnya ikut tidak
     * berlaku, dan peserta tidak lagi ikut rekap nilai maupun pengingat WhatsApp.
     */
    public function remove(): void
    {
        $proof = $this->attendance_proof;

        DB::transaction(function (): void {
            TrainingQuizResult::where('training_participant_id', $this->id)->delete();
            TrainingEvaluation::where('training_participant_id', $this->id)->delete();
            TrainingWaLog::where('training_participant_id', $this->id)->delete();
            $this->delete();
        });

        // Foto bukti presensi ikut dihapus setelah barisnya benar-benar terhapus.
        if ($proof && Storage::exists($proof)) {
            Storage::delete($proof);
        }
    }

    /**
     * Riwayat undangan WhatsApp, terbaru dulu.
     */
    public function waLogs()
    {
        return $this->hasMany(TrainingWaLog::class, 'training_participant_id')->latest('created_at')->latest('id');
    }

    /**
     * Catat satu percobaan undangan WhatsApp ke riwayat peserta.
     */
    public function logWa(string $channel, bool $success, ?string $detail = null, ?string $phone = null, ?int $sentBy = null): TrainingWaLog
    {
        return $this->waLogs()->create([
            'channel' => $channel,
            'success' => $success,
            'detail' => $detail === null ? null : Str::limit($detail, 250, ''),
            'phone' => $phone,
            'sent_by' => $sentBy,
            'created_at' => now(),
        ]);
    }

    /**
     * Hitungan riwayat undangan per peserta untuk daftar peserta, dihitung database supaya tetap benar walaupun
     * riwayat yang dimuat ke halaman dibatasi.
     *
     * @param  Builder<self>  $query
     */
    public function scopeWithWaSummary($query): void
    {
        $auto = fn (bool $success) => fn ($logs) => $logs->where('channel', TrainingWaLog::CHANNEL_AUTO)->where('success', $success);

        $query
            ->withCount([
                'waLogs as wa_auto_success_count' => $auto(true),
                'waLogs as wa_auto_failed_count' => $auto(false),
                'waLogs as wa_manual_count' => fn ($logs) => $logs->where('channel', TrainingWaLog::CHANNEL_MANUAL),
            ])
            ->withMax(['waLogs as wa_last_auto_success_at' => $auto(true)], 'created_at')
            ->withMax(['waLogs as wa_last_auto_failed_at' => $auto(false)], 'created_at');
    }

    /**
     * Ringkasan riwayat undangan: berapa kali kiriman otomatis berhasil/gagal dan kapan terakhirnya, serta berapa
     * kali kirim manual dibuka. Butuh scope withWaSummary.
     *
     * @return array{autoSuccess: int, autoFailed: int, manual: int, lastAutoSuccess: ?Carbon, lastAutoFailed: ?Carbon}
     */
    public function waSummary(): array
    {
        return [
            'autoSuccess' => (int) $this->wa_auto_success_count,
            'autoFailed' => (int) $this->wa_auto_failed_count,
            'manual' => (int) $this->wa_manual_count,
            'lastAutoSuccess' => $this->wa_last_auto_success_at ? Carbon::parse($this->wa_last_auto_success_at) : null,
            'lastAutoFailed' => $this->wa_last_auto_failed_at ? Carbon::parse($this->wa_last_auto_failed_at) : null,
        ];
    }

    /** Riwayat terbaru yang dimuat per peserta di halaman detail; hitungannya tetap dari seluruh riwayat. */
    public const WA_LOGS_SHOWN = 30;

    /** Status WhatsApp yang menandai pengiriman otomatis (WAGHub) gagal. */
    public const WA_FAILED_PREFIXES = ['Gagal', 'Error'];

    /**
     * Peserta yang undangan WhatsApp otomatisnya gagal terkirim.
     *
     * @param  Builder<self>  $query
     */
    public function scopeWaFailed($query): void
    {
        $query->where(function ($failed): void {
            foreach (self::WA_FAILED_PREFIXES as $prefix) {
                $failed->orWhere('wa_status', 'like', $prefix.'%');
            }
        });
    }

    public function waFailed(): bool
    {
        return Str::startsWith((string) $this->wa_status, self::WA_FAILED_PREFIXES);
    }

    /**
     * Status undangan WhatsApp untuk daftar peserta. Status terakhir didahulukan: kiriman ulang yang gagal tampil
     * gagal walaupun kiriman sebelumnya pernah terkirim.
     *
     * @return array{label: string, kind: 'success'|'danger'|'warning'|'idle', detail: string|null}
     */
    public function waState(): array
    {
        $sentAt = $this->wa_sent_at?->timezone(config('app.timezone'))->isoFormat('D MMM, HH:mm');

        return match (true) {
            $this->waFailed() => [
                'label' => 'Gagal kirim',
                'kind' => 'danger',
                'detail' => trim(Str::after((string) $this->wa_status, ':')).($sentAt ? " (terakhir terkirim {$sentAt})" : ''),
            ],
            $this->wa_status === 'Dalam antrean' => ['label' => 'Dalam antrean', 'kind' => 'warning', 'detail' => 'Sedang dikirim oleh WAGHub di latar belakang.'],
            $this->wa_sent_at !== null => [
                'label' => "Terkirim {$sentAt}",
                'kind' => 'success',
                'detail' => str_contains((string) $this->wa_status, 'Simulasi') ? 'Simulasi: token WAGHub belum diisi, pesan hanya dicatat di log.' : null,
            ],
            default => ['label' => 'Belum dikirim', 'kind' => 'idle', 'detail' => null],
        };
    }

    public function training()
    {
        return $this->belongsTo(Training::class, 'training_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Hasil posttest (kuis utama) peserta.
     */
    public function quizResult()
    {
        return $this->hasOne(TrainingQuizResult::class, 'training_participant_id')
            ->ofMany(['id' => 'max'], fn ($query) => $query->where('phase', Training::PHASE_POSTTEST));
    }

    public function pretestResult()
    {
        return $this->hasOne(TrainingQuizResult::class, 'training_participant_id')
            ->ofMany(['id' => 'max'], fn ($query) => $query->where('phase', Training::PHASE_PRETEST));
    }

    public function evaluation()
    {
        return $this->hasOne(TrainingEvaluation::class, 'training_participant_id');
    }

    public function resultFor(string $phase): ?TrainingQuizResult
    {
        return $phase === Training::PHASE_PRETEST ? $this->pretestResult : $this->quizResult;
    }

    /**
     * Langkah peserta berikutnya untuk daftar "Pelatihan Saya", apa pun isi pelatihannya (pretest saja, posttest saja,
     * keduanya, hanya evaluasi, atau hanya sesi). Butuh relasi training, quizResult, pretestResult, dan evaluation.
     *
     * @return array{label: string, kind: 'action'|'done'|'idle'}
     */
    public function nextStep(): array
    {
        $training = $this->training;

        if ($training->status === TrainingStatus::Cancelled) {
            return ['label' => 'Dibatalkan', 'kind' => 'idle'];
        }

        // Langkah pertama yang bisa dikerjakan sekarang, menurut urutan yang diatur pemateri. Setelah semua tugas
        // selesai hanya presensi yang masih ditagih.
        $finished = $this->hasFinishedTasks();
        foreach ($training->portalSteps() as $step) {
            $label = $this->pendingStepLabel($step);
            if ($label !== null && (! $finished || $step === Training::STEP_ATTENDANCE)) {
                return ['label' => $label, 'kind' => 'action'];
            }
        }

        return match (true) {
            $finished => ['label' => 'Selesai', 'kind' => 'done'],
            $training->status === TrainingStatus::Completed => ['label' => 'Selesai', 'kind' => 'idle'],
            // Tidak ada yang perlu dikerjakan di portal: cukup mengikuti sesinya.
            ! $training->hasQuiz() && ! $training->has_attendance && ! $training->hasEvaluationForm() => ['label' => 'Ikuti sesi', 'kind' => 'idle'],
            default => ['label' => 'Menunggu pemateri', 'kind' => 'idle'],
        };
    }

    /**
     * Yang harus dikerjakan peserta pada sebuah langkah saat ini; null bila langkah itu belum dibuka atau sudah
     * dikerjakan.
     */
    private function pendingStepLabel(string $step): ?string
    {
        $training = $this->training;
        $quizName = $training->has_pretest ? 'posttest' : 'kuis';

        return match ($step) {
            Training::STEP_ATTENDANCE => $training->isAttendanceOpen() && $this->attendance_status === 'pending' ? 'Isi presensi' : null,
            Training::STEP_PRETEST => $training->isPhaseActive(Training::PHASE_PRETEST) && $this->pretestResult === null ? 'Kerjakan pretest' : null,
            // Selama posttest terbuka, evaluasi yang wajib diisi sebelumnya ditagih lewat langkah posttest.
            Training::STEP_EVALUATION => $training->awaitsEvaluationFrom($this) && $training->isEvaluationOpen()
                && $this->pendingStepLabel(Training::STEP_POSTTEST) === null
                ? 'Isi evaluasi'
                : null,
            Training::STEP_POSTTEST => match (true) {
                ! $training->isPhaseActive(Training::PHASE_POSTTEST) || $this->quizResult !== null => null,
                $training->requiresEvaluationBeforePosttest($this) => "Isi evaluasi, lalu kerjakan {$quizName}",
                default => "Kerjakan {$quizName}",
            },
            default => null,
        };
    }

    /**
     * Semua yang diminta pelatihan ini dari peserta sudah dikerjakan: posttest, pretest (bila tanpa posttest), dan
     * form evaluasi yang ada; tanpa semuanya, cukup presensi (bila dipakai).
     */
    public function hasFinishedTasks(): bool
    {
        $training = $this->training;

        if ($training->has_posttest) {
            if ($this->quizResult === null) {
                return false;
            }

            // Form evaluasi punya jam sendiri, jadi posttest bisa selesai tanpa evaluasi (jam evaluasinya belum tiba
            // atau sudah lewat). Di urutan mana pun, form yang ada masih harus diisi.
            return ! $training->hasEvaluationForm() || $this->evaluation !== null;
        }

        $hasEvaluation = $training->hasEvaluationForm();
        if (! $training->has_pretest && ! $hasEvaluation) {
            return $training->has_attendance && $this->attendance_status !== 'pending';
        }

        return (! $training->has_pretest || $this->pretestResult !== null) && (! $hasEvaluation || $this->evaluation !== null);
    }

    public function getAttendanceProofUrlAttribute()
    {
        return $this->attendance_proof ? StorageUrl::for($this->attendance_proof) : null;
    }
}
