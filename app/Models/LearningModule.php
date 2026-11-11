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

    /** Aksi halaman peserta (rute learning.* untuk modul biasa). */
    public const PARTICIPANT_ACTIONS = ['show', 'quiz', 'answer', 'submit', 'attempt', 'items.show', 'items.open', 'items.complete'];

    public const MODE_FORMAL = 'formal';

    /** Mode game ala Quizizz: satu soal per layar dengan timer, jawaban dikunci per soal di server. */
    public const MODE_GAME = 'game';

    public const MODES = [self::MODE_FORMAL, self::MODE_GAME];

    public const MIN_QUESTION_SECONDS = 5;

    public const MAX_QUESTION_SECONDS = 300;

    protected $guarded = ['id'];

    protected $attributes = [
        'quiz_mode' => self::MODE_FORMAL,
        'question_seconds' => 30,
        'pretest_shows_answers' => true,
        'posttest_shows_answers' => true,
    ];

    protected function casts(): array
    {
        return [
            'passing_score' => 'integer',
            'shuffle_questions' => 'boolean',
            'question_seconds' => 'integer',
            'is_published' => 'boolean',
            'has_pretest' => 'boolean',
            'has_posttest' => 'boolean',
            'pretest_max_attempts' => 'integer',
            'posttest_max_attempts' => 'integer',
            'pretest_shows_answers' => 'boolean',
            'posttest_shows_answers' => 'boolean',
        ];
    }

    /**
     * Peserta melihat rincian jawaban (benar/salah + kunci) setelah fase ini selesai; kalau tidak, hanya nilainya.
     */
    public function showsAnswers(string $phase): bool
    {
        return $phase === LearningModuleAttempt::PHASE_PRETEST ? $this->pretest_shows_answers : $this->posttest_shows_answers;
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

    public function isGameMode(): bool
    {
        return $this->quiz_mode === self::MODE_GAME;
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
     * Halaman tempat peserta kembali setelah kuis: halaman tonton untuk video, halaman kuis/modul untuk lainnya.
     */
    public function participantUrl(): string
    {
        return $this->isOwnedByVideo()
            ? route('video.show', $this->source_id)
            : $this->url('show');
    }

    /**
     * Konteks rute: kuis video dan dokumen punya alamat sendiri (video/kuis/..., document/kuis/...) agar tidak
     * tercampur dengan menu Modul Belajar.
     */
    public function routeContext(): string
    {
        return $this->source_type ?? 'standalone';
    }

    /**
     * Nama rute untuk sebuah aksi di konteks modul ini. Modul biasa memakai learning.* (peserta) dan
     * learning-module.* (pengelola, rekap = learning-module.show); kuis video/dokumen memakai video-quiz.* /
     * document-quiz.* untuk keduanya.
     */
    public function routeName(string $action): string
    {
        return match ($this->source_type) {
            LearningModuleItem::TYPE_VIDEO => 'video-quiz.'.$action,
            LearningModuleItem::TYPE_DOCUMENT => 'document-quiz.'.$action,
            default => match (true) {
                in_array($action, self::PARTICIPANT_ACTIONS, true) => 'learning.'.$action,
                $action === 'results' => 'learning-module.show',
                default => 'learning-module.'.$action,
            },
        };
    }

    /**
     * Kebalikan routeName(): aksi dari nama rute mana pun.
     */
    public static function actionFromRouteName(string $routeName): string
    {
        return $routeName === 'learning-module.show' ? 'results' : substr($routeName, strpos($routeName, '.') + 1);
    }

    /**
     * @param  array<string, mixed>  $parameters  parameter rute selain module, mis. ['phase' => 'pretest']
     */
    public function url(string $action, array $parameters = []): string
    {
        return route($this->routeName($action), ['module' => $this, ...$parameters]);
    }

    /**
     * Menu sidebar yang aktif: kuis video/dokumen tetap di bawah menunya sendiri.
     */
    public function menuKey(string $standaloneKey): string
    {
        return match ($this->source_type) {
            LearningModuleItem::TYPE_VIDEO => 'video',
            LearningModuleItem::TYPE_DOCUMENT => 'document',
            default => $standaloneKey,
        };
    }

    /**
     * Pratinjau materi asal tanpa pretest, khusus pengelola video/dokumen.
     */
    public function previewUrl(): ?string
    {
        return match ($this->source_type) {
            LearningModuleItem::TYPE_VIDEO => route('video.show', ['video' => $this->source_id, 'preview' => 1]),
            LearningModuleItem::TYPE_DOCUMENT => $this->source?->file_url,
            default => null,
        };
    }

    /**
     * Halaman pengelolaan materi asal, untuk tombol kembali di halaman admin.
     */
    public function managementUrl(): string
    {
        return match ($this->source_type) {
            LearningModuleItem::TYPE_VIDEO => route('video.show', ['video' => $this->source_id, 'preview' => 1]),
            LearningModuleItem::TYPE_DOCUMENT => route('document.index'),
            default => route('learning-module.index'),
        };
    }

    /**
     * Breadcrumb halaman admin modul/kuis, mengikuti menu sidebar asalnya. ['Label' => url|null]
     *
     * @return array<string, string|null>
     */
    public function adminBreadcrumbs(string $current): array
    {
        $root = match ($this->source_type) {
            LearningModuleItem::TYPE_VIDEO => ['Video Materi' => route('video.index')],
            LearningModuleItem::TYPE_DOCUMENT => ['Dokumen' => route('document.index')],
            default => ['Modul Belajar' => null, 'Kelola Modul' => route('learning-module.index')],
        };
        $self = $this->isOwned() ? $this->managementUrl() : route('learning-module.show', $this);

        return $root + [$this->title => $self, $current => null];
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
     * User yang termasuk sasaran modul (aturan sama dengan scopeVisibleTo, tanpa syarat publikasi). Butuh relasi divisis dan roles.
     */
    public function targetUsers(): Builder
    {
        $divisiIds = $this->divisis->pluck('id');
        $roleIds = $this->roles->pluck('id');

        return User::query()
            ->when($divisiIds->isNotEmpty(), fn (Builder $q) => $q->whereIn('divisi_id', $divisiIds))
            ->when($roleIds->isNotEmpty(), fn (Builder $q) => $q->whereHas('roles', fn (Builder $q) => $q->whereIn('roles.id', $roleIds)));
    }

    /**
     * Hal yang masih kurang sebelum modul bisa dipublikasikan, mis. ['materi', 'soal']. Butuh relasi items.materialable.
     *
     * @return list<string>
     */
    public function missingForPublish(): array
    {
        $questionCount = $this->questions_count ?? $this->questions()->count();

        return array_values(array_filter([
            $this->availableItems()->isEmpty() ? 'materi' : null,
            $questionCount === 0 ? 'soal' : null,
        ]));
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
