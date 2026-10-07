<?php

namespace Tests\Feature;

use App\Models\Divisi;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\TrainingQuestion;
use App\Models\TrainingQuizResult;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TrainingQuizNoRetakeTest extends TestCase
{
    use DatabaseTransactions;

    protected $admin;
    protected $participantUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('username', 'admin')->first() ?? User::factory()->create([
            'username' => 'admin',
            'job_level_id' => 1,
        ]);

        $divisi = Divisi::first() ?? Divisi::create(['name' => 'Divisi Test']);

        $this->participantUser = User::create([
            'username' => 'peserta_noretake_' . uniqid(),
            'full_name' => 'Peserta No Retake',
            'email' => 'noretake_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'job_level_id' => 2,
            'divisi_id' => $divisi->id,
        ]);
    }

    public function test_participant_cannot_retake_quiz()
    {
        // 1. Create training & question
        $training = Training::create([
            'title' => 'Pelatihan No Retake Test',
            'trainer_id' => $this->admin->id,
            'description' => 'Test memastikan peserta tidak dapat mengerjakan ulang kuis.',
            'training_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'zoom_link' => 'https://zoom.us/test',
            'is_quiz_active' => true,
            'is_attendance_active' => true,
            'quiz_mode' => 'formal',
        ]);

        $question = TrainingQuestion::create([
            'training_id' => $training->id,
            'type' => 'multiple_choice',
            'question' => 'Apakah peserta boleh mengulang kuis secara mandiri?',
            'option_a' => 'Tidak boleh',
            'option_b' => 'Boleh',
            'option_c' => 'Bebas',
            'option_d' => 'Tergantung',
            'correct_answer' => 'a',
        ]);

        $participant = TrainingParticipant::create([
            'training_id' => $training->id,
            'user_id' => $this->participantUser->id,
            'token' => 'token_noretake_' . uniqid(),
            'attendance_status' => 'hadir',
            'attended_at' => now(),
        ]);

        $token = $participant->token;

        // 2. Participant submits quiz for the first time
        $submitRes = $this->post("/training/portal/{$token}/quiz", [
            'answers' => [
                $question->id => 'a',
            ],
        ]);
        $submitRes->assertRedirect("/training/portal/{$token}/result");

        $result = TrainingQuizResult::where('training_participant_id', $participant->id)->first();
        $this->assertNotNull($result);
        $this->assertEquals(100, (int)$result->score);

        // 3. Participant attempts to revisit /quiz directly -> redirected to /result
        $quizPageRes = $this->get("/training/portal/{$token}/quiz");
        $quizPageRes->assertRedirect("/training/portal/{$token}/result");

        // 4. Participant attempts to access /retake URL -> redirected to /result, score not deleted
        $retakeGetRes = $this->get("/training/portal/{$token}/retake");
        $retakeGetRes->assertRedirect("/training/portal/{$token}/result");
        $this->assertDatabaseHas('training_quiz_results', [
            'id' => $result->id,
            'training_participant_id' => $participant->id,
        ]);

        // 5. Participant attempts to POST to /retake URL -> redirected to /result, score not deleted
        $retakePostRes = $this->post("/training/portal/{$token}/retake");
        $retakePostRes->assertRedirect("/training/portal/{$token}/result");
        $this->assertDatabaseHas('training_quiz_results', [
            'id' => $result->id,
            'training_participant_id' => $participant->id,
        ]);

        // 6. Participant attempts to send POST to /quiz again -> blocked and redirected to /result
        $resubmitRes = $this->post("/training/portal/{$token}/quiz", [
            'answers' => [
                $question->id => 'b',
            ],
        ]);
        $resubmitRes->assertRedirect("/training/portal/{$token}/result");

        // Score should still be intact (100)
        $this->assertEquals(100, (int)$result->fresh()->score);

        // 7. Verify UI does not show "Kerjakan Ulang" buttons
        $portalPage = $this->get("/training/portal/{$token}");
        $portalPage->assertStatus(200);
        $portalPage->assertDontSee('Kerjakan Ulang');

        $resultPage = $this->get("/training/portal/{$token}/result");
        $resultPage->assertStatus(200);
        $resultPage->assertDontSee('Kerjakan Ulang Kuis');

        // 8. Admin resets quiz
        $resetRes = $this->actingAs($this->admin)->post("/training/{$training->id}/reset-quiz/{$participant->id}");
        $resetRes->assertSessionHas('success');
        $this->assertDatabaseMissing('training_quiz_results', [
            'training_participant_id' => $participant->id,
        ]);

        // 9. Now participant can access quiz again
        $reopenedRes = $this->get("/training/portal/{$token}/quiz");
        $reopenedRes->assertStatus(200);
        $reopenedRes->assertSee('Apakah peserta boleh mengulang kuis secara mandiri?');
    }
}
