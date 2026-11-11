<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\LearningModule;
use App\Models\LearningModuleAttempt;
use App\Models\Training;
use App\Models\TrainingParticipant;
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
        ])->assertRedirect(LearningModule::sole()->url('questions.index'));

        $quiz = LearningModule::sole();
        $this->assertSame('video', $quiz->source_type);
        $this->assertTrue($quiz->has_pretest && $quiz->has_posttest);
        $this->assertSame(1, $quiz->pretest_max_attempts);
        $this->assertNull($quiz->posttest_max_attempts);
        $this->assertSame(80, $quiz->passing_score);
        $this->assertFalse($quiz->is_published, 'Kuis belum aktif sebelum ada soal.');

        // Menambah soal pertama mengaktifkan kuis.
        $this->actingAs($this->admin)->post($quiz->url('questions.store'), [
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
            ->assertRedirect($quiz->url('quiz', ['phase' => 'pretest']));

        $this->actingAs($this->staff)->post($quiz->url('submit', ['phase' => 'pretest']), $this->answer($quiz, 'b'))->assertRedirect();
        $this->actingAs($this->staff)->get(route('video.show', $video))->assertOk()->assertSee('Kerjakan Posttest');

        // Posttest terkunci sampai video selesai ditonton.
        $this->actingAs($this->staff)->post($quiz->url('submit', ['phase' => 'posttest']), $this->answer($quiz, 'a'))
            ->assertRedirect(route('video.show', $video));
        $this->assertSame(0, LearningModuleAttempt::where('phase', 'posttest')->count());

        $this->actingAs($this->staff)->postJson($quiz->url('items.complete', ['item' => $item]))
            ->assertOk()->assertJson(['posttest_open' => true]);

        $this->actingAs($this->staff)->post($quiz->url('submit', ['phase' => 'posttest']), $this->answer($quiz, 'a'))->assertRedirect();
        $this->actingAs($this->staff)->post($quiz->url('submit', ['phase' => 'posttest']), $this->answer($quiz, 'b'))->assertRedirect();
        $this->assertSame([1, 2], LearningModuleAttempt::where('phase', 'posttest')->orderBy('id')->pluck('attempt_number')->all());

        // Pretest dibatasi sekali.
        $this->actingAs($this->staff)->post($quiz->url('submit', ['phase' => 'pretest']), $this->answer($quiz, 'a'))
            ->assertSessionHas('error', 'Pretest hanya dapat dikerjakan satu kali.');
        $this->assertSame(1, LearningModuleAttempt::where('phase', 'pretest')->count());
    }

    public function test_posttest_only_video_can_be_watched_right_away(): void
    {
        [$video, $quiz] = $this->videoWithQuiz(['pretest' => false, 'posttest' => true]);

        $this->actingAs($this->staff)->get(route('video.show', $video))->assertOk()->assertDontSee('Kerjakan Pretest');
        $this->actingAs($this->staff)->get($quiz->url('quiz', ['phase' => 'pretest']))->assertRedirect(route('video.show', $video));

        $this->actingAs($this->staff)->postJson($quiz->url('items.complete', ['item' => $quiz->items()->sole()]))->assertOk();
        $this->actingAs($this->staff)->get($quiz->url('quiz', ['phase' => 'posttest']))->assertOk();
    }

    public function test_attempt_limits_are_configurable_per_phase(): void
    {
        [, $quiz] = $this->videoWithQuiz(['pretest' => true, 'posttest' => true, 'pretest_max_attempts' => 2, 'posttest_max_attempts' => 2]);

        foreach (['pretest', 'posttest'] as $phase) {
            if ($phase === 'posttest') {
                $this->actingAs($this->staff)->postJson($quiz->url('items.complete', ['item' => $quiz->items()->sole()]))->assertOk();
            }
            $this->actingAs($this->staff)->post($quiz->url('submit', ['phase' => $phase]), $this->answer($quiz, 'b'));
            $this->actingAs($this->staff)->post($quiz->url('submit', ['phase' => $phase]), $this->answer($quiz, 'a'));
            $this->actingAs($this->staff)->post($quiz->url('submit', ['phase' => $phase]), $this->answer($quiz, 'a'))
                ->assertSessionHas('error');
        }

        $this->assertSame(2, LearningModuleAttempt::where('phase', 'pretest')->count());
        $this->assertSame(2, LearningModuleAttempt::where('phase', 'posttest')->count());
        // Nilai awal tetap pretest pertama.
        $this->actingAs($this->admin)->get($quiz->url('results'))->assertOk()->assertSee('+100');
    }

    public function test_pretest_is_required_for_everyone_but_managers_get_a_preview_link(): void
    {
        [$video, $quiz] = $this->videoWithQuiz(['pretest' => true, 'posttest' => true]);
        $trainer = User::factory()->staff()->create();
        $trainer->givePermissionTo('manage-videos');
        $previewUrl = route('video.show', ['video' => $video, 'preview' => 1]);

        foreach ([$this->admin, $trainer, $this->staff] as $user) {
            $this->actingAs($user)->get(route('video.show', $video))->assertRedirect($quiz->url('quiz', ['phase' => 'pretest']));
        }

        $this->actingAs($trainer)->get($quiz->url('quiz', ['phase' => 'pretest']))->assertOk()->assertSee($previewUrl, false);
        $this->actingAs($trainer)->get($previewUrl)->assertOk();

        // Peserta tidak melihat link pratinjau dan tidak bisa memakainya.
        $this->actingAs($this->staff)->get($quiz->url('quiz', ['phase' => 'pretest']))->assertOk()->assertDontSee('Pratinjau video tanpa pretest');
        $this->actingAs($this->staff)->get($previewUrl)->assertRedirect($quiz->url('quiz', ['phase' => 'pretest']));
    }

    public function test_video_manager_can_manage_quiz_without_learning_module_permission(): void
    {
        [, $quiz] = $this->videoWithQuiz(['pretest' => true, 'posttest' => true]);
        $trainer = User::factory()->staff()->create();
        $trainer->givePermissionTo('manage-videos');

        $this->actingAs($trainer)->get($quiz->url('questions.index'))->assertOk();
        $this->actingAs($trainer)->get($quiz->url('results'))->assertOk();
        $this->actingAs($trainer)->get(route('learning-module.edit', $quiz))->assertNotFound();
    }

    public function test_turning_quiz_off_keeps_history_and_removes_gating(): void
    {
        [$video, $quiz] = $this->videoWithQuiz(['pretest' => true, 'posttest' => false]);
        $this->actingAs($this->staff)->post($quiz->url('submit', ['phase' => 'pretest']), $this->answer($quiz, 'a'));

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
        $this->actingAs($this->admin)->get(route('video.show', ['video' => $video, 'preview' => 1]))->assertOk()->assertSee('Rekap Nilai');
        $this->actingAs($this->admin)->get(route('document.create'))->assertOk()->assertSee('Aktifkan Posttest')->assertSee('satu dokumen per jabatan');
        $this->actingAs($this->admin)->get(route('document.edit', $document))->assertOk();
        $this->actingAs($this->admin)->get($quiz->url('questions.index'))->assertOk()->assertSee('Kuis aktif');
        $this->actingAs($this->admin)->get(route('learning-module.create'))->assertOk()->assertSee('Batas Percobaan Posttest');

        $this->actingAs($this->staff)->post($quiz->url('submit', ['phase' => 'pretest']), $this->answer($quiz, 'a'));
        $attempt = LearningModuleAttempt::sole();
        $this->actingAs($this->staff)->get($quiz->url('attempt', ['attempt' => $attempt]))->assertOk()->assertSee('Lanjut Tonton Video');
        $this->actingAs($this->staff)->get($quiz->url('quiz', ['phase' => 'pretest']))->assertRedirect(route('video.show', $video));
    }

    public function test_document_pretest_is_required_for_everyone_but_managers_get_a_preview_link(): void
    {
        $document = Document::create(['name' => 'SOP PDF', 'divisi_id' => 1, 'document_type' => 1, 'path' => 'sop.pdf']);
        $quiz = app(MaterialQuizService::class)->sync($document, ['pretest' => true, 'posttest' => false, 'pretest_max_attempts' => 1]);
        $quiz->questions()->create(['question' => 'APD utama?', 'option_a' => 'Helm', 'option_b' => 'Topi', 'correct_answer' => 'a']);
        app(MaterialQuizService::class)->refresh($quiz);
        $hubUrl = $quiz->url('show');

        // Tombol Buka di menu Dokumen mengarah ke halaman kuis, juga untuk admin.
        foreach ([$this->admin, $this->staff] as $user) {
            $this->actingAs($user)->get(route('document.index'))->assertSee($hubUrl, false);
        }

        $this->actingAs($this->admin)->get($hubUrl)->assertOk()->assertSee('Pratinjau dokumen tanpa pretest');
        $this->actingAs($this->admin)->get($quiz->url('quiz', ['phase' => 'pretest']))->assertOk()->assertSee('Pratinjau dokumen tanpa pretest');
        $this->actingAs($this->staff)->get($hubUrl)->assertOk()->assertDontSee('Pratinjau dokumen tanpa pretest');
        $this->actingAs($this->staff)->get($quiz->url('items.open', ['item' => $quiz->items()->sole()]))->assertRedirect($hubUrl);

        // Portal peserta pelatihan (link WhatsApp) juga lewat halaman kuis, bukan file langsung.
        $training = Training::create([
            'title' => 'Pelatihan K3', 'trainer_id' => $this->admin->id, 'training_date' => now()->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:00', 'zoom_link' => 'https://zoom.us/test',
        ]);
        $training->documents()->attach($document);
        $participant = TrainingParticipant::create(['training_id' => $training->id, 'user_id' => $this->staff->id, 'token' => 'token_doc_quiz']);

        $this->get(route('training.portal', $participant->token))->assertOk()->assertSee($hubUrl, false)->assertDontSee('dokumen/sop.pdf');
    }

    public function test_video_and_document_quizzes_live_under_their_own_menus(): void
    {
        [$video, $videoQuiz] = $this->videoWithQuiz(['pretest' => true, 'posttest' => true, 'pretest_max_attempts' => 1]);
        $document = Document::create(['name' => 'SOP PDF', 'divisi_id' => 1, 'document_type' => 1, 'path' => 'sop.pdf']);
        $documentQuiz = app(MaterialQuizService::class)->sync($document, ['pretest' => true, 'posttest' => true]);

        $this->assertStringContainsString('/video/kuis/', $videoQuiz->url('quiz', ['phase' => 'pretest']));
        $this->assertStringContainsString('/document/kuis/', $documentQuiz->url('show'));
        $this->assertStringContainsString('/video/kuis/', $videoQuiz->url('questions.index'));

        // Halaman tes video: menu Video Materi yang aktif, menu Modul Belajar tetap tertutup.
        $page = $this->actingAs($this->staff)->get($videoQuiz->url('quiz', ['phase' => 'pretest']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#href="/video" class="nav-link active"#', $page);
        $this->assertDoesNotMatchRegularExpression('#modul-saya" class="nav-link active"#', $page);

        $page = $this->actingAs($this->admin)->get($documentQuiz->url('questions.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#href="/document" class="nav-link active"#', $page);

        // Breadcrumb dan checklist mengikuti menu asal, bukan Modul Belajar.
        $this->assertStringContainsString('Langkah menyiapkan kuis', $page);
        $this->assertStringContainsString('<a href="'.route('document.index').'">Dokumen</a>', $page);

        // Link lama /modul-saya/... dan /learning-module/... dialihkan ke alamat barunya; POST lama ditolak.
        $this->actingAs($this->staff)->get(route('learning.quiz', [$videoQuiz, 'pretest']))
            ->assertRedirect($videoQuiz->url('quiz', ['phase' => 'pretest']));
        $this->actingAs($this->admin)->get(route('learning-module.questions.index', $documentQuiz))
            ->assertRedirect($documentQuiz->url('questions.index'));
        $this->actingAs($this->staff)->post(route('learning.submit', [$videoQuiz, 'pretest']), $this->answer($videoQuiz, 'a'))->assertNotFound();

        // Modul Belajar biasa tidak bisa dibuka lewat alamat video/dokumen.
        $module = LearningModule::create(['title' => 'Modul Biasa', 'passing_score' => 70, 'is_published' => true]);
        $this->actingAs($this->staff)->get(route('video-quiz.show', $module))->assertRedirect(route('learning.show', $module));
    }

    public function test_pretest_back_button_and_question_order_stay_put(): void
    {
        [$video, $quiz] = $this->videoWithQuiz(['pretest' => true, 'posttest' => true, 'pretest_max_attempts' => 1]);
        foreach (range(2, 12) as $n) {
            $quiz->questions()->create(['question' => "Soal nomor {$n}?", 'option_a' => 'Ya', 'option_b' => 'Tidak', 'correct_answer' => 'a']);
        }
        $pretestUrl = $quiz->url('quiz', ['phase' => 'pretest']);
        $order = fn (string $html) => preg_match_all('/name="answers\[(\d+)\]" value="a"/', $html, $m) ? $m[1] : [];

        // Halaman tonton mengalihkan ke pretest, jadi panah kembali menuju galeri video.
        $first = $this->actingAs($this->staff)->get($pretestUrl)->assertOk()->assertSee('href="'.route('video.index').'"', false)->getContent();
        $second = $this->actingAs($this->staff)->get($pretestUrl)->getContent();

        $this->assertCount(12, $order($first));
        $this->assertSame($order($first), $order($second), 'Membuka ulang pretest tidak boleh mengacak soal lagi.');

        // Setelah pretest dikirim, posttest kembali ke halaman tonton dan urutannya disimpan terpisah.
        $this->actingAs($this->staff)->post($quiz->url('submit', ['phase' => 'pretest']), ['answers' => []]);
        $this->assertNull(session('learning-quiz-order.'.$quiz->id.'.pretest.1'));
        $this->actingAs($this->staff)->postJson($quiz->url('items.complete', ['item' => $quiz->items()->sole()]));
        $this->actingAs($this->staff)->get($quiz->url('quiz', ['phase' => 'posttest']))
            ->assertOk()->assertSee('href="'.route('video.show', $video).'"', false);
    }

    public function test_login_after_hitting_a_quiz_page_goes_to_the_dashboard(): void
    {
        [$video, $quiz] = $this->videoWithQuiz(['pretest' => true, 'posttest' => true]);

        // Sesi habis lalu halaman pretest di-refresh: setelah login masuk ke dashboard, bukan lembar soal.
        $this->get($quiz->url('quiz', ['phase' => 'pretest']))->assertRedirect(route('login'));
        $this->post('/login', ['username' => $this->staff->username, 'password' => 'password'])->assertRedirect(url('dashboard'));
        $this->post('/logout');

        // Membuka video ber-kuis dari link saat belum login tetap kembali ke video tersebut.
        $this->get(route('video.show', $video))->assertRedirect(route('login', ['redirect' => '/video/watch/'.$video->id]));
        $this->get(route('login', ['redirect' => '/video/watch/'.$video->id]));
        $this->post('/login', ['username' => $this->staff->username, 'password' => 'password'])->assertRedirect(url('/video/watch/'.$video->id));
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

        $this->actingAs($this->staff)->get(route('document.index'))->assertSee($quiz->url('show'));
        $this->actingAs($this->staff)->getJson(route('document.history', $document))
            ->assertJsonPath('versions.0.file_url', $quiz->url('show'));

        $this->actingAs($this->staff)->get($quiz->url('items.open', ['item' => $item]))->assertRedirect($quiz->url('show'));
        $this->actingAs($this->staff)->post($quiz->url('submit', ['phase' => 'pretest']), $this->answer($quiz, 'b'));
        $this->actingAs($this->staff)->get($quiz->url('items.open', ['item' => $item]))->assertRedirectContains('dokumen/');
        $this->actingAs($this->staff)->post($quiz->url('submit', ['phase' => 'posttest']), $this->answer($quiz, 'a'));

        $this->assertSame(['pretest', 'posttest'], LearningModuleAttempt::orderBy('id')->pluck('phase')->all());
        $this->actingAs($this->staff)->get($quiz->url('show'))->assertOk()->assertSee('Kuis Dokumen');
        $this->actingAs($this->staff)->get(route('learning.index'))->assertDontSee('SOP-GUDANG');
    }
}
