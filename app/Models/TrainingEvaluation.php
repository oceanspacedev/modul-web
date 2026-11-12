<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Jawaban form evaluasi seorang peserta untuk satu pelatihan (satu baris per peserta). answers berisi
 * [id pertanyaan => jawaban]: angka untuk skala, teks untuk jawaban teks dan pilihan.
 */
class TrainingEvaluation extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function answerTo(TrainingEvaluationQuestion $question): int|string|null
    {
        return $this->answers[$question->id] ?? null;
    }

    /**
     * Rata-rata jawaban skala (1-5) peserta ini; null bila tidak ada pertanyaan skala yang dijawab.
     *
     * @param  Collection<int, TrainingEvaluationQuestion>  $questions  pertanyaan pelatihannya
     */
    public function average(Collection $questions): ?float
    {
        $values = $questions->filter->isScale()
            ->map(fn (TrainingEvaluationQuestion $question) => $this->answerTo($question))
            ->filter(fn ($value) => is_numeric($value));

        return $values->isEmpty() ? null : round($values->avg(), 2);
    }

    public function training()
    {
        return $this->belongsTo(Training::class, 'training_id');
    }

    public function participant()
    {
        return $this->belongsTo(TrainingParticipant::class, 'training_participant_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
