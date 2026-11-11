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
            ->assertSee('<option value="'.$user->id.'"', false)
            // Pemilih peserta dibuat browser dari data ringkas ini: nama, ID karyawan, dan username bisa dicari.
            ->assertSee($this->pickerRow($user), false)
            ->assertViewHas('users', fn ($users) => $users->contains('id', $user->id));
    }

    /**
     * Baris user di data JSON pemilih peserta: [id, nama, id karyawan, username, id divisi, punya nomor WA].
     */
    private function pickerRow(User $user): string
    {
        return json_encode([$user->id, $user->nama_lengkap, 'EMP-901', 'azka', $user->divisi_id, false]);
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
            ->assertSee('<option value="'.$user->id.'"', false)
            ->assertSee($this->pickerRow($user), false);
    }

    public function test_edit_form_marks_saved_participants_and_only_ships_lean_user_rows(): void
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
        $member = User::factory()->staff()->create(['no_hp' => '081234567890']);
        $training->participants()->create(['user_id' => $member->id, 'token' => 'tok-member', 'attendance_status' => 'pending']);

        $response = $this->actingAs($admin)->get(route('training.edit', $training))->assertOk();

        // Peserta tersimpan menjadi pilihan awal dan dasar konfirmasi "dikeluarkan".
        $response->assertSee('const SAVED_IDS = ['.$member->id.'];', false)
            ->assertSee('const selected = new Set(['.$member->id.']);', false)
            ->assertSee('id="participantList"', false)
            // Baris daftar tidak lagi dirender server untuk setiap user.
            ->assertDontSee('name="participants[]" value="'.$member->id.'"', false)
            ->assertViewHas('users', fn ($users) => ! $users->first() instanceof User && ! property_exists($users->first(), 'password'));
    }
}
