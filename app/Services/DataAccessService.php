<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Akses data karyawan per badan usaha (tabel areas, kolom users.area_id), sama dengan DnD.
 *
 * - ADMIN dengan data_access 'all'      => seluruh badan usaha (super admin).
 * - ADMIN dengan data_access 'selected' => hanya badan usaha di user_area_access,
 *                                          ditambah tim (approval line) dan dirinya sendiri.
 * - Non-admin                           => tidak punya akses badan usaha; hanya timnya sendiri.
 */
class DataAccessService
{
    /** Jabatan atasan yang mengelola tim lewat approval line. */
    public const SUPERVISOR_ROLES = ['TEAM LEADER', 'COORDINATOR', 'MANAGER', 'CHIEF', 'BOD'];

    /** @var array<int, array<int>> */
    private array $areaMemo = [];

    public static function clearMemo(): void
    {
        resolve(self::class)->areaMemo = [];
        ApprovalScopeService::clearMemo();
    }

    public static function isAdmin(?User $user): bool
    {
        return $user?->isAdmin() ?? false;
    }

    /**
     * Super admin: ADMIN dengan akses seluruh badan usaha.
     */
    public static function hasGlobalAccess(?User $user): bool
    {
        return self::isAdmin($user) && $user->data_access !== User::DATA_ACCESS_SELECTED;
    }

    /**
     * Minimal satu super admin harus tersisa sebelum role ADMIN / akses seluruh badan usaha dilepas.
     */
    public static function anotherGlobalAdminExists(User $user): bool
    {
        return User::query()
            ->whereKeyNot($user->getKey())
            ->role(User::ROLE_ADMIN)
            ->where(fn (Builder $access) => $access
                ->whereNull('data_access')
                ->orWhere('data_access', '!=', User::DATA_ACCESS_SELECTED))
            ->exists();
    }

    public static function isAreaScopedAdmin(?User $user): bool
    {
        return self::isAdmin($user) && $user->data_access === User::DATA_ACCESS_SELECTED;
    }

    /**
     * @return array<int>|null null = seluruh badan usaha; [] = tidak punya akses badan usaha.
     */
    public static function allowedAreaIds(?User $user): ?array
    {
        if (self::hasGlobalAccess($user)) {
            return null;
        }

        if (! self::isAreaScopedAdmin($user)) {
            return [];
        }

        return resolve(self::class)->selectedAreaIds((int) $user->getKey());
    }

    /**
     * Atasan approval line: jabatan atasan, atau siapa pun yang punya bawahan.
     */
    public static function isTeamSupervisor(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return in_array($user->jabatan?->name, self::SUPERVISOR_ROLES, true)
            || ApprovalScopeService::getManagedUserIdsOneLevelDown((int) $user->getKey()) !== [];
    }

    public static function canAccessEmployees(?User $user): bool
    {
        return self::hasGlobalAccess($user) || self::allowedAreaIds($user) !== [];
    }

    public static function canAccessArea(?User $user, int|string|null $areaId): bool
    {
        $allowed = self::allowedAreaIds($user);

        if ($allowed === null) {
            return true;
        }

        if ($areaId === null || $areaId === '') {
            return false;
        }

        return in_array((int) $areaId, $allowed, true);
    }

    /**
     * Karyawan berada di badan usaha yang boleh diakses actor (tanpa memperhitungkan tim).
     */
    public static function isEmployeeInScope(?User $actor, User $target): bool
    {
        $allowed = self::allowedAreaIds($actor);

        if ($allowed === null) {
            return true;
        }

        return $allowed !== [] && $target->area_id !== null && in_array((int) $target->area_id, $allowed, true);
    }

    /**
     * Id user yang terlihat oleh actor: badan usaha yang diizinkan, tim, dan dirinya sendiri. null = semua user.
     *
     * @return array<int>|null
     */
    public static function visibleUserIds(?User $actor): ?array
    {
        if (self::hasGlobalAccess($actor)) {
            return null;
        }

        if (! $actor instanceof User) {
            return [];
        }

        $allowedAreaIds = self::allowedAreaIds($actor);
        $ids = $allowedAreaIds === []
            ? []
            : DB::table('users')->whereIn('area_id', $allowedAreaIds)->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        $ids = array_merge($ids, ApprovalScopeService::getManagedUserIdsOneLevelDown((int) $actor->getKey()));
        $ids[] = (int) $actor->getKey();

        return array_values(array_unique($ids));
    }

    /**
     * Batasi query users ke karyawan yang terlihat oleh actor (badan usaha, tim, diri sendiri).
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public static function scopeVisibleUsers(Builder $query, ?User $actor): Builder
    {
        $ids = self::visibleUserIds($actor);

        return $ids === null ? $query : $query->whereIn($query->qualifyColumn('id'), $ids);
    }

    /**
     * Seluruh anggota tim target terlihat oleh actor. Bila tidak, akun target menjangkau karyawan di luar
     * cakupan actor, jadi actor tidak boleh mengambil alih akun tersebut (mis. mengubah data login-nya).
     */
    public static function coversTeamOf(?User $actor, User $target): bool
    {
        if (self::hasGlobalAccess($actor)) {
            return true;
        }

        $teamIds = ApprovalScopeService::getManagedUserIdsOneLevelDown((int) $target->getKey());

        if ($teamIds === []) {
            return true;
        }

        $visible = self::visibleUserIds($actor) ?? [];

        return array_diff($teamIds, $visible) === [];
    }

    /**
     * @return array<int>
     */
    private function selectedAreaIds(int $userId): array
    {
        if (array_key_exists($userId, $this->areaMemo)) {
            return $this->areaMemo[$userId];
        }

        return $this->areaMemo[$userId] = DB::table('user_area_access')
            ->where('user_id', $userId)
            ->pluck('area_id')
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
