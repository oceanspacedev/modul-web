<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * Modul Belajar: pretest, materi (video/dokumen) wajib diselesaikan, lalu posttest.
 *
 * Modul dengan source (video/dokumen) adalah kuis milik satu materi: tidak tampil di menu Modul Belajar dan dikelola
 * dari form video/dokumen tersebut. Pretest/posttest bisa dimatikan; *_max_attempts null berarti tanpa batas.
 */
class LearningModule extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'passing_score' => 'integer',
            'shuffle_questions' => 'boolean',
            'is_published' => 'boolean',
            'has_pretest' => 'boolean',
            'has_posttest' => 'boolean',
            'pretest_max_attempts' => 'integer',
            'posttest_max_attempts' => 'integer',
        ];
    }

    /**
     * Video/dokumen pemilik kuis; null untuk Modul Belajar biasa.
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function isOwned(): bool
    {
        return $this->source_type !== null;
    }

    public function isOwnedByVideo(): bool
    {
        return $this->source_type === LearningModuleItem::TYPE_VIDEO;
    }

    /**
     * Modul Belajar biasa (bukan kuis milik video/dokumen).
     */
    public function scopeStandalone(Builder $query): void
    {
        $query->whereNull('source_type');
    }

    public function hasPhase(string $phase): bool
    {
        return $phase === LearningModuleAttempt::PHASE_PRETEST ? $this->has_pretest : $this->has_posttest;
    }

    /**
     * Batas percobaan per fase; null berarti tanpa batas.
     */
    public function maxAttempts(string $phase): ?int
    {
        return $phase === LearningModuleAttempt::PHASE_PRETEST ? $this->pretest_max_attempts : $this->posttest_max_attempts;
    }

    /**
     * Halaman tempat peserta kembali setelah kuis: halaman tonton untuk video, halaman modul untuk lainnya.
     */
    public function participantUrl(): string
    {
        return $this->isOwnedByVideo()
            ? route('video.show', $this->source_id)
            : route('learning.show', $this);
    }

    /**
     * Halaman pengelolaan materi asal, untuk tombol kembali di halaman admin.
     */
    public function managementUrl(): string
    {
        return match ($this->source_type) {
            LearningModuleItem::TYPE_VIDEO => route('video.show', $this->source_id),
            LearningModuleItem::TYPE_DOCUMENT => route('document.index'),
            default => route('learning-module.index'),
        };
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Divisi sasaran; kosong berarti semua divisi.
     */
    public function divisis(): BelongsToMany
    {
        return $this->belongsToMany(Divisi::class, 'learning_module_divisi');
    }

    /**
     * Jabatan sasaran; kosong berarti semua jabatan.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'learning_module_role');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LearningModuleItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(LearningModuleQuestion::class)->orderBy('sort_order')->orderBy('id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(LearningModuleAttempt::class);
    }

    /**
     * Modul yang sudah dipublikasikan dan menyasar divisi serta jabatan user.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $roleIds = $user->roles()->pluck('roles.id');

        $query->where('is_published', true)
            ->where(fn (Builder $q) => $q->doesntHave('divisis')
                ->orWhereHas('divisis', fn (Builder $q) => $q->where('divisis.id', $user->divisi_id)))
            ->where(fn (Builder $q) => $q->doesntHave('roles')
                ->orWhereHas('roles', fn (Builder $q) => $q->whereIn('roles.id', $roleIds)));
    }

    public function isVisibleTo(User $user): bool
    {
        $this->loadMissing(['divisis', 'roles']);
        $user->loadMissing('roles');

        return $this->is_published
            && ($this->divisis->isEmpty() || $this->divisis->contains('id', $user->divisi_id))
            && ($this->roles->isEmpty() || $this->roles->pluck('id')->intersect($user->roles->pluck('id'))->isNotEmpty());
    }

    /**
     * Materi yang masih ada; video/dokumen yang sudah dihapus tidak lagi diwajibkan. Butuh relasi items.materialable.
     *
     * @return Collection<int, LearningModuleItem>
     */
    public function availableItems(): Collection
    {
        return $this->items->filter(fn (LearningModuleItem $item) => $item->materialable !== null)->values();
    }

    /**
     * Label sasaran untuk tabel admin, mis. "Semua divisi · STAFF, TEAM LEADER". Butuh relasi divisis dan roles.
     */
    public function targetLabel(): string
    {
        $divisi = $this->divisis->isEmpty() ? 'Semua divisi' : $this->divisis->pluck('name')->join(', ');
        $jabatan = $this->roles->isEmpty() ? 'Semua jabatan' : $this->roles->pluck('name')->join(', ');

        return $divisi.' · '.$jabatan;
    }
}
