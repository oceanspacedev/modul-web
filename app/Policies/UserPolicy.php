<?php

namespace App\Policies;

use App\Models\User;
use App\Services\ApprovalScopeService;
use App\Services\DataAccessService;

/**
 * Permission "manage-users" membuka menu user; cakupan record mengikuti DnD:
 * super admin semua karyawan, admin badan usaha tertentu karyawan di badan usahanya, dan atasan timnya sendiri.
 * Super admin sudah diloloskan Gate::before.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('manage-users');
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo('manage-users');
    }

    public function update(User $user, User $model): bool
    {
        return $user->checkPermissionTo('manage-users') && $this->canAccessRecord($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        return $this->update($user, $model) && ! $user->is($model);
    }

    public function restore(User $user, User $model): bool
    {
        return $this->update($user, $model);
    }

    /**
     * Import, export massal, dan sinkronisasi DnD mencocokkan karyawan lintas badan usaha: khusus super admin.
     */
    public function import(User $user): bool
    {
        return DataAccessService::hasGlobalAccess($user);
    }

    /**
     * Hanya super admin yang boleh memberi jabatan ADMIN atau mengubah akses data badan usaha.
     */
    public function manageDataAccess(User $user): bool
    {
        return DataAccessService::hasGlobalAccess($user);
    }

    /**
     * Hak akses tambahan trainer memberi permission kelola training/video/dokumen, jadi ikut pengelola role.
     */
    public function manageTrainer(User $user): bool
    {
        return $user->checkPermissionTo('manage-roles');
    }

    /**
     * Mengubah data login (username, password, No. HP WhatsApp) sama dengan bisa masuk sebagai karyawan itu:
     * admin badan usaha tertentu tidak boleh mengubah login atasan yang timnya berisi karyawan di luar cakupannya.
     */
    public function changeCredentials(User $user, User $model): bool
    {
        if (! $this->update($user, $model)) {
            return false;
        }

        return ! DataAccessService::isAreaScopedAdmin($user) || DataAccessService::coversTeamOf($user, $model);
    }

    /**
     * No. HP adalah login WhatsApp; seperti DnD hanya admin yang boleh mengubahnya.
     */
    public function changePhone(User $user, ?User $model = null): bool
    {
        return DataAccessService::isAdmin($user) && ($model === null || $this->changeCredentials($user, $model));
    }

    private function canAccessRecord(User $user, User $model): bool
    {
        // Akun ADMIN hanya dikelola super admin.
        if ($model->isAdmin()) {
            return false;
        }

        if (DataAccessService::isEmployeeInScope($user, $model)) {
            return true;
        }

        return in_array((int) $model->id, ApprovalScopeService::getManagedUserIdsOneLevelDown((int) $user->id), true);
    }
}
