<?php

namespace Tests\Feature;

use App\Enums\CameraStatus;
use App\Jobs\AiTask;
use App\Jobs\ScanZoomOffCam;
use App\Models\Divisi;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\User;
use App\Support\Meetings\MeetingNameMatcher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ZoomOffCamAiFeatureTest extends TestCase
{
    protected $admin;

    protected $participantUser1;

    protected $participantUser2;

    protected $training;

    protected $participant1;

    protected $participant2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('username', 'admin')->first() ?? User::factory()->admin()->create([
            'username' => 'admin',
        ]);

        $divisi = Divisi::first() ?? Divisi::create(['name' => 'Divisi Test']);

        $this->participantUser1 = User::create([
            'username' => 'rizky_'.uniqid(),
            'nama_lengkap' => 'Muhammad Rizky Pratama',
            'employee_id' => 'EMP-001',
            'email' => 'rizky_'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'divisi_id' => $divisi->id,
        ]);

        $this->participantUser2 = User::create([
            'username' => 'budi_'.uniqid(),
            'nama_lengkap' => 'Budi Santoso',
            'employee_id' => 'EMP-002',
            'email' => 'budi_'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'divisi_id' => $divisi->id,
        ]);

        $this->training = Training::create([
            'title' => 'Training Zoom AI Test',
            'trainer_id' => $this->admin->id,
            'description' => 'Test deteksi kamera zoom',
            'training_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'meeting_link' => 'https://zoom.us/j/123456789',
            'status' => 'ongoing',
            'is_quiz_active' => false,
            'is_attendance_active' => true,
        ]);

        $this->participant1 = TrainingParticipant::create([
            'training_id' => $this->training->id,
            'user_id' => $this->participantUser1->id,
            'token' => 'token_rizky_'.uniqid(),
            'attendance_status' => 'hadir',
            'attended_at' => now(),
        ]);

        $this->participant2 = TrainingParticipant::create([
            'training_id' => $this->training->id,
            'user_id' => $this->participantUser2->id,
            'token' => 'token_budi_'.uniqid(),
            'attendance_status' => 'hadir',
            'attended_at' => now(),
        ]);
    }

    public function test_trainer_picks_a_camera_status_from_the_participant_list()
    {
        $url = route('training.camera-status.update', [$this->training, $this->participant1]);

        $this->actingAs($this->admin)->postJson($url, ['camera_status' => 'partial'])
            ->assertOk()
            ->assertJson(['success' => true, 'camera_status' => 'partial', 'label' => 'Tidak Full On Cam']);

        $this->participant1->refresh();
        $this->assertSame(CameraStatus::Partial, $this->participant1->camera_status);
        $this->assertNull($this->participant1->camera_on_percent);
        $this->assertNotNull($this->participant1->camera_recorded_at);
        $this->assertSame('hadir', $this->participant1->attendance_status);

        // Mengosongkan pilihan menghapus rekapnya.
        $this->postJson($url, ['camera_status' => null])->assertOk()->assertJson(['camera_status' => null]);
        $this->participant1->refresh();
        $this->assertNull($this->participant1->camera_status);
        $this->assertNull($this->participant1->camera_recorded_at);

        $this->postJson($url, ['camera_status' => 'sometimes'])->assertJsonValidationErrors('camera_status');
    }

    /**
     * The recap rule: on cam in more than half of the checks = On Cam, half or fewer = Tidak Full On Cam, never = Full
     * Off Cam.
     */
    public function test_camera_status_follows_the_share_of_checks_with_the_camera_on()
    {
        $this->assertSame(CameraStatus::OnCam, CameraStatus::fromChecks(9, 10));
        $this->assertSame(CameraStatus::OnCam, CameraStatus::fromChecks(6, 10));
        $this->assertSame(CameraStatus::Partial, CameraStatus::fromChecks(5, 10));
        $this->assertSame(CameraStatus::Partial, CameraStatus::fromChecks(1, 10));
        $this->assertSame(CameraStatus::OffCam, CameraStatus::fromChecks(0, 10));
        $this->assertNull(CameraStatus::fromChecks(0, 0));

        $this->assertSame(51, CameraStatus::percent(5040, 10000));
        $this->assertSame(50, CameraStatus::percent(5, 10));
        $this->assertSame(1, CameraStatus::percent(1, 1000));
        $this->assertSame(99, CameraStatus::percent(999, 1000));
        $this->assertSame(100, CameraStatus::percent(10, 10));
    }

    /**
     * Meeting display names are matched to registered participants despite abbreviations and platform labels.
     */
    public function test_meeting_name_matcher_fuzzy_matching()
    {
        $participantsList = [
            [
                'id' => 10,
                'name' => 'Muhammad Rizky Pratama',
                'id_karyawan' => 'EMP-001',
            ],
            [
                'id' => 20,
                'name' => 'Siti Nurhaliza',
                'id_karyawan' => 'EMP-002',
            ],
        ];

        $match = fn (string $name) => MeetingNameMatcher::match($name, $participantsList);

        // Case 1: Singkatan nama depan "M. Rizky"
        $match1 = $match('M. Rizky');
        $this->assertNotNull($match1);
        $this->assertEquals(10, $match1['id']);
        $this->assertGreaterThanOrEqual(65, $match1['score']);

        // Case 2: Label tambahan Zoom "(Me)"
        $match2 = $match('Siti Nurhaliza (Me)');
        $this->assertNotNull($match2);
        $this->assertEquals(20, $match2['id']);

        // Case 3: Nama asing tidak dikenal (tamu luar / host)
        $match3 = $match('Guest User 99');
        $this->assertNull($match3);

        // Case 4: Nama resmi utuh (minimal dua kata) dengan tambahan kode toko / jabatan
        $this->assertSame(10, $match('C1_ Muhammad Rizky Pratama')['id']);
        $this->assertSame(30, MeetingNameMatcher::match('YAYAT RUHIYATNA CS GEBANG', [['id' => 30, 'name' => 'Yayat Ruhiyatna'], ...$participantsList])['id']);

        // Case 5: Nama resmi satu kata tidak otomatis cocok dengan nama lain yang diawali kata itu
        $this->assertNull(MeetingNameMatcher::match('Budi Santoso', [['id' => 40, 'name' => 'Budi']]));

        // Case 6: Nama resmi yang lebih lengkap menang saat skornya sama
        $this->assertSame(2, MeetingNameMatcher::match('Ahmad Fauzi TINTP', [['id' => 1, 'name' => 'Ahmad Rizal'], ['id' => 3, 'name' => 'Ahmad'], ['id' => 2, 'name' => 'Ahmad Fauzi']])['id']);

        // Case 7: Kata resmi dua huruf tetap dicocokkan lewat awalan
        $this->assertSame(50, MeetingNameMatcher::match('Abdul Rahman', [['id' => 50, 'name' => 'Ab Rahman']])['id']);
    }

    private function screenshot(string $content = 'fake-jpeg-bytes'): string
    {
        return 'data:image/jpeg;base64,'.base64_encode($content);
    }

    /**
     * Scan berjalan di queue AI: request hanya menyimpan screenshot dan mengembalikan token.
     */
    public function test_zoom_scan_is_queued_and_does_not_call_the_ai_in_the_request()
    {
        config(['services.gemini.api_key' => 'test-key']);
        Storage::fake('local');
        Queue::fake();

        $token = $this->actingAs($this->admin)
            ->postJson(route('training.scan-zoom-ai', $this->training), ['images' => [$this->screenshot('satu'), $this->screenshot('dua')]])
            ->assertOk()
            ->assertJson(['success' => true])
            ->json('token');

        Queue::assertPushed(ScanZoomOffCam::class, fn (ScanZoomOffCam $job) => $job->token === $token
            && $job->trainingId === $this->training->id
            && $job->connection === config('queue.ai_connection'));
        Http::assertNothingSent();

        $this->assertSame('satu', Storage::disk('local')->get(ScanZoomOffCam::DIRECTORY.'/'.$token.'/0.jpg'));
        $this->assertSame('dua', Storage::disk('local')->get(ScanZoomOffCam::DIRECTORY.'/'.$token.'/1.jpg'));
        $this->getJson(route('training.scan-zoom-ai.status', [$this->training, $token]))->assertOk()->assertExactJson(['status' => 'pending']);
    }

    public function test_zoom_scan_result_is_fetched_by_polling_and_screenshots_are_removed()
    {
        config(['services.gemini.api_key' => 'test-key']);
        Storage::fake('local');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode(['screenshots' => [
                    ['screenshot_index' => 1, 'participants' => [
                        ['zoom_name' => 'M. Rizky', 'camera_on' => true, 'matched_participant_id' => $this->participant1->id, 'similarity_score' => 95],
                        ['zoom_name' => '02_Budi', 'camera_on' => false],
                        ['zoom_name' => 'Tamu Luar', 'camera_on' => false, 'matched_participant_id' => 999999],
                    ]],
                    ['screenshot_index' => 2, 'participants' => [
                        ['zoom_name' => 'M. Rizky', 'camera_on' => false, 'matched_participant_id' => $this->participant1->id, 'similarity_score' => 95],
                        ['zoom_name' => 'Budi Santoso', 'camera_on' => false, 'matched_participant_id' => $this->participant2->id, 'similarity_score' => 100],
                    ]],
                    ['screenshot_index' => 3, 'participants' => [
                        // Gallery tile and participant-list row of the same person: one check, camera on.
                        ['zoom_name' => 'M. Rizky', 'camera_on' => true, 'matched_participant_id' => $this->participant1->id],
                        ['zoom_name' => 'M. Rizky', 'camera_on' => false, 'matched_participant_id' => $this->participant1->id],
                        ['zoom_name' => 'Budi Santoso', 'camera_on' => true, 'matched_participant_id' => $this->participant2->id],
                    ]],
                ]])]]]]],
            ]),
        ]);

        $token = $this->actingAs($this->admin)
            ->postJson(route('training.scan-zoom-ai', $this->training), ['images' => [$this->screenshot()]])
            ->assertOk()
            ->json('token');

        $this->getJson(route('training.scan-zoom-ai.status', [$this->training, $token]))
            ->assertOk()
            ->assertJson([
                'status' => 'done',
                'data' => ['total_screens_analyzed' => 3, 'camera_participants' => [
                    [
                        'zoom_name' => 'M. Rizky',
                        'participant_id' => $this->participant1->id,
                        'official_name' => 'Muhammad Rizky Pratama',
                        'is_registered' => true,
                        'checks' => 3,
                        'on_cam_checks' => 2,
                        'camera_status' => 'on_cam',
                        'camera_on_percent' => 67,
                        'visual_evidence' => 'Kamera menyala di 2 dari 3 screenshot',
                    ],
                    [
                        // Matched by name here when the AI gave no participant ID.
                        'zoom_name' => '02_Budi / Budi Santoso',
                        'participant_id' => $this->participant2->id,
                        'checks' => 3,
                        'on_cam_checks' => 1,
                        'camera_status' => 'partial',
                        'camera_on_percent' => 33,
                    ],
                    [
                        'zoom_name' => 'Tamu Luar',
                        'participant_id' => null,
                        'is_registered' => false,
                        'checks' => 1,
                        'camera_status' => 'off_cam',
                    ],
                ]],
            ])
            ->assertJsonCount(2, 'registered_participants');

        Http::assertSent(fn (Request $request) => $request['contents'][0]['parts'][0]['inline_data'] === [
            'mime_type' => 'image/jpeg',
            'data' => base64_encode('fake-jpeg-bytes'),
        ]);
        $this->assertSame([], Storage::disk('local')->allFiles(ScanZoomOffCam::DIRECTORY));
    }

    public function test_google_meet_training_can_be_scanned_with_a_meet_specific_prompt()
    {
        config(['services.gemini.api_key' => 'test-key']);
        Storage::fake('local');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode(['screenshots' => []])]]]]],
            ]),
        ]);
        $this->training->update(['meeting_platform' => 'google_meet', 'meeting_link' => 'https://meet.google.com/abc-defg-hij']);

        $token = $this->actingAs($this->admin)
            ->postJson(route('training.scan-zoom-ai', $this->training), ['images' => [$this->screenshot()]])
            ->assertOk()
            ->json('token');

        $this->getJson(route('training.scan-zoom-ai.status', [$this->training, $token]))
            ->assertOk()->assertJson(['status' => 'done']);
        Http::assertSent(function (Request $request) {
            $prompt = collect($request['contents'][0]['parts'])->pluck('text')->filter()->implode("\n");

            return str_contains($prompt, 'Gallery View) Google Meet') && ! str_contains($prompt, 'Zoom');
        });

        $this->training->update(['meeting_platform' => null, 'meeting_link' => null]);
        $this->postJson(route('training.scan-zoom-ai', $this->training), ['images' => [$this->screenshot()]])->assertNotFound();
    }

    public function test_zoom_scan_rejects_invalid_screenshots_and_missing_api_key()
    {
        Storage::fake('local');
        Queue::fake();

        config(['services.gemini.api_key' => 'test-key']);
        $this->actingAs($this->admin)
            ->postJson(route('training.scan-zoom-ai', $this->training), ['images' => ['bukan gambar']])
            ->assertUnprocessable()
            ->assertJson(['success' => false]);

        config(['services.gemini.api_key' => null]);
        $this->actingAs($this->admin)
            ->postJson(route('training.scan-zoom-ai', $this->training), ['images' => [$this->screenshot()]])
            ->assertUnprocessable()
            ->assertJson(['success' => false]);

        Queue::assertNothingPushed();
        $this->assertSame([], Storage::disk('local')->allFiles(ScanZoomOffCam::DIRECTORY));
        $this->getJson(route('training.scan-zoom-ai.status', [$this->training, 'tidak-ada']))->assertNotFound();
    }

    public function test_zoom_scan_killed_by_the_worker_is_reported_and_cleaned_up()
    {
        Storage::fake('local');
        $token = AiTask::newToken();
        ScanZoomOffCam::storeImages($token, [$this->screenshot()]);

        (new ScanZoomOffCam($token, $this->training->id))->failed(null);

        $this->assertSame('failed', AiTask::status($token)['status']);
        $this->assertSame([], Storage::disk('local')->allFiles(ScanZoomOffCam::DIRECTORY));
    }
}
