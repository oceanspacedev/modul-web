<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Divisi;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * User module aligned with DnD: area/divisi/jabatan/posisi/approval line, data access scope and DnD sync.
 */
class UserModuleDndTest extends TestCase
{
    private function superAdmin(): User
    {
        return User::where('username', 'admin')->first();
    }

    private function jabatanId(string $name): int
    {
        return Role::findByName($name, 'web')->id;
    }

    /**
     * Full update payload for an existing user, with overrides.
     *
     * @return array<string, mixed>
     */
    private function payloadFor(User $user, array $overrides = []): array
    {
        return [
            'employee_id' => $user->employee_id,
            'nama_lengkap' => $user->nama_lengkap,
            'username' => $user->username,
            'email' => $user->email,
            'area_id' => $user->area_id,
            'divisi_id' => $user->divisi_id,
            'role_id' => $user->jabatan?->id ?? $this->jabatanId('STAFF'),
            'position_id' => $user->position_id,
            'approval_id' => $user->approval_id,
            ...$overrides,
        ];
    }

    private function areaScopedAdmin(Area $area, array $attributes = []): User
    {
        $admin = User::factory()->jabatan('ADMIN')->create([
            'data_access' => User::DATA_ACCESS_SELECTED,
            'area_id' => $area->id,
            ...$attributes,
        ]);
        $admin->dataAccessAreas()->sync([$area->id]);

        return $admin->fresh();
    }

    public function test_super_admin_can_create_user_with_area_divisi_jabatan_position_and_approval(): void
    {
        $area = Area::create(['name' => 'PT MAJU JAYA']);
        $divisi = Divisi::create(['name' => 'SALES', 'area_id' => $area->id]);
        $position = Position::create(['name' => 'SALES EXECUTIVE']);
        $manager = User::factory()->jabatan('MANAGER')->create(['area_id' => $area->id, 'divisi_id' => $divisi->id]);

        $this->actingAs($this->superAdmin())->post('/user', [
            'employee_id' => ' EMP-777 ',
            'nama_lengkap' => '  siti   aminah ',
            'email' => 'Siti@Example.com',
            'no_hp' => '+62 812-9999-0077',
            'area_id' => $area->id,
            'divisi_id' => $divisi->id,
            'role_id' => $this->jabatanId('STAFF'),
            'position_id' => $position->id,
            'approval_id' => $manager->id,
            'username' => 'Siti.Aminah',
            'password' => 'rahasia-kuat-123',
            'trainer' => '1',
        ])->assertRedirect('/user')->assertSessionHas('success');

        $user = User::where('username', 'siti.aminah')->firstOrFail();
        $this->assertSame('EMP-777', $user->employee_id);
        $this->assertSame('SITI AMINAH', $user->nama_lengkap);
        $this->assertSame('siti@example.com', $user->email);
        $this->assertSame('081299990077', $user->no_hp);
        $this->assertSame($area->id, (int) $user->area_id);
        $this->assertSame($divisi->id, (int) $user->divisi_id);
        $this->assertSame($position->id, (int) $user->position_id);
        $this->assertSame($manager->id, (int) $user->approval_id);
        $this->assertSame('STAFF', $user->jabatan->name);
        $this->assertSame(1, $user->roles()->count());
        $this->assertTrue($user->isTrainer());
        $this->assertFalse($user->isAdministrator());
    }

    public function test_super_admin_can_create_area_scoped_admin(): void
    {
        $areaB = Area::create(['name' => 'PT CABANG']);

        $this->actingAs($this->superAdmin())->post('/user', [
            'nama_lengkap' => 'Admin Cabang',
            'area_id' => 1,
            'divisi_id' => 1,
            'role_id' => $this->jabatanId('ADMIN'),
            'username' => 'admin_cabang',
            'password' => 'rahasia-kuat-123',
            'data_access' => 'selected',
            'data_access_areas' => [$areaB->id],
        ])->assertRedirect('/user')->assertSessionHas('success');

        $user = User::where('username', 'admin_cabang')->firstOrFail();
        $this->assertTrue($user->isAdmin());
        $this->assertSame('selected', $user->data_access);
        $this->assertSame([$areaB->id], $user->dataAccessAreas()->pluck('areas.id')->map(fn ($id) => (int) $id)->all());
        $this->assertFalse($user->isAdministrator());
    }

