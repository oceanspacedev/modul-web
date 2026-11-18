<?php

namespace Tests\Feature;

use App\Enums\TrainingStatus;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Every participant's portal polls /status, so with a full class it is the busiest route of the app. It must not
 * start a session, set cookies or write to the database.
 */
class PortalPollingLoadTest extends TestCase
{
    private function participant(): TrainingParticipant
    {
        $training = Training::create([
            'title' => 'Training K3',
            'trainer_id' => User::where('username', 'admin')->value('id'),
            'training_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => TrainingStatus::Ongoing,
            'has_attendance' => true,
            'has_posttest' => true,
        ]);

        return $training->participants()->create([
            'user_id' => User::factory()->staff()->create()->id,
            'token' => Str::random(40),
            'attendance_status' => 'pending',
        ]);
    }

    public function test_status_poll_is_stateless(): void
    {
        $participant = $this->participant();

        $response = $this->get("/training/portal/{$participant->token}/status")->assertOk();

        $this->assertSame([], $response->headers->getCookies());
        $response->assertJsonPath('attendance_status', 'pending')->assertJsonPath('has_quiz_result', false);
    }

    public function test_status_poll_only_reads(): void
    {
        $participant = $this->participant();

        DB::enableQueryLog();
        $this->get("/training/portal/{$participant->token}/status")->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query');

        $writes = $queries->filter(fn (string $sql) => preg_match('/^\s*(insert|update|delete)/i', $sql));
        $this->assertSame([], $writes->values()->all());
        $this->assertLessThanOrEqual(4, $queries->count(), $queries->implode("\n"));
    }

    public function test_status_poll_from_a_signed_in_browser_does_not_touch_the_user(): void
    {
        $participant = $this->participant();
        $user = $participant->user;
        $user->forceFill(['last_seen' => null])->save();

        $this->actingAs($user)->get("/training/portal/{$participant->token}/status")->assertOk();

        $this->assertNull($user->refresh()->last_seen);
    }

    public function test_status_poll_reports_a_finished_quiz(): void
    {
        $participant = $this->participant();
        $participant->training->results()->create([
            'user_id' => $participant->user_id,
            'training_participant_id' => $participant->id,
            'phase' => Training::PHASE_POSTTEST,
            'score' => 80,
            'answers' => [],
        ]);

        $this->get("/training/portal/{$participant->token}/status")->assertJsonPath('has_quiz_result', true);
    }

    public function test_unknown_token_is_not_found(): void
    {
        $this->get('/training/portal/'.Str::random(40).'/status')->assertNotFound();
    }

    public function test_last_seen_is_written_at_most_once_a_minute(): void
    {
        $admin = User::where('username', 'admin')->first();
        Carbon::setTestNow('2026-11-13 10:00:00');

        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->assertEquals('2026-11-13 10:00:00', $admin->refresh()->last_seen->toDateTimeString());

        Carbon::setTestNow('2026-11-13 10:00:40');
        DB::enableQueryLog();
        $this->actingAs($admin->refresh())->get('/dashboard')->assertOk();
        $userUpdates = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql) => str_starts_with(strtolower($sql), 'update "users"'));
        $this->assertCount(0, $userUpdates);
        $this->assertEquals('2026-11-13 10:00:00', $admin->refresh()->last_seen->toDateTimeString());

        Carbon::setTestNow('2026-11-13 10:01:05');
        $this->actingAs($admin->refresh())->get('/dashboard')->assertOk();
        $this->assertEquals('2026-11-13 10:01:05', $admin->refresh()->last_seen->toDateTimeString());

        Carbon::setTestNow();
    }
}
