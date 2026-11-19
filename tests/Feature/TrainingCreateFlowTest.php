<?php

namespace Tests\Feature;

use App\Models\Training;
use App\Models\User;
use Tests\TestCase;

/**
 * Form Buat Pelatihan tiga langkah: info, langkah peserta (kuis, presensi, evaluasi), lalu peserta & materi.
 * Presensi ditanyakan di form, jadi pelatihan baru tidak perlu lagi melewati pertanyaan pengaturan terpisah.
 */
class TrainingCreateFlowTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('username', 'admin')->sole();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function submit(array $overrides): Training
    {
        $this->actingAs($this->admin)->post('/training', [
            'title' => 'Pelatihan Tiga Langkah',
            'trainer_id' => $this->admin->id,
            'training_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'participants' => [User::factory()->staff()->create()->id],
            'quiz' => ['pretest' => '0', 'posttest' => '1'],
            'has_attendance' => '1',
            'require_attendance_proof' => '0',
            'has_evaluation' => '0',
            ...$overrides,
        ])->assertRedirect();

        return Training::where('title', 'Pelatihan Tiga Langkah')->sole();
    }

    public function test_create_form_shows_three_steps_with_one_list_of_participant_steps(): void
    {
        $this->actingAs($this->admin)->get(route('training.create'))->assertOk()
            ->assertSeeInOrder(['Info pelatihan', 'Langkah peserta', 'Peserta &amp; materi'], false)
            ->assertSeeInOrder(['data-step="1"', 'name="title"', 'data-step="2"', 'name="quiz[pretest]"', 'name="has_attendance"',
                'name="require_attendance_proof"', 'name="has_evaluation"', 'name="quiz[posttest]"', 'Atur detail kuis',
                'data-step="3"', 'Impor Excel', 'name="documents[]"', 'name="send_wa_now"'], false)
            // Posttest dan presensi menyala secara bawaan; pretest dan evaluasi tidak.
            ->assertSee('id="quiz_posttest" name="quiz[posttest]" value="1" data-quiz-toggle checked', false)
            ->assertSee('id="has_attendance" name="has_attendance" value="1" checked', false)
            ->assertDontSee('id="quiz_pretest" name="quiz[pretest]" value="1" data-quiz-toggle checked', false)
            ->assertDontSee('Kirim notifikasi WhatsApp');
    }

    public function test_training_with_quiz_goes_to_questions_without_the_separate_setup_step(): void
    {
        $training = $this->submit(['require_attendance_proof' => '1']);

        $this->assertFalse($training->needsSetup());
        $this->assertTrue($training->has_attendance);
        $this->assertTrue($training->require_attendance_proof);
        $this->assertFalse($training->is_attendance_active);
        $this->assertTrue($training->has_posttest);
        $this->assertFalse($training->has_pretest);

        // Simpan soal tidak lagi mengarahkan ke pertanyaan pengaturan.
        $this->actingAs($this->admin)->postJson(route('training.questions.save-ai-batch', $training), [
            'questions' => [['type' => 'multiple_choice', 'question' => 'APD utama?', 'option_a' => 'Helm', 'option_b' => 'Topi', 'correct_answer' => 'a']],
        ])->assertOk()->assertJson(['redirect' => null]);
        $this->actingAs($this->admin)->get(route('training.show', $training))->assertOk()->assertDontSee('Lengkapi pengaturan');
    }

    public function test_training_without_quiz_or_attendance_opens_its_detail_page(): void
    {
        $this->actingAs($this->admin)->post('/training', [
            'title' => 'Pelatihan Tiga Langkah',
            'trainer_id' => $this->admin->id,
            'training_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'participants' => [User::factory()->staff()->create()->id],
            'quiz' => ['pretest' => '0', 'posttest' => '0'],
            'has_attendance' => '0',
            // Bukti foto tidak berlaku tanpa presensi, walau tercentang.
            'require_attendance_proof' => '1',
            'has_evaluation' => '1',
        ])->assertRedirect(route('training.show', Training::where('title', 'Pelatihan Tiga Langkah')->sole()))
            ->assertSessionHas('success');

        $training = Training::where('title', 'Pelatihan Tiga Langkah')->sole();
        $this->assertFalse($training->needsSetup());
        $this->assertFalse($training->hasQuiz());
        $this->assertFalse($training->has_attendance);
        $this->assertFalse($training->require_attendance_proof);
        $this->assertTrue($training->has_evaluation);
    }

    public function test_failed_validation_reopens_the_step_with_the_error(): void
    {
        $form = [
            'title' => 'Tanpa Peserta',
            'trainer_id' => $this->admin->id,
            'training_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'participants' => [User::factory()->staff()->create()->id],
            'quiz' => ['pretest' => '0', 'posttest' => '1'],
            'has_attendance' => '1',
        ];
        $retry = fn (array $overrides) => $this->actingAs($this->admin)->from(route('training.create'))->followingRedirects()
            ->post('/training', [...$form, ...$overrides])->assertOk();

        $retry(['participants' => []])->assertSee('data-start-step="3"', false)->assertSee('Pilih minimal satu peserta');
        $retry(['quiz' => ['pretest' => '0', 'posttest' => '1', 'posttest_max_attempts' => 'banyak']])->assertSee('data-start-step="2"', false);
        $retry(['title' => ''])->assertSee('data-start-step="1"', false);
        $this->assertSame(0, Training::count());
    }
}