    public function test_jabatan_requiring_approval_needs_an_approval_line(): void
    {
        $role = Role::create(['name' => 'SALES LEAD', 'guard_name' => 'web', 'level' => 15, 'requires_approval' => true]);

        $this->actingAs($this->superAdmin())->from('/user/create')->post('/user', [
            'nama_lengkap' => 'Butuh Atasan',
            'area_id' => 1,
            'divisi_id' => 1,
            'role_id' => $role->id,
            'username' => 'butuh_atasan',
            'password' => 'rahasia-kuat-123',
        ])->assertRedirect('/user/create')->assertSessionHasErrors('approval_id');

        $this->assertDatabaseMissing('users', ['username' => 'butuh_atasan']);
    }

    public function test_non_super_admin_with_manage_users_cannot_grant_admin(): void
    {
        $manager = User::factory()->staff()->create(['area_id' => 1]);
        $manager->givePermissionTo('manage-users');
        $areaAdmin = $this->areaScopedAdmin(Area::findOrFail(1));

        foreach ([$manager, $areaAdmin] as $actor) {
            $this->actingAs($actor)->from('/user/create')->post('/user', [
                'nama_lengkap' => 'Calon Admin',
                'area_id' => 1,
                'divisi_id' => 1,
                'role_id' => $this->jabatanId('ADMIN'),
                'approval_id' => $actor->id,
                'username' => 'calon_admin',
                'password' => 'rahasia-kuat-123',
            ])->assertRedirect('/user/create')->assertSessionHasErrors('role_id');
        }

        $this->assertDatabaseMissing('users', ['username' => 'calon_admin']);

        // Promoting an existing employee to ADMIN is refused too.
        $employee = User::factory()->staff()->create(['area_id' => 1, 'approval_id' => $manager->id]);
        $this->actingAs($manager)->from('/user')->put("/user/{$employee->id}", $this->payloadFor($employee, ['role_id' => $this->jabatanId('ADMIN')]))
            ->assertSessionHasErrors('role_id');
        $this->assertFalse($employee->fresh()->isAdmin());
    }

    public function test_approval_line_cannot_be_self_or_create_a_cycle(): void
    {
        $boss = User::factory()->jabatan('MANAGER')->create(['area_id' => 1]);
        $lead = User::factory()->jabatan('TEAM LEADER')->create(['area_id' => 1, 'approval_id' => $boss->id]);
        $staff = User::factory()->staff()->create(['area_id' => 1, 'approval_id' => $lead->id]);

        $this->actingAs($this->superAdmin())->from('/user')
            ->put("/user/{$boss->id}", $this->payloadFor($boss, ['approval_id' => $boss->id]))
            ->assertSessionHasErrors('approval_id');

        // boss -> staff would close the loop boss <- lead <- staff.
        $this->actingAs($this->superAdmin())->from('/user')
            ->put("/user/{$boss->id}", $this->payloadFor($boss, ['approval_id' => $staff->id]))
            ->assertSessionHasErrors('approval_id');

        $this->assertNull($boss->fresh()->approval_id);
    }

