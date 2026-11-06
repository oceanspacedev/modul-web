<?php

namespace Tests\Feature;

use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\TrainingQuizResult;
use App\Models\User;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrainingLiveStatsTest extends TestCase
{
    private function makeTraining(User $trainer): Training
    {
        return Training::create([
            'title' => 'Training Live Stats',
            'trainer_id' => $trainer->id,
            'training_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'zoom_link' => 'https://zoom.us/test-live-stats',
            'status' => 'ongoing',
        ]);
    }

    private function addParticipant(Training $training, string $status, ?float $score = null): TrainingParticipant
    {
        $user = User::factory()->staff()->create();

        $participant = TrainingParticipant::create([
            'training_id' => $training->id,
            'user_id' => $user->id,
            'token' => Str::random(40),
            'attendance_status' => $status,
        ]);

        if ($score !== null) {
            TrainingQuizResult::create([
                'training_id' => $training->id,
                'user_id' => $user->id,
                'total_questions' => 10,
                'correct_answers' => (int) round($score / 10),
                'score' => $score,
                'submitted_at' => now(),
            ]);
        }

        return $participant;
    }

    public function test_admin_gets_the_same_counters_the_detail_page_shows(): void
    {
        $admin = User::factory()->admin()->create();
        $training = $this->makeTraining($admin);

        $this->addParticipant($training, 'hadir', 80);
        $this->addParticipant($training, 'hadir', 75);
        $this->addParticipant($training, 'hadir');
        $this->addParticipant($training, 'tidak_hadir');
        $this->addParticipant($training, 'pending');

        $expected = [
            'total' => 5,
            'attended' => 3,
            'absent' => 1,
            'pending' => 1,
            'quizSubmitted' => 2,
            'avgScore' => 77.5,
        ];

        $this->actingAs($admin)
            ->getJson(route('training.live-stats', $training))
            ->assertOk()
            ->assertExactJson($expected);

        $this->actingAs($admin)
            ->get(route('training.show', $training))
            ->assertOk()
            ->assertViewHas('stats', $expected)
            ->assertSee(Js::from(route('training.live-stats', $training))->toHtml(), false);
    }

    public function test_counters_follow_attendance_changes(): void
    {
        $admin = User::factory()->admin()->create();
        $training = $this->makeTraining($admin);
        $participant = $this->addParticipant($training, 'pending');

        $this->actingAs($admin)->getJson(route('training.live-stats', $training))
            ->assertJson(['total' => 1, 'attended' => 0, 'pending' => 1, 'quizSubmitted' => 0, 'avgScore' => 0]);

        $participant->update(['attendance_status' => 'hadir']);

        $this->actingAs($admin)->getJson(route('training.live-stats', $training))
            ->assertJson(['total' => 1, 'attended' => 1, 'pending' => 0]);
    }

    public function test_staff_without_manage_trainings_cannot_read_live_stats(): void
    {
        $training = $this->makeTraining(User::factory()->admin()->create());

        $this->actingAs(User::factory()->staff()->create())
            ->getJson(route('training.live-stats', $training))
            ->assertForbidden();
    }

    public function test_guest_cannot_read_live_stats(): void
    {
        $training = $this->makeTraining(User::factory()->admin()->create());

        $this->getJson(route('training.live-stats', $training))->assertUnauthorized();
    }
}
