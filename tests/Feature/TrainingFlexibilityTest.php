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

    public function test_ai_questions_must_be_saved_with_a_real_answer_key(): void
    {
        $training = $this->training();
        $question = ['type' => 'multiple_choice', 'question' => 'Siapa yang melakukan setting unit?', 'option_a' => 'Kasir', 'option_b' => 'Gadget Specialist'];
        $save = fn (array $q) => $this->actingAs($this->admin)->postJson(route('training.questions.save-ai-batch', $training), ['questions' => [$q]]);

        // Nilai yang bukan huruf pilihan (dulu "question" dari panel Ubah dengan AI), kunci kosong, dan huruf yang
        // pilihannya kosong semuanya ditolak.
        $save([...$question, 'correct_answer' => 'question'])->assertStatus(422)->assertJson(['success' => false]);
        $save([...$question, 'correct_answer' => ''])->assertStatus(422);
        $save([...$question, 'correct_answer' => 'c'])->assertStatus(422);
        $this->assertSame(0, $training->questions()->count());

        $save([...$question, 'correct_answer' => 'B'])->assertOk()->assertJson(['success' => true]);
        $this->assertSame('b', $training->questions()->sole()->correct_answer);

        // Essay tetap memakai teks pedoman sebagai kuncinya.
        $save(['type' => 'essay', 'question' => 'Jelaskan alurnya.', 'correct_answer' => 'Kasir memeriksa email.'])->assertOk();
        $this->assertSame(2, $training->questions()->count());
    }

    public function test_question_with_a_broken_answer_key_is_flagged_and_blocks_the_quiz(): void
    {
        $training = $this->training();
        $broken = $this->question($training, 'Soal kunci rusak', ['correct_answer' => 'question']);
        $this->question($training, 'Soal sehat');
        $this->assertFalse($broken->hasValidKey());

        $this->actingAs($this->admin)->get(route('training.questions.index', $training))->assertOk()
            ->assertSee('1 soal pilihan ganda tidak punya kunci jawaban yang valid.')->assertSee('Kunci jawaban tidak valid');
        $this->actingAs($this->admin)->get(route('training.show', $training))->assertOk()
            ->assertSee('1 soal pilihan ganda punya kunci jawaban yang tidak valid.');
        $this->actingAs($this->admin)->post(route('training.toggle-quiz', $training), ['open' => '1'])->assertSessionHas('error');
        $this->assertFalse($training->fresh()->is_quiz_active);

        // Setelah kuncinya diperbaiki lewat Edit, tanda hilang dan kuis bisa dibuka.
        $this->actingAs($this->admin)->put(route('training.questions.update', [$training, $broken]), [
            'type' => 'multiple_choice', 'question' => 'Soal kunci rusak', 'option_a' => 'Benar', 'option_b' => 'Salah', 'correct_answer' => 'b',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($broken->fresh()->hasValidKey());
        $this->actingAs($this->admin)->get(route('training.questions.index', $training))->assertOk()->assertDontSee('Kunci jawaban tidak valid');
        $this->actingAs($this->admin)->post(route('training.toggle-quiz', $training), ['open' => '1'])->assertSessionHas('success');
    }

    public function test_scores_are_recalculated_after_a_broken_key_is_fixed(): void
    {
        $training = $this->training(['has_pretest' => true]);
        $broken = $this->question($training, 'Soal kunci rusak', ['correct_answer' => 'question']);
        $healthy = $this->question($training, 'Soal sehat');
        $essay = TrainingQuestion::create(['training_id' => $training->id, 'type' => 'essay', 'question' => 'Jelaskan.', 'used_in' => 'both']);
        $participant = $training->participants()->sole();
        $answer = fn (string $given, string $key, bool $correct) => ['type' => 'multiple_choice', 'user_answer' => $given, 'correct_answer' => $key, 'is_correct' => $correct];
        $result = fn (string $phase, array $attributes) => TrainingQuizResult::create([
            'training_id' => $training->id, 'user_id' => $participant->user_id, 'training_participant_id' => $participant->id,
            'phase' => $phase, 'total_questions' => 3, 'attempt_count' => 1, 'submitted_at' => now(), ...$attributes,
        ]);

        // Peserta menjawab B pada soal rusak (dinilai salah karena kuncinya "question") dan benar pada soal sehat.
        $answers = [
            $broken->id => $answer('b', 'question', false),
            $healthy->id => $answer('a', 'a', true),
            $essay->id => ['type' => 'essay', 'user_answer' => 'Uraian.', 'correct_answer' => null, 'is_correct' => null],
        ];
        $posttest = $result('posttest', ['answers' => $answers, 'correct_answers' => 1, 'mc_score' => 50, 'score' => 50, 'essay_status' => 'pending']);
        // Essay pretest sudah dinilai 100: (50 x 2 PG + 100 x 1 essay) / 3.
        $pretest = $result('pretest', ['answers' => $answers, 'correct_answers' => 1, 'mc_score' => 50, 'essay_score' => 100, 'essay_status' => 'graded', 'score' => 66.67]);

        // Memperbaiki kunci belum mengubah nilai, tetapi pemateri diberi tahu harus menghitung ulang.
        $this->actingAs($this->admin)->put(route('training.questions.update', [$training, $broken]), [
            'type' => 'multiple_choice', 'question' => 'Soal kunci rusak', 'option_a' => 'Benar', 'option_b' => 'Salah', 'correct_answer' => 'b',
        ])->assertSessionHas('success', 'Soal kuis berhasil diperbarui! Kunci jawabannya berubah dan 2 hasil kuis sudah masuk: tekan Hitung Ulang Nilai agar nilai peserta mengikuti kunci baru.');
        $this->assertSame(50.0, $posttest->fresh()->score);
        $this->actingAs($this->admin)->get(route('training.questions.index', $training))->assertOk()->assertSee('Hitung Ulang Nilai');
        $this->actingAs($this->admin)->get(route('training.show', $training))->assertOk()
            ->assertSee('Hitung ulang nilai')->assertSee(route('training.regrade', $training), false);

        $this->actingAs(User::factory()->staff()->create())->from('/my-trainings')->post(route('training.regrade', $training))->assertSessionHas('error');
        $this->assertSame(50.0, $posttest->fresh()->score);

        $this->actingAs($this->admin)->post(route('training.regrade', $training))
            ->assertSessionHas('success', 'Nilai dihitung ulang memakai kunci jawaban saat ini: 2 dari 2 hasil kuis berubah.');

        // Posttest: 2 dari 2 PG benar; essay belum dinilai jadi nilainya nilai PG.
        $posttest->refresh();
        $this->assertSame(2, $posttest->correct_answers);
        $this->assertSame(100.0, $posttest->score);
        $this->assertSame('pending', $posttest->essay_status);
        $this->assertTrue($posttest->answers[$broken->id]['is_correct']);
        $this->assertSame('b', $posttest->answers[$broken->id]['correct_answer']);
        $this->assertSame('b', $posttest->answers[$broken->id]['user_answer']);
        // Pretest: nilai essay 100 tetap dipakai, jadi (100 x 2 + 100 x 1) / 3 = 100.
        $pretest->refresh();
        $this->assertSame(100.0, $pretest->score);
        $this->assertSame(100.0, (float) $pretest->essay_score);

        // Dijalankan lagi tanpa perubahan kunci: tidak ada yang berubah.
        $this->actingAs($this->admin)->post(route('training.regrade', $training))
            ->assertSessionHas('success', 'Nilai 2 hasil kuis sudah sesuai kunci jawaban saat ini; tidak ada yang berubah.');

        // Soal yang dihapus memakai penilaian lamanya.
        $healthy->delete();
        $this->actingAs($this->admin)->post(route('training.regrade', $training))->assertSessionHas('success');
        $this->assertSame(100.0, $posttest->fresh()->score);
    }

    public function test_recalculating_without_any_result_changes_nothing(): void
    {
        $training = $this->training();
        $this->question($training, 'Soal');

        $this->actingAs($this->admin)->get(route('training.questions.index', $training))->assertOk()->assertDontSee('Hitung Ulang Nilai');
        $this->actingAs($this->admin)->post(route('training.regrade', $training))
            ->assertSessionHas('success', 'Belum ada peserta yang mengerjakan kuis, jadi tidak ada nilai untuk dihitung ulang.');
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
        $this->actingAs($this->admin)->get(route('training.show', $training))->assertOk()
            ->assertSee('Pretest Budi Peserta: esai perlu dinilai')
            ->assertSeeInOrder(['participant-detail-label">Pretest</h6>', 'Esai perlu dinilai'], false);
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
        // Sebelum pemateri membuka atau menjadwalkannya, form tertutup.
        $training = $this->training(['has_posttest' => false, 'has_evaluation' => true, 'status' => TrainingStatus::Scheduled]);
        $this->get(self::PORTAL.'/evaluation')->assertRedirect(self::PORTAL);

        // Dijadwalkan 09.50 sampai 10.00.
        $this->actingAs($this->admin)->post(route('training.toggle-evaluation-access', $training), [
            'open' => '1', 'open_mode' => 'clock', 'open_time' => '09:50', 'close_mode' => 'clock', 'close_time' => '10:00',
        ])->assertSessionHas('success', 'Form evaluasi DIJADWALKAN buka pukul 09:50 dan tutup otomatis pukul 10:00.');
        $this->get(self::PORTAL)->assertOk()->assertSee('Form evaluasi dibuka otomatis pukul 09:50 WIB.');
        $this->get(self::PORTAL.'/status')->assertJson(['is_evaluation_open' => false]);
        $this->get(self::PORTAL.'/evaluation')->assertRedirect(self::PORTAL)->assertSessionHas('warning', 'Form evaluasi dibuka otomatis pukul 09:50 WIB.');
        $this->actingAs($this->admin)->get(route('training.show', $training))->assertOk()
            ->assertSee('Terjadwal buka pukul 09:50')->assertSee('Ubah jadwal')->assertSee('Batalkan jadwal');

        // 09.50: terbuka sendiri walau status belum Berlangsung.
        $this->travelTo(now()->setTime(9, 50, 0));
        $this->get(self::PORTAL.'/status')->assertJson(['is_evaluation_open' => true, 'evaluation_closes_at' => '10:00']);
        $this->get(self::PORTAL)->assertOk()->assertSee('data-deadline="evaluation" data-seconds-left="600"', false)->assertSee('Isi Form Evaluasi');
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

    public function test_evaluation_follows_the_trainer_not_the_training_status(): void
    {
        $training = $this->training(['has_posttest' => false, 'has_evaluation' => true, 'status' => TrainingStatus::Scheduled]);

        $this->actingAs($this->admin)->post(route('training.toggle-evaluation-access', $training), ['open' => '1', 'close_mode' => 'minutes', 'close_minutes' => '15'])
            ->assertSessionHas('success', 'Form evaluasi telah DIBUKA untuk seluruh peserta, tutup otomatis pukul 09:15.');
        $this->assertTrue($training->fresh()->isEvaluationOpen());

        $this->actingAs($this->admin)->post(route('training.quiz-deadline', $training), ['phase' => 'evaluation', 'close_mode' => 'none'])
            ->assertSessionHas('success', 'Form Evaluasi sekarang tanpa batas waktu; kunci secara manual bila sudah selesai.');
        $this->travel(3)->hours();
        $this->assertTrue($training->fresh()->isEvaluationOpen());

        // Dikunci: tertutup, dan status Berlangsung/Selesai tidak membukanya lagi.
        $this->actingAs($this->admin)->post(route('training.toggle-evaluation-access', $training), ['open' => '0'])->assertSessionHas('success');
        $training->refresh();
        $this->assertNull($training->evaluation_opens_at);
        $this->assertFalse($training->isEvaluationOpen());
        $training->update(['status' => TrainingStatus::Ongoing]);
        $this->assertFalse($training->fresh()->isEvaluationOpen());
        $training->update(['status' => TrainingStatus::Completed]);
        $this->assertFalse($training->fresh()->isEvaluationOpen());

        // Dengan posttest pun form evaluasi dibuka terpisah, supaya tidak memotong waktu posttest.
        $training->update(['has_posttest' => true]);
        $this->actingAs($this->admin)->post(route('training.toggle-evaluation-access', $training), ['open' => '1'])->assertSessionHas('success');
        $this->actingAs(User::factory()->staff()->create())->from('/my-trainings')
            ->post(route('training.toggle-evaluation-access', $training), ['open' => '0'])->assertSessionHas('error');
        $this->assertTrue($training->fresh()->isEvaluationOpen());
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
            ->assertSee('Presensi tidak dipakai di pelatihan ini')->assertSee('Pakai Presensi')
            ->assertDontSee('<dt>Kehadiran</dt>', false)->assertDontSee('Bukti Foto')->assertDontSee('Atur Presensi Manual')
            ->assertDontSee('<th scope="col">Presensi</th>', false)->assertSee('<th scope="col">Kamera</th>', false);
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
        $this->assertNotContains('Status Kehadiran', $rows[$header]);
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
        $both->participants()->create(['user_id' => $participant->user_id, 'token' => 'token_dua_fase']);
        // Satu hasil per peserta dan fase: tiap pelatihan memakai baris pesertanya sendiri.
        $result = fn (Training $of, string $phase, float $score) => TrainingQuizResult::create([
            'training_id' => $of->id, 'user_id' => $participant->user_id, 'training_participant_id' => $of->participants()->sole()->id,
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
