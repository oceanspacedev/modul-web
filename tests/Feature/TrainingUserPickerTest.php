<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Divisi;
use App\Models\Position;
use App\Models\Role;
use App\Models\Training;
use App\Models\User;
use Tests\TestCase;

/**
 * User baru dari Tambah User harus bisa dicari sebagai peserta/trainer di form pelatihan.
 */
class TrainingUserPickerTest extends TestCase
{
    private function createUserViaForm(User $admin): User
    {
        $area = Area::create(['name' => 'PT MAJU JAYA']);
        $divisi = Divisi::create(['name' => 'SALES', 'area_id' => $area->id]);
        $position = Position::create(['name' => 'SALES EXECUTIVE']);

        $this->actingAs($admin)->post('/user', [
            'employee_id' => 'EMP-901',
            'nama_lengkap' => 'budi santoso',
            'email' => 'budi@example.com',
            'username' => 'azka',
            'password' => 'Secret-Pass-123',
            'password_confirmation' => 'Secret-Pass-123',
            'area_id' => $area->id,
            'divisi_id' => $divisi->id,
            'role_id' => Role::findByName('STAFF', 'web')->id,
            'position_id' => $position->id,
        ])->assertSessionHasNoErrors();

        return User::where('employee_id', 'EMP-901')->firstOrFail();
    }

    public function test_new_user_is_searchable_by_name_and_username_in_create_form(): void
    {
        $admin = User::where('username', 'admin')->first();
        $user = $this->createUserViaForm($admin);

        $this->actingAs($admin)->get(route('training.create'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('data-name="budi santoso"', false)
            ->assertSee('data-username="azka"', false)
            ->assertSee('<option value="'.$user->id.'"', false)
            // Baris peserta ber-kelas d-flex (!important), jadi filter harus menyembunyikan lewat kelas d-none.
            ->assertSee("item.classList.toggle('d-none', !visible)", false)
            ->assertDontSee("item.style.display = 'none'", false);
    }

    public function test_new_user_is_searchable_in_edit_form(): void
    {
        $admin = User::where('username', 'admin')->first();
        $training = Training::create([
            'title' => 'Onboarding',
            'trainer_id' => $admin->id,
            'training_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'zoom_link' => 'https://zoom.us/j/123',
        ]);
        $user = $this->createUserViaForm($admin);

        $this->actingAs($admin)->get(route('training.edit', $training))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('data-username="azka"', false)
            ->assertSee('<option value="'.$user->id.'"', false)
            // Baris peserta ber-kelas d-flex (!important), jadi filter harus menyembunyikan lewat kelas d-none.
            ->assertSee("item.classList.toggle('d-none', !visible)", false)
            ->assertDontSee("item.style.display = 'none'", false);
    }
}
