<?php

namespace Tests\Feature;

use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\TrainingQuestion;
use App\Models\TrainingQuizResult;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Tests\TestCase;

/**
 * Portal peserta tetap benar saat banyak peserta memakainya bersamaan: kiriman ganda tidak membuat hasil ganda,
 * polling status tidak menyentuh sesi, dan unggahan yang terlalu besar kembali ke portal dengan pesan.
 */
class TrainingPortalStabilityTest extends TestCase
{
    private const PORTAL = '/training/portal/token_stabil';

    private Training $training;

    private TrainingParticipant $participant;

    private TrainingQuestion $question;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::where('username', 'admin')->sole();
        $staff = User::factory()->staff()->create(['nama_lengkap' => 'Dewi Peserta']);
        $this->training = Training::create([
            'title' => 'Uji Stabilitas Portal', 'trainer_id' => $admin->id, 'training_date' => now()->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:30', 'zoom_link' => 'https://zoom.us/test', 'setup_completed_at' => now(),
            'is_quiz_active' => true, 'is_attendance_active' => true,
        ]);
        $this->question = TrainingQuestion::create([
            'training_id' => $this->training->id, 'type' => 'multiple_choice', 'question' => 'APD utama?',
            'option_a' => 'Helm', 'option_b' => 'Topi', 'correct_answer' => 'a',
        ]);
        $this->participant = TrainingParticipant::create([
            'training_id' => $this->training->id, 'user_id' => $staff->id, 'token' => 'token_stabil', 'attendance_status' => 'pending',
        ]);
    }

    private function submit(string $answer, ?int $attemptNumber)
    {
        return $this->post(self::PORTAL.'/quiz', array_filter([
            'answers' => [$this->question->id => $answer],
            'attempt_number' => $attemptNumber,
        ]));
    }

    public function test_quiz_page_carries_the_attempt_number(): void
    {
        $this->get(self::PORTAL.'/quiz')->assertOk()->assertSee('name="attempt_number" value="1"', false);
    }

    public function test_a_repeated_submission_of_the_same_attempt_is_recorded_once(): void
    {
        $this->training->update(['quiz_max_attempts' => 3]);

        $this->submit('a', 1)->assertRedirect(self::PORTAL.'/result');
        // Klik kedua dari halaman yang sama: jawaban berbeda sekalipun tidak mengubah apa pun.
        $this->submit('b', 1)->assertRedirect(self::PORTAL.'/result')->assertSessionHas('success');

        $result = TrainingQuizResult::where('training_participant_id', $this->participant->id)->sole();
        $this->assertSame(1, $result->attempt_count);
        $this->assertSame(100.0, $result->score);
    }

    public function test_a_repeated_submission_on_a_single_attempt_quiz_is_not_an_error(): void
    {
        $this->submit('a', 1);

        $this->submit('a', 1)->assertRedirect(self::PORTAL.'/result')->assertSessionHas('success')->assertSessionMissing('warning');
        $this->postJson(self::PORTAL.'/quiz', ['answers' => [$this->question->id => 'a'], 'attempt_number' => 1])
            ->assertOk()->assertJson(['success' => true, 'score' => 100.0]);

        $this->assertSame(1, TrainingQuizResult::count());
    }

    public function test_a_real_retake_still_counts_as_a_new_attempt(): void
    {
        $this->training->update(['quiz_max_attempts' => 2]);

        $this->submit('b', 1);
        $this->get(self::PORTAL.'/quiz')->assertOk()->assertSee('name="attempt_number" value="2"', false);
        $this->submit('a', 2);

        $result = TrainingQuizResult::sole();
        $this->assertSame(2, $result->attempt_count);
        $this->assertSame(100.0, $result->score);

        // Jatah habis: kiriman percobaan ketiga ditolak seperti sebelumnya.
        $this->submit('a', 3)->assertRedirect(self::PORTAL.'/result')->assertSessionHas('warning');
        $this->assertSame(2, $result->fresh()->attempt_count);
    }

    public function test_the_database_allows_one_result_per_participant_and_phase(): void
    {
        $row = [
            'training_id' => $this->training->id, 'user_id' => $this->participant->user_id,
            'training_participant_id' => $this->participant->id, 'total_questions' => 1, 'attempt_count' => 1,
        ];
        TrainingQuizResult::create([...$row, 'phase' => Training::PHASE_POSTTEST]);
        TrainingQuizResult::create([...$row, 'phase' => Training::PHASE_PRETEST]);

        $this->expectException(UniqueConstraintViolationException::class);
        TrainingQuizResult::create([...$row, 'phase' => Training::PHASE_POSTTEST]);
    }

    public function test_status_polling_does_not_start_a_session(): void
    {
        $response = $this->get(self::PORTAL.'/status')->assertOk()->assertJson(['is_quiz_active' => true]);

        $this->assertSame([], $response->headers->getCookies());
        // Halaman portalnya sendiri tetap memakai sesi (pesan sukses/peringatan).
        $this->assertNotSame([], $this->get(self::PORTAL)->assertOk()->headers->getCookies());
    }

    public function test_an_upload_over_the_server_limit_returns_to_the_portal_with_a_message(): void
    {
        $this->withServerVariables(['CONTENT_LENGTH' => 1024 ** 3])
            ->post(self::PORTAL.'/attendance', ['status' => 'hadir'])
            ->assertRedirect(self::PORTAL.'?unggahan=terlalu-besar');

        $this->withServerVariables([])->get(self::PORTAL.'?unggahan=terlalu-besar')->assertOk()->assertSee('ukuran fotonya terlalu besar');
    }
}
