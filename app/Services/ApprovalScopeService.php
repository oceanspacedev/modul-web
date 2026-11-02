<?php

namespace App\Services;

use App\Models\User;

/**
 * Tim seorang atasan berdasarkan approval line (sama dengan DnD):
 * bawahan langsung dan satu tingkat di bawahnya, tidak rekursif tanpa batas.
 */
class ApprovalScopeService
{
    /** @var array<int, array<int>> */
    private array $memo = [];

    public static function clearMemo(): void
    {
        resolve(self::class)->memo = [];
    }

    /**
     * @return array<int>
     */
    public static function getManagedUserIdsOneLevelDown(int $supervisorId): array
    {
        return resolve(self::class)->managedUserIdsOneLevelDown($supervisorId);
    }

    /**
     * Approval line baru membentuk siklus (atasan yang dipilih ternyata bawahan record ini).
     */
    public static function createsApprovalCycle(int $recordId, int $approvalId): bool
    {
        $visited = [];
        $currentId = $approvalId;

        while ($currentId !== 0) {
            if ($currentId === $recordId) {
                return true;
            }

            if (isset($visited[$currentId])) {
                break;
            }

            $visited[$currentId] = true;
            $currentId = (int) (User::withTrashed()->whereKey($currentId)->value('approval_id') ?? 0);
        }

        return false;
    }

    /**
     * @return array<int>
     */
    private function managedUserIdsOneLevelDown(int $supervisorId): array
    {
        if (array_key_exists($supervisorId, $this->memo)) {
            return $this->memo[$supervisorId];
        }

        $directIds = User::query()
            ->where('approval_id', $supervisorId)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if (array_diff($directIds, [$supervisorId]) === []) {
            return $this->memo[$supervisorId] = [];
        }

        $secondLevelIds = User::query()
            ->whereIn('approval_id', $directIds)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        // Approval line ke diri sendiri (atau saling menyetujui) tidak menjadikan seseorang anggota timnya sendiri.
        return $this->memo[$supervisorId] = array_values(array_diff(array_unique(array_merge($directIds, $secondLevelIds)), [$supervisorId]));
    }
}
