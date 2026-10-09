<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Training extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'training_date' => 'date',
            'is_quiz_active' => 'boolean',
            'is_attendance_active' => 'boolean',
            'require_attendance_proof' => 'boolean',
        ];
    }

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

    public function videos()
    {
        return $this->hasMany(Video::class, 'training_id');
    }

    public function video()
    {
        return $this->hasOne(Video::class, 'training_id')->latestOfMany();
    }

    public function documents()
    {
        return $this->belongsToMany(Document::class, 'training_documents', 'training_id', 'document_id')->withTimestamps();
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
