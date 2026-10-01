<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Training extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'training_date' => 'date',
        'is_quiz_active' => 'boolean',
    ];

    public function trainer()
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function participants()
    {
        return $this->hasMany(TrainingParticipant::class, 'training_id');
    }

    public function questions()
    {
        return $this->hasMany(TrainingQuestion::class, 'training_id');
    }

    public function quizResults()
    {
        return $this->hasMany(TrainingQuizResult::class, 'training_id');
    }

    public function scopeFilter($query)
    {
        if (request('search')) {
            $search = request('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('trainer', function ($t) use ($search) {
                      $t->where('full_name', 'like', "%{$search}%");
                  });
            });
        }

        if (request('status')) {
            $query->where('status', request('status'));
        }
    }
}
