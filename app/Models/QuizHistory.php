<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuizHistory extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class,'user_id','id');
    }

    public function quiz()
    {
        return $this->belongsTo(Quiz::class,'quiz_id');
    }

    public function getCreatedAtAttribute($value)
    {
        if ($value) {
            return Carbon::parse($value)->getPreciseTimestamp(3);
        }
    }

    public function getUpdatedAtAttribute($value)
    {
        if ($value) {
            return Carbon::parse($value)->getPreciseTimestamp(3);
        }
    }
}
