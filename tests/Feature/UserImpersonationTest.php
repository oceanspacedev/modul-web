<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserImpersonationTest extends TestCase
{
    private function superAdmin(): User
    {
        return User::where('username', 'admin')->first();
    }

    /**
     * Atasan dengan kelola user + impersonate, beserta bawahan langsung dan karyawan di luar timnya.
     *
     * @return array{0: User, 1: User, 2: User}
     */
    private function supervisorWithTeam(bool $canImpersonate = true): array
    {
        $supervisor = User::factory()->staff()->create(['nama_lengkap' => 'ATASAN TIM', 'area_id' => 1]);
        $supervisor->givePermissionTo('manage-users');
        $supervisor->setImpersonator($canImpersonate);

        $member = User::factory()->staff()->create(['nama_lengkap' => 'ANGGOTA TIM', 'area_id' => 1, 'approval_id' => $supervisor->id]);
        $outsider = User::factory()->staff()->create(['nama_lengkap' => 'KARYAWAN LUAR TIM', 'area_id' => 1]);

        return [$supervisor->fresh(), $member, $outsider];
    }

    private function impersonationSession(User $impersonator): array
    {
        return ['impersonated_by' => $impersonator->id, 'impersonator_guard' => 'web', 'impersonator_guard_using' => 'web'];
    }

    /**
     * Karyawan yang hak impersonate-nya berasal dari jabatan (bukan grant langsung).
     */
    private function employeeWithImpersonateFromRole(): User
    {
        Role::findByName('STAFF', 'web')->givePermissionTo(User::IMPERSONATE_PERMISSION);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return User::factory()->staff()->create(['area_id' => 1]);
    }

    public function test_super_admin_grants_and_revokes_impersonate_from_the_user_form(): void
    {
        $employee = User::factory()->staff()->create(['area_id' => 1]);
        $payload = [
            'employee_id' => $employee->employee_id,
            'nama_lengkap' => $employee->nama_lengkap,
            'username' => $employee->username,
            'email' => $employee->email,
            'area_id' => 1,
            'divisi_id' => $employee->divisi_id,
            'role_id' => Role::findByName('STAFF', 'web')->id,
        ];

        $this->actingAs($this->superAdmin())->get("/user/{$employee->id}/edit")
            ->assertOk()
            ->assertSee('name="impersonate"', false);

        $this->actingAs($this->superAdmin())->put("/user/{$employee->id}", [...$payload, 'impersonate' => '1'])->assertRedirect('/user');
        $this->assertTrue($employee->fresh()->canImpersonate());

        $this->actingAs($this->superAdmin())->put("/user/{$employee->id}", [...$payload, 'impersonate' => '0'])->assertRedirect('/user');
        $this->assertFalse($employee->fresh()->canImpersonate());
    }

    public function test_impersonate_permission_is_listed_on_the_role_page(): void
    {
        $this->actingAs($this->superAdmin())->get('/roles')
            ->assertOk()
            ->assertSee('Masuk sebagai User Lain (Impersonate)');
    }

    public function test_user_with_permission_impersonates_and_leaves(): void
    {
        [$supervisor, $member] = $this->supervisorWithTeam();

        $this->actingAs($supervisor)->get('/user')
            ->assertOk()
            ->assertSee(route('user.impersonate', $member), false);

        $this->actingAs($supervisor)->post("/user/{$member->id}/impersonate")
            ->assertRedirect($member->homeUrl())
            ->assertSessionHas('impersonated_by', $supervisor->id);
        $this->assertAuthenticatedAs($member);

        $this->get('/my-trainings')
            ->assertOk()
            ->assertSee('Kembali ke akun saya');

        $this->delete('/impersonate')
            ->assertRedirect('/user')
            ->assertSessionMissing('impersonated_by');
        $this->assertAuthenticatedAs($supervisor);
    }

    public function test_impersonation_is_limited_to_users_the_actor_may_manage(): void
    {
        [$supervisor, , $outsider] = $this->supervisorWithTeam();

        $this->actingAs($supervisor)->get('/user')
            ->assertOk()
            ->assertDontSee(route('user.impersonate', $outsider), false);

        $this->actingAs($supervisor)->from('/user')->post("/user/{$outsider->id}/impersonate")
            ->assertRedirect('/user')
            ->assertSessionHas('error');
        $this->assertAuthenticatedAs($supervisor);

        $this->actingAs($supervisor)->from('/user')->post("/user/{$supervisor->id}/impersonate")->assertSessionHas('error');
        $this->assertAuthenticatedAs($supervisor);

        $this->actingAs($supervisor)->from('/user')->post('/user/'.$this->superAdmin()->id.'/impersonate')->assertSessionHas('error');
        $this->assertAuthenticatedAs($supervisor);
    }

    public function test_user_without_impersonate_permission_cannot_impersonate(): void
    {
        [$supervisor, $member] = $this->supervisorWithTeam(canImpersonate: false);

        $this->actingAs($supervisor)->get('/user')
            ->assertOk()
            ->assertSee('ANGGOTA TIM')
            ->assertDontSee(route('user.impersonate', $member), false);

        $this->actingAs($supervisor)->from('/user')->post("/user/{$member->id}/impersonate")->assertSessionHas('error');
        $this->assertAuthenticatedAs($supervisor);
    }

    public function test_super_admin_cannot_impersonate_self_archived_users_or_while_impersonating(): void
    {
        $admin = $this->superAdmin();
        $archived = User::factory()->staff()->create(['area_id' => 1]);
        $archived->delete();
        $other = User::factory()->staff()->create(['area_id' => 1]);

        $this->actingAs($admin)->from('/user')->post("/user/{$admin->id}/impersonate")->assertSessionHas('error');
        $this->actingAs($admin)->from('/user')->post("/user/{$archived->id}/impersonate")->assertSessionHas('error');
        $this->assertAuthenticatedAs($admin);

        $member = User::factory()->staff()->create(['area_id' => 1]);
        $this->actingAs($member)->withSession($this->impersonationSession($admin))
            ->from('/my-trainings')->post("/user/{$other->id}/impersonate")
            ->assertSessionHas('error');
        $this->assertAuthenticatedAs($member);
    }

    public function test_leaving_without_impersonation_is_refused(): void
    {
        $this->actingAs($this->superAdmin())->from('/user')->delete('/impersonate')->assertSessionHas('error');
    }

    public function test_impersonated_user_last_seen_is_not_recorded(): void
    {
        $member = User::factory()->staff()->create(['area_id' => 1, 'last_seen' => null]);

        $this->actingAs($member)->withSession($this->impersonationSession($this->superAdmin()))
            ->get('/my-trainings')
            ->assertOk();

        $this->assertNull($member->fresh()->last_seen);
    }

    public function test_impersonate_granted_by_jabatan_is_shown_checked_and_disabled(): void
    {
        $employee = $this->employeeWithImpersonateFromRole();

        $html = $this->actingAs($this->superAdmin())->get("/user/{$employee->id}/edit")
            ->assertOk()
            ->assertSee('berasal dari jabatan STAFF')
            ->getContent();

        // Kotak dicentang (hak efektif) tapi mati, plus input tersembunyi impersonate=0 seperti sebelumnya.
        $this->assertStringContainsString('name="impersonate" value="1" checked disabled', $html);
        $this->assertStringContainsString('<input type="hidden" name="impersonate" value="0">', $html);
        $this->assertFalse($employee->canImpersonateDirectly());
    }

    public function test_impersonate_granted_directly_stays_editable(): void
    {
        $employee = User::factory()->staff()->create(['area_id' => 1]);
        $employee->setImpersonator(true);

        $html = $this->actingAs($this->superAdmin())->get("/user/{$employee->id}/edit")
            ->assertOk()
            ->assertDontSee('berasal dari jabatan')
            ->getContent();

        $this->assertStringContainsString('name="impersonate" value="1" checked', $html);
        $this->assertStringNotContainsString('name="impersonate" value="1" checked disabled', $html);
    }

    public function test_saving_a_user_with_impersonate_from_jabatan_keeps_the_right_without_granting_it_directly(): void
    {
        $employee = $this->employeeWithImpersonateFromRole();
        $staffRoleId = Role::findByName('STAFF', 'web')->id;

        $this->actingAs($this->superAdmin())->put("/user/{$employee->id}", [
            'employee_id' => $employee->employee_id,
            'nama_lengkap' => $employee->nama_lengkap,
            'username' => $employee->username,
            'email' => $employee->email,
            'area_id' => 1,
            'divisi_id' => $employee->divisi_id,
            'role_id' => $staffRoleId,
            // Form selalu mengirim hidden 0; kotak yang mati tidak ikut terkirim.
            'impersonate' => '0',
        ])->assertRedirect('/user');

        $fresh = $employee->fresh();
        $this->assertTrue($fresh->checkPermissionTo(User::IMPERSONATE_PERMISSION), 'Hak dari jabatan tidak boleh tercabut.');
        $this->assertFalse($fresh->canImpersonateDirectly(), 'Jangan sampai muncul grant langsung yang duplikat.');
        $this->assertTrue(Role::findByName('STAFF', 'web')->hasPermissionTo(User::IMPERSONATE_PERMISSION));
    }

    public function test_impersonate_button_is_hidden_while_impersonating(): void
    {
        $manager = User::factory()->staff()->create(['area_id' => 1, 'nama_lengkap' => 'MANAJER TIM']);
        $manager->givePermissionTo('manage-users');
        $manager->setImpersonator(true);
        $member = User::factory()->staff()->create(['area_id' => 1, 'approval_id' => $manager->id]);

        $this->actingAs($manager->fresh())->get('/user')
            ->assertOk()
            ->assertSee(route('user.impersonate', $member), false);

        // Tombol masuk sebagai user lain selalu ditolak saat sedang impersonate: jangan ditampilkan sama sekali.
        $this->actingAs($manager)->withSession($this->impersonationSession($this->superAdmin()))
            ->get('/user')
            ->assertOk()
            ->assertDontSee(route('user.impersonate', $member), false);
    }

    public function test_refusals_explain_why_they_refuse(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->from('/user')->post("/user/{$admin->id}/impersonate")
            ->assertRedirect('/user')
            ->assertSessionHas('error', 'Anda tidak dapat masuk sebagai diri sendiri.');

        $archived = User::factory()->staff()->create(['area_id' => 1]);
        $archived->delete();

        $this->actingAs($admin)->from('/user')->post("/user/{$archived->id}/impersonate")
            ->assertRedirect('/user')
            ->assertSessionHas('error', 'User ini sudah diarsipkan, jadi tidak bisa dimasuki.');

        [$supervisor, $member] = $this->supervisorWithTeam();
        $other = User::factory()->staff()->create(['area_id' => 1]);

        $this->actingAs($member)->withSession($this->impersonationSession($supervisor))
            ->from('/my-trainings')->post("/user/{$other->id}/impersonate")
            ->assertSessionHas('error', 'Anda sedang masuk sebagai user lain. Kembali ke akun Anda dulu.');
        $this->assertAuthenticatedAs($member);
    }

    public function test_impersonator_last_seen_is_recorded_and_the_targets_is_not(): void
    {
        $admin = $this->superAdmin();
        User::whereKey($admin->id)->update(['last_seen' => null]);
        $member = User::factory()->staff()->create(['area_id' => 1, 'last_seen' => null]);

        $this->actingAs($member)->withSession($this->impersonationSession($admin))
            ->get('/my-trainings')
            ->assertOk();

        $this->assertNotNull($admin->fresh()->last_seen, 'Aktivitas akun asal harus tetap tercatat.');
        $this->assertNull($member->fresh()->last_seen);
    }

    public function test_leaving_with_an_archived_origin_account_ends_the_session_with_a_message(): void
    {
        $admin = $this->superAdmin();
        $member = User::factory()->staff()->create(['area_id' => 1]);
        $admin->delete();

        $this->actingAs($member)->withSession($this->impersonationSession($admin))
            ->from('/my-trainings')->delete('/impersonate')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Sesi Anda sebagai user lain berakhir karena akun asal Anda sudah tidak aktif. Silakan masuk kembali.');

        $this->assertGuest();
    }

    public function test_empty_user_list_spans_the_columns_the_tab_actually_renders(): void
    {
        $admin = $this->superAdmin();

        // Tab Semua punya kotak centang massal: 13 kolom.
        $semua = $this->actingAs($admin)->get('/user')->assertOk()->getContent();
        $this->assertSame(13, preg_match_all('/<th[\s>]/', $semua), 'Tab Semua harus punya 13 kolom.');

        // Tab Arsip tidak punya kotak centang, jadi baris "tidak ada data" tidak boleh melebar 13 kolom.
        $arsip = $this->actingAs($admin)->get('/user?tab=arsip')->assertOk()->getContent();
        $this->assertSame(12, preg_match_all('/<th[\s>]/', $arsip), 'Tab Arsip harus punya 12 kolom.');
        $this->assertStringContainsString('colspan="12"', $arsip);
    }

    public function test_inactive_user_is_labelled_in_indonesian(): void
    {
        User::factory()->staff()->create(['area_id' => 1, 'nama_lengkap' => 'KARYAWAN BARU', 'last_seen' => null]);

        $this->actingAs($this->superAdmin())->get('/user')
            ->assertOk()
            ->assertSee('Tidak aktif')
            ->assertDontSee('Inactive');
    }
}
