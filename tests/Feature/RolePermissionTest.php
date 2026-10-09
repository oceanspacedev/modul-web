<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure core roles and permissions exist
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_admin_can_access_roles_management_page()
    {
        $admin = User::where('job_level_id', 1)->first() ?? User::factory()->create(['job_level_id' => 1]);
        $admin->assignRole('Admin');

        $response = $this->actingAs($admin)->get('/roles');
        $response->assertStatus(200);
        $response->assertSee('Manajemen Role & Hak Akses', false);
        $response->assertSee('Daftar Role & Hak Akses Fitur', false);
        $response->assertSee('Admin');
        $response->assertSee('Trainer');
        $response->assertSee('Staff');
    }

    public function test_admin_can_create_new_role_with_selected_permissions()
    {
        $admin = User::where('job_level_id', 1)->first() ?? User::factory()->create(['job_level_id' => 1]);
        $admin->assignRole('Admin');

        $roleName = 'Supervisor Testing '.rand(100, 999);
        $permissions = ['view-dashboard', 'view-documents', 'comment-videos'];

        $response = $this->actingAs($admin)->post('/roles', [
            'name' => $roleName,
            'permissions' => $permissions,
        ]);

        $response->assertRedirect('/roles');
        $response->assertSessionHas('success');

        $role = Role::where('name', $roleName)->first();
        $this->assertNotNull($role);
        $this->assertTrue($role->hasPermissionTo('view-dashboard'));
        $this->assertTrue($role->hasPermissionTo('view-documents'));
        $this->assertFalse($role->hasPermissionTo('manage-trainings'));
    }

    public function test_admin_can_update_role_permissions()
    {
        $admin = User::where('job_level_id', 1)->first() ?? User::factory()->create(['job_level_id' => 1]);
        $admin->assignRole('Admin');

        $role = Role::create(['name' => 'Custom Role '.rand(100, 999), 'guard_name' => 'web']);
        $role->syncPermissions(['view-dashboard']);

        $response = $this->actingAs($admin)->put('/roles/'.$role->id, [
            'name' => $role->name,
            'permissions' => ['view-dashboard', 'manage-trainings'],
        ]);

        $response->assertRedirect('/roles');
        $response->assertSessionHas('success');

        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('manage-trainings'));
    }

    public function test_admin_can_assign_role_to_user()
    {
        $admin = User::where('job_level_id', 1)->first() ?? User::factory()->create(['job_level_id' => 1]);
        $admin->assignRole('Admin');

        $user = User::factory()->create(['job_level_id' => 2]);

        $response = $this->actingAs($admin)->post('/roles/assign', [
            'user_id' => $user->id,
            'role' => 'Trainer',
        ]);

        $response->assertSessionHas('success');
        $this->assertTrue($user->fresh()->hasRole('Trainer'));
    }

    public function test_role_permissions_enforce_route_access()
    {
        // 1. Staff without manage-trainings cannot access /training
        $staffUser = User::factory()->create(['job_level_id' => 2]);
        $staffUser->syncRoles(['Staff']);

        $forbiddenResponse = $this->actingAs($staffUser)->get('/training');
        $forbiddenResponse->assertSessionHas('error');

        // 2. Assign Trainer role (which has manage-trainings)
        $staffUser->syncRoles(['Trainer']);

        $allowedResponse = $this->actingAs($staffUser)->get('/training');
        $allowedResponse->assertStatus(200);
    }

    public function test_user_creation_with_role_assigns_spatie_role_distinct_from_job_level()
    {
        $admin = User::where('job_level_id', 1)->first() ?? User::factory()->create(['job_level_id' => 1]);
        $admin->assignRole('Admin');

        $username = 'testuser_'.rand(1000, 9999);
        $payload = [
            'username' => $username,
            'full_name' => 'Testing Role User',
            'id_karyawan' => 'EMP-'.rand(100, 999),
            'password' => 'secret123',
            'divisi_id' => 1,
            'subdivisi_id' => null,
            'job_level_id' => 2, // Perusahaan: STAFF
            'role' => 'Trainer', // Sistem: Trainer
            'email' => $username.'@example.com',
            'no_wa' => '08123456789',
        ];

        $response = $this->actingAs($admin)->post('/user', $payload);
        $response->assertRedirect('user');
        $response->assertSessionHas('success');

        $createdUser = User::where('username', $username)->first();
        $this->assertNotNull($createdUser);
        $this->assertEquals(2, $createdUser->job_level_id); // Job level in company
        $this->assertTrue($createdUser->hasRole('Trainer')); // System Spatie role
        $this->assertFalse($createdUser->hasRole('Admin'));
    }

    public function test_user_update_with_role_syncs_spatie_role()
    {
        $admin = User::where('job_level_id', 1)->first() ?? User::factory()->create(['job_level_id' => 1]);
        $admin->assignRole('Admin');

        $user = User::factory()->create([
            'job_level_id' => 2,
            'divisi_id' => 1,
        ]);
        $user->assignRole('Staff');

        $response = $this->actingAs($admin)->post('/user/'.$user->id, [
            'username' => $user->username,
            'full_name' => $user->full_name,
            'divisi_id' => $user->divisi_id,
            'job_level_id' => $user->job_level_id,
            'role' => 'Trainer',
        ]);

        $response->assertRedirect('user');
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertTrue($user->hasRole('Trainer'));
        $this->assertFalse($user->hasRole('Staff'));
    }

    public function test_sidebar_menus_are_hidden_when_user_lacks_permissions()
    {
        // 1. Staff user should not see Presence, Quiz, Settings, or Manage Trainings in sidebar
        $staffUser = User::factory()->create(['job_level_id' => 2]);
        $staffUser->syncRoles(['Staff']);

        $this->actingAs($staffUser);
        $sidebarStaff = view('layout.sidebarnav')->render();

        // Visible for Staff
        $this->assertStringContainsString('/dashboard', $sidebarStaff);
        $this->assertStringContainsString('/video', $sidebarStaff);
        $this->assertStringContainsString('/my-trainings', $sidebarStaff);
        $this->assertStringContainsString('/document', $sidebarStaff);

        // Hidden for Staff in sidebar
        $this->assertStringNotContainsString('/absent', $sidebarStaff);
        $this->assertStringNotContainsString('/training/create', $sidebarStaff);
        $this->assertStringNotContainsString('href="/training"', $sidebarStaff);
        $this->assertStringNotContainsString('/quiz', $sidebarStaff);
        $this->assertStringNotContainsString('/roles', $sidebarStaff);
        $this->assertStringNotContainsString('/user', $sidebarStaff);
        $this->assertStringNotContainsString('/divisi', $sidebarStaff);
        $this->assertStringNotContainsString('/subdivisi', $sidebarStaff);
        $this->assertStringNotContainsString('/joblevel', $sidebarStaff);
        $this->assertStringNotContainsString('/dokumentype', $sidebarStaff);
        $this->assertStringNotContainsString('Settings', $sidebarStaff);

        // 2. Admin should see all menus
        $admin = User::where('job_level_id', 1)->first() ?? User::factory()->create(['job_level_id' => 1]);
        $admin->syncRoles(['Admin']);

        $this->actingAs($admin);
        $sidebarAdmin = view('layout.sidebarnav')->render();

        $this->assertStringContainsString('/dashboard', $sidebarAdmin);
        $this->assertStringContainsString('/video', $sidebarAdmin);
        $this->assertStringContainsString('href="/training"', $sidebarAdmin);
        $this->assertStringContainsString('/training/create', $sidebarAdmin);
        $this->assertStringContainsString('/my-trainings', $sidebarAdmin);
        $this->assertStringContainsString('/absent', $sidebarAdmin);
        $this->assertStringContainsString('/quiz', $sidebarAdmin);
        $this->assertStringContainsString('/user', $sidebarAdmin);
        $this->assertStringContainsString('/roles', $sidebarAdmin);
        $this->assertStringContainsString('/divisi', $sidebarAdmin);
        $this->assertStringContainsString('/subdivisi', $sidebarAdmin);
        $this->assertStringContainsString('/joblevel', $sidebarAdmin);
        $this->assertStringContainsString('/dokumentype', $sidebarAdmin);
        $this->assertStringContainsString('Settings', $sidebarAdmin);
    }
}
