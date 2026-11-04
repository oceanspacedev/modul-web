<?php

namespace App\Support;

use App\Models\LearningModule;
use App\Models\LearningModuleAttempt;
use App\Models\LearningModuleItem;
use App\Models\LearningModuleItemProgress;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Posisi seorang peserta di sebuah modul: pretest -> materi -> posttest. Pretest/posttest bisa dimatikan per modul,
 * dan jumlah percobaannya dibatasi *_max_attempts modul (null = tanpa batas).
 *
 * Modul harus sudah memuat relasi items.materialable.
 */
final class LearningModuleProgress
{
    public const STAGE_PRETEST = 'pretest';

    public const STAGE_MATERIALS = 'materi';

    public const STAGE_POSTTEST = 'posttest';

    /** Materi selesai dan modul tidak punya posttest. */
    public const STAGE_DONE = 'selesai';

    /** Percobaan pretest pertama, dipakai sebagai nilai awal. */
    public readonly ?LearningModuleAttempt $pretest;

    /**
     * @param  Collection<int, LearningModuleAttempt>  $pretests  urut dari percobaan pertama
     * @param  Collection<int, LearningModuleAttempt>  $posttests  urut dari percobaan pertama
     * @param  Collection<int, int>  $completedItemIds
     * @param  Collection<int, int>  $openedItemIds
     */
    public function __construct(
        public readonly LearningModule $module,
        public readonly User $user,
        public readonly Collection $pretests,
        public readonly Collection $posttests,
        public readonly Collection $completedItemIds,
        public readonly Collection $openedItemIds,
    ) {
        $this->pretest = $pretests->first();
    }

    public static function for(LearningModule $module, User $user): self
    {
        return self::forMany(collect([$module]), $user)->get($module->id);
    }

    /**
     * Progres untuk banyak modul sekaligus dengan dua query.
     *
     * @param  Collection<int, LearningModule>  $modules
     * @return Collection<int, self> dikunci dengan id modul
     */
    public static function forMany(Collection $modules, User $user): Collection
    {
        $attempts = LearningModuleAttempt::query()
            ->whereIn('learning_module_id', $modules->pluck('id'))
            ->where('user_id', $user->id)
            ->orderBy('attempt_number')
            ->get()
            ->groupBy('learning_module_id');

        $progress = LearningModuleItemProgress::query()
            ->whereIn('learning_module_item_id', $modules->flatMap->items->pluck('id'))
            ->where('user_id', $user->id)
            ->get();

        return $modules->mapWithKeys(function (LearningModule $module) use ($user, $attempts, $progress) {
            $moduleAttempts = $attempts->get($module->id, collect());

            return [$module->id => new self(
                $module,
                $user,
                $moduleAttempts->where('phase', LearningModuleAttempt::PHASE_PRETEST)->values(),
                $moduleAttempts->where('phase', LearningModuleAttempt::PHASE_POSTTEST)->values(),
                $progress->whereNotNull('completed_at')->pluck('learning_module_item_id'),
                $progress->whereNotNull('opened_at')->pluck('learning_module_item_id'),
            )];
        });
    }

    /**
     * Progres setiap user yang sudah mulai mengerjakan modul (punya percobaan atau pernah membuka materi).
     *
     * @return Collection<int, self> urut nama peserta
     */
    public static function forParticipants(LearningModule $module): Collection
    {
        $attempts = $module->attempts()->orderBy('attempt_number')->get()->groupBy('user_id');
        $progress = LearningModuleItemProgress::query()
            ->whereIn('learning_module_item_id', $module->items->pluck('id'))
            ->get()
            ->groupBy('user_id');

        return User::withTrashed()
            ->with(['divisi', 'roles'])
            ->whereIn('id', $attempts->keys()->merge($progress->keys())->unique())
            ->orderBy('nama_lengkap')
            ->get()
            ->map(function (User $user) use ($module, $attempts, $progress) {
                $userAttempts = $attempts->get($user->id, collect());
                $userProgress = $progress->get($user->id, collect());

                return new self(
                    $module,
                    $user,
                    $userAttempts->where('phase', LearningModuleAttempt::PHASE_PRETEST)->values(),
                    $userAttempts->where('phase', LearningModuleAttempt::PHASE_POSTTEST)->values(),
                    $userProgress->whereNotNull('completed_at')->pluck('learning_module_item_id'),
                    $userProgress->whereNotNull('opened_at')->pluck('learning_module_item_id'),
                );
            });
    }

