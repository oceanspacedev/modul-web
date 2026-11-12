<?php

namespace App\Models;

use App\Support\TrainingEvaluationForm;
use Illuminate\Database\Eloquent\Model;

/**
 * Jawaban form evaluasi seorang peserta untuk satu pelatihan (satu baris per peserta).
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
            'ratings' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * Rata-rata semua skala yang diisi (1-5); null bila tidak ada nilai.
     */
    public function average(): ?float
    {
        $values = array_filter(array_intersect_key($this->ratings ?? [], TrainingEvaluationForm::questions()));

        return $values === [] ? null : round(array_sum($values) / count($values), 2);
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
