<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuizQuestion extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function scopeFilter($query)
    {
        if (request('search')) {
            $query->where('question', 'like', '%' . request('search') . '%');
        }
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function options()
    {
        return $this->hasMany(QuizOption::class);
    }
}
