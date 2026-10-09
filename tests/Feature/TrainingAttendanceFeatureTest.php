<?php

namespace Tests\Feature;

use App\Models\Divisi;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrainingAttendanceFeatureTest extends TestCase
{
    protected $admin;

    protected $participantUser;

    protected $training;

    protected $participant;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->admin = User::where('username', 'admin')->first() ?? User::factory()->create([
            'username' => 'admin',
            'job_level_id' => 1,
        ]);

        $divisi = Divisi::first() ?? Divisi::create(['name' => 'Divisi Test']);

        $this->participantUser = User::create([
            'username' => 'peserta_absen_'.uniqid(),
            'full_name' => 'Peserta Uji Absensi',
            'email' => 'peserta_'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'job_level_id' => 2,
            'divisi_id' => $divisi->id,
        ]);

        $this->training = Training::create([
            'title' => 'Training Absensi Test',
            'trainer_id' => $this->admin->id,
            'description' => 'Test absensi buka tutup dan screenshot bukti.',
            'training_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'zoom_link' => 'https://zoom.us/test-zoom',
            'status' => 'scheduled',
            'is_quiz_active' => false,
            'is_attendance_active' => false,
            'require_attendance_proof' => false,
        ]);

        $this->participant = TrainingParticipant::create([
            'training_id' => $this->training->id,
            'user_id' => $this->participantUser->id,
            'token' => Str::random(40).'_'.time(),
            'attendance_status' => 'pending',
        ]);
    }

    /**
     * Test admin can toggle attendance open and closed
     */
    public function test_admin_can_toggle_attendance()
    {
        $this->assertFalse($this->training->fresh()->is_attendance_active);

        // 1. Admin toggles attendance open
        $res = $this->actingAs($this->admin)->post("/training/{$this->training->id}/toggle-attendance");
        $res->assertSessionHas('success');
        $this->assertTrue($this->training->fresh()->is_attendance_active);

        // 2. Admin toggles attendance closed
        $res = $this->actingAs($this->admin)->post("/training/{$this->training->id}/toggle-attendance");
        $res->assertSessionHas('success');
        $this->assertFalse($this->training->fresh()->is_attendance_active);
    }

    /**
     * Test admin can toggle screenshot requirement
     */
    public function test_admin_can_toggle_screenshot_proof_requirement()
    {
        $this->assertFalse($this->training->fresh()->require_attendance_proof);

        // Toggle on
        $res = $this->actingAs($this->admin)->post("/training/{$this->training->id}/toggle-proof");
        $res->assertSessionHas('success');
        $this->assertTrue($this->training->fresh()->require_attendance_proof);

        // Toggle off
        $res = $this->actingAs($this->admin)->post("/training/{$this->training->id}/toggle-proof");
        $res->assertSessionHas('success');
        $this->assertFalse($this->training->fresh()->require_attendance_proof);
    }

    /**
     * Test participant cannot submit attendance when closed
     */
    public function test_participant_cannot_submit_attendance_when_closed()
    {
        $this->training->update([
            'is_attendance_active' => false,
            'is_quiz_active' => false,
        ]);

        $res = $this->post("/training/portal/{$this->participant->token}/attendance", [
            'status' => 'hadir',
        ]);

        $res->assertSessionHas('warning');
        $this->assertEquals('pending', $this->participant->fresh()->attendance_status);
    }

    /**
     * Test participant can submit attendance when quiz is opened (user requirement: kalo kuis dibuka baru bisa absen)
     */
    public function test_participant_can_submit_attendance_when_quiz_is_opened()
    {
        $this->training->update([
            'is_attendance_active' => false,
            'is_quiz_active' => true,
        ]);

        $res = $this->post("/training/portal/{$this->participant->token}/attendance", [
            'status' => 'hadir',
        ]);

        $res->assertSessionHas('success');
        $this->assertEquals('hadir', $this->participant->fresh()->attendance_status);
    }

    /**
     * Test participant attendance with screenshot proof requirement
     */
    public function test_participant_attendance_with_and_without_proof()
    {
        // Open attendance and require proof
        $this->training->update([
            'is_attendance_active' => true,
            'require_attendance_proof' => true,
        ]);

        // Submit without screenshot -> should fail validation
        $resFail = $this->post("/training/portal/{$this->participant->token}/attendance", [
            'status' => 'hadir',
        ]);
        $resFail->assertSessionHasErrors('attendance_proof');
        $this->assertEquals('pending', $this->participant->fresh()->attendance_status);

        // Submit with fake screenshot file -> should succeed
        $fakeScreenshot = UploadedFile::fake()->image('bukti_zoom.png', 800, 600);
        $resSuccess = $this->post("/training/portal/{$this->participant->token}/attendance", [
            'status' => 'hadir',
            'attendance_proof' => $fakeScreenshot,
        ]);

        $resSuccess->assertSessionHas('success');
        $freshPart = $this->participant->fresh();
        $this->assertEquals('hadir', $freshPart->attendance_status);
        $this->assertNotNull($freshPart->attended_at);
        $this->assertNotNull($freshPart->attendance_proof);
        Storage::disk('public')->assertExists($freshPart->attendance_proof);
    }

    /**
     * Test admin can reset participant attendance back to pending (Belum Absen)
     */
    public function test_admin_can_reset_participant_attendance()
    {
        // Participant attends first
        $this->participant->update([
            'attendance_status' => 'hadir',
            'attended_at' => now(),
            'attendance_proof' => 'attendance_proofs/test_proof.png',
        ]);
        Storage::disk('public')->put('attendance_proofs/test_proof.png', 'fake image content');

        $this->assertEquals('hadir', $this->participant->fresh()->attendance_status);

        // Admin resets attendance
        $res = $this->actingAs($this->admin)->post("/training/{$this->training->id}/reset-attendance/{$this->participant->id}");
        $res->assertSessionHas('success');

        $freshPart = $this->participant->fresh();
        $this->assertEquals('pending', $freshPart->attendance_status);
        $this->assertNull($freshPart->attended_at);
        $this->assertNull($freshPart->attendance_proof);
        Storage::disk('public')->assertMissing('attendance_proofs/test_proof.png');
    }

    /**
     * Test admin can update attendance status manually
     */
    public function test_admin_can_update_participant_attendance_manually()
    {
        // Admin manually sets status to tidak_hadir with notes
        $res = $this->actingAs($this->admin)->post("/training/{$this->training->id}/update-attendance/{$this->participant->id}", [
            'attendance_status' => 'tidak_hadir',
            'attendance_notes' => 'Izin sakit',
        ]);

        $res->assertSessionHas('success');
        $freshPart = $this->participant->fresh();
        $this->assertEquals('tidak_hadir', $freshPart->attendance_status);
        $this->assertEquals('Izin sakit', $freshPart->attendance_notes);

        // Admin sets back to pending (belum absen)
        $res2 = $this->actingAs($this->admin)->post("/training/{$this->training->id}/update-attendance/{$this->participant->id}", [
            'attendance_status' => 'pending',
        ]);

        $res2->assertSessionHas('success');
        $this->assertEquals('pending', $this->participant->fresh()->attendance_status);
    }
}
