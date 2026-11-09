<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Jabatan karyawan (role DnD) yang sekaligus menjadi role Spatie pembawa permission.
 */
class Role extends SpatieRole
{
    /** Jabatan bawaan DnD beserta level grade-nya. */
    public const JABATAN = [
        'ADMIN' => 100,
        'STAFF' => 10,
        'TEAM LEADER' => 20,
        'COORDINATOR' => 30,
        'MANAGER' => 40,
        'CHIEF' => 50,
        'BOD' => 60,
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'requires_approval' => 'boolean',
        ];
    }

    public function isBuiltIn(): bool
    {
        return array_key_exists($this->name, self::JABATAN);
    }
}
