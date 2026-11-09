<?php

namespace Tests\Feature;

use App\Models\Training;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * "Ubah dengan AI" per soal di layar tinjauan soal AI kuis pelatihan.
 */
class TrainingQuestionAiReviseTest extends TestCase
{
    private User $admin;

    private Training $training;

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

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode([
                    'question' => 'Pertanyaan yang tidak diminta diubah',
                    'correct_answer' => 'Helm, rompi, sepatu safety',
                    'explanation' => 'APD wajib di area proyek.',
                ])]]]]],
            ]),
        ]);
    }

    public function test_essay_answer_guide_can_be_revised_with_ai(): void
    {
        $this->actingAs($this->admin)->postJson(route('training.questions.revise-ai', $this->training), [
            'question' => ['type' => 'essay', 'question' => 'Sebutkan APD wajib!', 'correct_answer' => 'Helm', 'explanation' => ''],
            'part' => 'answers',
        ])->assertOk()->assertJson(['success' => true, 'question' => [
            'type' => 'essay',
            'question' => 'Sebutkan APD wajib!',
            'correct_answer' => 'Helm, rompi, sepatu safety',
            'explanation' => 'APD wajib di area proyek.',
        ]]);

        $this->assertSame(0, $this->training->questions()->count());
    }

    public function test_custom_api_key_from_the_modal_is_used(): void
    {
        $this->actingAs($this->admin)->postJson(route('training.questions.revise-ai', $this->training), [
            'question' => ['type' => 'essay', 'question' => 'Sebutkan APD wajib!'],
            'part' => 'question',
            'api_key' => 'kunci-kustom',
        ])->assertOk();

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'key=kunci-kustom'));
    }

    public function test_empty_question_is_rejected(): void
    {
        $this->actingAs($this->admin)->postJson(route('training.questions.revise-ai', $this->training), [
            'question' => ['type' => 'multiple_choice', 'question' => ''],
            'part' => 'all',
        ])->assertUnprocessable()->assertJsonValidationErrors('question.question');

        Http::assertNothingSent();
    }
}
