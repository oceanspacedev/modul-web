<?php

namespace App\Models;

use App\Enums\TrainingStatus;
use App\Support\StorageUrl;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
     * Semua yang diminta pelatihan ini dari peserta sudah dikerjakan: posttest (yang sudah mencakup evaluasi bila
     * evaluasi diurutkan sebelumnya); selain itu pretest dan form evaluasi yang ada; tanpa semuanya, cukup presensi
     * (bila dipakai).
     */
    public function hasFinishedTasks(): bool
    {
        $training = $this->training;

        if ($training->has_posttest) {
            if ($this->quizResult === null) {
                return false;
            }

            // Evaluasi yang diurutkan sebelum posttest wajib diisi dulu, jadi posttest yang selesai sudah mencakupnya;
            // yang diurutkan sesudahnya masih harus diisi.
            return $training->evaluationFollowsPosttest() || ! $training->hasEvaluationForm() || $this->evaluation !== null;
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
