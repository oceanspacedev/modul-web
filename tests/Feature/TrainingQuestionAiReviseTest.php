<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Training;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * "Ubah dengan AI" per soal di layar tinjauan soal AI kuis pelatihan.
 */
class TrainingQuestionAiReviseTest extends TestCase
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
            'document_ids' => [$this->document->id],
        ])->assertOk()->assertJson(['success' => true, 'question' => [
            'type' => 'essay',
            'question' => 'Sebutkan APD wajib!',
            'correct_answer' => 'Helm, rompi, sepatu safety',
            'explanation' => 'APD wajib di area proyek.',
        ]]);

        $this->assertSame(0, $this->training->questions()->count());
        Http::assertSent(fn (Request $request) => str_contains($request['contents'][0]['parts'][0]['text'], 'APD wajib: helm, rompi, sepatu safety.'));
    }

    public function test_other_review_cards_and_saved_questions_are_checked_for_duplicates(): void
    {
        $this->training->questions()->create(['type' => 'essay', 'question' => 'Jelaskan fungsi helm!', 'correct_answer' => 'Melindungi kepala']);

        $this->actingAs($this->admin)->postJson(route('training.questions.revise-ai', $this->training), [
            'question' => ['type' => 'essay', 'question' => 'Sebutkan APD wajib!'],
            'part' => 'all',
            'document_ids' => [$this->document->id],
            'other_questions' => ['Kapan rompi dipakai?'],
        ])->assertOk()->assertJson(['similar_to' => null]);

        Http::assertSent(fn (Request $request) => str_contains($request['contents'][0]['parts'][1]['text'], 'Kapan rompi dipakai?')
            && str_contains($request['contents'][0]['parts'][1]['text'], 'Jelaskan fungsi helm!'));
    }

    public function test_custom_api_key_from_the_modal_is_used(): void
    {
        $this->actingAs($this->admin)->postJson(route('training.questions.revise-ai', $this->training), [
            'question' => ['type' => 'essay', 'question' => 'Sebutkan APD wajib!'],
            'part' => 'question',
            'document_ids' => [$this->document->id],
            'api_key' => 'kunci-kustom',
        ])->assertOk();

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'key=kunci-kustom'));
    }

    public function test_empty_question_is_rejected(): void
    {
        $this->actingAs($this->admin)->postJson(route('training.questions.revise-ai', $this->training), [
            'question' => ['type' => 'multiple_choice', 'question' => ''],
            'part' => 'all',
            'document_ids' => [$this->document->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('question.question');

        // Dokumen acuan wajib, karena AI selalu membaca ulang materi.
        $this->actingAs($this->admin)->postJson(route('training.questions.revise-ai', $this->training), [
            'question' => ['type' => 'essay', 'question' => 'Sebutkan APD wajib!'],
            'part' => 'all',
        ])->assertUnprocessable()->assertJsonValidationErrors('document_ids');

        Http::assertNothingSent();
    }
}
