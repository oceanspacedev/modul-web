<?php

namespace App\Services;

use App\Models\Divisi;
use App\Models\User;

/**
 * Pilihan dan saran Approval Line (atasan) di form user, mengikuti DnD.
 * Matriks aturan approval DnD tidak dipakai di sini; saran berasal dari Kepala Divisi.
 */
class ApprovalLineService
{
    /** Jabatan yang dikelompokkan sebagai atasan/manajemen. */
    private const MANAGEMENT_ROLES = [User::ROLE_ADMIN, 'BOD', 'CHIEF', 'MANAGER', 'COORDINATOR', 'TEAM LEADER'];

    /**
     * Id user yang boleh dipilih sebagai atasan oleh actor. null = semua user.
     *
     * @return array<int>|null
     */
    public static function selectableApproverIds(User $actor, bool $creating): ?array
    {
        if (DataAccessService::hasGlobalAccess($actor)) {
            return null;
        }

        if (DataAccessService::isAdmin($actor)) {
            return DataAccessService::visibleUserIds($actor);
        }

        // Atasan non-admin yang menambah anggota tim menjadi approval line-nya sendiri.
        return $creating ? [(int) $actor->getKey()] : DataAccessService::visibleUserIds($actor);
    }

    /**
     * Opsi select Approval Line yang dikelompokkan seperti DnD.
     *
     * @return array<string, array<int, string>>
     */
    public static function groupedOptions(User $actor, ?User $record, ?int $divisiId): array
    {
        $query = User::with(['roles', 'divisi'])->orderBy('nama_lengkap');

        if ($record?->exists) {
            $query->whereKeyNot($record->getKey());
        }

        $selectableIds = self::selectableApproverIds($actor, ! $record?->exists);
        if ($selectableIds !== null) {
            $extraIds = array_filter([
                $record?->getOriginal('approval_id'),
                self::suggest($divisiId, $record?->id)?->id,
            ]);
            $query->whereIn('id', array_values(array_unique([...$selectableIds, ...array_map('intval', $extraIds)])));
        }

        $groups = $divisiId
            ? ['Satu Divisi' => [], 'Atasan Manajemen & Lintas Divisi' => [], 'Karyawan Lainnya (Lintas Divisi)' => []]
            : ['Atasan & Manajemen' => [], 'Karyawan Lainnya' => []];

        foreach ($query->get() as $user) {
            $label = $user->jabatan ? "{$user->nama_lengkap} ({$user->jabatan->name})" : $user->nama_lengkap;
            $isManagement = in_array(strtoupper((string) $user->jabatan?->name), self::MANAGEMENT_ROLES, true);

            if (! $divisiId) {
                $groups[$isManagement ? 'Atasan & Manajemen' : 'Karyawan Lainnya'][$user->id] = $label;

                continue;
            }

            if ((int) $user->divisi_id === $divisiId) {
                $groups['Satu Divisi'][$user->id] = $label;

                continue;
            }

            $label .= $user->divisi ? " - Divisi {$user->divisi->name}" : '';
            $groups[$isManagement ? 'Atasan Manajemen & Lintas Divisi' : 'Karyawan Lainnya (Lintas Divisi)'][$user->id] = $label;
        }

        return array_filter($groups);
    }

    /**
     * Saran approval line: Kepala Divisi, kecuali dirinya sendiri atau bila membentuk siklus approval.
     */
    public static function suggest(?int $divisiId, ?int $recordId): ?User
    {
        $manager = $divisiId ? Divisi::with('manager.roles')->find($divisiId)?->manager : null;

        if (! $manager instanceof User) {
            return null;
        }

        if ($recordId && ((int) $manager->id === $recordId || ApprovalScopeService::createsApprovalCycle($recordId, (int) $manager->id))) {
            return null;
        }

        return $manager;
    }
}
