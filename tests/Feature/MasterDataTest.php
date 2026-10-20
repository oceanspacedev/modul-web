<?php

namespace Tests\Feature;

use App\Models\Divisi;
use App\Models\SubDivisi;
use App\Models\User;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    private function admin(): User
    {
        return User::where('username', 'admin')->first();
    }

    public function test_divisi_can_be_created_updated_and_deleted(): void
    {
        $this->actingAs($this->admin())->post('/divisi', ['name' => '  marketing  '])
            ->assertRedirect('/divisi')
            ->assertSessionHas('success');

        $divisi = Divisi::where('name', 'MARKETING')->firstOrFail();

        $this->actingAs($this->admin())->get("/divisi/{$divisi->id}/edit")->assertOk()->assertSee('MARKETING');

        $this->actingAs($this->admin())->put("/divisi/{$divisi->id}", ['name' => 'Sales'])->assertRedirect('/divisi');
        $this->assertSame('SALES', $divisi->fresh()->name);

        $this->actingAs($this->admin())->delete("/divisi/{$divisi->id}")->assertRedirect('/divisi');
        $this->assertSoftDeleted($divisi);
    }

    public function test_divisi_name_is_required_and_unique(): void
    {
        $this->actingAs($this->admin())->from('/divisi')->post('/divisi', ['name' => ''])
            ->assertRedirect('/divisi')
            ->assertSessionHasErrors('name');

        Divisi::create(['name' => 'MARKETING']);

        $this->actingAs($this->admin())->from('/divisi')->post('/divisi', ['name' => 'marketing'])
            ->assertSessionHasErrors('name');
    }

    public function test_sub_divisi_must_belong_to_an_existing_divisi(): void
    {
        $this->actingAs($this->admin())->from('/subdivisi')->post('/subdivisi', ['divisi_id' => 999, 'name' => 'Area 1'])
            ->assertSessionHasErrors('divisi_id');

        $this->actingAs($this->admin())->post('/subdivisi', ['divisi_id' => 1, 'name' => 'Area 1'])->assertRedirect('/subdivisi');
        $this->assertDatabaseHas('sub_divisis', ['divisi_id' => 1, 'name' => 'AREA 1']);
    }

    public function test_sub_divisi_lookup_is_available_to_any_signed_in_user(): void
    {
        SubDivisi::create(['divisi_id' => 1, 'name' => 'AREA JAKARTA']);
        $staff = User::factory()->create(['job_level_id' => 2]);
        $staff->syncRoles(['Staff']);

        $this->actingAs($staff)->getJson('/subdivisi/get/1')
            ->assertOk()
            ->assertJsonFragment(['name' => 'AREA JAKARTA']);
    }

    public function test_master_data_requires_permission(): void
    {
        $staff = User::factory()->create(['job_level_id' => 2]);
        $staff->syncRoles(['Staff']);

        $this->actingAs($staff)->from('/dashboard')->get('/joblevel')
            ->assertRedirect('/dashboard')
            ->assertSessionHas('error');

        $this->actingAs($staff)->post('/dokumentype', ['name' => 'RAHASIA']);
        $this->assertDatabaseMissing('dokumen_types', ['name' => 'RAHASIA']);
    }
}
