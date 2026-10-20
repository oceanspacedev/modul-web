<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function scopeFilter($query)
    {
        $query->where('name', 'like', '%'.request('search').'%');
    }

    public function dokumentype()
    {
        return $this->belongsTo(DokumenType::class, 'document_type', 'id');
    }

    public function divisi()
    {
        return $this->belongsTo(Divisi::class, 'divisi_id', 'id');
    }

    /**
     * Jabatan sasaran dokumen; null berarti untuk semua jabatan.
     */
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }

    public function versions()
    {
        return $this->hasMany(DocumentVersion::class, 'document_id')->orderBy('version_number', 'desc');
    }

    public function trainings()
    {
        return $this->belongsToMany(Training::class, 'training_documents', 'document_id', 'training_id')->withTimestamps();
    }
}