    public function test_area_scoped_admin_only_sees_and_edits_users_in_their_areas(): void
    {
        $areaA = Area::findOrFail(1);
        $areaB = Area::create(['name' => 'PT AREA LAIN']);
        $scopedAdmin = $this->areaScopedAdmin($areaA);

        $inArea = User::factory()->staff()->create(['nama_lengkap' => 'KARYAWAN AREA SATU', 'username' => 'user_area_satu', 'area_id' => $areaA->id]);
        $outside = User::factory()->staff()->create(['nama_lengkap' => 'KARYAWAN AREA LAIN', 'username' => 'user_area_lain', 'area_id' => $areaB->id]);
        $otherAdmin = User::factory()->jabatan('ADMIN')->create([
            'nama_lengkap' => 'ADMIN LAIN SATU AREA', 'username' => 'admin_lain_satu_area', 'area_id' => $areaA->id, 'data_access' => 'selected',
        ]);

        // Usernames are only rendered in the user table rows.
        $this->actingAs($scopedAdmin)->get('/user')
            ->assertOk()
            ->assertSee('user_area_satu')
            ->assertDontSee('user_area_lain')
            ->assertDontSee('KARYAWAN AREA LAIN')
            ->assertDontSee('admin_lain_satu_area');

        $this->actingAs($scopedAdmin)
            ->putJson("/user/{$outside->id}", $this->payloadFor($outside, ['nama_lengkap' => 'DIUBAH']))
            ->assertForbidden();
        $this->assertSame('KARYAWAN AREA LAIN', $outside->fresh()->nama_lengkap);

        // Other ADMIN accounts are managed only by a super admin, even in the same area.
        $this->actingAs($scopedAdmin)
            ->putJson("/user/{$otherAdmin->id}", $this->payloadFor($otherAdmin, ['nama_lengkap' => 'DIUBAH']))
            ->assertForbidden();
        $this->actingAs($scopedAdmin)->deleteJson("/user/{$outside->id}")->assertForbidden();
        $this->assertNotSoftDeleted($outside);

        $this->actingAs($scopedAdmin)
            ->put("/user/{$inArea->id}", $this->payloadFor($inArea, ['nama_lengkap' => 'karyawan area satu baru']))
            ->assertRedirect('/user')
            ->assertSessionHas('success');
        $this->assertSame('KARYAWAN AREA SATU BARU', $inArea->fresh()->nama_lengkap);

        // A scoped admin cannot move an employee into an area outside their scope.
        $this->actingAs($scopedAdmin)->from('/user')
            ->put("/user/{$inArea->id}", $this->payloadFor($inArea->fresh(), ['area_id' => $areaB->id]))
            ->assertSessionHasErrors('area_id');
        $this->assertSame($areaA->id, (int) $inArea->fresh()->area_id);
    }

    public function test_non_admin_supervisor_only_sees_and_edits_their_team(): void
    {
        $supervisor = User::factory()->staff()->create(['nama_lengkap' => 'ATASAN TIM', 'area_id' => 1]);
        $supervisor->givePermissionTo('manage-users');

        $direct = User::factory()->staff()->create(['nama_lengkap' => 'BAWAHAN LANGSUNG', 'area_id' => 1, 'approval_id' => $supervisor->id]);
        $secondLevel = User::factory()->staff()->create(['nama_lengkap' => 'BAWAHAN TINGKAT DUA', 'area_id' => 1, 'approval_id' => $direct->id]);
        $outsider = User::factory()->staff()->create(['nama_lengkap' => 'KARYAWAN LUAR TIM', 'area_id' => 1]);

        $this->actingAs($supervisor)->get('/user')
            ->assertOk()
            ->assertSee('BAWAHAN LANGSUNG')
            ->assertSee('BAWAHAN TINGKAT DUA')
            ->assertDontSee('KARYAWAN LUAR TIM');

        $this->actingAs($supervisor)
            ->putJson("/user/{$outsider->id}", $this->payloadFor($outsider, ['nama_lengkap' => 'DIUBAH']))
            ->assertForbidden();
        $this->assertSame('KARYAWAN LUAR TIM', $outsider->fresh()->nama_lengkap);

        $this->actingAs($supervisor)
            ->put("/user/{$direct->id}", $this->payloadFor($direct, ['nama_lengkap' => 'Bawahan Langsung Baru']))
            ->assertRedirect('/user');
        $this->assertSame('BAWAHAN LANGSUNG BARU', $direct->fresh()->nama_lengkap);

        // New team members get the supervisor as approval line; another approver is refused.
        $payload = [
            'nama_lengkap' => 'Anggota Baru',
            'area_id' => 1,
            'divisi_id' => 1,
            'role_id' => $this->jabatanId('STAFF'),
            'username' => 'anggota_baru',
            'password' => 'rahasia-kuat-123',
        ];
        $this->actingAs($supervisor)->from('/user/create')->post('/user', [...$payload, 'approval_id' => $outsider->id])
            ->assertSessionHasErrors('approval_id');
        $this->assertDatabaseMissing('users', ['username' => 'anggota_baru']);

        $this->actingAs($supervisor)->post('/user', [...$payload, 'approval_id' => $supervisor->id])->assertRedirect('/user');
        $this->assertSame($supervisor->id, (int) User::where('username', 'anggota_baru')->value('approval_id'));
    }

