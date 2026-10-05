<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingParticipant extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'attended_at' => 'datetime',
        'wa_sent_at' => 'datetime',
    ];

    public function training()
    {
        return $this->belongsTo(Training::class, 'training_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function quizResult()
    {
        return $this->hasOne(TrainingQuizResult::class, 'training_participant_id')->latestOfMany();
    }

    public function getAttendanceProofUrlAttribute()
    {
        return $this->attendance_proof ? asset('storage/' . $this->attendance_proof) : null;
    }
}
