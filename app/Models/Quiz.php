<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quiz extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class, 'document_id', 'id');
    }

    public function getStartAttribute($value)
    {
        if ($value) {
            return Carbon::parse($value)->getPreciseTimestamp(3);
        }
    }

    public function getEndAttribute($value)
    {
        if ($value) {
            return Carbon::parse($value)->getPreciseTimestamp(3);
        }
    }
}
