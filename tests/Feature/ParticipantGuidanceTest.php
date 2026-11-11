<?php

namespace Tests\Feature;

use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\User;
use Tests\TestCase;

/**
 * Halaman peserta menunjukkan langkah berikutnya, bukan hanya status.
 */
class ParticipantGuidanceTest extends TestCase
{
    private User $staff;

    private Training $training;

    private TrainingParticipant $participant;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::where('username', 'admin')->sole();
        $this->staff = User::factory()->staff()->create();
        $this->training = Training::create([
            'title' => 'Pelatihan K3', 'trainer_id' => $admin->id, 'training_date' => now()->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:00', 'zoom_link' => 'https://zoom.us/test',
        ]);
        $this->participant = TrainingParticipant::create([
            'training_id' => $this->training->id, 'user_id' => $this->staff->id, 'token' => 'token_guidance', 'attendance_status' => 'pending',
        ]);
    }

    public function test_my_trainings_shows_the_next_step(): void
    {
        $this->actingAs($this->staff)->get(route('training.my-trainings'))
            ->assertOk()
            ->assertSee('Yang Perlu Dilakukan')
            ->assertSee('Menunggu pemateri membuka presensi/kuis');

        $this->training->update(['is_attendance_active' => true]);
        $this->actingAs($this->staff)->get(route('training.my-trainings'))->assertSee('Isi presensi sekarang');

        $this->participant->update(['attendance_status' => 'hadir']);
        $this->training->update(['is_quiz_active' => true]);
        $this->actingAs($this->staff)->get(route('training.my-trainings'))->assertSee('Kerjakan kuis sekarang');
    }

    public function test_session_portal_numbers_the_steps(): void
    {
        $this->get(route('training.portal', $this->participant->token))
            ->assertOk()
            ->assertSeeInOrder(['Langkah 1', 'Langkah 2', 'Presensi Kehadiran', 'Langkah 3', 'Kuis Evaluasi'])
            ->assertSee('Kuis belum dibuka.');
    }

    public function test_empty_module_list_explains_targeting(): void
    {
        $this->actingAs($this->staff)->get(route('learning.index'))
            ->assertOk()
            ->assertSee('untuk divisi dan jabatan Anda');
    }
}
