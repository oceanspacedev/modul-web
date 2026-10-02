<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingQuizResult extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'answers' => 'array',
        'violation_logs' => 'array',
        'is_force_submitted' => 'boolean',
        'tab_switch_count' => 'integer',
        'submitted_at' => 'datetime',
        'score' => 'float',
    ];

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
