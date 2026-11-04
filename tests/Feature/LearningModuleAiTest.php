<?php

namespace Tests\Feature;

use App\Models\LearningModule;
use App\Models\LearningModuleItem;
use App\Models\User;
use App\Models\Video;
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
}
