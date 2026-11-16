<?php

namespace Tests\Feature;

use App\Enums\CameraStatus;
use App\Models\MeetingAccount;
use App\Models\Training;
use App\Models\TrainingMeetingEvent;
use App\Models\TrainingMeetingMonitor;
use App\Models\TrainingParticipant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Rekap kamera dari monitoring meeting: porsi waktu kamera menyala selama peserta ada di meeting menentukan statusnya
 * (On Cam / Tidak Full On Cam / Full Off Cam), lalu diverifikasi pelatih dan disimpan lewat jalur yang sama dengan
 * pindaian screenshot dan input manual.
 */
class MeetingCameraFromMonitoringTest extends TestCase
{
    private User $admin;

    private Training $training;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('username', 'admin')->firstOrFail();
        $account = MeetingAccount::create([
            'user_id' => $this->admin->id,
            'name' => 'Zoom Pemateri',
            'provider' => 'zoom',
            'credentials' => ['client_id' => 'client', 'client_secret' => 'secret'],
        ]);
        $this->training = Training::create([
            'title' => 'Uji Rekap Kamera Monitoring',
            'trainer_id' => $this->admin->id,
            'training_date' => '2026-11-13',
            'start_time' => '14:00',
            'end_time' => '16:00',
            'status' => 'ongoing',
            'meeting_link' => 'https://zoom.us/j/123456789',
            'meeting_platform' => 'zoom',
            'meeting_monitoring_enabled' => true,
            'meeting_account_id' => $account->id,
        ]);
    }

    public function test_share_of_time_with_the_camera_on_decides_the_camera_status(): void
    {
        $yayat = $this->participant('Yayat Ruhiyatna');
        $raka = $this->participant('Muhammad Raka Fadilah');
        $nada = $this->participant('Nada Annisa Zahra');

        // Petugas terputus 14:30 lalu menyambung lagi 14:31 dan melihat semua peserta lagi sebagai "present".
        $first = $this->monitor('14:00:00', ended: '14:30:00');
        $second = $this->monitor('14:31:00', ended: '15:30:00');

        $this->events($first, [
            ['14:00:05', 'present', '1', 'YAYAT RUHIYATNA CS GEBANG', false],   // off #1
            ['14:00:05', 'present', '2', 'M.Raka Fadilah', true],
            ['14:00:05', 'present', '3', 'NADA ANNISA Z', true],
            ['14:00:05', 'present', '9', 'Organization Development Staff', false],
            ['14:20:00', 'camera', '1', 'YAYAT RUHIYATNA CS GEBANG', true],
            ['14:15:00', 'camera', '2', 'M.Raka Fadilah', false],    // off #1
            ['14:20:00', 'leave', '2', 'M.Raka Fadilah', null],      // time outside the meeting does not count
        ]);
        $this->events($second, [
            ['14:31:00', 'present', '1', 'YAYAT RUHIYATNA CS GEBANG', true],
            ['14:31:00', 'present', '3', 'NADA ANNISA Z', true],
            ['14:31:00', 'present', '9', 'Organization Development Staff', false], // still off: not counted again
            ['14:40:00', 'join', '22', 'M.Raka Fadilah', false],     // rejoined with a new Zoom ID, camera still off
            ['14:45:00', 'camera', '22', 'M.Raka Fadilah', true],
            ['15:00:00', 'camera', '1', 'YAYAT RUHIYATNA CS GEBANG', false],     // off #2, open until the monitor ended
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('training.meeting.camera', $this->training))
            ->assertOk();

        $rows = collect($response->json('camera_participants'))->keyBy('zoom_name');

        // Registered participants first, least camera time first. The 14:30-14:31 gap without monitoring is skipped.
        $this->assertSame(['YAYAT RUHIYATNA CS GEBANG', 'M.Raka Fadilah', 'NADA ANNISA Z', 'Organization Development Staff'], $rows->keys()->all());

        // Yayat: on 14:20-14:30 and 14:31-15:00, off 14:00:05-14:20 and 15:00-15:30. Mostly off: not fully on cam.
        $this->assertSame($yayat->id, $rows['YAYAT RUHIYATNA CS GEBANG']['participant_id']);
        $this->assertSame('partial', $rows['YAYAT RUHIYATNA CS GEBANG']['camera_status']);
        $this->assertSame(600 + 29 * 60, $rows['YAYAT RUHIYATNA CS GEBANG']['on_cam_seconds']);
        $this->assertSame(30 * 60 - 5 + 59 * 60, $rows['YAYAT RUHIYATNA CS GEBANG']['total_seconds']);
        $this->assertSame(44, $rows['YAYAT RUHIYATNA CS GEBANG']['camera_on_percent']);
        $this->assertSame(2, $rows['YAYAT RUHIYATNA CS GEBANG']['off_cam_count']);
        $this->assertStringContainsString('(44%), mati 2x, pertama pukul 14:00 WIB', $rows['YAYAT RUHIYATNA CS GEBANG']['visual_evidence']);

        // Raka: camera off for 10 of the 70 minutes he was in the meeting; mostly on, so On Cam.
        $this->assertSame($raka->id, $rows['M.Raka Fadilah']['participant_id']);
        $this->assertSame('on_cam', $rows['M.Raka Fadilah']['camera_status']);
        $this->assertSame(15 * 60 - 5 + 45 * 60 + 10 * 60, $rows['M.Raka Fadilah']['total_seconds']);
        $this->assertSame(86, $rows['M.Raka Fadilah']['camera_on_percent']);

        $this->assertSame($nada->id, $rows['NADA ANNISA Z']['participant_id']);
        $this->assertSame('on_cam', $rows['NADA ANNISA Z']['camera_status']);
        $this->assertSame(100, $rows['NADA ANNISA Z']['camera_on_percent']);
        $this->assertSame('Kamera menyala sepanjang ±89 menit di meeting', $rows['NADA ANNISA Z']['visual_evidence']);

        $this->assertNull($rows['Organization Development Staff']['participant_id']);
        $this->assertFalse($rows['Organization Development Staff']['is_registered']);
        $this->assertSame('off_cam', $rows['Organization Development Staff']['camera_status']);
        $this->assertSame(0, $rows['Organization Development Staff']['camera_on_percent']);
        $this->assertSame(1, $rows['Organization Development Staff']['off_cam_count']);
        // Never on: no "menyala <1 menit" or switch-off count that contradicts the status.
        $this->assertSame('Kamera tidak pernah menyala (±89 menit di meeting)', $rows['Organization Development Staff']['visual_evidence']);
        $this->assertSame('Nama di meeting tidak cocok dengan peserta terdaftar', $rows['Organization Development Staff']['match_reason']);

        // Pelatih menyimpan hasil yang sudah diverifikasi: status dan persentasenya ikut tersimpan.
        $this->postJson(route('training.camera-status.save', $this->training), [
            'items' => [[
                'participant_id' => $yayat->id,
                'zoom_name' => 'YAYAT RUHIYATNA CS GEBANG',
                'camera_status' => 'partial',
                'camera_on_percent' => 44,
            ]],
        ])->assertOk()->assertJsonPath('updated_count', 1);

        $yayat->refresh();
        $this->assertSame(CameraStatus::Partial, $yayat->camera_status);
        $this->assertSame(44, $yayat->camera_on_percent);
        $this->assertSame('Tidak Full On Cam (44% on cam)', $yayat->cameraSummary());
        $this->assertSame('YAYAT RUHIYATNA CS GEBANG', $yayat->meeting_display_name);
        $this->assertNotNull($yayat->camera_recorded_at);
    }

    public function test_renames_and_people_sharing_a_name_keep_their_own_camera_state(): void
    {
        $budi = $this->participant('Budi Santoso');
        $monitor = $this->monitor('09:00:00', ended: '12:00:00');

        $this->events($monitor, [
            ['09:00:00', 'present', '1', 'budi', false],
            ['09:05:00', 'camera', '1', 'Budi Santoso (Sales)', true],   // renamed, camera back on after 5 minutes
            ['09:00:00', 'present', '7', 'Peserta Zoom', false],
            ['09:00:00', 'present', '8', 'Peserta Zoom', true],
            ['09:30:00', 'camera', '8', 'Peserta Zoom', false],
            ['09:40:00', 'camera', '8', 'Peserta Zoom', true],
        ]);

        $rows = collect($this->actingAs($this->admin)
            ->getJson(route('training.meeting.camera', $this->training))
            ->assertOk()
            ->json('camera_participants'));

        $budiRow = $rows->firstWhere('participant_id', $budi->id);
        $this->assertSame('budi / Budi Santoso (Sales)', $budiRow['zoom_name']);
        $this->assertSame('on_cam', $budiRow['camera_status']);
        $this->assertSame(3 * 3600 - 5 * 60, $budiRow['on_cam_seconds']);

        // Two different people without a name: two rows, each with their own camera time.
        $unnamed = $rows->where('zoom_name', 'Peserta Zoom')->keyBy('camera_status');
        $this->assertCount(2, $unnamed);
        $this->assertSame(0, $unnamed['off_cam']['on_cam_seconds']);
        $this->assertSame(3 * 3600 - 10 * 60, $unnamed['on_cam']['on_cam_seconds']);
    }

    public function test_saving_replaces_the_status_and_only_full_off_cam_can_be_marked_absent(): void
    {
        $budi = $this->participant('Budi Santoso');
        $siti = $this->participant('Siti Aminah');
        $budi->update(['attendance_status' => 'hadir', 'camera_status' => CameraStatus::OffCam, 'camera_on_percent' => 0]);
        $siti->update(['attendance_status' => 'hadir']);

        $this->actingAs($this->admin)->postJson(route('training.camera-status.save', $this->training), [
            'items' => [
                ['participant_id' => $budi->id, 'camera_status' => 'partial', 'camera_on_percent' => 50],
                ['participant_id' => $siti->id, 'camera_status' => 'off_cam', 'camera_on_percent' => 0],
            ],
            'mark_off_cam_as_tidak_hadir' => true,
        ])->assertOk()->assertJsonPath('updated_count', 2)->assertJsonPath('replaced_hadir', 1);

        $this->assertSame(CameraStatus::Partial, $budi->refresh()->camera_status);
        $this->assertSame('hadir', $budi->attendance_status);
        $this->assertSame(CameraStatus::OffCam, $siti->refresh()->camera_status);
        $this->assertSame('tidak_hadir', $siti->attendance_status);

        $this->postJson(route('training.camera-status.save', $this->training), [
            'items' => [['participant_id' => $budi->id, 'camera_status' => 'off_cam_2x']],
        ])->assertJsonValidationErrors('items.0.camera_status');
    }

    public function test_participants_of_another_training_are_not_touched(): void
    {
        $other = Training::create([
            'title' => 'Pelatihan lain',
            'trainer_id' => $this->admin->id,
            'training_date' => '2026-11-13',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'meeting_link' => 'https://zoom.us/j/987654321',
        ]);
        $stranger = TrainingParticipant::createFor($other, User::factory()->staff()->create()->id);

        $this->actingAs($this->admin)->postJson(route('training.camera-status.save', $this->training), [
            'items' => [['participant_id' => $stranger->id, 'camera_status' => 'off_cam']],
        ])->assertOk()->assertJsonPath('updated_count', 0);

        $this->assertNull($stranger->refresh()->camera_status);
        $this->postJson(route('training.camera-status.update', [$this->training, $stranger]), ['camera_status' => 'off_cam'])->assertNotFound();
    }

    public function test_detail_page_offers_the_three_ways_to_recap_cameras(): void
    {
        $this->actingAs($this->admin);

        // Monitoring is set up but has not recorded anything yet: the button is there so data recorded later loads.
        $this->get(route('training.show', $this->training))
            ->assertOk()
            ->assertSee('Rekap Kamera')
            ->assertSee('Belum ada status kamera yang terekam')
            ->assertSee('id="btnLoadMonitorCamera"', false)
            ->assertDontSee('Disarankan')
            ->assertSee('Pindai screenshot dengan AI belum tersedia')->assertSee('Atur GEMINI_API_KEY di server.')
            ->assertSee('Isi Sendiri')->assertSee('id="btnDirectManualInput"', false)
            ->assertSee('const HAS_MONITOR_DATA = false;', false);

        // Monitoring switched off and nothing recorded: no monitoring step pointing to a page where it cannot run.
        $this->training->update(['meeting_monitoring_enabled' => false]);
        $this->get(route('training.show', $this->training))
            ->assertOk()
            ->assertDontSee('id="btnLoadMonitorCamera"', false)
            ->assertSee('Pindai screenshot dengan AI');

        // Camera data recorded earlier stays loadable.
        $this->events($this->monitor('14:00:00'), [['14:00:05', 'present', '1', 'Budi', false]]);
        $this->get(route('training.show', $this->training))
            ->assertOk()
            ->assertSee('id="btnLoadMonitorCamera"', false)
            ->assertSee('Disarankan')
            ->assertSee('const HAS_MONITOR_DATA = true;', false)
            ->assertDontSee('Belum ada status kamera yang terekam');

        // With the AI key set the screenshot upload replaces the "not available" note.
        config(['services.gemini.api_key' => 'test-key']);
        $this->get(route('training.show', $this->training))
            ->assertOk()
            ->assertSee('Dari screenshot (AI)')->assertSee('id="btnStartScan"', false)
            ->assertDontSee('Pindai screenshot dengan AI belum tersedia');
    }

    public function test_camera_report_is_limited_to_trainers_who_may_edit_the_training(): void
    {
        $this->events($this->monitor('14:00:00', ended: '14:10:00'), [['14:00:05', 'present', '1', 'Budi', false]]);

        $this->actingAs(User::factory()->staff()->create())
            ->getJson(route('training.meeting.camera', $this->training))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->getJson(route('training.meeting.camera', $this->training))
            ->assertOk()
            ->assertJsonCount(1, 'camera_participants');

        $this->training->update(['meeting_link' => null]);
        $this->getJson(route('training.meeting.camera', $this->training))->assertNotFound();
    }

    private function participant(string $name): TrainingParticipant
    {
        return TrainingParticipant::createFor($this->training, User::factory()->staff()->create(['nama_lengkap' => $name])->id);
    }

    private function monitor(string $start, ?string $ended = null): TrainingMeetingMonitor
    {
        return TrainingMeetingMonitor::create([
            'id' => (string) Str::uuid(),
            'training_id' => $this->training->id,
            'user_id' => $this->admin->id,
            'provider' => 'zoom',
            'meeting_code' => '123456789',
            'source' => TrainingMeetingMonitor::SOURCE_LIVE,
            'account_revision' => str_repeat('a', 64),
            'started_at' => $this->at($start),
            'last_seen_at' => $this->at($ended ?? $start),
            'ended_at' => $ended === null ? null : $this->at($ended),
        ]);
    }

    /** @param  list<array{string, string, string, string, ?bool}>  $events */
    private function events(TrainingMeetingMonitor $monitor, array $events): void
    {
        foreach ($events as [$time, $type, $key, $name, $cameraOn]) {
            TrainingMeetingEvent::insert([
                'monitor_id' => $monitor->id,
                'event_id' => (string) Str::uuid(),
                'type' => $type,
                'observed_at' => $this->at($time)->format('Y-m-d H:i:s.v'),
                'participant_key' => $key,
                'name' => $name,
                'camera_on' => $cameraOn,
            ]);
        }
    }

    private function at(string $time): Carbon
    {
        return Carbon::parse('2026-11-13 '.$time);
    }
}
