<?php

namespace Tests\Feature;

use App\Exports\TrainingQuestionTemplate;
use App\Models\Divisi;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\TrainingQuestion;
use App\Models\TrainingQuizResult;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class TrainingFullLifecycleTest extends TestCase
{
    protected $admin;

    protected $participantUser1;

    protected $participantUser2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('username', 'admin')->first();
        if (! $this->admin) {
            $this->admin = User::factory()->create([
                'username' => 'admin',
                'password' => bcrypt('complete123'),
                'job_level_id' => 1,
            ]);
        }

        $divisi = Divisi::first() ?? Divisi::create(['name' => 'Divisi Test']);

        $this->participantUser1 = User::create([
            'username' => 'peserta_1_'.uniqid(),
            'full_name' => 'Peserta Lifecycle Satu',
            'email' => 'peserta1_'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'job_level_id' => 2,
            'divisi_id' => $divisi->id,
        ]);

        $this->participantUser2 = User::create([
            'username' => 'peserta_2_'.uniqid(),
            'full_name' => 'Peserta Lifecycle Dua',
            'email' => 'peserta2_'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'job_level_id' => 2,
            'divisi_id' => $divisi->id,
        ]);
    }

    /**
     * 1. Test Admin Training Full CRUD Lifecycle:
     * Index -> Create -> Show -> Edit -> Update -> Status Change -> Export CSV -> Delete
     */
    public function test_admin_training_crud_lifecycle()
    {
        // 1.1 Access Training Index
        $indexRes = $this->actingAs($this->admin)->get('/training');
        $indexRes->assertStatus(200);
        $indexRes->assertSee('Pelatihan');

        // 1.2 Access Create Page
        $createPageRes = $this->actingAs($this->admin)->get('/training/create');
        $createPageRes->assertStatus(200);
        $createPageRes->assertSee('Tambah Pelatihan Baru');

        // 1.3 Store New Training
        $storeRes = $this->actingAs($this->admin)->post('/training', [
            'title' => 'Pelatihan CRUD Test System',
            'trainer_id' => $this->admin->id,
            'description' => 'Pelatihan untuk pengujian CRUD lengkap.',
            'training_date' => now()->addDays(2)->toDateString(),
            'start_time' => '13:00',
            'end_time' => '15:00',
            'zoom_link' => 'https://zoom.us/j/1234567890',
            'participants' => [$this->participantUser1->id, $this->participantUser2->id],
        ]);

        $training = Training::where('title', 'Pelatihan CRUD Test System')->first();
        $this->assertNotNull($training);
        $storeRes->assertRedirect("/training/{$training->id}");
        $this->assertCount(2, $training->participants);

        // 1.4 Access Show / Detail Page
        $showRes = $this->actingAs($this->admin)->get("/training/{$training->id}");
        $showRes->assertStatus(200);
        $showRes->assertSee('Pelatihan CRUD Test System');
        $showRes->assertSee($this->participantUser1->full_name);

        // 1.5 Access Edit Page
        $editPageRes = $this->actingAs($this->admin)->get("/training/{$training->id}/edit");
        $editPageRes->assertStatus(200);
        $editPageRes->assertSee('Edit Pelatihan');

        // 1.6 Update Training
        $updateRes = $this->actingAs($this->admin)->post("/training/{$training->id}/update", [
            'title' => 'Pelatihan CRUD Test System (Updated)',
            'trainer_id' => $this->admin->id,
            'description' => 'Deskripsi yang telah diperbarui.',
            'training_date' => now()->addDays(3)->toDateString(),
            'start_time' => '14:00',
            'end_time' => '16:00',
            'zoom_link' => 'https://zoom.us/j/9876543210',
            'status' => 'ongoing',
            'participants' => [$this->participantUser1->id], // Remove user 2, keep user 1
        ]);
        $updateRes->assertRedirect("/training/{$training->id}");

        $training->refresh();
        $this->assertEquals('Pelatihan CRUD Test System (Updated)', $training->title);
        $this->assertEquals('ongoing', $training->status);
        $this->assertCount(1, $training->participants);

        // 1.7 Update Status
        $statusRes = $this->actingAs($this->admin)->post("/training/{$training->id}/status", [
            'status' => 'completed',
        ]);
        $statusRes->assertSessionHas('success');
        $this->assertEquals('completed', $training->fresh()->status);

        // 1.8 Toggle Quiz Active
        $this->assertFalse((bool) $training->fresh()->is_quiz_active);
        $toggleRes = $this->actingAs($this->admin)->post("/training/{$training->id}/toggle-quiz");
        $toggleRes->assertSessionHas('success');
        $this->assertTrue((bool) $training->fresh()->is_quiz_active);

        // 1.9 Export Excel / CSV
        $exportRes = $this->actingAs($this->admin)->get("/training/{$training->id}/export");
        $exportRes->assertStatus(200);
        $this->assertTrue(
            str_contains($exportRes->headers->get('Content-Type'), 'spreadsheet') ||
            str_contains($exportRes->headers->get('Content-Type'), 'text/csv') ||
            str_contains($exportRes->headers->get('Content-Type'), 'octet-stream')
        );

        // 1.10 Delete Training
        $deleteRes = $this->actingAs($this->admin)->get("/training/delete/{$training->id}");
        $deleteRes->assertRedirect('/training');
        $this->assertNull(Training::find($training->id));
    }

    /**
     * 2. Test Question Management Full CRUD & Multiple Import Types:
     * Manual Add (PG & Essay) -> Manual Update -> Delete Single -> Delete All -> Import Text -> Import Excel
     */
    public function test_question_crud_and_import_lifecycle()
    {
        $training = Training::create([
            'title' => 'Training Soal CRUD Test',
            'trainer_id' => $this->admin->id,
            'training_date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'zoom_link' => 'https://zoom.us/test',
            'status' => 'scheduled',
        ]);

        // 2.1 Add Multiple Choice Question (A to F options)
        $addMCRes = $this->actingAs($this->admin)->post("/training/{$training->id}/questions", [
            'type' => 'multiple_choice',
            'question' => 'Siapa yang berhak menyetujui dokumen ini?',
            'option_a' => 'Staff Biasa',
            'option_b' => 'Supervisor',
            'option_c' => 'Manager Divisi',
            'option_d' => 'Direktur Utama',
            'option_e' => 'Auditor Internal',
            'option_f' => 'Semua Benar',
            'correct_answer' => 'c',
            'explanation' => 'Sesuai SOP, disetujui Manager.',
        ]);
        $addMCRes->assertSessionHas('success');
        $mcQuestion = TrainingQuestion::where('training_id', $training->id)->where('type', 'multiple_choice')->first();
        $this->assertNotNull($mcQuestion);
        $this->assertEquals('c', $mcQuestion->correct_answer);
        $this->assertEquals('Auditor Internal', $mcQuestion->option_e);

        // 2.2 Add Essay Question
        $addEssayRes = $this->actingAs($this->admin)->post("/training/{$training->id}/questions", [
            'type' => 'essay',
            'question' => 'Jelaskan tahapan eskalasi komplain pelanggan?',
            'correct_answer' => 'Tahap 1 CS, Tahap 2 Spv, Tahap 3 Mgr.',
            'explanation' => 'Pedoman penanganan komplain 2026.',
        ]);
        $addEssayRes->assertSessionHas('success');
        $essayQuestion = TrainingQuestion::where('training_id', $training->id)->where('type', 'essay')->first();
        $this->assertNotNull($essayQuestion);
        $this->assertEquals('essay', $essayQuestion->type);
        $this->assertNull($essayQuestion->option_a);

        // 2.3 Update MC Question
        $updateMCRes = $this->actingAs($this->admin)->post("/training/{$training->id}/questions/{$mcQuestion->id}/update", [
            'type' => 'multiple_choice',
            'question' => 'Siapa yang berhak menyetujui dokumen ini? (Diperbarui)',
            'option_a' => 'Staff Biasa',
            'option_b' => 'Supervisor',
            'option_d' => 'Direktur Utama',
            'correct_answer' => 'd', // Changed correct answer to d
        ]);
        $updateMCRes->assertSessionHas('success');
        $mcQuestion->refresh();
        $this->assertEquals('Siapa yang berhak menyetujui dokumen ini? (Diperbarui)', $mcQuestion->question);
        $this->assertEquals('d', $mcQuestion->correct_answer);

        // 2.4 Update Essay Question
        $updateEssayRes = $this->actingAs($this->admin)->post("/training/{$training->id}/questions/{$essayQuestion->id}/update", [
            'type' => 'essay',
            'question' => 'Jelaskan tahapan eskalasi komplain pelanggan? (Diperbarui)',
            'correct_answer' => 'Kunci revisi lengkap.',
        ]);
        $updateEssayRes->assertSessionHas('success');
        $essayQuestion->refresh();
        $this->assertEquals('Jelaskan tahapan eskalasi komplain pelanggan? (Diperbarui)', $essayQuestion->question);
        $this->assertEquals('Kunci revisi lengkap.', $essayQuestion->correct_answer);

        // 2.5 Delete Single Question
        $deleteSingleRes = $this->actingAs($this->admin)->get("/training/{$training->id}/questions/{$mcQuestion->id}/delete");
        $deleteSingleRes->assertSessionHas('success');
        $this->assertNull(TrainingQuestion::find($mcQuestion->id));
        $this->assertCount(1, $training->questions()->get());

        // 2.6 Delete All Questions
        $deleteAllRes = $this->actingAs($this->admin)->post("/training/{$training->id}/questions/delete-all");
        $deleteAllRes->assertSessionHas('success');
        $this->assertCount(0, $training->questions()->get());

        // 2.7 Import Questions via Text (Aiken Plaintext format)
        $rawText = '1. Berapa lama masa penyimpanan arsip aktif?
A. 1 Tahun
B. 2 Tahun
C. 5 Tahun
KUNCI: C
PENJELASAN: Sesuai retensi arsip.

2. Sebutkan visi dan misi unit kerja Anda pada modul ini!
TIPE: ESSAY
KUNCI: Menjadi unit kerja yang transparan, akuntabel, dan adaptif.';

        $importTextRes = $this->actingAs($this->admin)->post("/training/{$training->id}/questions/import-text", [
            'raw_text' => $rawText,
        ]);
        $importTextRes->assertSessionHas('success');
        $this->assertCount(2, $training->questions()->get());

        // 2.8 Import Questions via Excel (.xlsx)
        $excelBinary = Excel::raw(new TrainingQuestionTemplate, \Maatwebsite\Excel\Excel::XLSX);
        $file = UploadedFile::fake()->createWithContent('test_soal.xlsx', $excelBinary);

        $importExcelRes = $this->actingAs($this->admin)->post("/training/{$training->id}/questions/import-excel", [
            'file' => $file,
        ]);
        $importExcelRes->assertSessionHas('success');
        // 2 previous from text + 4 from template = 6 questions
        $this->assertCount(6, $training->questions()->get());
    }

    /**
     * 3. Test Participant Portal Lifecycle:
     * Invalid token check -> Portal view -> Attendance submit -> Quiz gate (inactive) ->
     * Quiz active -> Quiz answer submission (MC & Essay) -> Result verification ->
     * Trainer manual essay grading -> Proportional final score recalculation ->
     * Reset quiz by Admin -> Participant retakes and resubmits
     */
    public function test_participant_portal_and_grading_lifecycle()
    {
        // 3.1 Setup Training & Questions
        $training = Training::create([
            'title' => 'Training Simulasi Peserta & Penilaian',
            'trainer_id' => $this->admin->id,
            'training_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'zoom_link' => 'https://zoom.us/test',
            'status' => 'ongoing',
            'is_quiz_active' => false, // Start inactive
            'is_attendance_active' => true,
        ]);

        // Q1: MC (A-D)
        $q1 = TrainingQuestion::create([
            'training_id' => $training->id,
            'type' => 'multiple_choice',
            'question' => 'Apakah protokol keamanan data wajib dipatuhi?',
            'option_a' => 'Ya, mutlak wajib',
            'option_b' => 'Tidak wajib',
            'correct_answer' => 'a',
        ]);

        // Q2: MC (A-D)
        $q2 = TrainingQuestion::create([
            'training_id' => $training->id,
            'type' => 'multiple_choice',
            'question' => 'Kapan laporan evaluasi harus diserahkan?',
            'option_a' => 'Maksimal 1 hari setelah kegiatan',
            'option_b' => 'Maksimal 1 bulan',
            'correct_answer' => 'a',
        ]);

        // Q3: Essay
        $q3 = TrainingQuestion::create([
            'training_id' => $training->id,
            'type' => 'essay',
            'question' => 'Tuliskan resume singkat dari materi yang baru saja dipelajari!',
            'correct_answer' => 'Ringkasan mengenai keamanan data dan kepatuhan pelaporan.',
        ]);

        $token = 'test_token_lifecycle_'.uniqid();
        $participant = TrainingParticipant::create([
            'training_id' => $training->id,
            'user_id' => $this->participantUser1->id,
            'token' => $token,
            'attendance_status' => 'pending',
        ]);

        // 3.2 Invalid token check
        $invalidRes = $this->get('/training/portal/token_yang_pasti_ngawur_123');
        $invalidRes->assertStatus(404);

        // 3.3 Valid token access
        $portalRes = $this->get("/training/portal/{$token}");
        $portalRes->assertStatus(200);
        $portalRes->assertSee('Training Simulasi Peserta & Penilaian');
        $portalRes->assertSee($this->participantUser1->full_name);

        // 3.4 Participant Submit Attendance
        $attendRes = $this->post("/training/portal/{$token}/attendance", [
            'status' => 'hadir',
            'notes' => 'Hadir penuh via Zoom.',
        ]);
        $attendRes->assertSessionHas('success');
        $this->assertEquals('hadir', $participant->fresh()->attendance_status);
        $this->assertEquals('Hadir penuh via Zoom.', $participant->fresh()->attendance_notes);

        // 3.5 Attempt to access quiz when INACTIVE
        $quizBlockedRes = $this->get("/training/portal/{$token}/quiz");
        $quizBlockedRes->assertRedirect("/training/portal/{$token}");
        $quizBlockedRes->assertSessionHas('warning');

        // 3.6 Admin activates quiz
        $training->update(['is_quiz_active' => true]);

        // 3.7 Participant accesses quiz when ACTIVE
        $quizActiveRes = $this->get("/training/portal/{$token}/quiz");
        $quizActiveRes->assertStatus(200);
        $quizActiveRes->assertSee('Apakah protokol keamanan data wajib dipatuhi?');
        $quizActiveRes->assertSee('Tuliskan resume singkat dari materi yang baru saja dipelajari!');
        $quizActiveRes->assertSee('textarea');

        // 3.8 Participant submits answers: Q1 Correct ('a'), Q2 Incorrect ('b'), Q3 Essay Text
        $submitAnswersRes = $this->post("/training/portal/{$token}/quiz", [
            'answers' => [
                $q1->id => 'a', // Correct (1 of 2 MC correct = 50% MC score)
                $q2->id => 'b', // Incorrect
                $q3->id => 'Ini adalah jawaban essay saya mengenai pentingnya integritas data dan disiplin waktu pelaporan.',
            ],
        ]);
        $submitAnswersRes->assertRedirect("/training/portal/{$token}/result");

        // Verify Database State
        $result = TrainingQuizResult::where('training_participant_id', $participant->id)->first();
        $this->assertNotNull($result);
        $this->assertEquals(50, (int) $result->mc_score); // 1 out of 2 MC correct = 50%
        $this->assertEquals('pending', $result->essay_status);
        $this->assertEquals(50, (int) $result->score); // While pending, score reflects MC score

        // Verify saved JSON answers
        $answers = $result->answers;
        $this->assertTrue($answers[$q1->id]['is_correct']);
        $this->assertFalse($answers[$q2->id]['is_correct']);
        $this->assertNull($answers[$q3->id]['is_correct']);
        $this->assertEquals('essay', $answers[$q3->id]['type']);
        $this->assertStringContainsString('jawaban essay saya mengenai pentingnya integritas data', $answers[$q3->id]['user_answer']);

        // 3.9 Participant visits Result page
        $resultViewRes = $this->get("/training/portal/{$token}/result");
        $resultViewRes->assertStatus(200);
        $resultViewRes->assertSee('Hasil Evaluasi Kuis');
        $resultViewRes->assertSee('50');
        $resultViewRes->assertSee('Menunggu Review');
        $resultViewRes->assertSee('jawaban essay saya mengenai pentingnya integritas data');

        // 3.10 Participant tries to visit /quiz again after submitting -> Auto redirects to /result
        $retakeBlockRes = $this->get("/training/portal/{$token}/quiz");
        $retakeBlockRes->assertRedirect("/training/portal/{$token}/result");

        // 3.11 Admin Grades Essay
        // Question composition: 2 MC, 1 Essay. Total = 3 questions.
        // MC Score: 50 * (2/3) = 33.33
        // Trainer gives Essay Score: 80. Essay Score: 80 * (1/3) = 26.67
        // Final Score: 33.33 + 26.67 = 60.00
        $gradeRes = $this->actingAs($this->admin)->post("/training/{$training->id}/grade-essay/{$result->id}", [
            'essay_score' => 80,
            'essay_feedback' => 'Analisa sudah bagus dan sesuai dengan standar SOP.',
        ]);
        $gradeRes->assertSessionHas('success');

        $result->refresh();
        $this->assertEquals('graded', $result->essay_status);
        $this->assertEquals(80, (int) $result->essay_score);
        $this->assertEquals('Analisa sudah bagus dan sesuai dengan standar SOP.', $result->essay_feedback);
        $this->assertEquals(60.00, (float) $result->score);

        // 3.12 Participant views Result page again and sees updated final score and feedback
        $updatedResultView = $this->get("/training/portal/{$token}/result");
        $updatedResultView->assertStatus(200);
        $updatedResultView->assertSee('60');
        $updatedResultView->assertSee('Sudah Dinilai');
        $updatedResultView->assertSee('Analisa sudah bagus dan sesuai dengan standar SOP.');

        // 3.13 Admin Resets Participant Quiz
        $resetRes = $this->actingAs($this->admin)->post("/training/{$training->id}/reset-quiz/{$participant->id}");
        $resetRes->assertSessionHas('success');
        $this->assertNull(TrainingQuizResult::where('training_participant_id', $participant->id)->first());

        // 3.14 Participant is now permitted to take quiz again
        $reopenedQuizRes = $this->get("/training/portal/{$token}/quiz");
        $reopenedQuizRes->assertStatus(200);
        $reopenedQuizRes->assertSee('Apakah protokol keamanan data wajib dipatuhi?');

        // Participant resubmits with 100% MC correct
        $resubmitRes = $this->post("/training/portal/{$token}/quiz", [
            'answers' => [
                $q1->id => 'a', // Correct
                $q2->id => 'a', // Correct
                $q3->id => 'Jawaban essay revisi yang lebih komprehensif.',
            ],
        ]);
        $resubmitRes->assertRedirect("/training/portal/{$token}/result");

        $newResult = TrainingQuizResult::where('training_participant_id', $participant->id)->first();
        $this->assertNotNull($newResult);
        $this->assertEquals(100, (int) $newResult->mc_score);
        $this->assertEquals('pending', $newResult->essay_status);
    }

    /**
     * 4. Test Participant "My Trainings" Dashboard
     */
    public function test_participant_my_trainings_dashboard()
    {
        $training = Training::create([
            'title' => 'Pelatihan Dashboard Karyawan',
            'trainer_id' => $this->admin->id,
            'training_date' => now()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'zoom_link' => 'https://zoom.us/test',
            'status' => 'ongoing',
        ]);

        TrainingParticipant::create([
            'training_id' => $training->id,
            'user_id' => $this->participantUser2->id,
            'token' => 'token_dashboard_'.uniqid(),
            'attendance_status' => 'hadir',
        ]);

        $myTrainingsRes = $this->actingAs($this->participantUser2)->get('/my-trainings');
        $myTrainingsRes->assertStatus(200);
        $myTrainingsRes->assertSee('Pelatihan Dashboard Karyawan');
    }

    /**
     * 5. Test Anti-Cheat Alarm & Auto-Submit System
     */
    public function test_anti_cheat_detection_and_auto_submit_lifecycle()
    {
        $training = Training::create([
            'title' => 'Pelatihan Anti-Cheat Test',
            'trainer_id' => $this->admin->id,
            'training_date' => now()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'zoom_link' => 'https://zoom.us/test',
            'status' => 'ongoing',
            'is_quiz_active' => true,
        ]);

        $q = TrainingQuestion::create([
            'training_id' => $training->id,
            'type' => 'multiple_choice',
            'question' => 'Apakah integritas penting dalam bekerja?',
            'option_a' => 'Sangat Penting',
            'option_b' => 'Tidak Penting',
            'correct_answer' => 'a',
        ]);

        $token = 'token_anticheat_'.uniqid();
        $participant = TrainingParticipant::create([
            'training_id' => $training->id,
            'user_id' => $this->participantUser1->id,
            'token' => $token,
            'attendance_status' => 'hadir',
        ]);

        // 5.1 Access quiz and verify Anti-Cheat UI elements
        $quizPageRes = $this->get("/training/portal/{$token}/quiz");
        $quizPageRes->assertStatus(200);
        $quizPageRes->assertSee('Toleransi Keluar: 3x');
        $quizPageRes->assertSee('Peringatan Pindah Layar');
        $quizPageRes->assertSee('Matikan Alarm');
        $quizPageRes->assertSee('tabSwitchCount');

        // 5.2 Simulate Force Submit due to 3 violations
        $logs = [
            ['type' => 'Pindah Tab Browser', 'count' => 1, 'time' => now()->subMinutes(2)->toISOString()],
            ['type' => 'Buka Aplikasi Lain', 'count' => 2, 'time' => now()->subMinutes(1)->toISOString()],
            ['type' => 'Pindah Tab Browser', 'count' => 3, 'time' => now()->toISOString()],
        ];

        $submitRes = $this->post("/training/portal/{$token}/quiz", [
            'answers' => [
                $q->id => 'a',
            ],
            'tab_switch_count' => 3,
            'is_force_submitted' => 1,
            'violation_logs' => json_encode($logs),
        ]);

        $submitRes->assertRedirect("/training/portal/{$token}/result");
        $submitRes->assertSessionHas('warning');

        // 5.3 Verify Database values
        $result = TrainingQuizResult::where('training_participant_id', $participant->id)->first();
        $this->assertNotNull($result);
        $this->assertEquals(3, $result->tab_switch_count);
        $this->assertTrue((bool) $result->is_force_submitted);
        $this->assertCount(3, $result->violation_logs);

        // 5.4 Check Participant Result page shows auto-submit alert
        $resultPageRes = $this->get("/training/portal/{$token}/result");
        $resultPageRes->assertStatus(200);
        $resultPageRes->assertSee('Kuis Dikumpulkan Otomatis (Terdeteksi Berpindah Tab)');
        $resultPageRes->assertSee('3 kali');

        // 5.5 Check Admin Detail view shows cheat badge and audit log
        $adminViewRes = $this->actingAs($this->admin)->get("/training/{$training->id}");
        $adminViewRes->assertStatus(200);
        $adminViewRes->assertSee('Curang (Auto-Submit)');
        $adminViewRes->assertSee('Catatan Integritas');
        $adminViewRes->assertSee('3 kali');
    }
}
