<?php

namespace Tests\Feature;

use App\Models\LearningModule;
use App\Models\LearningModuleAttempt;
use App\Models\User;
use App\Models\Video;
use App\Services\MaterialQuizService;
use Tests\TestCase;

/**
 * Setelan "tampilkan jawaban benar/salah" per fase: nyala = rincian + kunci setelah selesai, mati = hanya nilai
 * (mode game pun tidak memberi tahu benar/salah per soal).
 */
class LearningModuleShowsAnswersTest extends TestCase
{
    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('username', 'admin')->sole();
        $this->staff = User::factory()->staff()->create();
    }

    private function videoQuiz(array $settings = []): LearningModule
    {
        $video = Video::create(['title' => 'K3 Gudang', 'video_type' => 'link', 'video_link' => 'https://youtu.be/dQw4w9WgXcQ']);
        $service = app(MaterialQuizService::class);
        $quiz = $service->sync($video, ['pretest' => true, 'posttest' => true, ...$settings]);
        $quiz->questions()->create(['question' => 'APD utama?', 'option_a' => 'Helm keselamatan', 'option_b' => 'Topi kain', 'correct_answer' => 'a']);
        $service->refresh($quiz);

        return $quiz->fresh();
    }

    private function questionId(LearningModule $quiz): int
    {
        return $quiz->questions()->value('id');
    }

    public function test_hidden_phase_shows_only_the_score_and_shown_phase_shows_the_key(): void
    {
        $quiz = $this->videoQuiz(['pretest_shows_answers' => false, 'posttest_shows_answers' => true]);
        $id = $this->questionId($quiz);

        $this->actingAs($this->staff)->post($quiz->url('submit', ['phase' => 'pretest']), ['answers' => [$id => 'b']]);
        $pretest = LearningModuleAttempt::where('phase', 'pretest')->sole();

        $this->actingAs($this->staff)->get($quiz->url('attempt', ['attempt' => $pretest]))
            ->assertOk()
            ->assertSee('Nilai Anda')
            ->assertSee('Rincian jawaban tidak ditampilkan')
            ->assertDontSee('Rincian Jawaban</h3>', false)
            ->assertDontSee('Helm keselamatan');

        $this->actingAs($this->staff)->postJson($quiz->url('items.complete', ['item' => $quiz->items()->sole()]))->assertOk();
        $this->actingAs($this->staff)->post($quiz->url('submit', ['phase' => 'posttest']), ['answers' => [$id => 'b']]);
        $posttest = LearningModuleAttempt::where('phase', 'posttest')->sole();

        // Jawaban salah: kunci jawaban yang benar ikut ditunjukkan.
        $this->actingAs($this->staff)->get($quiz->url('attempt', ['attempt' => $posttest]))
            ->assertOk()
            ->assertSee('Rincian Jawaban')
            ->assertSee('Salah')
            ->assertSee('Kunci')
            ->assertSee('Helm keselamatan');
    }

    public function test_hidden_game_mode_does_not_report_right_or_wrong(): void
    {
        $quiz = $this->videoQuiz(['mode' => 'game', 'pretest_shows_answers' => false]);
        $url = $quiz->url('answer', ['phase' => 'pretest']);

        $this->actingAs($this->staff)->postJson($url, ['question_id' => $this->questionId($quiz), 'answer' => 'a'])
            ->assertOk()
            ->assertJsonMissingPath('correct')
            ->assertJson(['answered' => 1]);

        // Melanjutkan permainan: soal ditandai sudah dijawab saja, tanpa status benar/salah.
        $this->actingAs($this->staff)->get($quiz->url('quiz', ['phase' => 'pretest']))
            ->assertOk()
            ->assertViewHas('showsAnswers', false)
            ->assertSee('Nilai Anda tampil setelah semua soal selesai');
    }

    public function test_shown_game_mode_still_reports_right_or_wrong(): void
    {
        $quiz = $this->videoQuiz(['mode' => 'game']);

        $this->actingAs($this->staff)->postJson($quiz->url('answer', ['phase' => 'pretest']), ['question_id' => $this->questionId($quiz), 'answer' => 'b'])
            ->assertOk()->assertJson(['correct' => false]);
    }

    public function test_video_and_module_forms_save_both_switches(): void
    {
        $this->actingAs($this->admin)->post(route('video.store'), [
            'title' => 'Video SOP',
            'video_type' => 'link',
            'video_link' => 'https://youtu.be/dQw4w9WgXcQ',
            'quiz' => ['pretest' => '1', 'posttest' => '1', 'pretest_shows_answers' => '0', 'posttest_shows_answers' => '1'],
        ])->assertSessionHasNoErrors();

        $quiz = LearningModule::sole();
        $this->assertFalse($quiz->pretest_shows_answers);
        $this->assertTrue($quiz->posttest_shows_answers);
        $this->actingAs($this->admin)->get(route('video.edit', Video::sole()))->assertOk()->assertSee('Setelah selesai, peserta melihat')->assertSee('Nilai saja');

        // Form Modul Belajar: switch yang tidak dicentang = mati.
        $this->actingAs($this->admin)->post(route('learning-module.store'), [
            'title' => 'K3 Dasar', 'passing_score' => 75, 'pretest_shows_answers' => '1',
        ])->assertSessionHasNoErrors();

        $module = LearningModule::where('title', 'K3 Dasar')->sole();
        $this->assertTrue($module->pretest_shows_answers);
        $this->assertFalse($module->posttest_shows_answers);
    }

    public function test_answers_are_shown_by_default(): void
    {
        $quiz = $this->videoQuiz();

        $this->assertTrue($quiz->showsAnswers('pretest'));
        $this->assertTrue($quiz->showsAnswers('posttest'));
        $this->assertTrue((new LearningModule)->pretest_shows_answers);
    }
}
