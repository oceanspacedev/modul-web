<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Divisi;
use App\Models\Position;
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
        $manager = User::factory()->jabatan('MANAGER')->create(['area_id' => 1]);

        $this->actingAs($this->admin())->post('/divisi', ['name' => '  marketing  ', 'area_id' => 1, 'manager_id' => $manager->id])
            ->assertRedirect('/divisi')
            ->assertSessionHas('success');

        $divisi = Divisi::where('name', 'MARKETING')->firstOrFail();
        $this->assertSame(1, (int) $divisi->area_id);
        $this->assertSame($manager->id, (int) $divisi->manager_id);

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

    public function test_divisi_area_and_manager_must_exist(): void
    {
        $this->actingAs($this->admin())->from('/divisi')->post('/divisi', ['name' => 'Gudang', 'area_id' => 999, 'manager_id' => 999])
            ->assertSessionHasErrors(['area_id', 'manager_id']);

        $this->assertDatabaseMissing('divisis', ['name' => 'GUDANG']);
    }

    public function test_area_can_be_created_updated_and_deleted(): void
    {
        $this->actingAs($this->admin())->post('/area', ['name' => '  pt jakarta  '])
            ->assertRedirect('/area')
            ->assertSessionHas('success');

        $area = Area::where('name', 'PT JAKARTA')->firstOrFail();

        $this->actingAs($this->admin())->get('/area')->assertOk()->assertSee('PT JAKARTA');
        $this->actingAs($this->admin())->get("/area/{$area->id}/edit")->assertOk()->assertSee('PT JAKARTA');

        $this->actingAs($this->admin())->put("/area/{$area->id}", ['name' => 'pt bandung'])->assertRedirect('/area');
        $this->assertSame('PT BANDUNG', $area->fresh()->name);

        $this->actingAs($this->admin())->delete("/area/{$area->id}")->assertRedirect('/area')->assertSessionHas('success');
        $this->assertModelMissing($area);
    }

    public function test_area_name_is_required_and_unique(): void
    {
        $this->actingAs($this->admin())->from('/area')->post('/area', ['name' => ''])
            ->assertRedirect('/area')
            ->assertSessionHasErrors('name');

        // Seeded area is named "TEST AREA".
        $this->actingAs($this->admin())->from('/area')->post('/area', ['name' => 'test area'])
            ->assertSessionHasErrors('name');
        $this->assertSame(1, Area::where('name', 'TEST AREA')->count());
    }

    public function test_area_used_by_users_cannot_be_deleted(): void
    {
        $area = Area::create(['name' => 'PT SURABAYA']);
        $user = User::factory()->staff()->create(['area_id' => $area->id]);

        $this->actingAs($this->admin())->delete("/area/{$area->id}")
            ->assertRedirect('/area')
            ->assertSessionHas('error');
        $this->assertModelExists($area);

        // Archived users still block the deletion.
        $user->delete();
        $this->actingAs($this->admin())->delete("/area/{$area->id}")->assertSessionHas('error');
        $this->assertModelExists($area);
    }

    public function test_position_can_be_created_updated_and_deleted(): void
    {
        $this->actingAs($this->admin())->post('/position', ['name' => '  sales executive '])
            ->assertRedirect('/position')
            ->assertSessionHas('success');

        $position = Position::where('name', 'SALES EXECUTIVE')->firstOrFail();

        $this->actingAs($this->admin())->get('/position')->assertOk()->assertSee('SALES EXECUTIVE');
        $this->actingAs($this->admin())->get("/position/{$position->id}/edit")->assertOk()->assertSee('SALES EXECUTIVE');

        $this->actingAs($this->admin())->put("/position/{$position->id}", ['name' => 'account manager'])->assertRedirect('/position');
        $this->assertSame('ACCOUNT MANAGER', $position->fresh()->name);

        $this->actingAs($this->admin())->delete("/position/{$position->id}")->assertRedirect('/position');
        $this->assertSoftDeleted($position);
    }

    public function test_position_name_is_required_and_unique(): void
    {
        $this->actingAs($this->admin())->from('/position')->post('/position', ['name' => ''])
            ->assertRedirect('/position')
            ->assertSessionHasErrors('name');

        Position::create(['name' => 'KASIR']);

        $this->actingAs($this->admin())->from('/position')->post('/position', ['name' => 'kasir'])
            ->assertSessionHasErrors('name');
    }

    public function test_removed_master_data_routes_no_longer_exist(): void
    {
        $this->actingAs($this->admin())->get('/subdivisi')->assertNotFound();
        $this->actingAs($this->admin())->get('/subdivisi/get/1')->assertNotFound();
        $this->actingAs($this->admin())->get('/joblevel')->assertNotFound();
    }

    public function test_master_data_requires_permission(): void
    {
        $staff = User::factory()->staff()->create();

        foreach (['/area', '/position', '/divisi'] as $url) {
            $this->actingAs($staff)->from('/dashboard')->get($url)
                ->assertRedirect('/dashboard')
                ->assertSessionHas('error');
        }

        $this->actingAs($staff)->post('/area', ['name' => 'AREA RAHASIA']);
        $this->assertDatabaseMissing('areas', ['name' => 'AREA RAHASIA']);

        $this->actingAs($staff)->post('/position', ['name' => 'POSISI RAHASIA']);
        $this->assertDatabaseMissing('positions', ['name' => 'POSISI RAHASIA']);

        $this->actingAs($staff)->delete('/area/1');
        $this->assertDatabaseHas('areas', ['id' => 1]);

        $this->actingAs($staff)->post('/dokumentype', ['name' => 'RAHASIA']);
        $this->assertDatabaseMissing('dokumen_types', ['name' => 'RAHASIA']);
    }
}
