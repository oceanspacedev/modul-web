<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ImpersonatePermissionMigrationTest extends TestCase
{
    private function dropImpersonatePermission(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'impersonate-users')->value('id');

        DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();

        app('cache')->forget(config('permission.cache.key'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_migration_recreates_the_permission_and_grants_it_to_admin(): void
    {
        $this->dropImpersonatePermission();
        $this->assertFalse(Permission::where('name', 'impersonate-users')->exists());

        $migration = require database_path('migrations/2026_11_24_100000_add_impersonate_permission.php');
        $migration->up();

        $permission = Permission::where('name', 'impersonate-users')->first();
        $this->assertNotNull($permission);

        $admin = Role::findByName(User::ROLE_ADMIN, 'web');
        $this->assertTrue($admin->hasPermissionTo('impersonate-users'));
    }

    public function test_saving_the_user_form_survives_a_database_that_only_ran_migrations(): void
    {
        $this->dropImpersonatePermission();

        // Seeder tidak dijalankan; ini yang dulu membuat POST/PUT user berakhir 500 (PermissionDoesNotExist).
        (require database_path('migrations/2026_11_24_100000_add_impersonate_permission.php'))->up();

        $staff = User::factory()->staff()->create();

        $staff->setImpersonator(true);
        $this->assertTrue($staff->fresh()->canImpersonateDirectly());

        $staff->setImpersonator(false);
        $this->assertFalse($staff->fresh()->canImpersonateDirectly());
    }

    public function test_migration_can_run_twice(): void
    {
        $migration = require database_path('migrations/2026_11_24_100000_add_impersonate_permission.php');

        $migration->up();
        $migration->up();

        $this->assertSame(1, Permission::where('name', 'impersonate-users')->count());
    }
}
