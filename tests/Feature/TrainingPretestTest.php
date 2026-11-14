<?php

namespace Tests\Feature;

use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\TrainingQuestion;
use App\Models\TrainingQuizResult;
use App\Models\User;
use Tests\TestCase;

/**
 * Pelatihan dengan pretest dan posttest: aturan per fase dari blok Aturan Kuis, akses dibuka terpisah, dan nilai
 * disimpan per fase.
 */
class TrainingPretestTest extends TestCase
{
    private User $admin;

    private Training $training;

    private TrainingParticipant $participant;

    private TrainingQuestion $question;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('username', 'admin')->sole();
        $this->training = Training::create([
            'title' => 'Pelatihan Pretest', 'trainer_id' => $this->admin->id, 'training_date' => now()->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:00', 'zoom_link' => 'https://zoom.us/test',
            'has_pretest' => true, 'pretest_max_attempts' => 2, 'pretest_shows_answers' => false,
        ]);
        $this->question = TrainingQuestion::create([
            'training_id' => $this->training->id, 'type' => 'multiple_choice', 'question' => 'APD utama?',
            'option_a' => 'Helm', 'option_b' => 'Topi', 'correct_answer' => 'a',
        ]);
        TrainingQuestion::create(['training_id' => $this->training->id, 'type' => 'essay', 'question' => 'Jelaskan prosedur evakuasi.']);
        $this->participant = TrainingParticipant::create([
            'training_id' => $this->training->id, 'user_id' => User::factory()->staff()->create()->id,
            'token' => 'token_pretest', 'attendance_status' => 'pending',
        ]);
    }

    private function submit(string $path, string $answer)
    {
        return $this->post('/training/portal/token_pretest/'.$path, ['answers' => [$this->question->id => $answer]]);
    }

    public function test_training_form_saves_rules_for_each_phase(): void
    {
        $this->actingAs($this->admin)->get(route('training.create'))->assertOk()->assertSee('Aturan Kuis')->assertSee('Sebelum sesi dimulai');

        $this->actingAs($this->admin)->post('/training', [
            'title' => 'Pelatihan Baru', 'trainer_id' => $this->admin->id, 'training_date' => now()->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:00', 'zoom_link' => 'https://zoom.us/test',
            'participants' => [User::factory()->staff()->create()->id],
            'quiz' => [
                'pretest' => '1', 'pretest_max_attempts' => '2', 'pretest_shows_answers' => '0',
                'posttest' => '1', 'posttest_max_attempts' => '', 'posttest_shows_answers' => '1', 'mode' => 'game',
            ],
        ])->assertSessionHasNoErrors();

        $training = Training::where('title', 'Pelatihan Baru')->sole();
        $this->assertTrue($training->has_pretest);
        $this->assertSame(2, $training->pretest_max_attempts);
        $this->assertFalse($training->pretest_shows_answers);
        $this->assertTrue($training->has_posttest);
        $this->assertNull($training->quiz_max_attempts);
        $this->assertTrue($training->show_quiz_answers);
        $this->assertSame('game', $training->quiz_mode);

        $this->actingAs($this->admin)->get(route('training.edit', $training))->assertOk()->assertSee('Aturan Kuis');
    }

    public function test_training_without_any_quiz_skips_the_question_step(): void
    {
        $this->actingAs($this->admin)->post('/training', [
            'title' => 'Tanpa Kuis', 'trainer_id' => $this->admin->id, 'training_date' => now()->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:00', 'zoom_link' => 'https://zoom.us/test',
            'participants' => [User::factory()->staff()->create()->id],
            'quiz' => ['pretest' => '0', 'posttest' => '0'],
        ])->assertRedirect(route('training.setup', Training::where('title', 'Tanpa Kuis')->sole()));

        $training = Training::where('title', 'Tanpa Kuis')->sole();
        $this->assertFalse($training->hasQuiz());

        $token = $training->participants()->sole()->token;
        $this->get("/training/portal/{$token}")->assertOk()->assertDontSee('Kuis Evaluasi')->assertDontSee('pretestCard');
        $this->actingAs($this->admin)->get(route('training.show', $training))->assertOk()->assertSee('Tanpa kuis');
        $this->actingAs($this->admin)->post(route('training.toggle-quiz', $training))->assertSessionHas('error');
    }

    public function test_trainer_opens_pretest_and_posttest_separately(): void
    {
        $this->actingAs($this->admin)->get(route('training.show', $this->training))
            ->assertOk()->assertSee('Akses Pretest')->assertSee('Akses Posttest')->assertSee('Pretest: 2 kali');

        // Pretest belum dibuka: peserta menunggu.
        $this->get('/training/portal/token_pretest')->assertOk()->assertSee('Pretest belum dibuka oleh pemateri');
        $this->get('/training/portal/token_pretest/pretest')->assertRedirect('/training/portal/token_pretest');

        $this->actingAs($this->admin)->post(route('training.toggle-pretest', $this->training))->assertSessionHas('success');
        $this->assertTrue($this->training->fresh()->is_pretest_active);
        $this->assertFalse($this->training->fresh()->is_quiz_active);

        $this->get('/training/portal/token_pretest/status')->assertJson(['is_pretest_active' => true, 'is_quiz_active' => false]);
        $this->get('/training/portal/token_pretest')->assertSee('Mulai Pretest')->assertSee('Boleh dikerjakan 2 kali');
        // Posttest tetap terkunci.
        $this->get('/training/portal/token_pretest/quiz')->assertRedirect('/training/portal/token_pretest');
    }

    public function test_staff_cannot_open_pretest_or_change_rules(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->from('/my-trainings')->post(route('training.toggle-pretest', $this->training))->assertSessionHas('error');
        $this->actingAs($staff)->from('/my-trainings')->post(route('training.quiz-rules', $this->training), ['quiz' => ['pretest' => '0', 'posttest' => '1']])
            ->assertSessionHas('error');

        $this->assertFalse($this->training->fresh()->is_pretest_active);
        $this->assertTrue($this->training->fresh()->has_pretest);
    }

    public function test_pretest_uses_multiple_choice_only_and_keeps_its_own_score(): void
    {
        $this->training->update(['is_pretest_active' => true]);

        // Soal essay tidak ikut pretest.
        $this->get('/training/portal/token_pretest/pretest')->assertOk()
            ->assertSee('Pretest Pelatihan')->assertSee('APD utama?')->assertDontSee('Jelaskan prosedur evakuasi.');

        $this->submit('pretest', 'b')->assertRedirect('/training/portal/token_pretest/pretest/result');

        $pretest = TrainingQuizResult::where('phase', 'pretest')->sole();
        $this->assertSame(0.0, $pretest->score);
        $this->assertSame(1, $pretest->total_questions);
        $this->assertSame('none', $pretest->essay_status);

        // Hasil pretest: hanya nilai, tanpa kunci jawaban.
        $this->get('/training/portal/token_pretest/pretest/result')->assertOk()->assertDontSee('Kunci Jawaban');

        // Percobaan kedua boleh, ketiga ditolak (batas 2 kali); nilai tertinggi yang dipakai.
        $this->get('/training/portal/token_pretest')->assertSee('Kerjakan Ulang')->assertSee('Sisa 1 kali');
        $this->submit('pretest', 'a')->assertRedirect('/training/portal/token_pretest/pretest/result');
        $this->assertSame(100.0, $pretest->fresh()->score);
        $this->get('/training/portal/token_pretest/pretest')->assertRedirect('/training/portal/token_pretest/pretest/result');
        $this->submit('pretest', 'b')->assertRedirect('/training/portal/token_pretest/pretest/result');
        $this->assertSame(100.0, $pretest->fresh()->score);
        $this->assertSame(2, $pretest->fresh()->attempt_count);

        // Posttest berdiri sendiri: nilai pretest tidak masuk rekap kuis.
        $this->assertNull($this->participant->fresh()->quizResult);
        $this->training->update(['is_quiz_active' => true]);
        $this->submit('quiz', 'a')->assertRedirect('/training/portal/token_pretest/result');

        $this->assertSame(2, TrainingQuizResult::count());
        $this->assertSame('posttest', $this->participant->fresh()->quizResult->phase);
        $this->assertSame('pending', $this->participant->fresh()->quizResult->essay_status);
        $this->assertSame(1, $this->training->quizResults()->count());
        $this->assertSame(1, $this->training->pretestResults()->count());

        $this->actingAs($this->admin)->get(route('training.show', $this->training))
            ->assertOk()->assertSeeInOrder(['participant-detail-label">Pretest</h6>', 'tertinggi dari 2x', 'participant-detail-value">100</span>'], false);
        $this->actingAs($this->participant->user)->get('/my-trainings')->assertOk()->assertSee('Pretest 100');
    }

    public function test_turning_a_phase_off_closes_it_for_participants(): void
    {
        $this->training->update(['is_pretest_active' => true, 'is_quiz_active' => true]);

        $this->actingAs($this->admin)->post(route('training.quiz-rules', $this->training), [
            'quiz' => ['pretest' => '0', 'posttest' => '1', 'posttest_max_attempts' => '1', 'posttest_shows_answers' => '1'],
        ])->assertSessionHas('success');

        $training = $this->training->fresh();
        $this->assertFalse($training->has_pretest);
        $this->assertFalse($training->is_pretest_active);
        $this->assertTrue($training->is_quiz_active);

        $this->get('/training/portal/token_pretest')->assertOk()->assertDontSee('Mulai Pretest')->assertSee('Kuis Evaluasi');
        $this->get('/training/portal/token_pretest/pretest')->assertRedirect('/training/portal/token_pretest');
        $this->submit('pretest', 'a')->assertNotFound();
    }

    public function test_pretest_needs_a_multiple_choice_question(): void
    {
        $this->question->delete();

        $this->actingAs($this->admin)->post(route('training.toggle-pretest', $this->training))->assertSessionHas('error');
        $this->assertFalse($this->training->fresh()->is_pretest_active);
    }
}
