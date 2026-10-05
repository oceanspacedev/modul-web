<?php

namespace Tests\Feature;

use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\TrainingQuestion;
use App\Models\TrainingQuizResult;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TrainingQuizComprehensiveTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test Complete Admin and Participant Flows
     */
    public function test_full_admin_and_participant_quiz_workflow()
    {
        // ==========================================
        // 1. ADMIN AUTHENTICATION
        // ==========================================
        $admin = User::where('username', 'admin')->first();
        if (!$admin) {
            $admin = User::factory()->create([
                'username' => 'admin',
                'password' => bcrypt('complete123'),
                'job_level_id' => 1,
            ]);
        } else {
            $admin->update(['password' => bcrypt('complete123')]);
        }

        // Test login
        $response = $this->post('/login', [
            'username' => 'admin',
            'password' => 'complete123',
        ]);
        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($admin);

        // ==========================================
        // 2. ADMIN MANAGES TRAINING & QUESTIONS
        // ==========================================
        $training = Training::create([
            'title' => 'Training Automated Test',
            'trainer_id' => $admin->id,
            'description' => 'Testing description',
            'training_date' => now()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'zoom_link' => 'https://zoom.us/test',
            'status' => 'scheduled',
            'is_quiz_active' => false,
        ]);

        // Access questions management page
        $res = $this->actingAs($admin)->get("/training/{$training->id}/questions");
        $res->assertStatus(200);
        $res->assertSee('Kelola Soal Kuis Pelatihan');
        $res->assertSee('Import Teks Cepat');
        $res->assertSee('Import Excel / CSV');

        // Test Excel Template Download
        $resTemplate = $this->actingAs($admin)->get("/training/{$training->id}/questions/template");
        $resTemplate->assertStatus(200);

        // Test Copy-Paste Text Import with Multiple Types (A-D, A-B, A-F, Essay)
        $rawText = "1. Apa fungsi fitur modul verifikasi?
A. Mengesahkan dokumen
B. Menghapus dokumen
C. Mengarsipkan dokumen
D. Menolak dokumen
KUNCI: A
PENJELASAN: Modul verifikasi untuk mengesahkan dokumen.

2. Apakah absensi wajib diisi peserta setelah sesi zoom?
A. Ya, wajib
B. Tidak wajib
KUNCI: A

3. Pilihlah divisi yang relevan dengan operasional!
A. Opsional 1
B. Opsional 2
C. Opsional 3
D. Opsional 4
E. Opsional 5
F. Opsional 6
KUNCI: E

4. Jelaskan secara ringkas materi yang telah dipaparkan pada sesi zoom tadi!
TIPE: ESSAY
KUNCI: Pemaparan mengenai alur kerja sistem dan verifikasi kehadiran.";

        $importRes = $this->actingAs($admin)->post("/training/{$training->id}/questions/import-text", [
            'raw_text' => $rawText,
        ]);
        $importRes->assertSessionHas('success');

        // Verify imported questions in database
        $questions = TrainingQuestion::where('training_id', $training->id)->get();
        $this->assertCount(4, $questions);

        // Check Q1: Multiple choice A-D
        $q1 = $questions->firstWhere('correct_answer', 'a');
        $this->assertEquals('multiple_choice', $q1->type);
        $this->assertEquals('Mengesahkan dokumen', $q1->option_a);
        $this->assertEquals('Menghapus dokumen', $q1->option_b);

        // Check Q2: Multiple choice A-B (True/False)
        $q2 = $questions->where('type', 'multiple_choice')->first(function($q) {
            return empty($q->option_c) && !empty($q->option_b);
        });
        $this->assertNotNull($q2);
        $this->assertEquals('Ya, wajib', $q2->option_a);
        $this->assertNull($q2->option_c);

        // Check Q3: Multiple choice A-F
        $q3 = $questions->firstWhere('correct_answer', 'e');
        $this->assertNotNull($q3);
        $this->assertEquals('Opsional 5', $q3->option_e);
        $this->assertEquals('Opsional 6', $q3->option_f);

        // Check Q4: Essay
        $q4 = $questions->firstWhere('type', 'essay');
        $this->assertNotNull($q4);
        $this->assertNull($q4->option_a);
        $this->assertStringContainsString('Pemaparan mengenai alur kerja', $q4->correct_answer);

        // Test Manual Question Store (Manual Add)
        $manualRes = $this->actingAs($admin)->post("/training/{$training->id}/questions", [
            'type' => 'essay',
            'question' => 'Sebutkan 3 hal penting dalam materi?',
            'correct_answer' => 'Hal 1, Hal 2, Hal 3',
        ]);
        $manualRes->assertSessionHas('success');
        $this->assertDatabaseHas('training_questions', [
            'training_id' => $training->id,
            'question' => 'Sebutkan 3 hal penting dalam materi?',
            'type' => 'essay',
        ]);

        // Toggle Quiz Active so participant can take it
        $this->actingAs($admin)->post("/training/{$training->id}/toggle-quiz");
        $this->assertTrue($training->fresh()->is_quiz_active);

        // ==========================================
        // 3. PARTICIPANT PORTAL WORKFLOW
        // ==========================================
        $participantUser = User::where('id', '!=', $admin->id)->first() ?? User::factory()->create([
            'username' => 'peserta_test',
            'full_name' => 'Peserta Test',
            'job_level_id' => 2,
        ]);

        $token = 'test_token_' . uniqid();
        $participant = TrainingParticipant::create([
            'training_id' => $training->id,
            'user_id' => $participantUser->id,
            'token' => $token,
            'attendance_status' => 'pending',
        ]);

        // Access Portal page
        $portalRes = $this->get("/training/portal/{$token}");
        $portalRes->assertStatus(200);
        $portalRes->assertSee($training->title);

        // Submit attendance
        $attendRes = $this->post("/training/portal/{$token}/attendance", [
            'status' => 'hadir',
            'notes' => 'Hadir tepat waktu',
        ]);
        $attendRes->assertSessionHas('success');
        $this->assertEquals('hadir', $participant->fresh()->attendance_status);

        // Access Quiz Page
        $quizPageRes = $this->get("/training/portal/{$token}/quiz");
        $quizPageRes->assertStatus(200);
        $quizPageRes->assertSee('Kuis Evaluasi');
        // Check for textarea for essay
        $quizPageRes->assertSee('textarea');
        $quizPageRes->assertSee('Essay / Uraian');

        // Submit Quiz Answers
        $submittedAnswers = [
            $q1->id => 'a', // Correct
            $q2->id => 'a', // Correct
            $q3->id => 'e', // Correct
            $q4->id => 'Saya telah mempelajari alur verifikasi dokumen dan pentingnya absensi kehadiran melalui Zoom.', // Essay
        ];

        $submitRes = $this->post("/training/portal/{$token}/quiz", [
            'answers' => $submittedAnswers,
        ]);
        $submitRes->assertRedirect("/training/portal/{$token}/result");

        // Verify result in database
        $result = TrainingQuizResult::where('training_participant_id', $participant->id)->first();
        $this->assertNotNull($result);
        $this->assertEquals(100, $result->score); // All 3 MC questions were correct (3/3 = 100%)

        $savedAnswers = $result->answers;
        // Verify Essay answer is preserved exactly in JSON
        $this->assertEquals('essay', $savedAnswers[$q4->id]['type']);
        $this->assertStringContainsString('Saya telah mempelajari alur verifikasi', $savedAnswers[$q4->id]['user_answer']);

        // Access Result Page
        $resultPageRes = $this->get("/training/portal/{$token}/result");
        $resultPageRes->assertStatus(200);
        $resultPageRes->assertSee('Hasil Evaluasi Kuis');
        $resultPageRes->assertSee('100');
        $resultPageRes->assertSee('Saya telah mempelajari alur verifikasi');
        $resultPageRes->assertSee('Tersimpan');

        // Test Admin Grading Essay
        $gradeRes = $this->actingAs($admin)->post("/training/{$training->id}/grade-essay/{$result->id}", [
            'essay_score' => 90,
            'essay_feedback' => 'Penjelasan sangat komprehensif dan tepat.',
        ]);
        $gradeRes->assertSessionHas('success');

        $result->refresh();
        $this->assertEquals('graded', $result->essay_status);
        $this->assertEquals(90, $result->essay_score);
        $this->assertEquals('Penjelasan sangat komprehensif dan tepat.', $result->essay_feedback);
        // Proportional score check
        // Total questions = 5 (3 MC from text import, 1 Essay from text import, 1 Essay from manual store = 3 MC, 2 Essay)
        // MC Score: 100 * (3/5) = 60, Essay Score: 90 * (2/5) = 36 -> Total = 96
        $this->assertEquals(96, (int)$result->score);

        // Test Reset Participant Quiz
        $resetRes = $this->actingAs($admin)->post("/training/{$training->id}/reset-quiz/{$participant->id}");
        $resetRes->assertSessionHas('success');
        $this->assertNull(TrainingQuizResult::where('training_participant_id', $participant->id)->first());
    }

    /**
     * Test Excel Import Workflow
     */
    public function test_excel_import_workflow()
    {
        $admin = User::where('username', 'admin')->first();
        $training = Training::create([
            'title' => 'Training Excel Import Test',
            'trainer_id' => $admin->id,
            'description' => 'Testing excel import',
            'training_date' => now()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'zoom_link' => 'https://zoom.us/test',
            'status' => 'scheduled',
        ]);

        // Generate binary excel content from template
        $excelBinary = \Maatwebsite\Excel\Facades\Excel::raw(new \App\Exports\TrainingQuestionTemplate, \Maatwebsite\Excel\Excel::XLSX);
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('template.xlsx', $excelBinary);

        $res = $this->actingAs($admin)->post("/training/{$training->id}/questions/import-excel", [
            'file' => $file,
        ]);
        $res->assertSessionHas('success');

        // Check that sample questions in template (4 rows) are imported
        $questions = TrainingQuestion::where('training_id', $training->id)->get();
        $this->assertCount(4, $questions);
        $this->assertTrue($questions->contains('type', 'essay'));
        $this->assertTrue($questions->contains('type', 'multiple_choice'));
    }
}
