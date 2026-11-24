<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permission impersonate. Form user selalu mengirim pilihan ini, jadi deployment yang belum menjalankan ulang
 * RoleAndPermissionSeeder akan gagal (PermissionDoesNotExist) saat menyimpan user. Migration ini membuatnya untuk
 * database yang sudah berjalan; hanya jabatan ADMIN yang langsung mendapatkannya, sisanya diberikan per orang.
 */
return new class extends Migration
{
    private const PERMISSION = 'impersonate-users';

    public function up(): void
    {
        DB::table('permissions')->insertOrIgnore([
            'name' => self::PERMISSION,
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $adminRoleId = DB::table('roles')->where('name', User::ROLE_ADMIN)->where('guard_name', 'web')->value('id');

        if ($adminRoleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => DB::table('permissions')
                    ->where('name', self::PERMISSION)
                    ->where('guard_name', 'web')
                    ->value('id'),
                'role_id' => $adminRoleId,
            ]);
        }

        app('cache')->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', self::PERMISSION)->where('guard_name', 'web')->delete();

        app('cache')->forget(config('permission.cache.key'));
    }
};
