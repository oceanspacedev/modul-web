<?php

namespace Tests\Feature;

use App\Models\LearningModule;
use App\Models\LearningModuleItem;
use App\Models\User;
use App\Models\Video;
use App\Services\MaterialQuizService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * "Buat Soal via AI" di Modul Belajar membaca video: file upload lewat Gemini Files API, link YouTube langsung.
 */
class LearningModuleAiTest extends TestCase
{
    private User $admin;

    private LearningModule $module;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.api_key' => 'test-key']);
        Sleep::fake();

        $this->admin = User::where('username', 'admin')->sole();
        $this->module = LearningModule::create(['title' => 'K3', 'passing_score' => 70]);
    }

    private function addVideo(array $attributes): LearningModuleItem
    {
        $video = Video::create(['title' => 'Video K3', ...$attributes]);

        return $this->module->items()->create(['materialable_type' => 'video', 'materialable_id' => $video->id]);
    }

    private function fakeGemini(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/upload/v1beta/files*' => Http::response([], 200, ['X-Goog-Upload-URL' => 'https://upload.gemini.test/session-1']),
            'upload.gemini.test/*' => Http::response(['file' => [
                'name' => 'files/abc', 'uri' => 'https://generativelanguage.googleapis.com/v1beta/files/abc', 'mimeType' => 'video/mp4', 'state' => 'PROCESSING',
            ]]),
            'generativelanguage.googleapis.com/v1beta/files/abc*' => Http::response([
                'name' => 'files/abc', 'uri' => 'https://generativelanguage.googleapis.com/v1beta/files/abc', 'mimeType' => 'video/mp4', 'state' => 'ACTIVE',
            ]),
            'generativelanguage.googleapis.com/v1beta/models/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode(['questions' => [[
                    'question' => 'APD apa yang dibahas di video?',
                    'option_a' => 'Helm', 'option_b' => 'Topi', 'option_c' => 'Sandal', 'option_d' => 'Payung', 'option_e' => 'Jas',
                    'correct_answer' => 'a',
                ]]])]]]]],
            ]),
        ]);
    }

    private function generate(LearningModuleItem $item): array
    {
        $token = $this->actingAs($this->admin)->postJson(route('learning-module.questions.generate-ai', $this->module), [
            'item_ids' => [$item->id],
            'question_count' => 5,
            'difficulty' => 'campuran',
        ])->assertOk()->json('token');

        return $this->actingAs($this->admin)->getJson(route('learning-module.questions.ai-status', [$this->module, $token]))->json();
    }

    public function test_uploaded_video_is_sent_through_the_files_api_and_cleaned_up(): void
    {
        Storage::fake('public')->put('videos/k3.mp4', 'fake-video-bytes');
        $item = $this->addVideo(['video_type' => 'file', 'video_file' => 'videos/k3.mp4']);
        $this->fakeGemini();

        $result = $this->generate($item);

        $this->assertSame('done', $result['status']);
        $this->assertSame('APD apa yang dibahas di video?', $result['questions'][0]['question']);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://upload.gemini.test/session-1'
            && $request->header('X-Goog-Upload-Command') === ['upload, finalize']);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), ':generateContent')
            && ($request['contents'][0]['parts'][0]['file_data']['file_uri'] ?? null) === 'https://generativelanguage.googleapis.com/v1beta/files/abc'
            && $request['generationConfig']['media_resolution'] === 'MEDIA_RESOLUTION_LOW');
        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE' && str_contains($request->url(), 'v1beta/files/abc'));
        $this->assertSame([], glob(sys_get_temp_dir().'/gemini-video-*'));
    }

    public function test_youtube_link_is_sent_as_file_uri(): void
    {
        $item = $this->addVideo(['video_type' => 'link', 'video_link' => 'https://youtu.be/dQw4w9WgXcQ']);
        $this->fakeGemini();

        $this->assertSame('done', $this->generate($item)['status']);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), ':generateContent')
            && ($request['contents'][0]['parts'][0]['file_data']['file_uri'] ?? null) === 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/upload/'));
    }

    public function test_drive_link_is_reported_as_unsupported(): void
    {
        $item = $this->addVideo(['video_type' => 'link', 'video_link' => 'https://drive.google.com/file/d/abc123/view']);
        $this->fakeGemini();

        $result = $this->generate($item);

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('tidak bisa dibaca AI', $result['message']);
        Http::assertNothingSent();
    }

    public function test_reviewed_ai_questions_are_saved(): void
    {
        $this->actingAs($this->admin)->postJson(route('learning-module.questions.save-ai-batch', $this->module), [
            'questions' => [[
                'question' => 'APD?', 'option_a' => 'Helm', 'option_b' => 'Topi', 'option_c' => null, 'correct_answer' => 'a', 'explanation' => '',
            ]],
        ])->assertOk()->assertJson(['success' => true]);

        $this->actingAs($this->admin)->postJson(route('learning-module.questions.save-ai-batch', $this->module), [
            'questions' => [['question' => 'APD?', 'option_a' => 'Helm', 'option_b' => 'Topi', 'correct_answer' => 'e']],
        ])->assertUnprocessable();

        $this->assertSame(1, $this->module->questions()->count());
    }

    public function test_one_reviewed_question_can_be_revised_with_ai(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/models/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode([
                    'question' => 'APD apa yang wajib dipakai di area proyek?',
                    'option_a' => 'Topi', 'option_b' => 'Helm', 'option_c' => 'Sandal', 'option_d' => 'Payung', 'option_e' => 'Jas',
                    'correct_answer' => 'b', 'explanation' => 'Helm melindungi kepala.',
                ])]]]]],
            ]),
        ]);
        $item = $this->addVideo(['video_type' => 'link', 'video_link' => 'https://youtu.be/dQw4w9WgXcQ']);
        $this->module->questions()->create(['question' => 'Apa fungsi rambu kuning?', 'option_a' => 'Peringatan', 'option_b' => 'Larangan', 'correct_answer' => 'a', 'sort_order' => 1]);
        $current = ['question' => 'APD?', 'option_a' => 'Helm', 'option_b' => 'Topi', 'option_c' => '', 'correct_answer' => 'a', 'explanation' => ''];

        $token = $this->actingAs($this->admin)->postJson(route('learning-module.questions.revise-ai', $this->module), [
            'question' => $current, 'part' => 'all', 'instruction' => 'buat lebih jelas',
            'item_ids' => [$item->id], 'other_questions' => ['Kapan APD dipakai?'],
        ])->assertOk()->json('token');

        $this->actingAs($this->admin)->getJson(route('learning-module.questions.ai-status', [$this->module, $token]))
            ->assertOk()->assertJson(['status' => 'done', 'similar_to' => null, 'question' => [
                'question' => 'APD apa yang wajib dipakai di area proyek?', 'option_b' => 'Helm', 'correct_answer' => 'b',
            ]]);

        // Video dibaca ulang; soal lain di tinjauan dan soal yang sudah tersimpan dikirim supaya tidak muncul dua kali.
        Http::assertSent(function (Request $request) {
            $parts = $request['contents'][0]['parts'];

            return ($parts[0]['file_data']['file_uri'] ?? null) === 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'
                && str_contains($parts[1]['text'], 'Kapan APD dipakai?')
                && str_contains($parts[1]['text'], 'Apa fungsi rambu kuning?');
        });

        $this->actingAs($this->admin)->postJson(route('learning-module.questions.revise-ai', $this->module), [
            'question' => $current, 'part' => 'semua', 'item_ids' => [$item->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('part');

        // Materi acuan wajib, karena AI selalu membaca ulang materi.
        $this->actingAs($this->admin)->postJson(route('learning-module.questions.revise-ai', $this->module), [
            'question' => $current, 'part' => 'all',
        ])->assertUnprocessable()->assertJsonValidationErrors('item_ids');

        config(['services.gemini.api_key' => null]);
        $this->actingAs($this->admin)->postJson(route('learning-module.questions.revise-ai', $this->module), [
            'question' => $current, 'part' => 'question', 'item_ids' => [$item->id],
        ])->assertUnprocessable()->assertJson(['success' => false]);

        // Hanya mengubah isi kartu tinjauan; tidak ada soal baru yang tersimpan.
        $this->assertSame(1, $this->module->questions()->count());
        Http::assertSentCount(1);
    }

    public function test_saving_ai_questions_returns_where_to_go_next(): void
    {
        $question = ['question' => 'APD?', 'option_a' => 'Helm', 'option_b' => 'Topi', 'correct_answer' => 'a'];

        // Regular module: back to its own question list.
        $this->actingAs($this->admin)->postJson(route('learning-module.questions.save-ai-batch', $this->module), ['questions' => [$question]])
            ->assertOk()->assertJson(['redirect' => route('learning-module.questions.index', $this->module)]);

        // Video quiz: straight back to the video page, with the success message flashed.
        $video = Video::create(['title' => 'Video SOP', 'video_type' => 'link', 'video_link' => 'https://youtu.be/dQw4w9WgXcQ']);
        $quiz = app(MaterialQuizService::class)->sync($video, ['pretest' => true, 'posttest' => false]);

        $this->actingAs($this->admin)->postJson($quiz->url('questions.save-ai-batch'), ['questions' => [$question]])
            ->assertOk()
            ->assertJson(['redirect' => route('video.show', ['video' => $video->id, 'preview' => 1])])
            ->assertSessionHas('success');
    }
}
