<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VideoComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'video_id',
        'user_id',
        'parent_id',
        'comment',
    ];

    public function video()
    {
        return $this->belongsTo(Video::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(VideoComment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(VideoComment::class, 'parent_id')->oldest();
    }

    /**
     * Check if comment author is the trainer of this video's training
     */
    public function isTrainer(): bool
    {
        if ($this->video && $this->video->training && $this->video->training->trainer_id) {
            return $this->user_id === $this->video->training->trainer_id;
        }
        return false;
    }

    /**
     * Check if comment author is an admin
     */
    public function isAdmin(): bool
    {
        return $this->user && $this->user->job_level_id == 1;
    }
}
