<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
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
        $this->actingAs($admin)->post("/user/{$archived->id}/impersonate")->assertNotFound();
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
}
