<?php

namespace Tests\Feature;

use App\Models\MeetingAccount;
use App\Models\Training;
use App\Models\TrainingMeetingChatMessage;
use App\Models\TrainingMeetingEvent;
use App\Models\TrainingMeetingMonitor;
use App\Models\TrainingParticipant;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrainingMeetingMonitorTest extends TestCase
{
    public function test_duplicate_batches_preserve_zoom_observations_and_official_attendance(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $training = $this->training($admin);
        $participant = TrainingParticipant::createFor($training, User::factory()->staff()->create()->id);
        $id = (string) Str::uuid();
        $start = route('training.meeting.monitors.start', $training);
        $sync = route('training.meeting.monitors.sync', [$training, $id]);
        $end = route('training.meeting.monitors.end', [$training, $id]);
        $this->actingAs($admin);
        $join = $this->join($training);

        $this->postJson($start, ['id' => $id, ...$join])
            ->assertOk()->assertExactJson(['id' => $id]);
        $this->postJson($start, ['id' => $id, ...$join])->assertOk();
        $this->postJson($start, ['id' => (string) Str::uuid(), ...$join])
            ->assertConflict()->assertJsonPath('code', 'monitor_busy');

        $event = [
            'id' => (string) Str::uuid(),
            'type' => 'camera',
            'observed_at' => now()->utc()->setMicrosecond(123000)->toISOString(),
            'participant_key' => '42',
            'name' => 'Nama dari Zoom',
            'camera_on' => false,
        ];
        $this->postJson($sync, ['events' => [$event]])->assertNoContent();
        $this->postJson($sync, ['events' => [[...$event, 'name' => 'Pengiriman ulang', 'camera_on' => true]]])
            ->assertNoContent();

        $record = TrainingMeetingEvent::sole();
        $this->assertSame('Nama dari Zoom', $record->name);
        $this->assertFalse($record->camera_on);
        $this->assertSame('123', $record->observed_at->format('v'));
        $this->assertSame('pending', $participant->refresh()->attendance_status);
        $this->assertNull($participant->attended_at);
        $this->get(route('training.meeting.activity', [$training, 'nama' => 'Nama dari Zoom']))
            ->assertOk()->assertSee('Nama dari Zoom')->assertSee('Off Cam');

        $this->postJson($end)->assertNoContent();
        $this->postJson($end)->assertNoContent();
        $this->postJson($sync, ['events' => []])->assertConflict()->assertJsonPath('code', 'monitor_expired');

        $training->update(['meeting_monitoring_enabled' => false]);
        $this->get(route('training.meeting.activity', $training))->assertOk()->assertSee('Nama dari Zoom');
    }

    public function test_live_monitor_saves_public_zoom_chat_once_and_lists_it(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $training = $this->training($admin);
        $id = (string) Str::uuid();
        $sync = route('training.meeting.monitors.sync', [$training, $id]);
        $this->actingAs($admin);
        $this->postJson(route('training.meeting.monitors.start', $training), ['id' => $id, ...$this->join($training)])
            ->assertOk();

        $chat = [
            'id' => 'zoom-msg-1',
            'sent_at' => now()->subMinutes(5)->utc()->toISOString(),
            'sender' => 'Machpudin Apud',
            'message' => "Bagaimana kalau stok di sistem berbeda?\nMohon dijelaskan",
        ];
        $this->postJson($sync, ['events' => [], 'chats' => [$chat]])->assertNoContent();
        // A retried batch, even with changed text, keeps the first copy.
        $this->postJson($sync, ['events' => [], 'chats' => [[...$chat, 'message' => 'Pengiriman ulang']]])
            ->assertNoContent();
        $this->postJson($sync, ['events' => [], 'chats' => [[...$chat, 'id' => 'zoom-msg-2', 'sent_at' => now()->addMinutes(10)->utc()->toISOString()]]])
            ->assertUnprocessable()->assertJsonValidationErrors('chats.0.sent_at');

        $message = TrainingMeetingChatMessage::sole();
        $this->assertSame('Machpudin Apud', $message->sender);
        $this->assertSame("Bagaimana kalau stok di sistem berbeda?\nMohon dijelaskan", $message->message);
        $this->assertSame('zoom', $message->provider);
        $this->assertSame('123456789', $message->meeting_code);
        $this->assertSame(now()->subMinutes(5)->format('Y-m-d H:i'), substr($message->sent_time, 0, 16));

        $this->get(route('training.meeting.chat.index', $training))
            ->assertOk()->assertSee('Machpudin Apud')->assertSee('Bagaimana kalau stok di sistem berbeda?');
        $this->get(route('training.meeting.show', $training))
            ->assertOk()->assertSee('Machpudin Apud')->assertSee('Chat ke semua peserta tercatat otomatis');
    }

    public function test_expired_monitor_is_not_resumed_or_accessible_by_another_trainer(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $training = $this->training($admin);
        $id = (string) Str::uuid();
        $start = route('training.meeting.monitors.start', $training);
        $sync = route('training.meeting.monitors.sync', [$training, $id]);

        $this->actingAs($admin);
        $join = $this->join($training);
        $this->postJson($start, ['id' => $id, ...$join])->assertOk();
        $this->actingAs(User::factory()->staff()->create())
            ->postJson($sync, ['events' => []])->assertForbidden();
        $this->actingAs(User::factory()->staff()->trainer()->create())
            ->postJson($sync, ['events' => []])->assertNotFound();
        $this->travel(46)->seconds();
        $this->actingAs($admin)->postJson($sync, ['events' => []])
            ->assertConflict()->assertJsonPath('code', 'monitor_expired');
        $this->postJson(route('training.meeting.monitors.end', [$training, $id]))->assertNoContent();
        $this->assertNull(TrainingMeetingMonitor::findOrFail($id)->ended_at);

        $newId = (string) Str::uuid();
        $this->postJson($start, ['id' => $newId, ...$join])->assertOk();
        $training->update(['meeting_link' => 'https://zoom.us/j/987654321']);
        $this->postJson(route('training.meeting.monitors.sync', [$training, $newId]), ['events' => []])
            ->assertConflict()->assertJsonPath('code', 'meeting_changed');
    }

    public function test_turning_off_monitoring_and_rotating_credentials_invalidate_a_running_session(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $training = $this->training($admin);
        $this->actingAs($admin);

        $firstJoin = $this->join($training);
        $start = route('training.meeting.monitors.start', $training);
        $firstId = (string) Str::uuid();
        $this->postJson($start, ['id' => $firstId, ...$firstJoin])->assertOk();
        $training->update(['meeting_monitoring_enabled' => false]);
        $this->postJson(route('training.meeting.monitors.sync', [$training, $firstId]), ['events' => []])
            ->assertConflict()->assertJsonPath('code', 'monitor_unavailable');
        $this->postJson($start, ['id' => (string) Str::uuid(), ...$firstJoin])
            ->assertConflict()->assertJsonPath('code', 'monitor_unavailable');

        $training->update(['meeting_monitoring_enabled' => true]);
        $this->postJson($start, ['id' => (string) Str::uuid(), ...$firstJoin])
            ->assertConflict()->assertJsonPath('code', 'meeting_changed');
        $secondId = (string) Str::uuid();
        $this->postJson($start, ['id' => $secondId, ...$this->join($training)])->assertOk();
        $account = $training->meetingAccount()->firstOrFail();
        $account->update(['credentials' => [...$account->credentials, 'client_secret' => 'rotated']]);
        $this->postJson(route('training.meeting.monitors.sync', [$training, $secondId]), ['events' => []])
            ->assertConflict()->assertJsonPath('code', 'meeting_changed');
    }

    private function join(Training $training): array
    {
        $data = $this->postJson(route('training.meeting.join', $training))->assertOk()->json();

        return [
            'meeting_code' => $data['meetingCode'],
            'account_revision' => $data['accountRevision'],
        ];
    }

    private function training(User $trainer): Training
    {
        $account = MeetingAccount::create([
            'user_id' => $trainer->id,
            'name' => 'Zoom Pemateri',
            'provider' => 'zoom',
            'credentials' => ['client_id' => 'client', 'client_secret' => 'secret'],
        ]);

        return Training::create([
            'title' => 'Uji Aktivitas Zoom',
            'trainer_id' => $trainer->id,
            'training_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'ongoing',
            'meeting_link' => 'https://zoom.us/j/123456789',
            'meeting_platform' => 'zoom',
            'meeting_monitoring_enabled' => true,
            'meeting_account_id' => $account->id,
        ]);
    }
}
