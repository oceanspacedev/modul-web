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
        $quizName = $training->has_pretest ? 'posttest' : 'kuis';
        $awaitsEvaluation = $training->awaitsEvaluationFrom($this);

        return match (true) {
            $training->status === TrainingStatus::Cancelled => ['label' => 'Dibatalkan', 'kind' => 'idle'],
            $training->isAttendanceOpen() && $this->attendance_status === 'pending' => ['label' => 'Isi presensi', 'kind' => 'action'],
            $this->hasFinishedTasks() => ['label' => 'Selesai', 'kind' => 'done'],
            $training->isPhaseActive(Training::PHASE_PRETEST) && $this->pretestResult === null => ['label' => 'Kerjakan pretest', 'kind' => 'action'],
            $training->isPhaseActive(Training::PHASE_POSTTEST) => [
                'label' => $awaitsEvaluation ? "Isi evaluasi, lalu kerjakan {$quizName}" : "Kerjakan {$quizName}",
                'kind' => 'action',
            ],
            $awaitsEvaluation && $training->isEvaluationOpen() => ['label' => 'Isi evaluasi', 'kind' => 'action'],
            $training->status === TrainingStatus::Completed => ['label' => 'Selesai', 'kind' => 'idle'],
            // Tidak ada yang perlu dikerjakan di portal: cukup mengikuti sesinya.
            ! $training->hasQuiz() && ! $training->has_attendance && ! $training->hasEvaluationForm() => ['label' => 'Ikuti sesi', 'kind' => 'idle'],
            default => ['label' => 'Menunggu pemateri', 'kind' => 'idle'],
        };
    }

    /**
     * Semua yang diminta pelatihan ini dari peserta sudah dikerjakan. Dengan posttest, cukup posttestnya (evaluasi
     * diisi sebelum posttest); tanpa posttest, pretest dan form evaluasi yang ada; tanpa keduanya, cukup presensi (bila dipakai).
     */
    public function hasFinishedTasks(): bool
    {
        $training = $this->training;

        if ($training->has_posttest) {
            return $this->quizResult !== null;
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
