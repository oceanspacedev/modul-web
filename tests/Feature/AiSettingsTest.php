<?php

namespace Tests\Feature;

use App\Models\AiSetting;
use App\Models\Document;
use App\Models\User;
use App\Models\Video;
use App\Services\GeminiQuestionService;
use App\Support\Ai\AiSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pengaturan AI: admin memilih provider (Gemini, OpenAI, Anthropic), API key dan model dari aplikasi; .env hanya
 * cadangan.
 */
class AiSettingsTest extends TestCase
{
    private const QUESTION = [
        'question' => 'APD apa yang wajib dipakai?',
        'option_a' => 'Helm', 'option_b' => 'Topi', 'option_c' => 'Sandal', 'option_d' => 'Payung', 'option_e' => 'Kaos',
        'correct_answer' => 'a',
    ];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.api_key' => null]);
        $this->admin = User::where('username', 'admin')->sole();
    }

    private function pdfDocument(): Document
    {
        Storage::fake(config('filesystems.default'))->put('dokumen/sop.pdf', '%PDF-1.4 isi sop');
        $document = new Document(['name' => 'SOP APD']);
        $document->path = 'sop.pdf';

        return $document;
    }

    public function test_admin_saves_provider_key_and_model_with_the_key_encrypted(): void
    {
        Http::fake(['api.openai.com/v1/models' => Http::response(['data' => [['id' => 'gpt-test', 'created' => 1]]])]);

        $this->actingAs($this->admin)->put(route('ai-settings.update'), [
            'provider' => 'openai',
            'openai_api_key' => 'sk-rahasia-1234',
            'openai_model' => 'gpt-test',
        ])->assertRedirect()->assertSessionHas('success');

        $stored = DB::table('ai_settings')->where('key', 'openai.api_key')->value('value');
        $this->assertNotSame('sk-rahasia-1234', $stored);
        $this->assertStringNotContainsString('sk-rahasia', $stored);
        $this->assertSame('openai', AiSettings::provider()->key());
        $this->assertSame('sk-rahasia-1234', AiSettings::apiKey());
        $this->assertSame('gpt-test', AiSettings::model());
        $this->assertTrue(AiSettings::isConfigured());
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer sk-rahasia-1234'));

        $this->get(route('ai-settings.index'))->assertOk()->assertSee('Tersimpan ••••1234')->assertDontSee('sk-rahasia-1234');
    }

    public function test_blank_key_keeps_the_stored_key_and_forget_removes_it(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['models' => []])]);
        AiSetting::put(['gemini.api_key' => 'kunci-lama']);

        $this->actingAs($this->admin)->put(route('ai-settings.update'), ['provider' => 'gemini', 'gemini_api_key' => ''])
            ->assertSessionHas('success');
        $this->assertSame('kunci-lama', AiSettings::apiKey());

        config(['services.gemini.api_key' => 'kunci-env']);
        $this->put(route('ai-settings.update'), ['provider' => 'gemini', 'gemini_forget' => '1'])->assertSessionHas('success');
        $this->assertSame('kunci-env', AiSettings::apiKey());
        $this->assertSame('env', AiSettings::source());
    }

    public function test_saving_one_provider_leaves_the_others_untouched(): void
    {
        Http::fake(['api.anthropic.com/v1/models*' => Http::response(['data' => [['id' => 'claude-opus-5-5']]])]);
        AiSetting::put(['openai.api_key' => 'sk-openai', 'openai.model' => 'gpt-test']);

        $this->actingAs($this->admin)->put(route('ai-settings.update'), [
            'provider' => 'anthropic',
            'anthropic_api_key' => 'sk-ant',
            'anthropic_model' => 'claude-opus-5-5',
            // Isian provider lain (mis. dari browser tanpa JS) diabaikan.
            'openai_model' => '',
            'openai_forget' => '1',
        ])->assertSessionHas('success');

        $this->assertSame('anthropic', AiSettings::provider()->key());
        $this->assertSame('sk-openai', AiSetting::secret('openai.api_key'));
        $this->assertSame('gpt-test', AiSetting::value('openai.model'));
    }

    public function test_settings_page_shows_the_three_steps_for_each_provider(): void
    {
        AiSetting::put(['provider' => 'openai', 'openai.api_key' => 'sk-openai-9876', 'openai.model' => 'gpt-test']);

        $this->actingAs($this->admin)->get(route('ai-settings.index'))
            ->assertOk()
            ->assertSeeInOrder(['Pilih provider', 'API key', 'Tes API key', 'Pilih model'])
            ->assertSee('Saat ini: <b>OpenAI</b> · gpt-test', false)
            ->assertSee('data-ai-panel="openai" data-has-key="1"', false)
            ->assertSee('data-ai-panel="anthropic" data-has-key="0"', false)
            ->assertDontSee('sk-openai-9876');
    }

    public function test_active_provider_needs_a_key_and_model_and_the_key_is_not_flashed_back(): void
    {
        Http::fake();

        $this->actingAs($this->admin)->put(route('ai-settings.update'), ['provider' => 'anthropic', 'openai_api_key' => 'sk-lain'])
            ->assertSessionHasErrors(['anthropic_api_key', 'anthropic_model'])
            ->assertSessionMissing('_old_input.openai_api_key');

        $this->assertNull(AiSetting::value(AiSetting::PROVIDER));
        Http::assertNothingSent();
    }

    public function test_saving_reports_an_invalid_key(): void
    {
        Http::fake(['api.anthropic.com/v1/models*' => Http::response(['error' => ['message' => 'invalid x-api-key']], 401)]);

        $this->actingAs($this->admin)->put(route('ai-settings.update'), [
            'provider' => 'anthropic', 'anthropic_api_key' => 'salah', 'anthropic_model' => 'claude-opus-5-5',
        ])->assertSessionHas('error', fn (string $message) => str_contains($message, 'API key Anthropic Claude tidak valid'));
    }

    public function test_load_models_lists_each_provider_with_the_typed_key(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/models*' => Http::response(['models' => [
                ['name' => 'models/gemini-3.5-flash', 'displayName' => 'Gemini 3.5 Flash', 'supportedGenerationMethods' => ['generateContent']],
                ['name' => 'models/text-embedding', 'displayName' => 'Embedding', 'supportedGenerationMethods' => ['embedContent']],
            ]]),
            'api.openai.com/v1/models' => Http::response(['data' => [
                ['id' => 'gpt-5', 'created' => 2], ['id' => 'whisper-1', 'created' => 3], ['id' => 'gpt-image-1', 'created' => 4],
            ]]),
            'api.anthropic.com/v1/models*' => Http::response(['data' => [['id' => 'claude-opus-5-5', 'display_name' => 'Claude Opus 5.5']]]),
        ]);

        $this->actingAs($this->admin);
        $this->postJson(route('ai-settings.models', 'gemini'), ['api_key' => 'g-key'])
            ->assertOk()->assertExactJson(['models' => [['id' => 'gemini-3.5-flash', 'label' => 'Gemini 3.5 Flash']]]);
        $this->postJson(route('ai-settings.models', 'openai'), ['api_key' => 'o-key'])
            ->assertOk()->assertExactJson(['models' => [['id' => 'gpt-5', 'label' => 'gpt-5']]]);
        $this->postJson(route('ai-settings.models', 'anthropic'), ['api_key' => 'a-key'])
            ->assertOk()->assertExactJson(['models' => [['id' => 'claude-opus-5-5', 'label' => 'Claude Opus 5.5']]]);
        $this->postJson(route('ai-settings.models', 'openai'))->assertUnprocessable();

        Http::assertSent(fn (Request $request) => $request->hasHeader('x-api-key', 'a-key') && $request->hasHeader('anthropic-version'));
    }

    public function test_non_admin_cannot_open_or_use_ai_settings(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get(route('ai-settings.index'))->assertRedirect()->assertSessionHas('error');
        $this->actingAs($staff)->postJson(route('ai-settings.models', 'gemini'), ['api_key' => 'x'])->assertForbidden();
        $this->actingAs($staff)->put(route('ai-settings.update'), ['provider' => 'openai'])->assertRedirect();
        $this->assertNull(AiSetting::value(AiSetting::PROVIDER));
    }

    public function test_questions_are_generated_with_openai_and_the_pdf_sent_as_a_file(): void
    {
        AiSetting::put(['provider' => 'openai', 'openai.api_key' => 'sk-openai', 'openai.model' => 'gpt-test']);
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => json_encode(['questions' => [self::QUESTION]])]]],
        ])]);

        $questions = (new GeminiQuestionService)->generateQuestions([$this->pdfDocument()], 1);

        $this->assertSame('APD apa yang wajib dipakai?', $questions[0]['question']);
        Http::assertSent(function (Request $request) {
            $content = $request['messages'][0]['content'];

            return $request->hasHeader('Authorization', 'Bearer sk-openai')
                && $request['model'] === 'gpt-test'
                && $request['response_format'] === ['type' => 'json_object']
                && ! isset($request['temperature'])
                && $content[0]['type'] === 'file'
                && $content[0]['file']['file_data'] === 'data:application/pdf;base64,'.base64_encode('%PDF-1.4 isi sop')
                && $content[1]['type'] === 'text';
        });
    }

    public function test_questions_are_generated_with_anthropic_and_the_pdf_sent_as_a_document(): void
    {
        AiSetting::put(['provider' => 'anthropic', 'anthropic.api_key' => 'sk-ant', 'anthropic.model' => 'claude-opus-5-5']);
        Http::fake(['api.anthropic.com/v1/messages' => Http::response([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => "```json\n".json_encode(['questions' => [self::QUESTION]])."\n```"]],
        ])]);

        $questions = (new GeminiQuestionService)->generateQuestions([$this->pdfDocument()], 1);

        $this->assertSame('a', $questions[0]['correct_answer']);
        Http::assertSent(function (Request $request) {
            $content = $request['messages'][0]['content'];

            return $request->hasHeader('x-api-key', 'sk-ant')
                && $request['model'] === 'claude-opus-5-5'
                && $request['max_tokens'] > 0
                && ! isset($request['temperature'])
                && $content[0] === ['type' => 'document', 'source' => [
                    'type' => 'base64', 'media_type' => 'application/pdf', 'data' => base64_encode('%PDF-1.4 isi sop'),
                ]];
        });
    }

    public function test_video_material_is_refused_when_the_provider_cannot_read_video(): void
    {
        AiSetting::put(['provider' => 'openai', 'openai.api_key' => 'sk-openai', 'openai.model' => 'gpt-test']);
        Http::fake();
        $video = Video::create(['title' => 'Video K3', 'video_type' => 'link', 'video_link' => 'https://youtu.be/dQw4w9WgXcQ']);

        $this->assertFalse(GeminiQuestionService::supportsVideo($video));
        $this->expectExceptionMessage('OpenAI tidak bisa membaca video "Video K3"');

        try {
            (new GeminiQuestionService)->generateQuestions([$video], 1);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_stored_gemini_key_and_model_override_the_env_values(): void
    {
        config(['services.gemini.api_key' => 'kunci-env', 'services.gemini.model' => 'gemini-env']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode(['questions' => [self::QUESTION]])]]]]],
        ])]);

        (new GeminiQuestionService)->generateQuestions([$this->pdfDocument()], 1);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'models/gemini-env:generateContent?key=kunci-env'));

        AiSetting::put(['gemini.api_key' => 'kunci-db', 'gemini.model' => 'gemini-db']);
        (new GeminiQuestionService)->generateQuestions([$this->pdfDocument()], 1);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'models/gemini-db:generateContent?key=kunci-db'));
        $this->assertSame('pengaturan', AiSettings::source());
    }
}
