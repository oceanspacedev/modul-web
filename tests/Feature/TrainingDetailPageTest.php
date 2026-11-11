<?php

namespace Tests\Feature;

use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\TrainingQuizResult;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Halaman detail pelatihan hanya memuat satu halaman peserta, dan lembar jawaban dimuat saat dibuka, supaya
 * ukuran halaman tidak ikut membesar bersama jumlah peserta dan soal.
 */
class TrainingDetailPageTest extends TestCase
{
    private User $admin;

    private Training $training;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('username', 'admin')->sole();
        $this->training = Training::create([
            'title' => 'K3 Dasar',
            'trainer_id' => $this->admin->id,
            'training_date' => now()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'zoom_link' => 'https://zoom.us/test',
            'status' => 'ongoing',
        ]);
    }

    private function addParticipant(array $userAttributes = []): TrainingParticipant
    {
        $user = User::factory()->staff()->create($userAttributes);

        return TrainingParticipant::create([
            'training_id' => $this->training->id,
            'user_id' => $user->id,
            'token' => Str::random(40),
            'attendance_status' => 'hadir',
            'attended_at' => now(),
        ]);
    }

    private function addResult(TrainingParticipant $participant, Training $training, array $answers): TrainingQuizResult
    {
        return TrainingQuizResult::create([
            'training_id' => $training->id,
            'user_id' => $participant->user_id,
            'training_participant_id' => $participant->id,
            'total_questions' => 1,
            'correct_answers' => 1,
            'score' => 100,
            'answers' => $answers,
            'submitted_at' => now(),
        ]);
    }

    public function test_participants_are_paginated_while_counts_and_zoom_list_cover_everyone(): void
    {
        foreach (range(1, 55) as $number) {
            $this->addParticipant(['nama_lengkap' => sprintf('PESERTA %03d', $number)]);
        }

        $this->actingAs($this->admin)->get(route('training.show', $this->training))
            ->assertOk()
            ->assertViewHas('participants', fn ($participants) => $participants->count() === 50 && $participants->total() === 55)
            ->assertViewHas('zoomParticipants', fn ($list) => $list->count() === 55 && $list->last()['name'] === 'PESERTA 055')
            ->assertSee('(55 orang)')
            ->assertSee('Menampilkan 1-50 dari 55 peserta')
            ->assertSee('page=2#peserta', false);

        $this->actingAs($this->admin)->get(route('training.show', [$this->training, 'page' => 2]))
            ->assertOk()
            ->assertViewHas('participants', fn ($participants) => $participants->pluck('user.nama_lengkap')->all() === ['PESERTA 051', 'PESERTA 052', 'PESERTA 053', 'PESERTA 054', 'PESERTA 055'])
            ->assertSee('Menampilkan 51-55 dari 55 peserta');
    }

    public function test_participants_are_searched_on_the_server(): void
    {
        $this->addParticipant(['nama_lengkap' => 'BUDI SANTOSO', 'employee_id' => 'EMP-777']);
        $this->addParticipant(['nama_lengkap' => 'SITI AMINAH', 'no_hp' => '081299990000']);

        $names = fn (string $search) => $this->actingAs($this->admin)->get(route('training.show', [$this->training, 'peserta' => $search]))
            ->assertOk()
            ->viewData('participants')->pluck('user.nama_lengkap')->all();

        $this->assertSame(['BUDI SANTOSO'], $names('budi'));
        $this->assertSame(['BUDI SANTOSO'], $names('EMP-777'));
        $this->assertSame(['SITI AMINAH'], $names('08129999'));
        $this->assertSame(['BUDI SANTOSO', 'SITI AMINAH'], $names('Test Division'));
        $this->assertSame([], $names('tidak ada'));

        $this->actingAs($this->admin)->get(route('training.show', [$this->training, 'peserta' => 'tidak ada']))
            ->assertSee('Peserta tidak ditemukan')
            ->assertSee('(2 orang)');
    }

    public function test_answer_sheet_is_loaded_on_demand_instead_of_rendered_for_every_participant(): void
    {
        $question = $this->training->questions()->create([
            'type' => 'multiple_choice',
            'question' => 'APD apa yang wajib dipakai di area proyek?',
            'option_a' => 'Helm', 'option_b' => 'Topi',
            'correct_answer' => 'a',
            'explanation' => 'Helm melindungi kepala.',
        ]);
        $participant = $this->addParticipant(['nama_lengkap' => 'BUDI SANTOSO']);
        $result = $this->addResult($participant, $this->training, [$question->id => ['user_answer' => 'a', 'is_correct' => true]]);

        $this->actingAs($this->admin)->get(route('training.show', $this->training))
            ->assertOk()
            ->assertSee(route('training.answer-sheet', [$this->training, $result]), false)
            ->assertSee('id="answerSheetModal"', false)
            ->assertDontSee('Rincian Pertanyaan & Jawaban Peserta', false)
            ->assertDontSee('Helm melindungi kepala.');

        $this->actingAs($this->admin)->get(route('training.answer-sheet', [$this->training, $result]))
            ->assertOk()
            ->assertSee('Lembar Jawaban: BUDI SANTOSO')
            ->assertSee('APD apa yang wajib dipakai di area proyek?')
            ->assertSee('Helm melindungi kepala.')
            ->assertDontSee('Penilaian Soal Essay oleh Pemateri')
            ->assertDontSee('<html', false);
    }

    public function test_answer_sheet_offers_essay_grading_and_is_limited_to_its_training_and_managers(): void
    {
        $this->training->questions()->create(['type' => 'essay', 'question' => 'Jelaskan alur evakuasi!', 'correct_answer' => 'Ikuti jalur hijau.']);
        $participant = $this->addParticipant();
        $result = $this->addResult($participant, $this->training, [1 => ['user_answer' => 'Lewat tangga darurat']]);

        $this->actingAs($this->admin)->get(route('training.answer-sheet', [$this->training, $result]))
            ->assertOk()
            ->assertSee('Penilaian Soal Essay oleh Pemateri')
            ->assertSee("/training/{$this->training->id}/grade-essay/{$result->id}", false)
            ->assertSee('name="_token"', false);

        $other = Training::create([...$this->training->only(['trainer_id', 'start_time', 'end_time', 'zoom_link', 'status']), 'title' => 'Lain', 'training_date' => now()->toDateString()]);
        $this->actingAs($this->admin)->get(route('training.answer-sheet', [$other, $result]))->assertNotFound();

        $staff = User::factory()->staff()->create();
        // Sama seperti halaman detailnya: user tanpa izin kelola pelatihan dialihkan, tidak melihat lembar jawaban.
        $this->actingAs($staff)->get(route('training.answer-sheet', [$this->training, $result]))
            ->assertRedirect()
            ->assertDontSee('Lewat tangga darurat');
    }
}