    public function test_bulk_position_update(): void
    {
        $position = Position::create(['name' => 'SUPERVISOR TOKO']);
        $first = User::factory()->staff()->create(['area_id' => 1]);
        $second = User::factory()->staff()->create(['area_id' => 1]);
        $untouched = User::factory()->staff()->create(['area_id' => 1]);

        $this->actingAs($this->superAdmin())->from('/user')->post('/user/bulk', [
            'action' => 'position',
            'ids' => [$first->id, $second->id],
            'position_id' => $position->id,
        ])->assertRedirect('/user')->assertSessionHas('success');

        $this->assertSame($position->id, (int) $first->fresh()->position_id);
        $this->assertSame($position->id, (int) $second->fresh()->position_id);
        $this->assertNull($untouched->fresh()->position_id);

        $this->actingAs($this->superAdmin())->from('/user')->post('/user/bulk', ['action' => 'position', 'ids' => [$untouched->id]])
            ->assertSessionHasErrors('position_id');
    }

    public function test_bulk_update_is_refused_when_a_selected_user_is_out_of_scope(): void
    {
        $areaB = Area::create(['name' => 'PT AREA LAIN']);
        $scopedAdmin = $this->areaScopedAdmin(Area::findOrFail(1));
        $position = Position::create(['name' => 'KASIR']);
        $inArea = User::factory()->staff()->create(['area_id' => 1]);
        $outside = User::factory()->staff()->create(['area_id' => $areaB->id]);

        $this->actingAs($scopedAdmin)->postJson('/user/bulk', [
            'action' => 'position',
            'ids' => [$inArea->id, $outside->id],
            'position_id' => $position->id,
        ])->assertForbidden();

        $this->assertNull($inArea->fresh()->position_id);
        $this->assertNull($outside->fresh()->position_id);
    }

    public function test_bulk_approval_update_skips_cycles(): void
    {
        $boss = User::factory()->jabatan('MANAGER')->create(['area_id' => 1]);
        $lead = User::factory()->jabatan('TEAM LEADER')->create(['area_id' => 1, 'approval_id' => $boss->id]);
        $staff = User::factory()->staff()->create(['area_id' => 1]);

        $this->actingAs($this->superAdmin())->from('/user')->post('/user/bulk', [
            'action' => 'approval',
            'ids' => [$boss->id, $staff->id],
            'approval_id' => $lead->id,
        ])->assertRedirect('/user')->assertSessionHas('success');

        $this->assertNull($boss->fresh()->approval_id);
        $this->assertSame($lead->id, (int) $staff->fresh()->approval_id);
    }

    public function test_archived_user_is_listed_in_archive_and_can_be_restored(): void
    {
        $user = User::factory()->staff()->create(['nama_lengkap' => 'KARYAWAN DIARSIPKAN', 'username' => 'user_diarsipkan', 'area_id' => 1]);

        $this->actingAs($this->superAdmin())->delete("/user/{$user->id}")->assertRedirect('/user');
        $this->assertSoftDeleted($user);

        $active = User::factory()->staff()->create(['nama_lengkap' => 'KARYAWAN AKTIF', 'username' => 'user_aktif', 'area_id' => 1]);

        $this->actingAs($this->superAdmin())->get('/user?tab=arsip')
            ->assertOk()
            ->assertSee('KARYAWAN DIARSIPKAN')
            ->assertSee('Aktifkan')
            // Usernames are only rendered in the user table rows.
            ->assertSee('user_diarsipkan')
            ->assertDontSee('user_aktif');

        $this->actingAs($this->superAdmin())->patch("/user/{$user->id}/restore")
            ->assertRedirect(route('user.index', ['tab' => 'arsip']))
            ->assertSessionHas('success');
        $this->assertNotSoftDeleted($user);

        $this->actingAs($this->superAdmin())->get('/user?tab=arsip')->assertDontSee('user_diarsipkan');
    }

