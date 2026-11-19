<?php

namespace Tests\Feature;

use App\Models\MeetingAccount;
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
            'meeting_link' => 'https://zoom.us/test',
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
            ->assertViewHas('participants', fn ($participants) => $participants->count() === 10 && $participants->total() === 55)
            ->assertViewHas('zoomParticipants', fn ($list) => $list->count() === 55 && $list->last()['name'] === 'PESERTA 055')
            ->assertSee('(55 orang)')
            ->assertSee('Menampilkan 1-10 dari 55 peserta')
            ->assertSeeInOrder(['<dt>Kehadiran</dt>', 'aria-label="Bagian pelatihan"', 'Tampilkan', 'per halaman'], false)
            ->assertSee('page=2#peserta', false);

        $this->actingAs($this->admin)->get(route('training.show', [$this->training, 'page' => 6]))
            ->assertOk()
            ->assertViewHas('participants', fn ($participants) => $participants->pluck('user.nama_lengkap')->all() === ['PESERTA 051', 'PESERTA 052', 'PESERTA 053', 'PESERTA 054', 'PESERTA 055'])
            ->assertSee('Menampilkan 51-55 dari 55 peserta');
    }

    public function test_trainer_picks_how_many_participants_a_page_shows(): void
    {
        foreach (range(1, 30) as $number) {
            $this->addParticipant(['nama_lengkap' => sprintf('PESERTA %03d', $number)]);
        }
        $page = fn (array $query) => $this->actingAs($this->admin)->get(route('training.show', [$this->training, ...$query]))->assertOk();

        // Pilihan jumlah per halaman dibawa ke tautan halaman, ke pencarian, dan ke tab lain.
        $page([])->assertSee('<option value="10" selected>10</option>', false)
            ->assertSee('<option value="20"', false)->assertSee('<option value="100"', false);
        $page(['per_halaman' => 20])
            ->assertViewHas('participants', fn ($participants) => $participants->count() === 20 && $participants->lastPage() === 2)
            ->assertSee('<option value="20" selected>20</option>', false)
            ->assertSee('per_halaman=20&amp;page=2#peserta', false)
            ->assertSee('<input type="hidden" name="per_halaman" value="20">', false)
            ->assertSee('bagian=kontrol-sesi', false);
        $page(['per_halaman' => 20, 'peserta' => 'PESERTA 01'])
            ->assertViewHas('participants', fn ($participants) => $participants->total() === 10)
            ->assertSee('Menampilkan 1-10 dari 10 peserta yang cocok')
            ->assertDontSee('page=2#peserta', false);
        $page(['per_halaman' => 50])->assertViewHas('participants', fn ($participants) => $participants->count() === 30 && ! $participants->hasPages());
        $page(['per_halaman' => 100])->assertViewHas('perPage', 100);

        // Nilai di luar pilihan kembali ke bawaan, dan bawaan tidak ditulis di tautan.
        $page(['per_halaman' => 1000])
            ->assertViewHas('perPage', 10)
            ->assertViewHas('participants', fn ($participants) => $participants->count() === 10)
            ->assertSee('?page=2#peserta', false)
            ->assertSee('href="'.route('training.show', [$this->training, 'bagian' => 'kontrol-sesi']).'"', false);
    }

    public function test_page_shows_only_what_a_training_without_quiz_or_attendance_uses(): void
    {
        $this->training->update(['meeting_link' => null, 'has_pretest' => false, 'has_posttest' => false, 'has_attendance' => false, 'has_evaluation' => false]);
        $this->addParticipant(['nama_lengkap' => 'BUDI SANTOSO']);

        $this->actingAs($this->admin)->get(route('training.show', $this->training))->assertOk()
            ->assertSee('Daftar Peserta')->assertDontSee('Daftar Peserta &amp; Nilai', false)
            ->assertDontSee('class="training-totals"', false)
            ->assertDontSee('<th scope="col">Presensi</th>', false)->assertDontSee('<th scope="col">Kamera</th>', false)
            ->assertDontSee('training-participant-score">', false)
            ->assertDontSee('data-training-average="', false)
            ->assertDontSee('Atur Presensi Manual')->assertDontSee('editAttendanceModal_', false)
            ->assertDontSee('Reset Kuis Peserta')->assertDontSee('dropdown-item answer-sheet-link', false)
            ->assertDontSee('Soal Kuis (')
            ->assertSee('Presensi tidak dipakai di pelatihan ini')->assertSee('Peserta tidak diminta mengisi evaluasi');
    }

    public function test_page_follows_a_training_with_every_step_turned_on(): void
    {
        $this->training->update([
            'meeting_link' => null, 'has_pretest' => true, 'has_posttest' => true, 'has_attendance' => true,
            'require_attendance_proof' => true, 'has_evaluation' => true,
            'portal_steps' => ['pretest', 'session', 'attendance', 'evaluation', 'posttest'],
        ]);
        $this->training->questions()->create(['type' => 'multiple_choice', 'question' => 'Soal?', 'option_a' => 'A', 'option_b' => 'B', 'correct_answer' => 'a', 'used_in' => 'both']);
        $this->addParticipant(['nama_lengkap' => 'BUDI SANTOSO']);

        $this->actingAs($this->admin)->get(route('training.show', $this->training))->assertOk()
            ->assertSeeInOrder(['<dt>Kehadiran</dt>', '<dt>Pretest</dt>', '<dt>Posttest</dt>', '<dt>Evaluasi</dt>'], false)
            // Sebelum ada yang mengerjakan, ringkasan tidak menampilkan rata-rata kosong.
            ->assertSee('Belum ada yang mengerjakan')->assertSee('Belum ada yang mengisi')
            ->assertDontSee('Rata-rata <b', false)
            ->assertSeeInOrder(['<th scope="col">Presensi</th>', '>Pretest</th>', '>Posttest</th>', '>Evaluasi</th>'], false)
            ->assertSee('Atur Presensi Manual')->assertSee('editAttendanceModal_', false)
            ->assertDontSee('tidak dipakai di pelatihan ini');
    }

    public function test_online_training_shows_the_camera_column_only_with_camera_data(): void
    {
        $account = MeetingAccount::create([
            'user_id' => $this->admin->id, 'provider' => 'zoom', 'name' => 'Zoom Kantor',
            'credentials' => ['client_id' => 'client', 'client_secret' => 'secret'],
        ]);
        $this->training->update([
            'meeting_link' => 'https://zoom.us/j/123456789', 'meeting_platform' => 'zoom',
            'meeting_monitoring_enabled' => true, 'meeting_account_id' => $account->id, 'has_attendance' => false,
        ]);
        $this->addParticipant(['nama_lengkap' => 'BUDI SANTOSO'])->update(['camera_status' => 'on_cam', 'camera_on_percent' => 90]);
        $this->addParticipant(['nama_lengkap' => 'SITI AMINAH'])->update(['camera_status' => 'off_cam']);
        $this->addParticipant(['nama_lengkap' => 'ANDI']);

        $this->actingAs($this->admin)->get(route('training.show', $this->training))->assertOk()
            ->assertSee('<th scope="col">Kamera</th>', false)->assertDontSee('<th scope="col">Presensi</th>', false)
            ->assertSee('On Cam (90% on cam)')
            ->assertSee('Rekap Kamera');

        // Tanpa pemantauan dan belum ada rekap, tidak ada kolom kamera yang isinya "—" semua.
        $this->training->update(['meeting_monitoring_enabled' => false]);
        $this->training->participants()->update(['camera_status' => null, 'camera_on_percent' => null]);
        $this->actingAs($this->admin)->get(route('training.show', $this->training))->assertOk()
            ->assertDontSee('<th scope="col">Kamera</th>', false)
            ->assertSee('Rekap Kamera');
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

        $other = Training::create([...$this->training->only(['trainer_id', 'start_time', 'end_time', 'meeting_link', 'status']), 'title' => 'Lain', 'training_date' => now()->toDateString()]);
        $this->actingAs($this->admin)->get(route('training.answer-sheet', [$other, $result]))->assertNotFound();

        $staff = User::factory()->staff()->create();
        // Sama seperti halaman detailnya: user tanpa izin kelola pelatihan dialihkan, tidak melihat lembar jawaban.
        $this->actingAs($staff)->get(route('training.answer-sheet', [$this->training, $result]))
            ->assertRedirect()
            ->assertDontSee('Lewat tangga darurat');
    }
}
