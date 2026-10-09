<?php

namespace App\Models;

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

    public function quizResult()
    {
        return $this->hasOne(TrainingQuizResult::class, 'training_participant_id')->latestOfMany();
    }

    public function getAttendanceProofUrlAttribute()
    {
        return $this->attendance_proof ? asset('storage/'.$this->attendance_proof) : null;
    }
}
