<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Anggota tim OD: user yang diberi akses OD Report (langsung, atau lewat jabatan selain ADMIN yang memang
 * diberi hak akses itu). ADMIN memang bisa membuka OD Report, tapi tidak ikut dihitung sebagai anggota tim.
 */
final class OdTeam
{
    /** @return Builder<User> */
    public static function query(): Builder
    {
        $grantsAccess = fn (Builder $q) => $q->whereIn('name', ['access-od-report', 'manage-od-report']);

        return User::query()
            ->where(fn (Builder $q) => $q
                ->whereHas('permissions', $grantsAccess)
                ->orWhereHas('roles', fn (Builder $r) => $r->where('name', '!=', User::ROLE_ADMIN)->whereHas('permissions', $grantsAccess)))
            ->orderBy('nama_lengkap');
    }

    /** @return Collection<int, User> */
    public static function members(): Collection
    {
        return self::query()->with('position')->get();
    }

    /**
     * Teks kolom Posisi di Weekly/Monthly: nama posisi user, atau ORGANIZATION DEVELOPMENT LEADER/STAFF.
     */
    public static function positionOf(User $user): string
    {
        $position = $user->relationLoaded('position') ? $user->position : $user->position()->first();

        if ($position && filled($position->name)) {
            return mb_strtoupper($position->name);
        }

        return $user->can('manage-od-report') ? 'ORGANIZATION DEVELOPMENT LEADER' : 'ORGANIZATION DEVELOPMENT STAFF';
    }
}