    public function hasDonePretest(): bool
    {
        return $this->pretest !== null;
    }

    /**
     * Pretest nyala tetapi belum pernah dikerjakan; materi dan posttest menunggu.
     */
    public function needsPretest(): bool
    {
        return $this->module->has_pretest && ! $this->hasDonePretest();
    }

    /**
     * @return Collection<int, LearningModuleAttempt>
     */
    public function attemptsFor(string $phase): Collection
    {
        return $phase === LearningModuleAttempt::PHASE_PRETEST ? $this->pretests : $this->posttests;
    }

    /**
     * Sisa percobaan pada fase ini; null berarti tanpa batas.
     */
    public function remainingAttempts(string $phase): ?int
    {
        $max = $this->module->maxAttempts($phase);

        return $max === null ? null : max(0, $max - $this->attemptsFor($phase)->count());
    }

    public function canTakePretest(): bool
    {
        return $this->module->has_pretest && $this->remainingAttempts(LearningModuleAttempt::PHASE_PRETEST) !== 0;
    }

    /**
     * Posttest sudah terbuka (pretest beres dan semua materi selesai), terlepas dari sisa percobaan.
     */
    public function isPosttestUnlocked(): bool
    {
        return $this->module->has_posttest && ! $this->needsPretest() && $this->hasCompletedMaterials();
    }

    public function isItemCompleted(LearningModuleItem $item): bool
    {
        return $this->completedItemIds->contains($item->id);
    }

    public function completedItemCount(): int
    {
        return $this->module->availableItems()->filter(fn (LearningModuleItem $item) => $this->isItemCompleted($item))->count();
    }

    public function totalItemCount(): int
    {
        return $this->module->availableItems()->count();
    }

    public function hasCompletedMaterials(): bool
    {
        return $this->completedItemCount() === $this->totalItemCount();
    }

    public function stage(): string
    {
        return match (true) {
            $this->needsPretest() => self::STAGE_PRETEST,
            ! $this->hasCompletedMaterials() => self::STAGE_MATERIALS,
            $this->module->has_posttest => self::STAGE_POSTTEST,
            default => self::STAGE_DONE,
        };
    }

    public function canTakePosttest(): bool
    {
        return $this->isPosttestUnlocked() && $this->remainingAttempts(LearningModuleAttempt::PHASE_POSTTEST) !== 0;
    }

    public function bestPosttest(): ?LearningModuleAttempt
    {
        return $this->posttests->sortByDesc('score')->first();
    }

    public function latestPosttest(): ?LearningModuleAttempt
    {
        return $this->posttests->last();
    }

    public function hasPassed(): bool
    {
        return $this->posttests->contains('is_passed', true);
    }

    /**
     * Selisih nilai posttest terbaik terhadap pretest; null bila salah satunya belum ada.
     */
    public function improvement(): ?float
    {
        $best = $this->bestPosttest();

        return $best && $this->pretest ? round($best->score - $this->pretest->score, 2) : null;
    }

    public function lastActivityAt(): ?Carbon
    {
        return $this->posttests->last()?->submitted_at ?? $this->pretests->last()?->submitted_at;
    }

    /**
     * Status untuk rekap admin.
     */
    public function statusLabel(): string
    {
        return match (true) {
            $this->hasPassed() => 'Lulus',
            $this->posttests->isNotEmpty() => 'Belum Lulus',
            $this->stage() === self::STAGE_DONE => 'Selesai',
            $this->stage() === self::STAGE_POSTTEST => 'Belum Posttest',
            $this->stage() === self::STAGE_MATERIALS => 'Mempelajari Materi',
            default => 'Belum Pretest',
        };
    }
}
