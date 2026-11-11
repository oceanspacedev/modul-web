<?php

namespace Tests\Feature;

use App\Models\Training;
use App\Models\User;
use Tests\TestCase;

/**
 * Alur pelatihan baru: simpan → buat soal → pertanyaan pengaturan Ya/Tidak → halaman pelatihan.
 */
class TrainingSetupWizardTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('username', 'admin')->sole();
    }

    private function createTraining(): Training
    {
        $this->actingAs($this->admin)->post('/training', [
            'title' => 'Pelatihan Wizard',
            'trainer_id' => $this->admin->id,
            'training_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'zoom_link' => 'https://zoom.us/test',
            'participants' => [User::factory()->staff()->create()->id],
        ])->assertRedirect();

        return Training::where('title', 'Pelatihan Wizard')->sole();
    }

    public function test_new_training_goes_to_questions_then_setup(): void
    {
        $training = $this->createTraining();
        $this->assertTrue($training->needsSetup());

        // Belum ada soal: belum ada tombol lanjut.
        $this->actingAs($this->admin)->get(route('training.questions.index', $training))
            ->assertOk()->assertDontSee('Lanjut: Pengaturan');

        // Soal AI tersimpan → diarahkan ke pengaturan.
        $this->actingAs($this->admin)->postJson(route('training.questions.save-ai-batch', $training), [
            'questions' => [['type' => 'multiple_choice', 'question' => 'APD utama?', 'option_a' => 'Helm', 'option_b' => 'Topi', 'correct_answer' => 'a']],
        ])->assertOk()->assertJson(['redirect' => route('training.setup', $training)]);

        $this->actingAs($this->admin)->get(route('training.questions.index', $training))->assertSee('Lanjut: Pengaturan');
        $this->actingAs($this->admin)->get(route('training.setup', $training))
            ->assertOk()
            ->assertSeeInOrder(['Buka presensi sekarang?', 'Wajib upload bukti foto', 'Aturan kuis saat ini'])
            ->assertDontSee('Berapa kali kuis boleh dikerjakan?');

        $this->actingAs($this->admin)->post(route('training.setup.save', $training), [
            'is_attendance_active' => '1',
            'require_attendance_proof' => '0',
        ])->assertRedirect(route('training.show', $training))->assertSessionHas('success');

        $training->refresh();
        $this->assertFalse($training->needsSetup());
        $this->assertTrue($training->is_attendance_active);
        $this->assertFalse($training->require_attendance_proof);
        // Aturan kuis diatur di blok Aturan Kuis, bukan di pertanyaan pengaturan.
        $this->assertTrue($training->show_quiz_answers);
        $this->assertSame(1, $training->quiz_max_attempts);

        // Setelah diatur, simpan soal AI berikutnya tidak lagi mengarahkan ke pengaturan.
        $this->actingAs($this->admin)->postJson(route('training.questions.save-ai-batch', $training), [
            'questions' => [['type' => 'multiple_choice', 'question' => 'Warna jalur evakuasi?', 'option_a' => 'Hijau', 'option_b' => 'Merah', 'correct_answer' => 'a']],
        ])->assertJson(['redirect' => null]);
        $this->actingAs($this->admin)->get(route('training.questions.index', $training))->assertDontSee('Lanjut: Pengaturan');
    }

    public function test_every_answer_is_required(): void
    {
        $training = $this->createTraining();

        $this->actingAs($this->admin)->post(route('training.setup.save', $training), ['is_attendance_active' => '1'])
            ->assertSessionHasErrors(['require_attendance_proof']);
        $this->assertTrue($training->fresh()->needsSetup());
    }

    public function test_staff_cannot_open_setup(): void
    {
        $training = $this->createTraining();

        $this->actingAs(User::factory()->staff()->create())->get(route('training.setup', $training))->assertRedirect();
        $this->actingAs(User::factory()->staff()->create())->from('/my-trainings')->post(route('training.setup.save', $training), [
            'is_attendance_active' => '1', 'require_attendance_proof' => '1',
        ])->assertSessionHas('error');
        $this->assertTrue($training->fresh()->needsSetup());
    }
}
