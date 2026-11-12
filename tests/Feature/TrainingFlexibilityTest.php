<?php

namespace Tests\Feature;

use App\Enums\TrainingStatus;
use App\Exports\TrainingRekapExport;
use App\Models\Training;
use App\Models\TrainingEvaluation;
use App\Models\TrainingEvaluationQuestion;
use App\Models\TrainingParticipant;
use App\Models\TrainingQuestion;
use App\Models\TrainingQuizResult;
use App\Models\User;
use App\Services\WhatsAppService;
use Tests\TestCase;

/**
 * Kebebasan mengatur pelatihan: soal khusus pretest/posttest (termasuk essay di pretest), mode kuis per fase, jam form
 * evaluasi tanpa posttest, presensi yang bisa dimatikan, dashboard yang menghitung pelatihan pretest saja, dan
 * pelatihan tatap muka tanpa fitur Zoom.
 */
class TrainingFlexibilityTest extends TestCase
{
    private const PORTAL = '/training/portal/token_bebas';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setTime(9, 0, 0));
        $this->admin = User::where('username', 'admin')->sole();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function training(array $attributes = []): Training
    {
        $training = Training::create([
            'title' => 'Pelatihan Bebas', 'trainer_id' => $this->admin->id, 'training_date' => now()->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:00', 'zoom_link' => 'https://zoom.us/test',
            'status' => TrainingStatus::Ongoing, 'setup_completed_at' => now(),
            ...$attributes,
        ]);
        TrainingParticipant::create([
            'training_id' => $training->id, 'user_id' => User::factory()->staff()->create(['nama_lengkap' => 'Budi Peserta'])->id,
            'token' => 'token_bebas', 'attendance_status' => 'pending',
        ]);

        return $training;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function question(Training $training, string $text, array $attributes = []): TrainingQuestion
    {
        return TrainingQuestion::create([
            'training_id' => $training->id, 'type' => 'multiple_choice', 'question' => $text,
            'option_a' => 'Benar', 'option_b' => 'Salah', 'correct_answer' => 'a',
            ...$attributes,
        ]);
    }

    private function participant(Training $training): TrainingParticipant
    {
        return $training->participants()->with(['training.evaluationQuestions', 'quizResult', 'pretestResult', 'evaluation', 'user'])->sole();
    }

    /**
     * @return array{answers: array<int, int|string>}
     */
    private function evaluationAnswers(Training $training): array
    {
        return ['answers' => $training->evaluationQuestions()->get()->mapWithKeys(fn ($question) => [$question->id => match ($question->type) {
            TrainingEvaluationQuestion::TYPE_SCALE => 4,
            TrainingEvaluationQuestion::TYPE_CHOICE => $question->options[0],
            default => 'Materinya jelas.',
        }])->all()];
    }

    /** Selisih jumlah tag pembuka dan penutup div di luar skrip. */
    private function divBalance(string $html): int
    {
        $html = preg_replace('/<script\b.*?<\/script>/s', '', $html);

        return preg_match_all('/<div\b/', $html) - preg_match_all('/<\/div>/', $html);
    }

    public function test_questions_can_belong_to_one_phase_only(): void
    {
        $training = $this->training(['has_pretest' => true, 'is_pretest_active' => true, 'is_quiz_active' => true]);
        $shared = $this->question($training, 'Soal untuk keduanya');
        $pretestOnly = $this->question($training, 'Soal khusus pretest', ['used_in' => 'pretest']);
        $posttestOnly = $this->question($training, 'Soal khusus posttest', ['used_in' => 'posttest']);

        $this->get(self::PORTAL.'/pretest')->assertOk()
            ->assertSee('Soal untuk keduanya')->assertSee('Soal khusus pretest')->assertDontSee('Soal khusus posttest');
        $this->get(self::PORTAL.'/quiz')->assertOk()
            ->assertSee('Soal untuk keduanya')->assertSee('Soal khusus posttest')->assertDontSee('Soal khusus pretest');

        // Nilai dihitung dari soal fase itu saja: 1 benar dari 2 soal pretest.
        $this->post(self::PORTAL.'/pretest', ['answers' => [$shared->id => 'a', $pretestOnly->id => 'b', $posttestOnly->id => 'a']])
            ->assertRedirect(self::PORTAL.'/pretest/result');
        $result = TrainingQuizResult::where('phase', 'pretest')->sole();
        $this->assertSame(2, $result->total_questions);
        $this->assertSame(50.0, $result->score);
        $this->assertArrayNotHasKey($posttestOnly->id, $result->answers);

        $this->actingAs($this->admin)->get(route('training.questions.index', $training))->assertOk()
            ->assertSee('Dipakai di')->assertSee('Pretest saja')->assertSee('Posttest saja');
    }

    public function test_trainer_sets_the_phase_of_a_question_from_the_form(): void
    {
        $training = $this->training(['has_pretest' => true]);
        $payload = ['type' => 'multiple_choice', 'question' => 'Soal baru', 'option_a' => 'Ya', 'option_b' => 'Tidak', 'correct_answer' => 'a'];

        $this->actingAs($this->admin)->post(route('training.questions.store', $training), [...$payload, 'used_in' => 'pretest'])->assertSessionHasNoErrors();
        $question = $training->questions()->sole();
        $this->assertSame('pretest', $question->used_in);

        $this->actingAs($this->admin)->put(route('training.questions.update', [$training, $question]), [...$payload, 'used_in' => 'both'])->assertSessionHasNoErrors();
        $this->assertSame('both', $question->fresh()->used_in);

        // Tanpa field (form pelatihan satu fase) pilihan lama dipertahankan; nilai asing ditolak.
        $question->update(['used_in' => 'posttest']);
        $this->actingAs($this->admin)->put(route('training.questions.update', [$training, $question]), $payload)->assertSessionHasNoErrors();
        $this->assertSame('posttest', $question->fresh()->used_in);
        $this->actingAs($this->admin)->put(route('training.questions.update', [$training, $question]), [...$payload, 'used_in' => 'lain'])->assertSessionHasErrors('used_in');

        // Soal tanpa pilihan (impor, AI): pilihan ganda di kedua fase, essay hanya di posttest.
        $this->assertSame('both', $this->question($training, 'Impor PG')->used_in);
        $this->assertSame('posttest', TrainingQuestion::create(['training_id' => $training->id, 'type' => 'essay', 'question' => 'Impor essay'])->used_in);

        // Pelatihan satu fase tidak menawarkan pilihan ini.
        $single = Training::create([...$training->only(['trainer_id', 'start_time', 'end_time', 'zoom_link']), 'title' => 'Satu fase', 'training_date' => now()->toDateString()]);
        $this->actingAs($this->admin)->get(route('training.questions.index', $single))->assertOk()->assertDontSee('Dipakai di');
    }

    public function test_pretest_cannot_open_without_a_question_of_its_own(): void
    {
        $training = $this->training(['has_pretest' => true]);
        $this->question($training, 'Hanya posttest', ['used_in' => 'posttest']);

        $this->actingAs($this->admin)->post(route('training.toggle-pretest', $training), ['open' => '1'])
            ->assertSessionHas('error', 'Pretest belum bisa dibuka: buat minimal 1 soal yang dipakai di pretest terlebih dahulu.');
        $this->actingAs($this->admin)->post(route('training.toggle-quiz', $training), ['open' => '1'])->assertSessionHas('success');
        $this->actingAs($this->admin)->get(route('training.show', $training))->assertOk()->assertSee('Belum ada soal untuk pretest');
    }

    public function test_essay_in_the_pretest_is_graded_by_the_trainer(): void
    {
        $training = $this->training(['has_pretest' => true, 'is_pretest_active' => true]);
        $choice = $this->question($training, 'Soal PG');
        $essay = TrainingQuestion::create(['training_id' => $training->id, 'type' => 'essay', 'question' => 'Jelaskan prosedur evakuasi.', 'used_in' => 'both']);
        // Soal khusus posttest tidak ikut bobot nilai pretest.
        $this->question($training, 'Khusus posttest 1', ['used_in' => 'posttest']);
        $this->question($training, 'Khusus posttest 2', ['used_in' => 'posttest']);

        $this->get(self::PORTAL.'/pretest')->assertOk()->assertSee('Jelaskan prosedur evakuasi.');
        $this->post(self::PORTAL.'/pretest', ['answers' => [$choice->id => 'a', $essay->id => 'Keluar lewat tangga darurat.']])
            ->assertRedirect(self::PORTAL.'/pretest/result');

        $result = TrainingQuizResult::where('phase', 'pretest')->sole();
        $this->assertSame('pending', $result->essay_status);
        $this->actingAs($this->admin)->get(route('training.show', $training))->assertOk()->assertSee('Essay pretest perlu dinilai');
        $this->actingAs($this->admin)->get(route('training.answer-sheet', [$training, $result]))->assertOk()
            ->assertSee('Penilaian Soal Essay oleh Pemateri')->assertSee('Keluar lewat tangga darurat.');

        // 1 PG (100) + 1 essay (50) dengan bobot sama = 75.
        $this->actingAs($this->admin)->post(route('training.grade-essay', [$training, $result]), ['essay_score' => 50])->assertSessionHas('success');
        $result->refresh();
        $this->assertSame('graded', $result->essay_status);
        $this->assertSame(75.0, $result->score);
    }

    public function test_training_with_one_phase_uses_all_of_its_questions(): void
    {
        $training = $this->training(['has_pretest' => true, 'has_posttest' => false, 'is_pretest_active' => true]);
        $this->question($training, 'Soal PG');
        // Essay bawaan ditandai posttest, tetapi pelatihan ini hanya punya pretest.
        TrainingQuestion::create(['training_id' => $training->id, 'type' => 'essay', 'question' => 'Soal essay']);

        $this->get(self::PORTAL.'/pretest')->assertOk()->assertSee('Soal PG')->assertSee('Soal essay');
    }

    public function test_each_phase_can_use_its_own_quiz_mode(): void
    {
        $training = $this->training(['has_pretest' => true, 'is_pretest_active' => true, 'is_quiz_active' => true]);
        $this->question($training, 'Soal');

        $this->actingAs($this->admin)->post(route('training.quiz-rules', $training), [
            'quiz' => ['pretest' => '1', 'pretest_mode' => 'game', 'posttest' => '1', 'posttest_mode' => 'formal'],
        ])->assertSessionHasNoErrors();
        $training->update(['is_pretest_active' => true, 'is_quiz_active' => true]);

        $training->refresh();
        $this->assertSame('game', $training->quizMode('pretest'));
        $this->assertSame('formal', $training->quizMode('posttest'));
        $this->get(self::PORTAL.'/pretest')->assertOk()->assertSee('Mode game');
        $this->get(self::PORTAL.'/quiz')->assertOk()->assertDontSee('Mode game')->assertSee('Kirim Jawaban');
        $this->actingAs($this->admin)->get(route('training.show', $training))->assertOk()->assertSee('mode game')->assertSee('mode formal');

        // Form lama mengirim satu mode untuk keduanya.
        $this->actingAs($this->admin)->post(route('training.quiz-rules', $training), ['quiz' => ['pretest' => '1', 'posttest' => '1', 'mode' => 'game']])
            ->assertSessionHasNoErrors();
        $training->refresh();
        $this->assertSame('game', $training->quizMode('pretest'));
        $this->assertSame('game', $training->quizMode('posttest'));

        $this->actingAs($this->admin)->post(route('training.quiz-rules', $training), ['quiz' => ['pretest' => '1', 'posttest' => '1', 'pretest_mode' => 'lain']])
            ->assertSessionHasErrors('quiz.pretest_mode');
    }

    public function test_evaluation_without_posttest_can_have_its_own_hours(): void
    {
        // Status masih Terjadwal: tanpa jam sendiri, form belum terbuka.
        $training = $this->training(['has_posttest' => false, 'has_evaluation' => true, 'status' => TrainingStatus::Scheduled]);
        $this->get(self::PORTAL.'/evaluation')->assertRedirect(self::PORTAL);

        // Dijadwalkan 09.50 sampai 10.00.
        $this->actingAs($this->admin)->post(route('training.evaluation-time', $training), [
            'open_mode' => 'clock', 'open_time' => '09:50', 'close_mode' => 'clock', 'close_time' => '10:00',
        ])->assertSessionHas('success', 'Form evaluasi DIJADWALKAN buka pukul 09:50 dan tutup otomatis pukul 10:00.');
        $this->get(self::PORTAL)->assertOk()->assertSee('Form evaluasi dibuka otomatis pukul 09:50 WIB.');
        $this->get(self::PORTAL.'/status')->assertJson(['is_evaluation_open' => false]);
        $this->get(self::PORTAL.'/evaluation')->assertRedirect(self::PORTAL)->assertSessionHas('warning', 'Form evaluasi dibuka otomatis pukul 09:50 WIB.');
        $this->actingAs($this->admin)->get(route('training.show', $training))->assertOk()
            ->assertSee('Terjadwal buka pukul 09:50')->assertSee('Ikuti status')->assertSee('Atur waktu');

        // 09.50: terbuka sendiri walau status belum Berlangsung.
        $this->travelTo(now()->setTime(9, 50, 0));
        $this->get(self::PORTAL.'/status')->assertJson(['is_evaluation_open' => true]);
        $this->get(self::PORTAL)->assertOk()->assertSee('Bisa diisi sampai pukul')->assertSee('Isi Form Evaluasi');
        $this->get(self::PORTAL.'/evaluation')->assertOk();
        $this->assertSame('Isi evaluasi', $this->participant($training)->nextStep()['label']);

        // 10.00: tertutup. Yang sedang mengisi masih diterima beberapa menit, setelah itu ditolak.
        $this->travelTo(now()->setTime(10, 0, 0));
        $this->get(self::PORTAL.'/status')->assertJson(['is_evaluation_open' => false]);
        $this->get(self::PORTAL)->assertOk()->assertSee('Waktu pengisian form evaluasi sudah habis.');
        $this->get(self::PORTAL.'/evaluation')->assertRedirect(self::PORTAL);

        $this->travelTo(now()->setTime(10, 6, 0));
        $this->post(self::PORTAL.'/evaluation', $this->evaluationAnswers($training))->assertRedirect(self::PORTAL)
            ->assertSessionHas('warning', 'Waktu pengisian form evaluasi sudah habis.');
        $this->assertSame(0, TrainingEvaluation::count());

        $this->travelTo(now()->setTime(10, 3, 0));
        $this->post(self::PORTAL.'/evaluation', $this->evaluationAnswers($training))->assertRedirect(self::PORTAL)->assertSessionHas('success');
        $this->assertSame(1, TrainingEvaluation::count());
    }

    public function test_evaluation_hours_can_open_now_and_go_back_to_following_the_status(): void
    {
        $training = $this->training(['has_posttest' => false, 'has_evaluation' => true, 'status' => TrainingStatus::Scheduled]);

        $this->actingAs($this->admin)->post(route('training.evaluation-time', $training), ['open_mode' => 'now', 'close_mode' => 'minutes', 'close_minutes' => '15'])
            ->assertSessionHas('success', 'Form evaluasi DIBUKA sekarang dan tutup otomatis pukul 09:15.');
        $this->assertTrue($training->fresh()->isEvaluationOpen());

        $this->actingAs($this->admin)->post(route('training.evaluation-time', $training), ['open_mode' => 'now', 'close_mode' => 'none'])->assertSessionHas('success');
        $this->travel(3)->hours();
        $this->assertTrue($training->fresh()->isEvaluationOpen());

        // "Ikuti status": kembali tertutup karena pelatihan masih Terjadwal.
        $this->actingAs($this->admin)->post(route('training.evaluation-time', $training), ['reset' => '1'])->assertSessionHas('success');
        $training->refresh();
        $this->assertNull($training->evaluation_opens_at);
        $this->assertFalse($training->isEvaluationOpen());
        $training->update(['status' => TrainingStatus::Ongoing]);
        $this->assertTrue($training->fresh()->isEvaluationOpen());

        // Dengan posttest, evaluasi menempel ke posttest dan jamnya tidak diatur terpisah.
        $training->update(['has_posttest' => true]);
        $this->actingAs($this->admin)->post(route('training.evaluation-time', $training), ['open_mode' => 'now', 'close_mode' => 'none'])->assertSessionHas('error');
        $this->actingAs(User::factory()->staff()->create())->from('/my-trainings')
            ->post(route('training.evaluation-time', $training), ['reset' => '1'])->assertSessionHas('error');
    }

    public function test_attendance_can_be_turned_off_for_the_whole_training(): void
    {
        $training = $this->training(['is_attendance_active' => true, 'has_posttest' => false]);
        $this->get(self::PORTAL)->assertOk()->assertSee('id="attendanceCard"', false);

        $this->actingAs($this->admin)->post(route('training.toggle-attendance-use', $training))->assertSessionHas('success');
        $training->refresh();
        $this->assertFalse($training->has_attendance);
        $this->assertFalse($training->is_attendance_active);

        // Pemateri: tidak ada kotak, kolom, langkah, atau aksi presensi.
        $this->actingAs($this->admin)->get(route('training.show', $training))->assertOk()
            ->assertSee('Presensi tidak dipakai di pelatihan ini')->assertSee('Pakai Presensi')->assertSee('tanpa presensi')
            ->assertDontSee('Kehadiran</p>', false)->assertDontSee('Bukti Foto')->assertDontSee('Atur Presensi Manual')
            ->assertDontSee('<th>Presensi</th>', false)->assertSee('<th>Kamera</th>', false);
        $this->actingAs($this->admin)->post(route('training.toggle-attendance', $training))->assertSessionHas('error');
        $this->assertFalse($training->fresh()->is_attendance_active);

        // Bagian yang disembunyikan tidak merusak susunan halaman, juga bersama form evaluasi berjadwal.
        $training->update(['has_evaluation' => true, 'evaluation_opens_at' => now()->addHour(), 'evaluation_closes_at' => now()->addHours(2)]);
        $this->assertSame(
            $this->divBalance($this->actingAs($this->admin)->get(route('training.index'))->getContent()),
            $this->divBalance($this->actingAs($this->admin)->get(route('training.show', $training))->assertOk()->getContent()),
        );
        $training->update(['has_evaluation' => false, 'evaluation_opens_at' => null, 'evaluation_closes_at' => null]);

        // Peserta: tidak diminta presensi di mana pun.
        $this->get(self::PORTAL)->assertOk()->assertDontSee('id="attendanceCard"', false);
        $this->get(self::PORTAL.'/status')->assertJson(['is_attendance_active' => false]);
        $this->post(self::PORTAL.'/attendance', ['status' => 'hadir'])->assertSessionHas('warning', 'Pelatihan ini tidak memakai presensi.');
        $this->assertSame('pending', $training->participants()->sole()->attendance_status);
        $this->assertSame('Ikuti sesi', $this->participant($training)->nextStep()['label']);
        $this->actingAs($training->participants()->sole()->user)->get(route('training.my-trainings'))->assertOk()->assertDontSee('Belum Konfirmasi');

        // Rekap dan undangan tidak menyebut presensi.
        $training->load(['trainer', 'participants.user.divisi', 'participants.quizResult', 'participants.pretestResult']);
        $rows = (new TrainingRekapExport($training))->array();
        $header = collect($rows)->search(fn ($row) => ($row[0] ?? null) === 'No');
        $this->assertSame('-', array_combine($rows[$header], $rows[$header + 1])['Status Kehadiran']);
        $this->assertStringContainsString('Untuk melihat rincian pelatihan, silakan', WhatsAppService::buildInvitationMessage($training, $this->participant($training)));

        // Dinyalakan lagi: presensi kembali seperti biasa.
        $this->actingAs($this->admin)->post(route('training.toggle-attendance-use', $training))->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('training.toggle-attendance', $training))->assertSessionHas('success');
        $this->post(self::PORTAL.'/attendance', ['status' => 'hadir'])->assertSessionHas('success');
    }

    public function test_setup_wizard_asks_whether_attendance_is_used(): void
    {
        $training = $this->training(['setup_completed_at' => null]);

        $this->actingAs($this->admin)->get(route('training.setup', $training))->assertOk()->assertSee('Pakai presensi kehadiran?');
        // "Tidak" membuat jawaban presensi lainnya tidak berlaku.
        $this->actingAs($this->admin)->post(route('training.setup.save', $training), [
            'has_attendance' => '0', 'is_attendance_active' => '1', 'require_attendance_proof' => '1',
        ])->assertRedirect(route('training.show', $training));

        $training->refresh();
        $this->assertFalse($training->has_attendance);
        $this->assertFalse($training->is_attendance_active);
        $this->assertFalse($training->require_attendance_proof);
        $this->assertNotNull($training->setup_completed_at);
    }

    public function test_dashboard_counts_the_score_of_a_pretest_only_training(): void
    {
        $training = $this->training(['has_pretest' => true, 'has_posttest' => false]);
        $both = Training::create([...$training->only(['trainer_id', 'start_time', 'end_time', 'zoom_link']), 'title' => 'Dua fase', 'training_date' => now()->toDateString(), 'has_pretest' => true]);
        $participant = $training->participants()->sole();
        $result = fn (Training $of, string $phase, float $score) => TrainingQuizResult::create([
            'training_id' => $of->id, 'user_id' => $participant->user_id, 'training_participant_id' => $participant->id,
            'phase' => $phase, 'total_questions' => 1, 'correct_answers' => 1, 'score' => $score, 'mc_score' => $score,
            'essay_status' => 'none', 'attempt_count' => 1, 'submitted_at' => now(), 'answers' => [],
        ]);

        $result($training, 'pretest', 90);   // pretest saja: ini nilai pelatihannya
        $result($both, 'pretest', 20);       // ada posttest: pretestnya tidak dihitung
        $result($both, 'posttest', 60);

        $data = $this->actingAs($this->admin)->get('/dashboard')->assertOk()->viewData('data');
        $this->assertSame(2, $data['totalQuizResult']);
        $this->assertSame(75.0, (float) $data['quizAvgScore']);
        $this->assertSame(2, $data['recentQuizResults']->count());
    }

    public function test_face_to_face_training_hides_zoom_only_tools(): void
    {
        $online = $this->training(['is_attendance_active' => true, 'require_attendance_proof' => true]);
        $this->actingAs($this->admin)->get(route('training.show', $online))->assertOk()
            ->assertSee('fa-video-slash"></i> Scan Off Cam', false)->assertSee('id="btnToggleManualOffCamMode"', false);
        $this->get(self::PORTAL)->assertOk()->assertSee('Unggah Screenshot Bukti Zoom / Pelatihan');

        $online->update(['zoom_link' => null]);
        $this->actingAs($this->admin)->get(route('training.show', $online))->assertOk()
            ->assertDontSee('fa-video-slash"></i> Scan Off Cam', false)->assertDontSee('id="btnToggleManualOffCamMode"', false)
            ->assertSee('Wajib upload foto kehadiran');
        $this->get(self::PORTAL)->assertOk()
            ->assertSee('Unggah Foto Bukti Kehadiran')->assertSee('Wajib Foto')->assertDontSee('Zoom');
        $this->post(self::PORTAL.'/attendance', ['status' => 'hadir'])
            ->assertSessionHasErrors(['attendance_proof' => 'Wajib mengunggah foto bukti kehadiran Anda di lokasi pelatihan.']);
    }
}
