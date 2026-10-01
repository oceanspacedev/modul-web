<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobLevel extends Model
{
    use HasFactory,SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function user()
    {
        return $this->hasOne(User::class,'user_id','id');
    }

    public function dokumen()
    {
        return $this->hasOne(Document::class, 'id', 'job_level_id')->withTrashed();
    }
}
