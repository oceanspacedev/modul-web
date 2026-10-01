<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Divisi extends Model
{
    use HasFactory,SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function user()
    {
        return $this->belongsToMany(User::class);
    }

    public function subdivisi()
    {
        return $this->hasMany(SubDivisi::class);
    }

    public function dokumen()
    {
        return $this->hasMany(Document::class,'id','divisi_id');
    }
}