    public function test_user_cannot_archive_themselves(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->deleteJson("/user/{$admin->id}")->assertForbidden();
        $this->assertNotSoftDeleted($admin);
    }

    public function test_last_super_admin_cannot_be_demoted(): void
    {
        $admin = $this->superAdmin();
        $this->assertSame(1, User::role('ADMIN')->count());

        $this->actingAs($admin)->from('/user')
            ->put("/user/{$admin->id}", $this->payloadFor($admin, ['role_id' => $this->jabatanId('STAFF')]))
            ->assertRedirect('/user')
            ->assertSessionHasErrors('role_id');

        $admin->refresh();
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->isAdministrator());

        // Once another super admin exists, the demotion is allowed.
        User::factory()->admin()->create(['area_id' => 1]);

        $this->actingAs($admin)
            ->put("/user/{$admin->id}", $this->payloadFor($admin, ['role_id' => $this->jabatanId('STAFF')]))
            ->assertRedirect('/user')
            ->assertSessionHas('success');
        $this->assertSame('STAFF', $admin->fresh()->jabatan->name);
    }

    public function test_dnd_sync_previews_then_creates_and_updates_users(): void
    {
        Storage::fake('local');
        config(['services.dnd.url' => 'https://dnd.test', 'services.dnd.token' => 'dnd-test-token']);

        $existing = User::factory()->staff()->create([
            'employee_id' => 'EMP-100',
            'username' => 'budi',
            'nama_lengkap' => 'BUDI LAMA',
            'area_id' => 1,
        ]);
        $usersBefore = User::count();

        $area = ['id' => 11, 'name' => 'PT DND JAKARTA'];
        $divisi = ['id' => 21, 'name' => 'SALES DND'];

        Http::fake([
            'dnd.test/api/v1/users*' => Http::response([
                'success' => true,
                'data' => [
                    [
                        'id' => 1, 'employee_id' => 'EMP-100', 'username' => 'budi', 'nama_lengkap' => 'Budi Baru',
                        'email' => 'budi@dnd.test', 'no_hp' => '081299990101',
                        'role' => ['id' => 5, 'name' => 'MANAGER'], 'area' => $area, 'divisi' => $divisi,
                        'position' => ['id' => 31, 'name' => 'SALES MANAGER'], 'supervisor' => null,
                    ],
                    [
                        'id' => 2, 'employee_id' => 'EMP-200', 'username' => 'siti', 'nama_lengkap' => 'Siti Dnd',
                        'email' => 'siti@dnd.test', 'no_hp' => '+6281299990102',
                        'role' => ['id' => 2, 'name' => 'STAFF'], 'area' => $area, 'divisi' => $divisi,
                        'position' => ['id' => 32, 'name' => 'SALES EXECUTIVE'],
                        'supervisor' => ['id' => 1, 'nama_lengkap' => 'Budi Baru', 'username' => 'budi', 'employee_id' => 'EMP-100'],
                    ],
                    [
                        'id' => 3, 'employee_id' => 'EMP-300', 'username' => 'dnd_admin', 'nama_lengkap' => 'Admin Dnd',
                        'email' => null, 'no_hp' => null,
                        'role' => ['id' => 1, 'name' => 'ADMIN'], 'area' => $area, 'divisi' => $divisi,
                        'position' => null, 'supervisor' => null,
                    ],
                    [
                        'id' => 4, 'employee_id' => 'EMP-400', 'username' => 'andi', 'nama_lengkap' => 'Andi Dnd',
                        'email' => null, 'no_hp' => null,
                        'role' => ['id' => 9, 'name' => 'Supervisor Gudang'], 'area' => $area, 'divisi' => $divisi,
                        'position' => null,
                        'supervisor' => ['id' => 2, 'nama_lengkap' => 'Siti Dnd', 'username' => 'siti', 'employee_id' => 'EMP-200'],
                    ],
                ],
                'meta' => ['last_page' => 1],
            ]),
            'dnd.test/api/v1/master/areas*' => Http::response(['success' => true, 'data' => [$area]]),
            'dnd.test/api/v1/master/divisis*' => Http::response(['success' => true, 'data' => [$divisi]]),
            'dnd.test/api/v1/master/positions*' => Http::response(['success' => true, 'data' => [
                ['id' => 31, 'name' => 'SALES MANAGER'], ['id' => 32, 'name' => 'SALES EXECUTIVE'],
            ]]),
            'dnd.test/api/v1/master/roles*' => Http::response(['success' => true, 'data' => [
                ['id' => 1, 'name' => 'ADMIN'], ['id' => 2, 'name' => 'STAFF'], ['id' => 5, 'name' => 'MANAGER'], ['id' => 9, 'name' => 'Supervisor Gudang'],
            ]]),
        ]);

        // 1. Preview: fetches DnD, stores a snapshot and changes nothing yet.
        $preview = $this->actingAs($this->superAdmin())->post('/user/sync-dnd');
        $preview->assertRedirect();
        $this->assertMatchesRegularExpression('#/user/sync-dnd/([0-9a-f-]{36})$#', $preview->headers->get('Location'));
        preg_match('#/user/sync-dnd/([0-9a-f-]{36})$#', $preview->headers->get('Location'), $matches);
        $token = $matches[1];

        Storage::disk('local')->assertExists("dnd-sync/{$token}.json");
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://dnd.test/api/v1/users')
            && $request->hasHeader('Authorization', 'Bearer dnd-test-token'));
        $this->assertSame($usersBefore, User::count());
        $this->assertDatabaseMissing('areas', ['name' => 'PT DND JAKARTA']);
        $this->assertSame('BUDI LAMA', $existing->fresh()->nama_lengkap);

        $this->actingAs($this->superAdmin())->get("/user/sync-dnd/{$token}")
            ->assertOk()
            ->assertSee('SITI DND')
            ->assertSee('PT DND JAKARTA');

        // 2. Process: masters, users, jabatan and approval lines are applied.
        $this->actingAs($this->superAdmin())->post('/user/sync-dnd/process', ['token' => $token])
            ->assertRedirect('/user')
            ->assertSessionHas('success');

        Storage::disk('local')->assertMissing("dnd-sync/{$token}.json");

        $newArea = Area::where('name', 'PT DND JAKARTA')->firstOrFail();
        $newDivisi = Divisi::where('name', 'SALES DND')->firstOrFail();
        $this->assertTrue(Position::where('name', 'SALES MANAGER')->exists());
        $this->assertTrue(Position::where('name', 'SALES EXECUTIVE')->exists());
        $newRole = Role::where('name', 'SUPERVISOR GUDANG')->firstOrFail();
        // New DnD jabatan inherit the STAFF permissions.
        $this->assertEqualsCanonicalizing(
            Role::findByName('STAFF', 'web')->permissions->pluck('name')->all(),
            $newRole->permissions->pluck('name')->all()
        );

        $this->assertSame($usersBefore + 3, User::count());

        // Existing user matched by employee ID is updated.
        $budi = $existing->fresh();
        $this->assertSame('BUDI BARU', $budi->nama_lengkap);
        $this->assertSame('budi@dnd.test', $budi->email);
        $this->assertSame('081299990101', $budi->no_hp);
        $this->assertSame($newArea->id, (int) $budi->area_id);
        $this->assertSame($newDivisi->id, (int) $budi->divisi_id);
        $this->assertSame('SALES MANAGER', $budi->position->name);
        $this->assertSame('MANAGER', $budi->jabatan->name);
        $this->assertSame(1, $budi->roles()->count());

        // New users are created, supervisors mapped to approval_id.
        $siti = User::where('username', 'siti')->firstOrFail();
        $this->assertSame('EMP-200', $siti->employee_id);
        $this->assertSame('SITI DND', $siti->nama_lengkap);
        $this->assertSame('081299990102', $siti->no_hp);
        $this->assertSame('STAFF', $siti->jabatan->name);
        $this->assertSame('SALES EXECUTIVE', $siti->position->name);
        $this->assertSame($budi->id, (int) $siti->approval_id);

        $andi = User::where('username', 'andi')->firstOrFail();
        $this->assertSame('SUPERVISOR GUDANG', $andi->jabatan->name);
        $this->assertSame($siti->id, (int) $andi->approval_id);

        // ADMIN from DnD never becomes a super admin: restricted to its own area.
        $dndAdmin = User::where('username', 'dnd_admin')->firstOrFail();
        $this->assertTrue($dndAdmin->isAdmin());
        $this->assertSame('selected', $dndAdmin->data_access);
        $this->assertFalse($dndAdmin->isAdministrator());
        $this->assertSame([$newArea->id], $dndAdmin->dataAccessAreas()->pluck('areas.id')->map(fn ($id) => (int) $id)->all());

        // The local super admin is untouched.
        $this->assertTrue($this->superAdmin()->isAdministrator());
    }

    public function test_dnd_sync_is_reserved_for_super_admins(): void
    {
        Storage::fake('local');
        config(['services.dnd.url' => 'https://dnd.test', 'services.dnd.token' => 'dnd-test-token']);
        Http::fake(['dnd.test/*' => Http::response(['success' => true, 'data' => [], 'meta' => ['last_page' => 1]])]);

        $scopedAdmin = $this->areaScopedAdmin(Area::findOrFail(1));

        $this->actingAs($scopedAdmin)->postJson('/user/sync-dnd')->assertForbidden();
        $this->actingAs($scopedAdmin)->postJson('/user/sync-dnd/process', ['token' => '00000000-0000-0000-0000-000000000000'])->assertForbidden();

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'dnd.test'));
    }

    public function test_talenta_json_without_passwords_creates_new_users_and_updates_existing_contacts(): void
    {
        $existing = User::factory()->staff()->create(['employee_id' => 'EMP-OLD', 'nama_lengkap' => 'KARYAWAN LAMA']);
        User::factory()->staff()->create(['username' => 'budisantoso']);

        $talenta = ['data' => ['data' => [
            ['id_employee' => 'EMP-OLD', 'first_name' => 'Karyawan', 'last_name' => 'Lama', 'email' => 'lama@example.com', 'mobile_phone' => '+62 812-1111-0001'],
            ['id_employee' => 'EMP-NEW1', 'first_name' => 'Budi', 'last_name' => 'Santoso', 'email' => 'budi@example.com', 'mobile_phone' => '081211110002', 'organization' => 'Finance', 'title' => 'Staff Accounting'],
            ['id_employee' => 'EMP-NEW2', 'first_name' => 'Siti', 'last_name' => 'Aminah', 'mobile_phone' => '12345', 'organization' => 'Finance', 'title' => 'Staff Accounting'],
        ]]];

        $file = UploadedFile::fake()->createWithContent('list-employee.json', json_encode($talenta));

        $this->actingAs($this->superAdmin())->post('/user/import-json', ['file' => $file])
            ->assertRedirect('/user')
            ->assertSessionHas('success');

        $this->assertSame('lama@example.com', $existing->fresh()->email);
        $this->assertSame('081211110001', $existing->fresh()->no_hp);

        $budi = User::where('employee_id', 'EMP-NEW1')->first();
        $this->assertSame('BUDI SANTOSO', $budi->nama_lengkap);
        $this->assertSame('budisantoso2', $budi->username);
        $this->assertSame('FINANCE', $budi->divisi->name);
        $this->assertSame('STAFF ACCOUNTING', $budi->position->name);
        $this->assertSame('STAFF', $budi->jabatan->name);

        // Nomor tidak valid dilewati; user tetap dibuat dengan divisi/posisi yang sama (tidak duplikat master).
        $siti = User::where('employee_id', 'EMP-NEW2')->first();
        $this->assertNull($siti->no_hp);
        $this->assertSame($budi->divisi_id, $siti->divisi_id);
        $this->assertSame(1, Position::where('name', 'STAFF ACCOUNTING')->count());
    }
}
