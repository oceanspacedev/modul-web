<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class TrainingQuizResult extends Model
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
            'answers' => 'array',
            'violation_logs' => 'array',
            'is_force_submitted' => 'boolean',
            'tab_switch_count' => 'integer',
            'submitted_at' => 'datetime',
            'score' => 'float',
        ];
    }

    public function scopePosttest($query): void
    {
        $query->where('phase', Training::PHASE_POSTTEST);
    }

    /**
     * Hasil yang menjadi nilai pelatihannya: posttest, ditambah pretest dari pelatihan yang hanya punya pretest.
     * Dipakai angka dan grafik dashboard supaya pelatihan pretest saja ikut terhitung.
     */
    public function scopeMainPhase($query): void
    {
        $query->where(fn ($main) => $main
            ->where('phase', Training::PHASE_POSTTEST)
            ->orWhere(fn ($pretest) => $pretest
                ->where('phase', Training::PHASE_PRETEST)
                ->whereHas('training', fn ($training) => $training->where('has_posttest', false))));
    }

    /**
     * Hitung ulang hasil ini memakai kunci jawaban soal saat ini, mis. setelah pemateri memperbaiki kunci yang salah.
     * Jawaban peserta tidak berubah; yang dihitung ulang adalah benar/salah tiap soal pilihan ganda, nilai PG, dan
     * nilai akhir. Nilai essay yang sudah diberikan pemateri tetap dipakai dengan bobot yang sama seperti saat
     * dinilai. Soal yang sudah dihapus memakai penilaian lamanya.
     *
     * @param  Collection<int, TrainingQuestion>  $questions  soal pelatihan hasil ini
     * @return bool nilai atau rincian jawabannya berubah
     */
    public function regrade(Collection $questions): bool
    {
        $questions = $questions->keyBy('id');
        $answers = $this->answers ?? [];
        $mcTotal = 0;
        $essayTotal = 0;
        $correct = 0;

        foreach ($answers as $questionId => $answer) {
            if (($answer['type'] ?? null) === 'essay') {
                $essayTotal++;

                continue;
            }

            $mcTotal++;
            $question = $questions->get($questionId);
            if ($question && $question->type !== 'essay') {
                $given = $answer['user_answer'] ?? null;
                $answers[$questionId] = [
                    ...$answer,
                    'correct_answer' => $question->correct_answer,
                    'is_correct' => filled($given) && strtolower((string) $given) === strtolower((string) $question->correct_answer),
                ];
            }

            $correct += ($answers[$questionId]['is_correct'] ?? false) ? 1 : 0;
        }

        $mcScore = $mcTotal > 0 ? round(($correct / $mcTotal) * 100, 2) : 100.0;
        $score = match (true) {
            $this->essay_status !== 'graded' || $essayTotal === 0 => $mcScore,
            $mcTotal === 0 => (float) $this->essay_score,
            default => round((($mcScore * $mcTotal) + ((float) $this->essay_score * $essayTotal)) / ($mcTotal + $essayTotal), 2),
        };

        $changed = $answers != ($this->answers ?? [])
            || $correct !== (int) $this->correct_answers
            || abs($score - (float) $this->score) > 0.001
            || abs($mcScore - (float) ($this->mc_score ?? $this->score)) > 0.001;

        if ($changed) {
            $this->update(['answers' => $answers, 'correct_answers' => $correct, 'mc_score' => $mcScore, 'score' => $score]);
        }

        return $changed;
    }

    public function isPretest(): bool
    {
        return $this->phase === Training::PHASE_PRETEST;
    }

    public function training()
    {
        return $this->belongsTo(Training::class, 'training_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function participant()
    {
        return $this->belongsTo(TrainingParticipant::class, 'training_participant_id');
    }
}
