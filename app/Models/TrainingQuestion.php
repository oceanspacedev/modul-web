<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingQuestion extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function training()
    {
        return $this->belongsTo(Training::class, 'training_id');
    }
}
