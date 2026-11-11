<?php

namespace Tests\Feature;

use App\Jobs\AiTask;
use App\Jobs\GenerateTrainingQuestions;
use App\Models\Document;
use App\Models\Training;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * "Buat soal dengan AI" di kuis pelatihan berjalan di queue AI; halaman soal mengambil hasilnya lewat polling.
 */
class TrainingQuestionAiGenerateTest extends TestCase
{
    private User $admin;

    private Training $training;

    private Document $document;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.api_key' => 'test-key']);
        $this->admin = User::where('username', 'admin')->sole();
        $this->training = Training::create([
            'title' => 'K3 Dasar',
            'trainer_id' => $this->admin->id,
            'training_date' => now()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'zoom_link' => 'https://zoom.us/test',
            'status' => 'scheduled',
        ]);

        Storage::fake(config('filesystems.default'))->put('dokumen/apd.txt', 'APD wajib: helm, rompi, sepatu safety.');
        $this->document = Document::create(['name' => 'SOP APD', 'divisi_id' => 1, 'document_type' => 1, 'path' => 'apd.txt']);
    }

    private function fakeGemini(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode(['questions' => [[
                    'question' => 'APD apa yang wajib dipakai?',
                    'option_a' => 'Helm', 'option_b' => 'Topi', 'option_c' => 'Sandal', 'option_d' => 'Payung',
                    'correct_answer' => 'a',
                ]]])]]]]],
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'document_ids' => [$this->document->id],
            'question_count' => 5,
            'question_type' => 'multiple_choice',
            'difficulty' => 'campuran',
            ...$overrides,
        ];
    }

    public function test_generation_is_queued_on_the_ai_connection_and_nothing_runs_in_the_request(): void
    {
        Queue::fake();

        $token = $this->actingAs($this->admin)
            ->postJson(route('training.questions.generate-ai', $this->training), $this->payload(['api_key' => 'kunci-kustom']))
            ->assertOk()
            ->assertJson(['success' => true])
            ->json('token');

        Queue::assertPushed(GenerateTrainingQuestions::class, fn (GenerateTrainingQuestions $job) => $job->token === $token
            && $job->connection === config('queue.ai_connection')
            && $job->documentIds === [$this->document->id]
            && $job->apiKey === 'kunci-kustom');
        Http::assertNothingSent();

        $this->assertSame(['status' => 'pending'], AiTask::status($token));
        $this->assertTrue($this->training->documents()->whereKey($this->document->id)->exists());
        $this->getJson(route('training.questions.ai-status', [$this->training, $token]))->assertOk()->assertExactJson(['status' => 'pending']);
    }

    public function test_generated_questions_are_fetched_by_polling(): void
    {
        $this->fakeGemini();

        $token = $this->actingAs($this->admin)
            ->postJson(route('training.questions.generate-ai', $this->training), $this->payload())
            ->assertOk()
            ->json('token');

        $this->getJson(route('training.questions.ai-status', [$this->training, $token]))
            ->assertOk()
            ->assertJson(['status' => 'done', 'count' => 1, 'questions' => [['question' => 'APD apa yang wajib dipakai?', 'correct_answer' => 'a']]]);

        Http::assertSent(fn (Request $request) => str_contains(json_encode($request['contents']), 'APD wajib: helm, rompi, sepatu safety.'));
        $this->assertSame(0, $this->training->questions()->count());
    }

    public function test_a_failing_ai_call_is_reported_through_the_status(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'API key not valid']], 400)]);

        $token = $this->actingAs($this->admin)
            ->postJson(route('training.questions.generate-ai', $this->training), $this->payload())
            ->assertOk()
            ->json('token');

        $status = $this->getJson(route('training.questions.ai-status', [$this->training, $token]))->assertOk()->json();
        $this->assertSame('failed', $status['status']);
        $this->assertNotEmpty($status['message']);
    }

    public function test_a_job_killed_by_the_worker_marks_the_status_failed(): void
    {
        $token = AiTask::newToken();

        (new GenerateTrainingQuestions($token, [$this->document->id], 5, 'multiple_choice', 'campuran'))->failed(null);

        $this->assertSame('failed', AiTask::status($token)['status']);
    }

    public function test_missing_api_key_and_unknown_token_are_rejected(): void
    {
        config(['services.gemini.api_key' => null]);
        Queue::fake();

        $this->actingAs($this->admin)
            ->postJson(route('training.questions.generate-ai', $this->training), $this->payload())
            ->assertUnprocessable()
            ->assertJson(['success' => false]);
        Queue::assertNothingPushed();

        $this->getJson(route('training.questions.ai-status', [$this->training, 'tidak-ada']))->assertNotFound()->assertJson(['status' => 'failed']);
    }
}
