<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Video;
use Database\Seeders\RoleAndPermissionSeeder;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure core jabatan and permissions exist
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function admin(): User
    {
        return User::where('username', 'admin')->first();
    }

    private function jabatanId(string $name): int
    {
        return Role::findByName($name, 'web')->id;
    }

    public function test_admin_can_access_roles_management_page()
    {
        $response = $this->actingAs($this->admin())->get('/roles');
        $response->assertStatus(200);
        $response->assertSee('Kelola jabatan dan tentukan fitur apa saja', false);
        $response->assertSee('Daftar Jabatan & Hak Akses Fitur', false);
        $response->assertSee('Matriks Hak Akses');
        $response->assertSee('Melihat Dashboard & Statistik');
        $response->assertSee('ADMIN');
        $response->assertSee('STAFF');
        $response->assertSee('MANAGER');
    }

    public function test_seeded_jabatan_have_expected_permissions()
    {
        $admin = Role::findByName('ADMIN', 'web');
        $staff = Role::findByName('STAFF', 'web');

        $this->assertTrue($admin->hasPermissionTo('manage-users'));
        $this->assertTrue($admin->hasPermissionTo('manage-master-data'));

        foreach (['view-dashboard', 'view-videos', 'comment-videos', 'view-my-trainings', 'view-documents'] as $permission) {
            $this->assertTrue($staff->hasPermissionTo($permission), "STAFF jabatan is missing {$permission}");
        }
        $this->assertFalse($staff->hasPermissionTo('manage-trainings'));
        $this->assertFalse($staff->hasPermissionTo('manage-users'));
    }

    public function test_admin_can_create_new_role_with_selected_permissions()
    {
        $roleName = 'Supervisor Testing '.rand(100, 999);
        $permissions = ['view-dashboard', 'view-documents', 'comment-videos'];

        $response = $this->actingAs($this->admin())->post('/roles', [
            'name' => $roleName,
            'level' => 25,
            'requires_approval' => '1',
            'permissions' => $permissions,
        ]);

        $response->assertRedirect('/roles');
        $response->assertSessionHas('success');

        $role = Role::where('name', strtoupper($roleName))->first();
        $this->assertNotNull($role);
        $this->assertSame(25, $role->level);
        $this->assertTrue($role->requires_approval);
        $this->assertTrue($role->hasPermissionTo('view-dashboard'));
        $this->assertTrue($role->hasPermissionTo('view-documents'));
        $this->assertFalse($role->hasPermissionTo('manage-trainings'));
    }

    public function test_admin_can_update_role_permissions()
    {
        $role = Role::create(['name' => 'CUSTOM ROLE '.rand(100, 999), 'guard_name' => 'web']);
        $role->syncPermissions(['view-dashboard']);

        $response = $this->actingAs($this->admin())->put('/roles/'.$role->id, [
            'name' => $role->name,
            'permissions' => ['view-dashboard', 'manage-trainings'],
        ]);

        $response->assertRedirect('/roles');
        $response->assertSessionHas('success');

        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('manage-trainings'));
    }

    public function test_built_in_jabatan_cannot_be_renamed_or_deleted()
    {
        $staff = Role::findByName('STAFF', 'web');

        $this->actingAs($this->admin())->from('/roles')->put('/roles/'.$staff->id, [
            'name' => 'KARYAWAN',
            'permissions' => ['view-dashboard'],
        ])->assertSessionHas('error');
        $this->assertSame('STAFF', $staff->fresh()->name);

        $this->actingAs($this->admin())->from('/roles')->delete('/roles/'.$staff->id)->assertSessionHas('error');
        $this->assertModelExists($staff);
    }

    public function test_admin_can_assign_jabatan_and_trainer_access_to_user()
    {
        $user = User::factory()->staff()->create();

        $response = $this->actingAs($this->admin())->post('/roles/assign', [
            'user_id' => $user->id,
            'role' => 'MANAGER',
            'trainer' => '1',
        ]);

        $response->assertSessionHas('success');
        $user = $user->fresh();
        $this->assertTrue($user->hasRole('MANAGER'));
        $this->assertFalse($user->hasRole('STAFF'));
        $this->assertSame(1, $user->roles()->count());
        $this->assertTrue($user->isTrainer());
    }

    public function test_user_assignment_tab_is_paginated_and_filtered_on_the_server()
    {
        User::factory()->count(30)->staff()->create();
        $budi = User::factory()->staff()->create(['nama_lengkap' => 'ZZ BUDI PENCARIAN', 'username' => 'budi.cari']);
        $manager = User::factory()->jabatan('MANAGER')->create(['nama_lengkap' => 'ZZ SITI MANAGER']);
        $trainer = User::factory()->staff()->create(['nama_lengkap' => 'ZZ TONO TRAINER']);
        $trainer->setTrainer(true);
        $noRole = User::factory()->create(['nama_lengkap' => 'ZZ TANPA JABATAN']);

        // Halaman pertama hanya memuat 25 user; sisanya ada di halaman berikutnya.
        $this->actingAs($this->admin())->get('/roles')
            ->assertOk()
            ->assertViewHas('users', fn ($users) => $users->count() === 25 && $users->total() === 35)
            ->assertViewHas('totalUsers', 35)
            ->assertDontSee('ZZ BUDI PENCARIAN')
            ->assertSee('page=2#tab-users', false);
        $this->actingAs($this->admin())->get('/roles?page=2')->assertOk()->assertSee('ZZ BUDI PENCARIAN');

        $names = fn (string $query) => $this->actingAs($this->admin())->get('/roles?'.$query)->assertOk()
            ->viewData('users')->pluck('nama_lengkap')->all();

        $this->assertSame(['ZZ BUDI PENCARIAN'], $names('search=budi.cari'));
        // Kartu ringkasan dan label tab tetap menghitung semua user saat tabel disaring.
        $this->actingAs($this->admin())->get('/roles?search=budi.cari')->assertViewHas('totalUsers', 35);
        $this->assertSame(['ZZ SITI MANAGER'], $names('role=MANAGER'));
        $this->assertSame(['ZZ TONO TRAINER'], $names('role=__trainer'));
        $this->assertSame(['ZZ TANPA JABATAN'], $names('role=__none'));
        $this->assertSame([], $names('role=MANAGER&search=budi'));
        $this->assertCount(25, $names('search=Test+Division'));
    }

    public function test_only_super_admin_can_assign_admin_jabatan()
    {
        $roleManager = User::factory()->staff()->create();
        $roleManager->givePermissionTo('manage-roles');
        $user = User::factory()->staff()->create();

        $this->actingAs($roleManager)->post('/roles/assign', [
            'user_id' => $user->id,
            'role' => 'ADMIN',
        ])->assertSessionHas('error');

        $this->assertFalse($user->fresh()->hasRole('ADMIN'));
    }

    public function test_role_permissions_enforce_route_access()
    {
        // 1. Staff without manage-trainings cannot access /training
        $staffUser = User::factory()->staff()->create();

        $forbiddenResponse = $this->actingAs($staffUser)->get('/training');
        $forbiddenResponse->assertSessionHas('error');

        // 2. Give trainer access (which grants manage-trainings)
        $staffUser->setTrainer(true);

        $allowedResponse = $this->actingAs($staffUser->fresh())->get('/training');
        $allowedResponse->assertStatus(200);
    }

    public function test_user_creation_assigns_jabatan_and_trainer_access()
    {
        $username = 'testuser_'.rand(1000, 9999);
        $payload = [
            'username' => $username,
            'nama_lengkap' => 'Testing Role User',
            'employee_id' => 'EMP-'.rand(100, 999),
            'password' => 'secret123',
            'area_id' => 1,
            'divisi_id' => 1,
            'role_id' => $this->jabatanId('STAFF'),
            'trainer' => '1',
            'email' => $username.'@example.com',
            'no_hp' => '081234567890',
        ];

        $response = $this->actingAs($this->admin())->post('/user', $payload);
        $response->assertRedirect('user');
        $response->assertSessionHas('success');

        $createdUser = User::where('username', $username)->first();
        $this->assertNotNull($createdUser);
        $this->assertSame('TESTING ROLE USER', $createdUser->nama_lengkap);
        $this->assertSame('081234567890', $createdUser->no_hp);
        $this->assertSame('STAFF', $createdUser->jabatan->name);
        $this->assertTrue($createdUser->isTrainer());
        $this->assertFalse($createdUser->hasRole('ADMIN'));
    }

    public function test_user_update_with_role_syncs_jabatan()
    {
        $user = User::factory()->staff()->create(['area_id' => 1, 'divisi_id' => 1]);

        $response = $this->actingAs($this->admin())->put('/user/'.$user->id, [
            'username' => $user->username,
            'nama_lengkap' => $user->nama_lengkap,
            'area_id' => $user->area_id,
            'divisi_id' => $user->divisi_id,
            'role_id' => $this->jabatanId('COORDINATOR'),
            'trainer' => '1',
        ]);

        $response->assertRedirect('user');
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertTrue($user->hasRole('COORDINATOR'));
        $this->assertFalse($user->hasRole('STAFF'));
        $this->assertTrue($user->isTrainer());
    }

    public function test_sidebar_menus_are_hidden_when_user_lacks_permissions()
    {
        // 1. Staff user should not see Settings or Manage Trainings in sidebar
        $staffUser = User::factory()->staff()->create();

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
        $this->assertStringNotContainsString('/area', $sidebarStaff);
        $this->assertStringNotContainsString('/divisi', $sidebarStaff);
        $this->assertStringNotContainsString('/position', $sidebarStaff);
        $this->assertStringNotContainsString('/dokumentype', $sidebarStaff);
        $this->assertStringNotContainsString('Settings', $sidebarStaff);

        // 2. Super admin should see all menus
        $this->actingAs($this->admin());
        $sidebarAdmin = view('layout.sidebarnav')->render();

        $this->assertStringContainsString('/dashboard', $sidebarAdmin);
        $this->assertStringContainsString('/video', $sidebarAdmin);
        $this->assertStringContainsString('href="/training"', $sidebarAdmin);
        // Pelatihan baru ditambahkan dari halaman Jadwal Pelatihan, seperti modul.
        $this->assertStringNotContainsString('/training/create', $sidebarAdmin);
        $this->assertStringContainsString('/my-trainings', $sidebarAdmin);
        $this->assertStringContainsString('/user', $sidebarAdmin);
        $this->assertStringContainsString('/roles', $sidebarAdmin);
        $this->assertStringContainsString('Jabatan & Hak Akses', $sidebarAdmin);
        $this->assertStringContainsString('/area', $sidebarAdmin);
        $this->assertStringContainsString('/divisi', $sidebarAdmin);
        $this->assertStringContainsString('/position', $sidebarAdmin);
        $this->assertStringContainsString('/dokumentype', $sidebarAdmin);
        $this->assertStringContainsString('Pengaturan', $sidebarAdmin);
        $this->assertStringNotContainsString('/subdivisi', $sidebarAdmin);
        $this->assertStringNotContainsString('/joblevel', $sidebarAdmin);
    }

    public function test_video_manage_buttons_only_shown_to_users_who_can_manage_videos()
    {
        $video = Video::create([
            'title' => 'Video Uji Tombol',
            'video_type' => 'link',
            'video_link' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
        $editUrl = route('video.edit', $video->id);

        $this->actingAs(User::factory()->staff()->create())
            ->get('/video')
            ->assertOk()
            ->assertSee('Video Uji Tombol')
            ->assertDontSee($editUrl);

        $this->actingAs($this->admin())
            ->get('/video')
            ->assertOk()
            ->assertSee($editUrl);
    }
}
