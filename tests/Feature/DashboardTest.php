<?php

namespace Tests\Feature;

use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\TrainingQuizResult;
use App\Models\User;
use Tests\TestCase;

/**
 * Angka di Dashboard harus cocok dengan yang terlihat di halaman lain: pelatihan yang sudah dihapus tidak boleh
 * masih menyumbang peserta atau nilai.
 */
class DashboardTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('username', 'admin')->sole();
    }

    private function trainingWithResult(string $title, User $user, float $score): Training
    {
        $training = Training::create([
            'title' => $title, 'trainer_id' => $this->admin->id, 'training_date' => now()->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:00',
        ]);
        $participant = TrainingParticipant::create(['training_id' => $training->id, 'user_id' => $user->id, 'token' => 'tok-'.$training->id]);
        TrainingQuizResult::create([
            'training_id' => $training->id, 'user_id' => $user->id, 'training_participant_id' => $participant->id,
            'phase' => Training::PHASE_POSTTEST, 'total_questions' => 10, 'correct_answers' => (int) ($score / 10), 'score' => $score,
            'submitted_at' => now(),
        ]);

        return $training;
    }

    public function test_deleted_trainings_do_not_count_toward_participants_or_scores(): void
    {
        $budi = User::factory()->staff()->create(['nama_lengkap' => 'Budi Peserta']);
        $sari = User::factory()->staff()->create(['nama_lengkap' => 'Sari Peserta']);

        $this->trainingWithResult('Pelatihan Aktif', $budi, 80);
        $this->trainingWithResult('Pelatihan Aktif Kedua', $budi, 90);
        $this->trainingWithResult('Pelatihan Dihapus', $sari, 0)->delete();

        $data = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()
            ->assertDontSee('Pelatihan Dihapus')->assertDontSee('Sari Peserta')
            ->viewData('data');

        $this->assertSame(2, $data['totalTraining']);
        // Budi terdaftar di dua pelatihan: dua pendaftaran, satu orang.
        $this->assertSame([2, 1], [$data['totalParticipant'], $data['uniqueParticipant']]);
        $this->assertSame(2, $data['totalQuizResult']);
        $this->assertSame([85.0, 90.0, 80.0], [$data['quizAvgScore'], $data['quizMaxScore'], $data['quizMinScore']]);
        $this->assertSame(['sangat_baik' => 1, 'baik' => 1, 'cukup' => 0, 'kurang' => 0], $data['scoreBuckets']);
    }
}
