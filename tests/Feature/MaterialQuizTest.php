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
 * Pretest/posttest yang dinyalakan langsung pada Video Materi dan Dokumen.
 */
class MaterialQuizTest extends TestCase
{
    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('username', 'admin')->sole();
        $this->staff = User::factory()->staff()->create();
    }

    private function videoWithQuiz(array $settings): array
    {
        $video = Video::create(['title' => 'K3 Gudang', 'video_type' => 'link', 'video_link' => 'https://youtu.be/dQw4w9WgXcQ']);
        $quiz = app(MaterialQuizService::class)->sync($video, $settings);
        $quiz->questions()->create(['question' => 'APD utama?', 'option_a' => 'Helm', 'option_b' => 'Topi', 'correct_answer' => 'a']);
        app(MaterialQuizService::class)->refresh($quiz);

        return [$video, $quiz->fresh()];
    }

    private function answer(LearningModule $quiz, string $letter): array
    {
        return ['answers' => [$quiz->questions()->value('id') => $letter]];
    }

    public function test_admin_enables_quiz_from_the_video_form_and_is_sent_to_add_questions(): void
    {
        $this->actingAs($this->admin)->post(route('video.store'), [
            'title' => 'Video SOP',
            'video_type' => 'link',
            'video_link' => 'https://youtu.be/dQw4w9WgXcQ',
            'quiz' => ['pretest' => '1', 'posttest' => '1', 'pretest_max_attempts' => '1', 'posttest_max_attempts' => '', 'passing_score' => '80'],
        ])->assertRedirect(route('learning-module.questions.index', LearningModule::sole()));

        $quiz = LearningModule::sole();
        $this->assertSame('video', $quiz->source_type);
        $this->assertTrue($quiz->has_pretest && $quiz->has_posttest);
        $this->assertSame(1, $quiz->pretest_max_attempts);
        $this->assertNull($quiz->posttest_max_attempts);
        $this->assertSame(80, $quiz->passing_score);
        $this->assertFalse($quiz->is_published, 'Kuis belum aktif sebelum ada soal.');

        // Menambah soal pertama mengaktifkan kuis.
        $this->actingAs($this->admin)->post(route('learning-module.questions.store', $quiz), [
            'question' => 'APD utama?', 'option_a' => 'Helm', 'option_b' => 'Topi', 'correct_answer' => 'a',
        ]);
        $this->assertTrue($quiz->fresh()->is_published);

        // Kuis milik video tidak muncul di menu Modul Belajar.
        $this->actingAs($this->admin)->get(route('learning-module.index'))->assertDontSee('Video SOP');
    }

    public function test_video_without_quiz_stays_public(): void
    {
        $video = Video::create(['title' => 'Video Umum', 'video_type' => 'link', 'video_link' => 'https://youtu.be/dQw4w9WgXcQ']);

        $this->get(route('video.show', $video))->assertOk();
    }

    public function test_video_pretest_comes_before_watching_and_posttest_after_finishing(): void
    {
        [$video, $quiz] = $this->videoWithQuiz(['pretest' => true, 'posttest' => true, 'pretest_max_attempts' => 1]);
        $item = $quiz->items()->sole();

        $this->get(route('video.show', $video))->assertRedirect(route('login', ['redirect' => '/video/watch/'.$video->id]));
        $this->actingAs($this->staff)->get(route('video.show', $video))
            ->assertRedirect(route('learning.quiz', [$quiz, 'pretest']));

        $this->actingAs($this->staff)->post(route('learning.submit', [$quiz, 'pretest']), $this->answer($quiz, 'b'))->assertRedirect();
        $this->actingAs($this->staff)->get(route('video.show', $video))->assertOk()->assertSee('Kerjakan Posttest');

        // Posttest terkunci sampai video selesai ditonton.
        $this->actingAs($this->staff)->post(route('learning.submit', [$quiz, 'posttest']), $this->answer($quiz, 'a'))
            ->assertRedirect(route('video.show', $video));
        $this->assertSame(0, LearningModuleAttempt::where('phase', 'posttest')->count());

        $this->actingAs($this->staff)->postJson(route('learning.items.complete', [$quiz, $item]))
            ->assertOk()->assertJson(['posttest_open' => true]);

        $this->actingAs($this->staff)->post(route('learning.submit', [$quiz, 'posttest']), $this->answer($quiz, 'a'))->assertRedirect();
        $this->actingAs($this->staff)->post(route('learning.submit', [$quiz, 'posttest']), $this->answer($quiz, 'b'))->assertRedirect();
        $this->assertSame([1, 2], LearningModuleAttempt::where('phase', 'posttest')->orderBy('id')->pluck('attempt_number')->all());

        // Pretest dibatasi sekali.
        $this->actingAs($this->staff)->post(route('learning.submit', [$quiz, 'pretest']), $this->answer($quiz, 'a'))
            ->assertSessionHas('error', 'Pretest hanya dapat dikerjakan satu kali.');
        $this->assertSame(1, LearningModuleAttempt::where('phase', 'pretest')->count());
    }

    public function test_posttest_only_video_can_be_watched_right_away(): void
    {
        [$video, $quiz] = $this->videoWithQuiz(['pretest' => false, 'posttest' => true]);

        $this->actingAs($this->staff)->get(route('video.show', $video))->assertOk()->assertDontSee('Kerjakan Pretest');
        $this->actingAs($this->staff)->get(route('learning.quiz', [$quiz, 'pretest']))->assertRedirect(route('video.show', $video));

        $this->actingAs($this->staff)->postJson(route('learning.items.complete', [$quiz, $quiz->items()->sole()]))->assertOk();
        $this->actingAs($this->staff)->get(route('learning.quiz', [$quiz, 'posttest']))->assertOk();
    }

    public function test_attempt_limits_are_configurable_per_phase(): void
    {
        [, $quiz] = $this->videoWithQuiz(['pretest' => true, 'posttest' => true, 'pretest_max_attempts' => 2, 'posttest_max_attempts' => 2]);

        foreach (['pretest', 'posttest'] as $phase) {
            if ($phase === 'posttest') {
                $this->actingAs($this->staff)->postJson(route('learning.items.complete', [$quiz, $quiz->items()->sole()]))->assertOk();
            }
            $this->actingAs($this->staff)->post(route('learning.submit', [$quiz, $phase]), $this->answer($quiz, 'b'));
            $this->actingAs($this->staff)->post(route('learning.submit', [$quiz, $phase]), $this->answer($quiz, 'a'));
            $this->actingAs($this->staff)->post(route('learning.submit', [$quiz, $phase]), $this->answer($quiz, 'a'))
                ->assertSessionHas('error');
        }

        $this->assertSame(2, LearningModuleAttempt::where('phase', 'pretest')->count());
        $this->assertSame(2, LearningModuleAttempt::where('phase', 'posttest')->count());
        // Nilai awal tetap pretest pertama.
        $this->actingAs($this->admin)->get(route('learning-module.show', $quiz))->assertOk()->assertSee('+100');
    }

    public function test_video_manager_can_preview_and_manage_without_learning_module_permission(): void
    {
        [$video, $quiz] = $this->videoWithQuiz(['pretest' => true, 'posttest' => true]);
        $trainer = User::factory()->staff()->create();
        $trainer->givePermissionTo('manage-videos');

        $this->actingAs($trainer)->get(route('video.show', $video))->assertOk();
        $this->actingAs($trainer)->get(route('learning-module.questions.index', $quiz))->assertOk();
        $this->actingAs($trainer)->get(route('learning-module.show', $quiz))->assertOk();
        $this->actingAs($trainer)->get(route('learning-module.edit', $quiz))->assertNotFound();
    }

    public function test_turning_quiz_off_keeps_history_and_removes_gating(): void
    {
        [$video, $quiz] = $this->videoWithQuiz(['pretest' => true, 'posttest' => false]);
        $this->actingAs($this->staff)->post(route('learning.submit', [$quiz, 'pretest']), $this->answer($quiz, 'a'));

        $this->actingAs($this->admin)->put(route('video.update', $video), [
            'title' => 'K3 Gudang',
            'video_type' => 'link',
            'video_link' => 'https://youtu.be/dQw4w9WgXcQ',
            'quiz' => ['pretest' => '0', 'posttest' => '0'],
        ])->assertRedirect(route('video.index'));

        $this->assertFalse($quiz->fresh()->is_published);
        $this->assertSame(1, LearningModuleAttempt::count());
        $other = User::factory()->staff()->create();
        $this->actingAs($other)->get(route('video.show', $video))->assertOk();
    }

    public function test_pages_with_quiz_settings_render(): void
    {
        [$video, $quiz] = $this->videoWithQuiz(['pretest' => true, 'posttest' => true, 'pretest_max_attempts' => 1, 'posttest_max_attempts' => 3]);
        $document = Document::create(['name' => 'SOP PDF', 'divisi_id' => 1, 'document_type' => 1, 'path' => 'sop.pdf']);

        $this->actingAs($this->admin)->get(route('video.create'))->assertOk()->assertSee('Aktifkan Pretest');
        $this->actingAs($this->admin)->get(route('video.edit', $video))->assertOk()->assertSee('Kelola soal (1)');
        $this->actingAs($this->admin)->get(route('video.index'))->assertOk()->assertSee('Pretest &amp; Posttest', false);
        $this->actingAs($this->admin)->get(route('video.show', $video))->assertOk()->assertSee('Rekap Nilai');
        $this->actingAs($this->admin)->get(route('document.create'))->assertOk()->assertSee('Aktifkan Posttest');
        $this->actingAs($this->admin)->get(route('document.edit', $document))->assertOk();
        $this->actingAs($this->admin)->get(route('learning-module.questions.index', $quiz))->assertOk()->assertSee('Kuis aktif');
        $this->actingAs($this->admin)->get(route('learning-module.create'))->assertOk()->assertSee('Batas Percobaan Posttest');

        $this->actingAs($this->staff)->post(route('learning.submit', [$quiz, 'pretest']), $this->answer($quiz, 'a'));
        $attempt = LearningModuleAttempt::sole();
        $this->actingAs($this->staff)->get(route('learning.attempt', [$quiz, $attempt]))->assertOk()->assertSee('Lanjut Tonton Video');
        $this->actingAs($this->staff)->get(route('learning.quiz', [$quiz, 'pretest']))->assertRedirect(route('video.show', $video));
    }

    public function test_deleting_video_soft_deletes_its_quiz(): void
    {
        [$video, $quiz] = $this->videoWithQuiz(['pretest' => true, 'posttest' => true]);

        $this->actingAs($this->admin)->delete(route('video.destroy', $video));

        $this->assertSoftDeleted($quiz);
    }

    public function test_document_quiz_flow_and_history_does_not_leak_file(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin)->post(route('document.store'), [
            'file' => UploadedFile::fake()->create('sop-gudang.pdf', 10, 'application/pdf'),
            'document_type' => 1,
            'divisi_id' => 1,
            'role_id' => [0],
            'quiz' => ['pretest' => '1', 'posttest' => '1', 'pretest_max_attempts' => '1', 'passing_score' => '70'],
        ]);
        $document = Document::sole();
        $quiz = $document->quizModule;
        $this->assertSame('document', $quiz->source_type);
        $quiz->questions()->create(['question' => 'Rak maksimal?', 'option_a' => '1 ton', 'option_b' => '5 ton', 'correct_answer' => 'a']);
        app(MaterialQuizService::class)->refresh($quiz);
        $item = $quiz->items()->sole();

        $this->actingAs($this->staff)->get(route('document.index'))->assertSee(route('learning.show', $quiz));
        $this->actingAs($this->staff)->getJson(route('document.history', $document))
            ->assertJsonPath('versions.0.file_url', route('learning.show', $quiz));

        $this->actingAs($this->staff)->get(route('learning.items.open', [$quiz, $item]))->assertRedirect(route('learning.show', $quiz));
        $this->actingAs($this->staff)->post(route('learning.submit', [$quiz, 'pretest']), $this->answer($quiz, 'b'));
        $this->actingAs($this->staff)->get(route('learning.items.open', [$quiz, $item]))->assertRedirectContains('dokumen/');
        $this->actingAs($this->staff)->post(route('learning.submit', [$quiz, 'posttest']), $this->answer($quiz, 'a'));

        $this->assertSame(['pretest', 'posttest'], LearningModuleAttempt::orderBy('id')->pluck('phase')->all());
        $this->actingAs($this->staff)->get(route('learning.show', $quiz))->assertOk()->assertSee('Kuis Dokumen');
        $this->actingAs($this->staff)->get(route('learning.index'))->assertDontSee('SOP-GUDANG');
    }
}
