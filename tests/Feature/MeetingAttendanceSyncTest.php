<?php

namespace Tests\Feature;

use App\Jobs\SyncMeetingAttendance;
use App\Models\MeetingAccount;
use App\Models\Training;
use App\Models\TrainingMeetingEvent;
use App\Models\TrainingMeetingMonitor;
use App\Models\User;
use App\Notifications\BackgroundTaskFinished;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MeetingAttendanceSyncTest extends TestCase
{
    public function test_google_meet_attendance_is_pulled_once_per_session_and_shown_in_the_activity_log(): void
    {
        Notification::fake();
        $admin = User::where('username', 'admin')->firstOrFail();
        $training = $this->training($admin, 'google_meet', 'https://meet.google.com/abc-defg-hij', [
            'organizer_email' => 'od@example.test',
            'service_account_json' => $this->serviceAccountJson(),
        ]);
        $record = 'conferenceRecords/rec-1';
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-token']),
            'meet.googleapis.com/v2/conferenceRecords?*' => Http::response(['conferenceRecords' => [['name' => $record]]]),
            "meet.googleapis.com/v2/$record/participants?*" => Http::response(['participants' => [
                ['name' => "$record/participants/p1", 'signedinUser' => ['displayName' => 'Ana Peserta']],
                ['name' => "$record/participants/p2", 'anonymousUser' => ['displayName' => 'Tamu']],
            ]]),
            "meet.googleapis.com/v2/$record/participants/p1/participantSessions?*" => Http::response(['participantSessions' => [
                ['name' => "$record/participants/p1/participantSessions/s1", 'startTime' => '2026-11-15T02:00:00Z', 'endTime' => '2026-11-15T02:30:00Z'],
                ['name' => "$record/participants/p1/participantSessions/s2", 'startTime' => '2026-11-15T02:40:00Z', 'endTime' => '2026-11-15T03:00:00Z'],
            ]]),
            "meet.googleapis.com/v2/$record/participants/p2/participantSessions?*" => Http::response(['participantSessions' => [
                ['name' => "$record/participants/p2/participantSessions/s1", 'startTime' => '2026-11-15T02:05:00Z'],
            ]]),
        ]);
        $page = route('training.meeting.show', $training);

        $this->actingAs($admin)->get($page)->assertOk()
            ->assertSee('Tarik Kehadiran dari Google Meet')->assertDontSee('Mulai Monitoring')->assertDontSee('Impor Chat');
        // Bergabung dari browser hanya untuk platform yang punya klien pemantauan.
        $this->postJson(route('training.meeting.join', $training))->assertConflict();
        $this->postJson(route('training.meeting.monitors.start', $training), [
            'id' => (string) str()->uuid(), 'meeting_code' => 'abc-defg-hij', 'account_revision' => str_repeat('a', 64),
        ])->assertConflict()->assertJsonPath('code', 'monitor_unavailable');

        foreach (range(1, 2) as $attempt) {
            $this->post(route('training.meeting.attendance-sync', $training))->assertRedirect($page)->assertSessionHas('info');
        }

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'conferenceRecords?')
            && $request['filter'] === 'space.meeting_code = "abc-defg-hij"'
            && $request->hasHeader('Authorization', 'Bearer google-token'));
        $monitor = TrainingMeetingMonitor::sole();
        $this->assertSame(['google_meet', 'abc-defg-hij', 'import'], [$monitor->provider, $monitor->meeting_code, $monitor->source]);
        $this->assertSame('09:00:00', $monitor->started_at->format('H:i:s'));
        $this->assertSame('10:00:00', $monitor->ended_at->format('H:i:s'));
        // 2 sesi Ana (masuk + keluar) dan 1 sesi tamu yang belum keluar; penarikan kedua tidak menambah apa pun.
        $this->assertSame(5, TrainingMeetingEvent::count());
        $this->assertSame(['join' => 3, 'leave' => 2], TrainingMeetingEvent::query()->pluck('type')->countBy()->sortKeys()->all());
        $this->assertNull(TrainingMeetingEvent::first()->camera_on);
        Notification::assertSentToTimes($admin, BackgroundTaskFinished::class, 2);

        $this->get($page)->assertOk()->assertSee('Ana Peserta')->assertSee('Tamu')->assertSee('Data platform');
    }

    public function test_teams_attendance_reports_become_join_leave_and_camera_activity(): void
    {
        Notification::fake();
        $admin = User::where('username', 'admin')->firstOrFail();
        $organizer = 'dc17674c-81d9-4adb-bfb2-8f6a442e4622';
        $link = 'https://teams.microsoft.com/l/meetup-join/19%3ameeting_MGQ4MDQy%40thread.v2/0?context=%7b%7d';
        $training = $this->training($admin, 'teams', $link, [
            'tenant_id' => 'contoso.onmicrosoft.com', 'client_id' => 'app-id', 'client_secret' => 'app-secret', 'organizer_id' => $organizer,
        ]);
        $meetings = "graph.microsoft.com/v1.0/users/$organizer/onlineMeetings";
        Http::fake([
            'login.microsoftonline.com/contoso.onmicrosoft.com/oauth2/v2.0/token' => Http::response(['access_token' => 'graph-token']),
            "$meetings/meeting-1/attendanceReports/report-1/attendanceRecords" => Http::response(['value' => [[
                'emailAddress' => 'ana@example.test',
                'identity' => ['id' => 'user-1', 'displayName' => 'Ana Peserta'],
                'attendanceIntervals' => [['joinDateTime' => '2026-11-15T02:00:27.6027225Z', 'leaveDateTime' => '2026-11-15T03:00:00Z']],
                'engagements' => [
                    ['engagementType' => 'camera', 'engagementSubType' => 'cameraOn', 'dateTime' => '2026-11-15T02:10:00Z'],
                    ['engagementType' => 'reaction', 'engagementSubType' => 'like', 'dateTime' => '2026-11-15T02:11:00Z'],
                ],
            ]]]),
            "$meetings/meeting-1/attendanceReports" => Http::response(['value' => [['id' => 'report-1']]]),
            "$meetings?*" => Http::response(['value' => [['id' => 'meeting-1']]]),
        ]);

        $this->actingAs($admin)->post(route('training.meeting.attendance-sync', $training))->assertSessionHas('info');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'onlineMeetings?')
            && $request['$filter'] === "JoinWebUrl eq '$link'");
        $this->assertSame('19:meeting_MGQ4MDQy@thread.v2', TrainingMeetingMonitor::sole()->meeting_code);
        $events = TrainingMeetingEvent::orderBy('observed_at')->get();
        $this->assertSame(['join', 'camera', 'leave'], $events->pluck('type')->all());
        $this->assertSame([null, true, null], $events->pluck('camera_on')->all());
        $this->assertSame('user-1', $events[0]->participant_key);
        $this->assertSame('09:00:27.602', $events[0]->observed_at->format('H:i:s.v'));
    }

    public function test_a_rejected_credential_is_reported_without_recording_anything(): void
    {
        Notification::fake();
        $admin = User::where('username', 'admin')->firstOrFail();
        $training = $this->training($admin, 'teams', 'https://teams.microsoft.com/meet/2345678901234', [
            'tenant_id' => 'contoso', 'client_id' => 'app-id', 'client_secret' => 'wrong', 'organizer_id' => 'user-1',
        ]);
        Http::fake(['login.microsoftonline.com/*' => Http::response(['error' => 'invalid_client', 'error_description' => 'Invalid client secret'], 401)]);

        $this->actingAs($admin)->post(route('training.meeting.attendance-sync', $training))->assertSessionHas('info');

        $this->assertSame(0, TrainingMeetingMonitor::count());
        Notification::assertSentTo($admin, fn (BackgroundTaskFinished $notification) => $notification->status === BackgroundTaskFinished::STATUS_FAILED
            && str_contains($notification->message, 'Invalid client secret')
            && ! str_contains($notification->message, 'wrong'));
    }

    public function test_only_training_managers_can_pull_attendance_and_only_when_the_platform_offers_it(): void
    {
        Queue::fake();
        $admin = User::where('username', 'admin')->firstOrFail();
        $meet = $this->training($admin, 'google_meet', 'https://meet.google.com/abc-defg-hij', [
            'organizer_email' => 'od@example.test', 'service_account_json' => '{}',
        ]);
        $zoom = $this->training($admin, 'zoom', 'https://zoom.us/j/123456789', ['client_id' => 'id', 'client_secret' => 'secret']);

        $this->actingAs(User::factory()->staff()->create())->post(route('training.meeting.attendance-sync', $meet))->assertRedirect();
        $this->actingAs($admin)->post(route('training.meeting.attendance-sync', $zoom))->assertNotFound();
        $meet->update(['meeting_monitoring_enabled' => false]);
        $this->post(route('training.meeting.attendance-sync', $meet))->assertConflict();
        Queue::assertNothingPushed();

        $meet->update(['meeting_monitoring_enabled' => true]);
        $this->post(route('training.meeting.attendance-sync', $meet))->assertSessionHas('info');
        Queue::assertPushed(SyncMeetingAttendance::class, fn (SyncMeetingAttendance $job) => $job->trainingId === $meet->id && $job->userId === $admin->id);
    }

    private function training(User $trainer, string $platform, string $link, array $credentials): Training
    {
        $account = MeetingAccount::create([
            'user_id' => $trainer->id, 'provider' => $platform, 'name' => 'Akun '.$platform, 'credentials' => $credentials,
        ]);

        return Training::create([
            'title' => 'Pelatihan '.$platform,
            'trainer_id' => $trainer->id,
            'training_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'ongoing',
            'meeting_link' => $link,
            'meeting_platform' => $platform,
            'meeting_monitoring_enabled' => true,
            'meeting_account_id' => $account->id,
        ]);
    }

    private function serviceAccountJson(): string
    {
        openssl_pkey_export(openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]), $privateKey);

        return json_encode(['type' => 'service_account', 'client_email' => 'modul@project.iam.gserviceaccount.com', 'private_key' => $privateKey]);
    }
}
