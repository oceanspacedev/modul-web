<?php

namespace App\Models;

use App\Support\StorageUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Dokumen nonaktif tersembunyi di seluruh aplikasi kecuali halaman kelola dokumen (lihat withInactive()).
     * Dokumen yang dihapus memakai soft delete (deleted_at).
     */
    protected static function booted(): void
    {
        static::addGlobalScope('active', function (Builder $query) {
            $query->where($query->qualifyColumn('is_active'), true);
        });
    }

    public function scopeWithInactive(Builder $query): void
    {
        $query->withoutGlobalScope('active');
    }

    /**
     * Route {document} tetap menemukan dokumen nonaktif (edit, aktifkan, hapus), tetapi tidak dokumen yang dihapus.
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query->withoutGlobalScope('active'), $value, $field);
    }

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

    public function getFileUrlAttribute()
    {
        return StorageUrl::for('dokumen/'.$this->path);
    }

    /**
     * Kuis pretest/posttest milik dokumen ini (LearningModule tersembunyi).
     */
    public function quizModule(): MorphOne
    {
        return $this->morphOne(LearningModule::class, 'source');
    }

    /**
     * Kuis yang sedang aktif (pretest atau posttest nyala dan sudah ada soal); butuh relasi quizModule.
     */
    public function activeQuiz(): ?LearningModule
    {
        return $this->quizModule?->is_published ? $this->quizModule : null;
    }
}
