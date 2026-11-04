<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permission menu Modul Belajar. RoleAndPermissionSeeder tidak menambah permission ke jabatan yang sudah punya
 * hak akses, jadi pemegang akses Pelatihan yang ada mendapat hak akses yang setara di sini.
 */
return new class extends Migration
{
    /** Permission baru => permission lama yang pemegangnya ikut mendapat permission baru. */
    private const PERMISSIONS = [
        'view-learning-modules' => 'view-my-trainings',
        'manage-learning-modules' => 'manage-trainings',
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $name => $basedOn) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $name,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $permissionId = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');
            $basedOnId = DB::table('permissions')->where('name', $basedOn)->where('guard_name', 'web')->value('id');

            if (! $basedOnId) {
                continue;
            }

            DB::table('role_has_permissions')->insertOrIgnore(
                DB::table('role_has_permissions')->where('permission_id', $basedOnId)->pluck('role_id')
                    ->map(fn ($roleId) => ['permission_id' => $permissionId, 'role_id' => $roleId])->all()
            );

            DB::table('model_has_permissions')->insertOrIgnore(
                DB::table('model_has_permissions')->where('permission_id', $basedOnId)->get(['model_type', 'model_id'])
                    ->map(fn ($row) => ['permission_id' => $permissionId, 'model_type' => $row->model_type, 'model_id' => $row->model_id])->all()
            );
        }

        app('cache')->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', array_keys(self::PERMISSIONS))->where('guard_name', 'web')->delete();

        app('cache')->forget(config('permission.cache.key'));
    }
};
