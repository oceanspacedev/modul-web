<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\LearningModule;
use App\Models\LearningModuleAttempt;
use App\Models\User;
use App\Models\Video;
use App\Services\MaterialQuizService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Mode Quizizz pada pretest/posttest: satu soal per layar, jawaban dikunci per soal di server, kunci tidak dikirim.
 */
class LearningModuleGameModeTest extends TestCase
{
    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('username', 'admin')->sole();
        $this->staff = User::factory()->staff()->create();
    }

    /**
     * @return array{0: Video, 1: LearningModule}
     */
    private function gameVideo(array $settings = []): array
    {
        $video = Video::create(['title' => 'K3 Gudang', 'video_type' => 'link', 'video_link' => 'https://youtu.be/dQw4w9WgXcQ']);
        $service = app(MaterialQuizService::class);
        $quiz = $service->sync($video, ['pretest' => true, 'posttest' => true, 'pretest_max_attempts' => 1, 'mode' => 'game', 'question_seconds' => 20, ...$settings]);
        $quiz->questions()->create(['question' => 'APD utama?', 'option_a' => 'Helm keselamatan', 'option_b' => 'Topi kain', 'correct_answer' => 'a']);
        $quiz->questions()->create(['question' => 'Jalur evakuasi harus?', 'option_a' => 'Terkunci', 'option_b' => 'Bebas hambatan', 'correct_answer' => 'b']);
        $service->refresh($quiz);

        return [$video, $quiz->fresh()];
    }

    private function questionId(LearningModule $quiz, string $text): int
    {
        return $quiz->questions()->where('question', $text)->value('id');
    }

    public function test_video_form_saves_quiz_mode_and_seconds(): void
    {
        $this->actingAs($this->admin)->post(route('video.store'), [
            'title' => 'Video SOP',
            'video_type' => 'link',
            'video_link' => 'https://youtu.be/dQw4w9WgXcQ',
            'quiz' => ['pretest' => '1', 'posttest' => '1', 'mode' => 'game', 'question_seconds' => '15'],
        ])->assertSessionHasNoErrors();

        $quiz = LearningModule::sole();
        $this->assertTrue($quiz->isGameMode());
        $this->assertSame(15, $quiz->question_seconds);

        $this->actingAs($this->admin)->get(route('video.edit', Video::sole()))
            ->assertOk()->assertSee('Game (Quizizz)')->assertSee('value="15"', false);
    }

    public function test_quiz_mode_settings_are_validated(): void
    {
        $this->actingAs($this->admin)->post(route('video.store'), [
            'title' => 'Video SOP',
            'video_type' => 'link',
            'video_link' => 'https://youtu.be/dQw4w9WgXcQ',
            'quiz' => ['pretest' => '1', 'mode' => 'arcade', 'question_seconds' => '2'],
        ])->assertSessionHasErrors(['quiz.mode', 'quiz.question_seconds']);

        $this->assertSame(0, LearningModule::count());
    }

    public function test_document_form_saves_quiz_mode(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->post(route('document.store'), [
            'file' => UploadedFile::fake()->create('sop-gudang.pdf', 10, 'application/pdf'),
            'document_type' => 1,
            'divisi_id' => 1,
            'role_id' => [0],
            'quiz' => ['pretest' => '1', 'posttest' => '0', 'mode' => 'game', 'question_seconds' => '45'],
        ])->assertSessionHasNoErrors();

        $quiz = Document::sole()->quizModule;
        $this->assertTrue($quiz->isGameMode());
        $this->assertSame(45, $quiz->question_seconds);
    }

    public function test_learning_module_form_saves_quiz_mode(): void
    {
        $this->actingAs($this->admin)->post(route('learning-module.store'), [
            'title' => 'K3 Dasar',
            'passing_score' => 75,
            'quiz_mode' => 'game',
            'question_seconds' => 25,
        ])->assertSessionHasNoErrors();

        $module = LearningModule::sole();
        $this->assertTrue($module->isGameMode());
        $this->assertSame(25, $module->question_seconds);

        $this->actingAs($this->admin)->get(route('learning-module.edit', $module))->assertOk()->assertSee('Game (Quizizz)');
    }

    public function test_new_modules_default_to_formal_mode(): void
    {
        $this->actingAs($this->admin)->post(route('learning-module.store'), ['title' => 'K3 Dasar', 'passing_score' => 75]);

        $this->assertFalse(LearningModule::sole()->isGameMode());
        $this->assertSame(30, LearningModule::sole()->question_seconds);
    }

    public function test_game_page_does_not_send_the_answer_key(): void
    {
        [, $quiz] = $this->gameVideo();

        $html = $this->actingAs($this->staff)->get($quiz->url('quiz', ['phase' => 'pretest']))
            ->assertOk()
            ->assertViewIs('learning_module.portal.quiz_game')
            ->assertSee('QUIZIZZ MODE')
            ->assertSee('20 detik')
            ->getContent();

        $this->assertStringNotContainsString('correct_answer', $html);
        $this->assertStringContainsString('Helm keselamatan', $html);

        // Data soal untuk permainan hanya berisi id, teks, dan pilihan.
        preg_match('/const QUESTIONS = (\[.*?\]);/s', $html, $match);
        $questions = json_decode($match[1], true);
        $this->assertCount(2, $questions);
        $this->assertSame(['id', 'question', 'options'], array_keys($questions[0]));
    }

    public function test_answers_are_locked_and_only_report_right_or_wrong(): void
    {
        [, $quiz] = $this->gameVideo();
        $url = $quiz->url('answer', ['phase' => 'pretest']);
        $apd = $this->questionId($quiz, 'APD utama?');

        $this->actingAs($this->staff)->postJson($url, ['question_id' => $apd, 'answer' => 'b'])
            ->assertOk()->assertExactJson(['correct' => false, 'answered' => 1]);

        // Jawaban kedua untuk soal yang sama tidak mengubah jawaban yang sudah terkunci.
        $this->actingAs($this->staff)->postJson($url, ['question_id' => $apd, 'answer' => 'a'])
            ->assertOk()->assertJson(['correct' => false, 'answered' => 1]);

        // Waktu habis dikirim sebagai jawaban kosong.
        $this->actingAs($this->staff)->postJson($url, ['question_id' => $this->questionId($quiz, 'Jalur evakuasi harus?'), 'answer' => null])
            ->assertOk()->assertJson(['correct' => false, 'answered' => 2]);
    }

    public function test_answer_endpoint_rejects_foreign_questions_and_locked_phases(): void
    {
        [, $quiz] = $this->gameVideo();
        [, $other] = $this->gameVideo();

        $this->actingAs($this->staff)->postJson($quiz->url('answer', ['phase' => 'pretest']), [
            'question_id' => $other->questions()->value('id'), 'answer' => 'a',
        ])->assertNotFound();

        $this->actingAs($this->staff)->postJson($quiz->url('answer', ['phase' => 'pretest']), [
            'question_id' => $this->questionId($quiz, 'APD utama?'), 'answer' => 'z',
        ])->assertUnprocessable();

        // Posttest belum terbuka sebelum pretest dan materi selesai.
        $this->actingAs($this->staff)->postJson($quiz->url('answer', ['phase' => 'posttest']), [
            'question_id' => $this->questionId($quiz, 'APD utama?'), 'answer' => 'a',
        ])->assertForbidden()->assertJson(['message' => 'Kerjakan pretest terlebih dahulu.']);
    }

    public function test_answer_endpoint_is_only_for_game_mode(): void
    {
        [, $quiz] = $this->gameVideo(['mode' => 'formal']);

        $this->actingAs($this->staff)->postJson($quiz->url('answer', ['phase' => 'pretest']), [
            'question_id' => $this->questionId($quiz, 'APD utama?'), 'answer' => 'a',
        ])->assertNotFound();

        $this->actingAs($this->staff)->get($quiz->url('quiz', ['phase' => 'pretest']))->assertViewIs('learning_module.portal.quiz');
    }

    public function test_submit_grades_the_locked_answers_and_ignores_the_form(): void
    {
        [$video, $quiz] = $this->gameVideo();
        $apd = $this->questionId($quiz, 'APD utama?');
        $evac = $this->questionId($quiz, 'Jalur evakuasi harus?');

        $this->actingAs($this->staff)->postJson($quiz->url('answer', ['phase' => 'pretest']), ['question_id' => $apd, 'answer' => 'a']);

        // Jawaban dari form diabaikan; soal yang tidak dijawab dihitung salah.
        $this->actingAs($this->staff)->post($quiz->url('submit', ['phase' => 'pretest']), [
            'answers' => [$apd => 'a', $evac => 'b'],
        ])->assertRedirect();

        $attempt = LearningModuleAttempt::sole();
        $this->assertSame(1, $attempt->correct_answers);
        $this->assertEquals(50, $attempt->score);
        $this->assertNull(session('learning-quiz-answers.'.$quiz->id.'.pretest.1'));

        // Pretest dibatasi sekali: jawaban baru ditolak.
        $this->actingAs($this->staff)->postJson($quiz->url('answer', ['phase' => 'pretest']), ['question_id' => $apd, 'answer' => 'a'])
            ->assertForbidden();
        $this->actingAs($this->staff)->get(route('video.show', $video))->assertOk();
    }

    public function test_reopening_the_game_resumes_after_locked_answers(): void
    {
        [, $quiz] = $this->gameVideo();
        $apd = $this->questionId($quiz, 'APD utama?');

        $this->actingAs($this->staff)->postJson($quiz->url('answer', ['phase' => 'pretest']), ['question_id' => $apd, 'answer' => 'a']);

        $this->actingAs($this->staff)->get($quiz->url('quiz', ['phase' => 'pretest']))
            ->assertOk()
            ->assertViewHas('answered', fn ($answered) => $answered->all() === [$apd => true])
            ->assertSee('Lanjutkan')
            ->assertSee('1 dari 2 soal sudah Anda jawab');
    }

    public function test_posttest_attempts_use_their_own_locked_answers(): void
    {
        [, $quiz] = $this->gameVideo(['posttest_max_attempts' => null]);
        $apd = $this->questionId($quiz, 'APD utama?');
        $evac = $this->questionId($quiz, 'Jalur evakuasi harus?');

        $this->actingAs($this->staff)->post($quiz->url('submit', ['phase' => 'pretest']));
        $this->actingAs($this->staff)->postJson($quiz->url('items.complete', ['item' => $quiz->items()->sole()]))->assertOk();

        foreach ([['a', 'a'], ['a', 'b']] as [$first, $second]) {
            $this->actingAs($this->staff)->postJson($quiz->url('answer', ['phase' => 'posttest']), ['question_id' => $apd, 'answer' => $first]);
            $this->actingAs($this->staff)->postJson($quiz->url('answer', ['phase' => 'posttest']), ['question_id' => $evac, 'answer' => $second]);
            $this->actingAs($this->staff)->post($quiz->url('submit', ['phase' => 'posttest']))->assertRedirect();
        }

        $this->assertSame([50.0, 100.0], LearningModuleAttempt::where('phase', 'posttest')->orderBy('attempt_number')->pluck('score')->map(fn ($s) => (float) $s)->all());
    }

    public function test_standalone_module_and_document_contexts_have_answer_routes(): void
    {
        $module = LearningModule::create(['title' => 'Modul', 'passing_score' => 70, 'quiz_mode' => 'game', 'is_published' => true]);
        $this->assertStringContainsString('/modul-saya/'.$module->id.'/pretest/answer', $module->url('answer', ['phase' => 'pretest']));

        Storage::fake('public');
        $this->actingAs($this->admin)->post(route('document.store'), [
            'file' => UploadedFile::fake()->create('sop.pdf', 10, 'application/pdf'),
            'document_type' => 1, 'divisi_id' => 1, 'role_id' => [0],
            'quiz' => ['pretest' => '1', 'mode' => 'game'],
        ]);
        $quiz = Document::sole()->quizModule;
        $this->assertStringContainsString('/document/kuis/'.$quiz->id.'/pretest/answer', $quiz->url('answer', ['phase' => 'pretest']));
    }
}
