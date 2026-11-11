<?php

namespace Tests\Feature;

use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\TrainingQuestion;
use App\Models\TrainingQuizResult;
use App\Models\User;
use Tests\TestCase;

/**
 * Pemateri bisa mengizinkan kuis pelatihan diulang tanpa batas; nilai tertinggi yang dipakai.
 */
class TrainingQuizRetakeTest extends TestCase
{
    private User $admin;

    private Training $training;

    private TrainingParticipant $participant;

    /** @var list<TrainingQuestion> */
    private array $questions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('username', 'admin')->sole();
        $this->training = Training::create([
            'title' => 'Pelatihan Ulang', 'trainer_id' => $this->admin->id, 'training_date' => now()->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:00', 'zoom_link' => 'https://zoom.us/test', 'is_quiz_active' => true,
        ]);
        $this->questions = [
            TrainingQuestion::create(['training_id' => $this->training->id, 'type' => 'multiple_choice', 'question' => 'Soal 1', 'option_a' => 'Benar', 'option_b' => 'Salah', 'correct_answer' => 'a']),
            TrainingQuestion::create(['training_id' => $this->training->id, 'type' => 'multiple_choice', 'question' => 'Soal 2', 'option_a' => 'Benar', 'option_b' => 'Salah', 'correct_answer' => 'a']),
        ];
        $this->participant = TrainingParticipant::create([
            'training_id' => $this->training->id, 'user_id' => User::factory()->staff()->create()->id,
            'token' => 'token_retake', 'attendance_status' => 'hadir',
        ]);
    }

    private function submit(int $correct)
    {
        $answers = [];
        foreach ($this->questions as $i => $question) {
            $answers[$question->id] = $i < $correct ? 'a' : 'b';
        }

        return $this->post('/training/portal/token_retake/quiz', ['answers' => $answers]);
    }

    private function storedResult(): TrainingQuizResult
    {
        return TrainingQuizResult::where('training_participant_id', $this->participant->id)->sole();
    }

    public function test_retake_is_off_by_default(): void
    {
        $this->assertFalse($this->training->fresh()->allow_quiz_retake);

        $this->submit(1)->assertRedirect('/training/portal/token_retake/result');
        $this->get('/training/portal/token_retake/quiz')->assertRedirect('/training/portal/token_retake/result');
        $this->submit(2)->assertRedirect('/training/portal/token_retake/result');

        $this->assertSame(50.0, $this->storedResult()->score);
        $this->get('/training/portal/token_retake')->assertDontSee('Kerjakan Ulang');
    }

    public function test_trainer_toggles_retake_from_the_training_page(): void
    {
        $this->actingAs($this->admin)->get(route('training.show', $this->training))->assertSee('Ulang kuis: Tidak boleh');

        $this->actingAs($this->admin)->post(route('training.toggle-quiz-retake', $this->training))->assertSessionHas('success');
        $this->assertTrue($this->training->fresh()->allow_quiz_retake);
        $this->actingAs($this->admin)->get(route('training.show', $this->training))->assertSee('Ulang kuis: Boleh');

        $this->actingAs($this->admin)->post(route('training.toggle-quiz-retake', $this->training));
        $this->assertFalse($this->training->fresh()->allow_quiz_retake);
    }

    public function test_staff_cannot_toggle_retake(): void
    {
        $this->actingAs(User::factory()->staff()->create())->from('/my-trainings')
            ->post(route('training.toggle-quiz-retake', $this->training))
            ->assertRedirect('/my-trainings')
            ->assertSessionHas('error');

        $this->assertFalse($this->training->fresh()->allow_quiz_retake);
    }

    public function test_retake_keeps_the_highest_score(): void
    {
        $this->training->update(['allow_quiz_retake' => true]);

        $this->submit(1);
        $this->assertSame(50.0, $this->storedResult()->score);
        $this->get('/training/portal/token_retake')->assertSee('Kerjakan Ulang');
        $this->get('/training/portal/token_retake/retake')->assertRedirect('/training/portal/token_retake/quiz');
        $this->get('/training/portal/token_retake/quiz')->assertOk();

        // Lebih tinggi: menggantikan hasil lama.
        $this->submit(2)->assertSessionHas('success');
        $this->assertSame(100.0, $this->storedResult()->score);
        $this->assertSame(2, $this->storedResult()->attempt_count);

        // Lebih rendah: nilai tertinggi tetap, percobaan tetap dihitung.
        $this->submit(0)->assertSessionHas('warning', 'Nilai percobaan ini 0. Nilai tertinggi Anda (100) tetap dipakai.');
        $this->assertSame(100.0, $this->storedResult()->score);
        $this->assertSame(3, $this->storedResult()->attempt_count);
        $this->assertSame(1, TrainingQuizResult::count());

        $this->get('/training/portal/token_retake/result')->assertSee('Dikerjakan 3x');
        $this->actingAs($this->admin)->get(route('training.show', $this->training))->assertSee('tertinggi dari 3x');
    }

    public function test_retake_needs_the_quiz_to_be_open(): void
    {
        $this->training->update(['allow_quiz_retake' => true]);
        $this->submit(1);
        $this->training->update(['is_quiz_active' => false]);

        $this->get('/training/portal/token_retake')->assertDontSee('Kerjakan Ulang');
        $this->get('/training/portal/token_retake/retake')->assertRedirect('/training/portal/token_retake/result');
        $this->get('/training/portal/token_retake/quiz')->assertRedirect('/training/portal/token_retake');
    }
}
