<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
