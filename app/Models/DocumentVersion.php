<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'version_number',
        'file_name',
        'path',
        'file_size',
        'change_note',
        'created_by',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function getFileUrlAttribute()
    {
        return asset('storage/dokumen/' . $this->path);
    }
}
