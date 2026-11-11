<?php

namespace Tests\Feature;

use App\Models\Divisi;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\TrainingQuestion;
use App\Models\User;
use Tests\TestCase;

class TrainingQuizAnswersVisibilityTest extends TestCase
{
    protected User $admin;

    protected Training $training;

    protected TrainingParticipant $participant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('username', 'admin')->first() ?? User::factory()->admin()->create([
            'username' => 'admin',
        ]);

        $divisi = Divisi::first() ?? Divisi::create(['name' => 'Divisi Test']);

        $user = User::create([
            'username' => 'peserta_kunci_'.uniqid(),
            'nama_lengkap' => 'Peserta Uji Kunci',
            'email' => 'kunci_'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'divisi_id' => $divisi->id,
        ]);

        $this->training = Training::create([
            'title' => 'Pelatihan Uji Visibilitas Jawaban',
            'trainer_id' => $this->admin->id,
            'description' => 'Test setelan tampilkan jawaban benar/salah.',
            'training_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'zoom_link' => 'https://zoom.us/test',
            'is_quiz_active' => true,
            'is_attendance_active' => true,
            'quiz_mode' => 'formal',
        ]);

        TrainingQuestion::create([
            'training_id' => $this->training->id,
            'type' => 'multiple_choice',
            'question' => 'Warna langit cerah?',
            'option_a' => 'Merah',
            'option_b' => 'Biru',
            'option_c' => 'Hijau',
            'option_d' => 'Kuning',
            'correct_answer' => 'b',
            'explanation' => 'Pembahasan rahasia hamburan Rayleigh',
        ]);

        $this->participant = TrainingParticipant::create([
            'training_id' => $this->training->id,
            'user_id' => $user->id,
            'token' => 'token_kunci_'.uniqid(),
            'attendance_status' => 'hadir',
            'attended_at' => now(),
        ]);
    }

    public function test_answers_are_shown_by_default(): void
    {
        $this->assertTrue($this->training->fresh()->show_quiz_answers);
    }

    public function test_admin_can_toggle_answer_visibility(): void
    {
        $this->actingAs($this->admin)
            ->post(route('training.quiz-rules', $this->training), ['quiz' => ['posttest' => '1', 'posttest_max_attempts' => '1', 'posttest_shows_answers' => '0']])
            ->assertSessionHas('success');

        $this->assertFalse($this->training->fresh()->show_quiz_answers);

        $this->actingAs($this->admin)
            ->get("/training/{$this->training->id}")
            ->assertOk()
            ->assertSee('hasil nilai saja');

        $this->actingAs($this->admin)
            ->post(route('training.quiz-rules', $this->training), ['quiz' => ['posttest' => '1', 'posttest_max_attempts' => '1', 'posttest_shows_answers' => '1']]);

        $this->assertTrue($this->training->fresh()->show_quiz_answers);
    }

    public function test_result_page_shows_answer_details_when_enabled(): void
    {
        $this->submitWrongAnswer();

        $this->get("/training/portal/{$this->participant->token}/result")
            ->assertOk()
            ->assertSee('Rincian Jawaban & Kunci', false)
            ->assertSee('Kunci Jawaban:')
            ->assertSee('Pembahasan rahasia hamburan Rayleigh')
            ->assertDontSee('Rincian jawaban tidak ditampilkan untuk kuis ini');
    }

    public function test_result_page_hides_answer_details_when_disabled(): void
    {
        $this->training->update(['show_quiz_answers' => false]);
        $this->submitWrongAnswer();

        $this->get("/training/portal/{$this->participant->token}/result")
            ->assertOk()
            ->assertSee('Rincian jawaban tidak ditampilkan untuk kuis ini')
            ->assertSee('0 / 1 Benar')
            ->assertDontSee('Rincian Jawaban & Kunci', false)
            ->assertDontSee('Kunci Jawaban:')
            ->assertDontSee('Pembahasan rahasia hamburan Rayleigh');
    }

    public function test_game_mode_keeps_answer_key_when_enabled(): void
    {
        $this->training->update(['quiz_mode' => 'game']);

        $this->get("/training/portal/{$this->participant->token}/quiz")
            ->assertOk()
            ->assertSee('"correct_answer":"b"', false)
            ->assertSee('const SHOW_ANSWERS = true', false)
            ->assertSee('Jawaban Benar');
    }

    public function test_game_mode_does_not_send_answer_key_when_disabled(): void
    {
        $this->training->update(['quiz_mode' => 'game', 'show_quiz_answers' => false]);

        $this->get("/training/portal/{$this->participant->token}/quiz")
            ->assertOk()
            ->assertSee('Warna langit cerah?')
            ->assertSee('const SHOW_ANSWERS = false', false)
            ->assertDontSee('"correct_answer"', false)
            ->assertDontSee('Pembahasan rahasia hamburan Rayleigh')
            ->assertDontSee('Jawaban Benar');
    }

    private function submitWrongAnswer(): void
    {
        $questionId = $this->training->questions()->value('id');

        $this->post("/training/portal/{$this->participant->token}/quiz", [
            'answers' => [$questionId => 'a'],
        ])->assertRedirect();
    }
}
