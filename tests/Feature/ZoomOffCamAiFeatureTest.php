<?php

namespace Tests\Feature;

use App\Models\Divisi;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\User;
use App\Services\ZoomAttendanceAiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ZoomOffCamAiFeatureTest extends TestCase
{
    use DatabaseTransactions;

    protected $admin;
    protected $participantUser1;
    protected $participantUser2;
    protected $training;
    protected $participant1;
    protected $participant2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('username', 'admin')->first() ?? User::factory()->create([
            'username' => 'admin',
            'job_level_id' => 1,
        ]);

        $divisi = Divisi::first() ?? Divisi::create(['name' => 'Divisi Test']);

        $this->participantUser1 = User::create([
            'username' => 'rizky_' . uniqid(),
            'full_name' => 'Muhammad Rizky Pratama',
            'id_karyawan' => 'EMP-001',
            'email' => 'rizky_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'job_level_id' => 2,
            'divisi_id' => $divisi->id,
        ]);

        $this->participantUser2 = User::create([
            'username' => 'budi_' . uniqid(),
            'full_name' => 'Budi Santoso',
            'id_karyawan' => 'EMP-002',
            'email' => 'budi_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'job_level_id' => 2,
            'divisi_id' => $divisi->id,
        ]);

        $this->training = Training::create([
            'title' => 'Training Zoom AI Test',
            'trainer_id' => $this->admin->id,
            'description' => 'Test deteksi kamera zoom',
            'training_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'zoom_link' => 'https://zoom.us/j/123456789',
            'status' => 'ongoing',
            'is_quiz_active' => false,
            'is_attendance_active' => true,
        ]);

        $this->participant1 = TrainingParticipant::create([
            'training_id' => $this->training->id,
            'user_id' => $this->participantUser1->id,
            'token' => 'token_rizky_' . uniqid(),
            'attendance_status' => 'hadir',
            'attended_at' => now(),
        ]);

        $this->participant2 = TrainingParticipant::create([
            'training_id' => $this->training->id,
            'user_id' => $this->participantUser2->id,
            'token' => 'token_budi_' . uniqid(),
            'attendance_status' => 'hadir',
            'attended_at' => now(),
        ]);
    }

    /**
     * Test saving verified off-cam participants by supervisor
     */
    public function test_save_verified_zoom_off_cam_successfully()
    {
        $payload = [
            'off_cam_items' => [
                [
                    'participant_id' => $this->participant1->id,
                    'zoom_name' => 'M. Rizky',
                    'reason' => 'Layar hitam polos'
                ]
            ],
            'mark_as_tidak_hadir' => false,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson("/training/{$this->training->id}/save-zoom-off-cam", $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'updated_count' => 1,
        ]);

        $this->participant1->refresh();
        $this->assertTrue($this->participant1->is_off_cam);
        $this->assertEquals('M. Rizky', $this->participant1->zoom_display_name);
        $this->assertNotNull($this->participant1->zoom_off_cam_at);
        $this->assertStringContainsString('Off Cam Zoom (M. Rizky)', $this->participant1->attendance_notes);
        // Kehadiran tetap hadir karena mark_as_tidak_hadir false
        $this->assertEquals('hadir', $this->participant1->attendance_status);
    }

    /**
     * Test save with option mark as tidak hadir
     */
    public function test_save_verified_zoom_off_cam_with_mark_as_tidak_hadir()
    {
        $payload = [
            'off_cam_items' => [
                [
                    'participant_id' => $this->participant2->id,
                    'zoom_name' => '02_Budi Santoso',
                    'reason' => 'Foto profil inisial'
                ]
            ],
            'mark_as_tidak_hadir' => true,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson("/training/{$this->training->id}/save-zoom-off-cam", $payload);

        $response->assertStatus(200);

        $this->participant2->refresh();
        $this->assertTrue($this->participant2->is_off_cam);
        $this->assertEquals('tidak_hadir', $this->participant2->attendance_status);
        $this->assertStringContainsString('Off Cam Zoom', $this->participant2->attendance_notes);
    }

    /**
     * Test resetting/cancelling off-cam status for a participant
     */
    public function test_reset_zoom_off_cam_clears_status_cleanly()
    {
        // First mark participant as off cam
        $this->participant1->update([
            'is_off_cam' => true,
            'zoom_display_name' => 'M. Rizky',
            'zoom_off_cam_at' => now(),
            'attendance_notes' => 'Catatan awal; Off Cam Zoom (M. Rizky)',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/training/{$this->training->id}/reset-zoom-off-cam/{$this->participant1->id}");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->participant1->refresh();
        $this->assertFalse($this->participant1->is_off_cam);
        $this->assertNull($this->participant1->zoom_display_name);
        $this->assertNull($this->participant1->zoom_off_cam_at);
        // Note Off Cam harus terhapus, catatan awal tetap ada
        $this->assertStringNotContainsString('Off Cam Zoom', (string)$this->participant1->attendance_notes);
        $this->assertStringContainsString('Catatan awal', (string)$this->participant1->attendance_notes);
    }

    /**
     * Test ZoomAttendanceAiService local fuzzy matching logic
     */
    public function test_zoom_service_local_fuzzy_matching()
    {
        $service = new ZoomAttendanceAiService('dummy-key');

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
            ]
        ];

        // Reflection to test protected localFuzzyMatch
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('localFuzzyMatch');
        $method->setAccessible(true);

        // Case 1: Singkatan nama depan "M. Rizky"
        $match1 = $method->invoke($service, 'M. Rizky', $participantsList);
        $this->assertNotNull($match1);
        $this->assertEquals(10, $match1['id']);
        $this->assertGreaterThanOrEqual(65, $match1['score']);

        // Case 2: Label tambahan Zoom "(Me)"
        $match2 = $method->invoke($service, 'Siti Nurhaliza (Me)', $participantsList);
        $this->assertNotNull($match2);
        $this->assertEquals(20, $match2['id']);

        // Case 3: Nama asing tidak dikenal (tamu luar / host)
        $match3 = $method->invoke($service, 'Guest User 99', $participantsList);
        $this->assertNull($match3);
    }

    /**
     * Test stepper counter increment and decrement for off-cam frequency
     */
    public function test_update_off_cam_count_stepper()
    {
        // 1. Increment first time (0 -> 1)
        $res1 = $this->actingAs($this->admin)
            ->postJson("/training/{$this->training->id}/update-offcam-count/{$this->participant1->id}", [
                'action' => 'increment'
            ]);
        $res1->assertStatus(200);
        $res1->assertJson([
            'success' => true,
            'is_off_cam' => true,
            'off_cam_count' => 1,
        ]);

        $this->participant1->refresh();
        $this->assertTrue($this->participant1->is_off_cam);
        $this->assertEquals(1, $this->participant1->off_cam_count);
        $this->assertStringContainsString('Off Cam Zoom (1x)', $this->participant1->attendance_notes);

        // 2. Increment second time (1 -> 2)
        $res2 = $this->actingAs($this->admin)
            ->postJson("/training/{$this->training->id}/update-offcam-count/{$this->participant1->id}", [
                'action' => 'increment'
            ]);
        $res2->assertStatus(200);
        $res2->assertJson([
            'success' => true,
            'is_off_cam' => true,
            'off_cam_count' => 2,
        ]);

        $this->participant1->refresh();
        $this->assertEquals(2, $this->participant1->off_cam_count);
        $this->assertStringContainsString('Off Cam Zoom (2x)', $this->participant1->attendance_notes);

        // 3. Decrement once (2 -> 1)
        $res3 = $this->actingAs($this->admin)
            ->postJson("/training/{$this->training->id}/update-offcam-count/{$this->participant1->id}", [
                'action' => 'decrement'
            ]);
        $res3->assertStatus(200);
        $res3->assertJson([
            'success' => true,
            'is_off_cam' => true,
            'off_cam_count' => 1,
        ]);

        $this->participant1->refresh();
        $this->assertEquals(1, $this->participant1->off_cam_count);

        // 4. Decrement to zero (1 -> 0)
        $res4 = $this->actingAs($this->admin)
            ->postJson("/training/{$this->training->id}/update-offcam-count/{$this->participant1->id}", [
                'action' => 'decrement'
            ]);
        $res4->assertStatus(200);
        $res4->assertJson([
            'success' => true,
            'is_off_cam' => false,
            'off_cam_count' => 0,
        ]);

        $this->participant1->refresh();
        $this->assertFalse($this->participant1->is_off_cam);
        $this->assertEquals(0, $this->participant1->off_cam_count);
        $this->assertStringNotContainsString('Off Cam Zoom', (string)$this->participant1->attendance_notes);
    }
}

