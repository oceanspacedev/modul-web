<?php

namespace Tests\Feature;

use App\Models\Divisi;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\TrainingQuestion;
use App\Models\TrainingQuizResult;
use App\Models\User;
use Tests\TestCase;

class TrainingQuizModeTest extends TestCase
{
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
            'username' => 'peserta_mode_'.uniqid(),
            'full_name' => 'Peserta Uji Mode',
            'email' => 'mode_'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'job_level_id' => 2,
            'divisi_id' => $divisi->id,
        ]);
    }

    /**
     * Test Admin Selecting & Switching Quiz Mode, and Participant Portal Rendering
     */
    public function test_admin_can_choose_and_toggle_quiz_mode()
    {
        // 1. Admin creates training with Game Mode (Quizizz)
        $storeRes = $this->actingAs($this->admin)->post('/training', [
            'title' => 'Pelatihan Game Mode Test',
            'trainer_id' => $this->admin->id,
            'description' => 'Testing Quizizz game mode.',
            'training_date' => now()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'zoom_link' => 'https://zoom.us/test',
            'quiz_mode' => 'game',
            'participants' => [$this->participantUser->id],
        ]);

        $training = Training::where('title', 'Pelatihan Game Mode Test')->first();
        $this->assertNotNull($training);
        $this->assertEquals('game', $training->quiz_mode);

        // Add 2 sample questions
        $q1 = TrainingQuestion::create([
            'training_id' => $training->id,
            'type' => 'multiple_choice',
            'question' => 'Apa ibukota Indonesia masa kini?',
            'option_a' => 'Jakarta',
            'option_b' => 'Bandung',
            'option_c' => 'Surabaya',
            'option_d' => 'Medan',
            'correct_answer' => 'a',
        ]);

        // Toggle Quiz Active so participant can take it
        $training->update(['is_quiz_active' => true]);

        $participant = TrainingParticipant::where('training_id', $training->id)->first();
        $token = $participant->token;

        // 2. Participant visits quiz in Game Mode -> Should see Quizizz Game Interface
        $gameQuizRes = $this->get("/training/portal/{$token}/quiz");
        $gameQuizRes->assertStatus(200);
        $gameQuizRes->assertSee('QUIZIZZ MODE');
        $gameQuizRes->assertSee('Mulai Game Kuis');
        $gameQuizRes->assertSee('Apa ibukota Indonesia masa kini?');
        $gameQuizRes->assertSee('podium-stage');
        $gameQuizRes->assertSee('podiumCol1');
        $gameQuizRes->assertSee('podium-crown');

        // 3. Admin switches mode back to Formal Mode via toggle button
        $toggleRes = $this->actingAs($this->admin)->post("/training/{$training->id}/toggle-mode");
        $toggleRes->assertSessionHas('success');
        $this->assertEquals('formal', $training->fresh()->quiz_mode);

        // 4. Participant visits quiz again in Formal Mode -> Should see standard form
        $formalQuizRes = $this->get("/training/portal/{$token}/quiz");
        $formalQuizRes->assertStatus(200);
        $formalQuizRes->assertSee('Kuis Evaluasi Pelatihan');
        $formalQuizRes->assertSee('Toleransi Keluar: 3x');

        // 5. Switch back to Game Mode and submit answers via AJAX
        $this->actingAs($this->admin)->post("/training/{$training->id}/toggle-mode");
        $this->assertEquals('game', $training->fresh()->quiz_mode);

        $submitRes = $this->postJson("/training/portal/{$token}/quiz", [
            'answers' => [
                $q1->id => 'a',
            ],
        ]);
        $submitRes->assertStatus(200);
        $submitRes->assertJsonFragment(['success' => true]);
        $submitRes->assertJsonStructure(['success', 'score', 'leaderboard', 'redirect_url']);

        // Verify result recorded
        $result = TrainingQuizResult::where('training_participant_id', $participant->id)->first();
        $this->assertNotNull($result);
        $this->assertEquals(100, (int) $result->score);
    }
}
