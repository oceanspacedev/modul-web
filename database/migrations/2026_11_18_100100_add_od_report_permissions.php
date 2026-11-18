<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permission OD Report. Hanya jabatan ADMIN yang langsung mendapatkannya; anggota tim OD diberi akses per orang
 * (Staff/Leader) dari form user, karena jabatan seperti LEADER dipakai juga oleh divisi lain.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['access-od-report', 'manage-od-report'];

    public function up(): void
    {
        $adminRoleId = DB::table('roles')->where('name', User::ROLE_ADMIN)->where('guard_name', 'web')->value('id');

        foreach (self::PERMISSIONS as $name) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $name,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($adminRoleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id'),
                    'role_id' => $adminRoleId,
                ]);
            }
        }

        app('cache')->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->delete();

        app('cache')->forget(config('permission.cache.key'));
    }
};
