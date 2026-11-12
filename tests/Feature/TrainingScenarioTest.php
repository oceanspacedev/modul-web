<?php

namespace Tests\Feature;

use App\Enums\TrainingStatus;
use App\Exports\TrainingRekapExport;
use App\Models\Training;
use App\Models\TrainingEvaluation;
use App\Models\TrainingParticipant;
use App\Models\TrainingQuestion;
use App\Models\TrainingQuizResult;
use App\Models\User;
use App\Services\WhatsAppService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pemateri menjalankan pelatihan dengan cara berbeda-beda: pretest saja, posttest saja, keduanya, hanya form
 * evaluasi, atau hanya sesi dan presensi. Setiap kombinasi harus bisa dijalankan dari awal sampai rekap tanpa
 * tampilan atau angka yang menganggap "kuis = posttest".
 */
class TrainingScenarioTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('username', 'admin')->sole();
    }

    private function training(bool $pretest, bool $posttest, bool $evaluation, string $token = 'token_skenario'): Training
    {
        $training = Training::create([
            'title' => 'Pelatihan Skenario', 'trainer_id' => $this->admin->id, 'training_date' => now()->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:00', 'zoom_link' => 'https://zoom.us/test',
            'status' => TrainingStatus::Ongoing, 'setup_completed_at' => now(),
            'has_pretest' => $pretest, 'has_posttest' => $posttest, 'has_evaluation' => $evaluation,
        ]);
        TrainingQuestion::create([
            'training_id' => $training->id, 'type' => 'multiple_choice', 'question' => 'APD utama?',
            'option_a' => 'Helm', 'option_b' => 'Topi', 'correct_answer' => 'a',
        ]);
        TrainingParticipant::create([
            'training_id' => $training->id, 'user_id' => User::factory()->staff()->create(['nama_lengkap' => 'Budi Peserta'])->id,
            'token' => $token, 'attendance_status' => 'pending',
        ]);

        return $training;
    }

    private function participant(Training $training): TrainingParticipant
    {
        return $training->participants()->with(['training.evaluationQuestions', 'quizResult', 'pretestResult', 'evaluation', 'user'])->sole();
    }

    private function quizResult(Training $training, string $phase, float $score): TrainingQuizResult
    {
        $participant = $training->participants()->sole();
        $question = $training->questions()->sole();

        return TrainingQuizResult::create([
            'training_id' => $training->id, 'user_id' => $participant->user_id, 'training_participant_id' => $participant->id,
            'phase' => $phase, 'total_questions' => 1, 'correct_answers' => $score > 0 ? 1 : 0, 'score' => $score, 'mc_score' => $score,
            'essay_status' => 'none', 'attempt_count' => 1, 'submitted_at' => now(),
            'answers' => [$question->id => ['type' => 'multiple_choice', 'user_answer' => 'a', 'correct_answer' => 'a', 'is_correct' => true]],
        ]);
    }

    private function fillEvaluation(Training $training): void
    {
        $participant = $training->participants()->sole();
        TrainingEvaluation::create([
            'training_id' => $training->id, 'training_participant_id' => $participant->id, 'user_id' => $participant->user_id,
            'answers' => $training->evaluationQuestions->mapWithKeys(fn ($question) => [$question->id => $question->type === 'scale' ? 4 : 'Bagus'])->all(),
            'submitted_at' => now(),
        ]);
    }

    /** Selisih jumlah tag pembuka dan penutup div di luar skrip. */
    private function divBalance(string $html): int
    {
        $html = preg_replace('/<script\b.*?<\/script>/s', '', $html);

        return preg_match_all('/<div\b/', $html) - preg_match_all('/<\/div>/', $html);
    }

    /** Baris data peserta pertama di sheet rekap, dipetakan ke judul kolomnya. */
    private function rekapRow(Training $training): array
    {
        $training->load(['trainer', 'participants.user.divisi', 'participants.quizResult', 'participants.pretestResult']);
        $rows = (new TrainingRekapExport($training))->array();
        $header = collect($rows)->search(fn ($row) => ($row[0] ?? null) === 'No');

        return array_combine($rows[$header], $rows[$header + 1]);
    }

    /**
     * @return array<string, array{bool, bool, bool}>
     */
    public static function scenarios(): array
    {
        return [
            'pretest saja' => [true, false, false],
            'posttest saja' => [false, true, false],
            'pretest dan posttest' => [true, true, false],
            'hanya evaluasi' => [false, false, true],
            'hanya sesi' => [false, false, false],
            'pretest dan evaluasi' => [true, false, true],
            'posttest dan evaluasi' => [false, true, true],
            'semuanya' => [true, true, true],
        ];
    }

    #[DataProvider('scenarios')]
    public function test_every_combination_runs_on_every_page(bool $pretest, bool $posttest, bool $evaluation): void
    {
        $training = $this->training($pretest, $posttest, $evaluation);

        // Pemateri: halaman detail hanya menampilkan apa yang dipakai pelatihan ini.
        $detail = $this->actingAs($this->admin)->get(route('training.show', $training))->assertOk();
        $pretest ? $detail->assertSee('Akses Pretest') : $detail->assertDontSee('Akses Pretest');
        $posttest ? $detail->assertSee($pretest ? 'Akses Posttest' : 'Akses Kuis') : $detail->assertDontSee('Akses Posttest')->assertDontSee('Akses Kuis');
        if ($pretest || $posttest) {
            $detail->assertSee('Soal Kuis (1)')->assertSee('Daftar Peserta &amp; Nilai', false)->assertSee('Rata-rata nilai');
        } else {
            $detail->assertDontSee('Soal Kuis (')->assertDontSee('Kuis selesai')->assertDontSee('Rata-rata nilai')->assertDontSee('Belum mengerjakan')->assertSee('Tanpa kuis');
        }
        if (! $pretest && ! $posttest && $evaluation) {
            $detail->assertSee('Evaluasi terisi')->assertSee('Rata-rata evaluasi')->assertSee('Belum mengisi');
        }

        // Bagian yang disembunyikan tidak boleh meninggalkan tag penutup yang merusak susunan halaman: selisih tag div
        // halaman detail harus sama dengan halaman lain yang memakai layout yang sama.
        $this->assertSame(
            $this->divBalance($this->actingAs($this->admin)->get(route('training.index'))->getContent()),
            $this->divBalance($detail->getContent()),
            'Tag div halaman detail tidak seimbang.',
        );

        // Peserta: portal dan daftar pelatihan terbuka, hanya dengan langkah yang ada.
        $portal = $this->get('/training/portal/token_skenario')->assertOk();
        $pretest ? $portal->assertSee('id="pretestCard"', false) : $portal->assertDontSee('id="pretestCard"', false);
        $posttest ? $portal->assertSee('id="quizCard"', false) : $portal->assertDontSee('id="quizCard"', false);
        $evaluation ? $portal->assertSee('id="evaluationCard"', false) : $portal->assertDontSee('id="evaluationCard"', false);
        $this->get('/training/portal/token_skenario/status')->assertOk();
        $this->actingAs($training->participants()->sole()->user)->get(route('training.my-trainings'))->assertOk()->assertSee('Pelatihan Skenario');

        // Fase yang tidak diadakan tidak bisa dibuka pemateri maupun peserta.
        if (! $pretest) {
            $this->actingAs($this->admin)->post(route('training.toggle-pretest', $training), ['open' => '1'])->assertSessionHas('error');
            $this->get('/training/portal/token_skenario/pretest')->assertRedirect('/training/portal/token_skenario');
        }
        if (! $posttest) {
            $this->actingAs($this->admin)->post(route('training.toggle-quiz', $training), ['open' => '1'])->assertSessionHas('error');
            $this->get('/training/portal/token_skenario/quiz')->assertRedirect('/training/portal/token_skenario');
        }

        // Rekap: kolom fase yang tidak diadakan berisi "-", bukan "Belum Mengerjakan".
        $row = $this->rekapRow($training);
        $this->assertSame($posttest ? 'Belum Mengerjakan' : '-', $row['Status Kuis']);
        // Belum mengerjakan tidak ditulis 0, supaya tidak tertukar dengan peserta yang nilainya memang 0.
        $this->assertSame('-', $row['Nilai Akhir (0-100)']);
        $this->assertSame($pretest ? 'Belum Mengerjakan' : '-', $row['Nilai Pretest (0-100)']);
        $this->actingAs($this->admin)->post(route('training.export', $training))->assertSessionHasNoErrors();
    }

    public function test_pretest_only_training_treats_the_pretest_as_its_score(): void
    {
        $training = $this->training(pretest: true, posttest: false, evaluation: false);
        $this->actingAs($this->admin)->post(route('training.toggle-pretest', $training), ['open' => '1', 'close_mode' => 'none'])->assertSessionHas('success');

        $this->assertSame('Kerjakan pretest', $this->participant($training)->nextStep()['label']);
        $this->post('/training/portal/token_skenario/pretest', ['answers' => [$training->questions()->sole()->id => 'a']])
            ->assertRedirect('/training/portal/token_skenario/pretest/result');
        $result = TrainingQuizResult::where('phase', 'pretest')->sole();

        // Ringkasan dan tabel peserta memakai nilai pretest, bukan posttest yang tidak ada.
        $this->actingAs($this->admin)->getJson(route('training.live-stats', $training))->assertJson(['quizSubmitted' => 1, 'avgScore' => 100]);
        $this->actingAs($this->admin)->get(route('training.show', $training))->assertOk()
            ->assertSee('Pretest selesai')->assertSee('1 PG benar')->assertDontSee('Belum mengerjakan')->assertDontSee('Kuis selesai')
            ->assertSee(route('training.answer-sheet', [$training, $result]), false)->assertSee('Reset Kuis Peserta');
        $this->actingAs($this->admin)->get(route('training.answer-sheet', [$training, $result]))->assertOk()->assertSee('Lembar Jawaban: Budi Peserta');

        $this->assertSame(['label' => 'Selesai', 'kind' => 'done'], $this->participant($training)->nextStep());
        $this->actingAs($training->participants()->sole()->user)->get(route('training.my-trainings'))->assertOk()->assertSee('Selesai')->assertSee('100');
        $this->assertSame(100.0, $this->rekapRow($training)['Nilai Pretest (0-100)']);
        $this->assertSame('-', $this->rekapRow($training)['Status Kuis']);
    }

    public function test_training_with_both_phases_shows_the_pretest_next_to_the_posttest(): void
    {
        $training = $this->training(pretest: true, posttest: true, evaluation: false);
        $pretest = $this->quizResult($training, 'pretest', 0);
        $posttest = $this->quizResult($training, 'posttest', 100);

        $this->actingAs($this->admin)->getJson(route('training.live-stats', $training))
            ->assertJson(['quizSubmitted' => 1, 'avgScore' => 100, 'pretestSubmitted' => 1, 'pretestAvg' => 0]);
        $this->actingAs($this->admin)->get(route('training.show', $training))->assertOk()
            ->assertSee('Posttest selesai')->assertSee('Rata-rata nilai posttest')->assertSee('Pretest: 0')
            ->assertSee('Lembar Jawaban Pretest')->assertSee(route('training.answer-sheet', [$training, $pretest]), false);

        // Lembar jawaban tersedia untuk kedua fase, dan tidak untuk hasil pelatihan lain.
        $this->actingAs($this->admin)->get(route('training.answer-sheet', [$training, $pretest]))->assertOk()->assertSee('Lembar Jawaban Pretest: Budi Peserta');
        $this->actingAs($this->admin)->get(route('training.answer-sheet', [$training, $posttest]))->assertOk()->assertSee('Lembar Jawaban Posttest: Budi Peserta');
        $other = $this->training(pretest: true, posttest: true, evaluation: false, token: 'token_lain');
        $this->actingAs($this->admin)->get(route('training.answer-sheet', [$other, $pretest]))->assertNotFound();

        $row = $this->rekapRow($training);
        $this->assertSame(100.0, $row['Nilai Akhir (0-100)']);
        $this->assertSame(0.0, $row['Nilai Pretest (0-100)']);
    }

    public function test_evaluation_only_training_guides_the_participant_to_the_form(): void
    {
        $training = $this->training(pretest: false, posttest: false, evaluation: true);

        $this->assertSame(['label' => 'Isi evaluasi', 'kind' => 'action'], $this->participant($training)->nextStep());
        $this->get('/training/portal/token_skenario')->assertOk()->assertSee('Isi Form Evaluasi');
        $this->get('/training/portal/token_skenario/evaluation')->assertOk();

        $this->fillEvaluation($training);
        $this->assertSame(['label' => 'Selesai', 'kind' => 'done'], $this->participant($training)->nextStep());
        $this->actingAs($this->admin)->get(route('training.show', $training))->assertOk()
            ->assertSee('Evaluasi terisi')->assertSee('Terisi')->assertDontSee('Belum mengisi');
    }

    public function test_session_only_training_needs_nothing_but_attendance(): void
    {
        $training = $this->training(pretest: false, posttest: false, evaluation: false);
        $training->update(['is_attendance_active' => true]);

        $this->assertSame(['label' => 'Isi presensi', 'kind' => 'action'], $this->participant($training)->nextStep());
        $this->post('/training/portal/token_skenario/attendance', ['status' => 'hadir'])->assertSessionHas('success');
        $this->assertSame(['label' => 'Selesai', 'kind' => 'done'], $this->participant($training)->nextStep());

        $this->actingAs($this->admin)->get(route('training.show', $training))->assertOk()->assertSee('Hadir')->assertSee('Daftar Peserta')->assertDontSee('Nilai</th>', false);
    }

    public function test_posttest_with_evaluation_tells_the_participant_to_fill_the_form_first(): void
    {
        $training = $this->training(pretest: false, posttest: true, evaluation: true);
        // Sesi berlangsung: evaluasi sudah bisa diisi sebelum posttest dibuka, jadi tidak memotong waktunya.
        $this->assertSame(['label' => 'Isi evaluasi', 'kind' => 'action'], $this->participant($training)->nextStep());

        $this->actingAs($this->admin)->post(route('training.toggle-quiz', $training), ['open' => '1', 'close_mode' => 'none'])->assertSessionHas('success');
        $this->assertSame(['label' => 'Isi evaluasi, lalu kerjakan kuis', 'kind' => 'action'], $this->participant($training)->nextStep());

        $this->fillEvaluation($training);
        $this->assertSame('Kerjakan kuis', $this->participant($training)->nextStep()['label']);

        $this->quizResult($training, 'posttest', 100);
        $this->assertSame(['label' => 'Selesai', 'kind' => 'done'], $this->participant($training)->nextStep());
    }

    public function test_face_to_face_training_needs_no_meeting_link(): void
    {
        $this->actingAs($this->admin)->get(route('training.create'))->assertOk()->assertSee('Kosongkan untuk pelatihan tatap muka.');

        $this->actingAs($this->admin)->post('/training', [
            'title' => 'Pelatihan Tatap Muka', 'trainer_id' => $this->admin->id, 'training_date' => now()->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:00', 'zoom_link' => '',
            'participants' => [User::factory()->staff()->create()->id],
            'quiz' => ['pretest' => '0', 'posttest' => '0'],
        ])->assertSessionHasNoErrors();

        $training = Training::where('title', 'Pelatihan Tatap Muka')->sole();
        $this->assertNull($training->zoom_link);

        // Tidak ada tombol atau baris tautan kosong di halaman pemateri, portal peserta, dan undangan WhatsApp.
        $this->actingAs($this->admin)->get(route('training.show', $training))->assertOk()
            ->assertSee('Tatap muka (tanpa tautan)')->assertDontSee('Online via Zoom')->assertDontSee('Buka Tautan Zoom');
        $this->actingAs($this->admin)->get(route('training.index'))->assertOk()->assertSee('Pelatihan Tatap Muka');
        $this->actingAs($this->admin)->get(route('training.edit', $training))->assertOk();

        $participant = $this->participant($training);
        $this->get("/training/portal/{$participant->token}")->assertOk()
            ->assertSee('diadakan tatap muka')->assertDontSee('Buka Tautan Zoom');
        $message = WhatsAppService::buildInvitationMessage($training, $participant);
        $this->assertStringNotContainsString('Link Zoom/Meeting', $message);
        $this->assertStringContainsString("WIB\n\nUntuk melakukan konfirmasi kehadiran, silakan", $message);

        // Tautan tetap bisa ditambahkan atau dihapus lewat edit.
        $payload = [
            'title' => $training->title, 'trainer_id' => $this->admin->id, 'training_date' => now()->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:00', 'status' => $training->status->value,
            'participants' => [$participant->user_id],
        ];
        $this->actingAs($this->admin)->put(route('training.update', $training), [...$payload, 'zoom_link' => 'https://zoom.us/j/123'])->assertSessionHasNoErrors();
        $this->assertSame('https://zoom.us/j/123', $training->fresh()->zoom_link);
        $this->get("/training/portal/{$participant->token}")->assertOk()->assertSee('Buka Tautan Zoom');
    }

    public function test_invitation_mentions_only_what_the_training_has(): void
    {
        $message = fn (bool $pretest, bool $posttest, bool $evaluation, string $token) => WhatsAppService::buildInvitationMessage(
            $training = $this->training($pretest, $posttest, $evaluation, $token), $this->participant($training),
        );

        $this->assertStringContainsString('Untuk melakukan konfirmasi kehadiran dan pengerjaan kuis evaluasi, silakan', $message(false, true, false, 'wa_1'));
        $this->assertStringContainsString('Untuk melakukan konfirmasi kehadiran dan pengerjaan pretest, silakan', $message(true, false, false, 'wa_2'));
        $this->assertStringContainsString('konfirmasi kehadiran, pengerjaan pretest dan posttest dan pengisian form evaluasi, silakan', $message(true, true, true, 'wa_3'));
        $this->assertStringContainsString('Untuk melakukan konfirmasi kehadiran dan pengisian form evaluasi, silakan', $message(false, false, true, 'wa_4'));
        $this->assertStringContainsString('Untuk melakukan konfirmasi kehadiran, silakan', $message(false, false, false, 'wa_5'));
    }
}
