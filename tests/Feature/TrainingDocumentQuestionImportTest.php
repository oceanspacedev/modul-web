<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\LearningModule;
use App\Models\LearningModuleQuestion;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\TrainingQuizResult;
use App\Models\User;
use App\Services\MaterialQuizService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TrainingDocumentQuestionImportTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('username', 'admin')->sole();
    }

    public function test_document_questions_keep_their_phase_and_are_scored_in_the_training_portal(): void
    {
        $training = $this->training();
        [$pretest] = $this->documentWithQuestion('Dokumen pretest', 'pretest');
        [$posttest] = $this->documentWithQuestion('Dokumen posttest', 'posttest');
        [$both, , $source] = $this->documentWithQuestion('Dokumen kedua fase', 'both', [
            'option_c' => 'Pilihan C', 'option_d' => 'Pilihan D', 'option_e' => 'Pilihan E',
            'correct_answer' => 'e', 'explanation' => 'Pembahasan dari dokumen',
        ]);
        $training->documents()->attach($pretest);

        $this->actingAs($this->admin);
        foreach ([$pretest, $posttest, $both] as $document) {
            $this->from(route('training.questions.index', $training))
                ->post(route('training.questions.import-document', $training), ['quiz_document_id' => $document->id])
                ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
        }

        $questions = $training->questions()->get()->keyBy('used_in');
        $this->assertCount(3, $questions);
        $shared = $questions->get('both');
        $fields = ['question', 'option_a', 'option_b', 'option_c', 'option_d', 'option_e', 'correct_answer', 'explanation'];
        $this->assertSame($source->only($fields), $shared->only($fields));
        $this->assertSame('multiple_choice', $shared->type);
        $this->assertSame([$pretest->id], $training->documents()->pluck('documents.id')->all());
        $this->assertTrue($training->fresh()->hasBothPhases());
        $this->assertFalse($training->fresh()->is_quiz_active);

        $training->update(['is_pretest_active' => true, 'is_quiz_active' => true]);
        $participant = TrainingParticipant::create([
            'training_id' => $training->id,
            'user_id' => User::factory()->staff()->create()->id,
            'token' => 'document-questions-import-participant',
        ]);
        $this->actingAs($participant->user);
        $this->get(route('training.portal.pretest', $participant->token))->assertOk()
            ->assertSee('Soal Dokumen pretest')->assertSee('Soal Dokumen kedua fase')->assertDontSee('Soal Dokumen posttest');
        $this->get(route('training.portal.quiz', $participant->token))->assertOk()
            ->assertSee('Soal Dokumen posttest')->assertSee('Soal Dokumen kedua fase')->assertDontSee('Soal Dokumen pretest');

        $this->post(route('training.portal.pretest.submit', $participant->token), ['answers' => [
            $shared->id => 'e',
            $questions->get('pretest')->id => 'a',
        ]])->assertRedirect(route('training.portal.pretest.result', $participant->token));

        $result = TrainingQuizResult::where('phase', 'pretest')->sole();
        $this->assertSame(2, $result->total_questions);
        $this->assertSame(100.0, $result->score);
    }

    public function test_reimport_only_adds_new_source_questions_and_keeps_training_edits(): void
    {
        $training = $this->training();
        [$document, $quiz, $source] = $this->documentWithQuestion('Dokumen SOP', 'both');
        $sourceBeforeEdit = $source->fresh()->getAttributes();
        $manual = $training->questions()->create([
            'question' => $source->question, 'type' => 'multiple_choice', 'option_a' => 'Ya', 'option_b' => 'Tidak', 'correct_answer' => 'a',
        ]);

        $this->actingAs($this->admin);
        $url = route('training.questions.import-document', $training);
        $this->post($url, ['quiz_document_id' => $document->id])->assertSessionHasNoErrors();
        $copy = $training->questions()->where('source_learning_module_question_id', $source->id)->sole();
        $edited = [
            'question' => 'Soal disesuaikan untuk pelatihan', 'used_in' => 'posttest',
            'option_a' => 'Belum siap', 'option_b' => 'Siap', 'correct_answer' => 'b',
            'explanation' => 'Pembahasan khusus pelatihan',
        ];
        $this->put(route('training.questions.update', [$training, $copy]), $edited)
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->post($url, ['quiz_document_id' => $document->id])->assertSessionHasNoErrors();
        $this->assertSame(2, $training->questions()->count());

        $newSource = $quiz->questions()->create([
            'question' => 'Soal baru di dokumen', 'option_a' => 'Ya', 'option_b' => 'Tidak', 'correct_answer' => 'a',
        ]);
        $this->post($url, ['quiz_document_id' => $document->id])->assertSessionHasNoErrors();

        $this->assertSame(3, $training->questions()->count());
        $this->assertSame('Soal baru di dokumen', $training->questions()->where('source_learning_module_question_id', $newSource->id)->sole()->question);
        $this->assertSame($edited, $copy->fresh()->only(array_keys($edited)));
        $this->assertSame($sourceBeforeEdit, $source->fresh()->getAttributes());
        $this->assertNull($manual->fresh()->source_learning_module_question_id);
    }

    public function test_only_authorized_users_can_import_questions_from_an_available_document_quiz(): void
    {
        $training = $this->training();
        [$available] = $this->documentWithQuestion('Dokumen aktif', 'both');
        [$inactive] = $this->documentWithQuestion('Dokumen nonaktif', 'both');
        $inactive->update(['is_active' => false]);
        [$deleted] = $this->documentWithQuestion('Dokumen terhapus', 'both');
        $deleted->delete();
        [$disabled, $disabledQuiz] = $this->documentWithQuestion('Dokumen kuis dimatikan', 'both');
        app(MaterialQuizService::class)->sync($disabled, ['pretest' => false, 'posttest' => false]);
        $empty = $this->document('Dokumen tanpa soal');
        app(MaterialQuizService::class)->sync($empty, ['pretest' => true]);
        $training->documents()->attach($available);

        $trainingManager = User::factory()->staff()->create();
        $trainingManager->givePermissionTo('manage-trainings');
        $documentManager = User::factory()->staff()->create();
        $documentManager->givePermissionTo('manage-documents');
        $url = route('training.questions.import-document', $training);
        $questionPage = route('training.questions.index', ['training' => $training, 'quiz_document' => $available->id]);

        $this->actingAs($trainingManager)->get($questionPage)->assertOk()->assertDontSee('Ambil Soal dari Dokumen');
        $this->get(route('training.show', $training))->assertOk()->assertDontSee($questionPage, false);
        foreach ([$trainingManager, $documentManager] as $user) {
            $this->actingAs($user)->postJson($url, ['quiz_document_id' => $available->id])->assertForbidden();
        }

        $this->actingAs($this->admin)->get(route('training.show', $training))->assertOk()->assertSee($questionPage, false);
        $this->get($questionPage)->assertOk()->assertSee('Ambil Soal dari Dokumen')
            ->assertSee('value="'.$available->id.'" selected', false)
            ->assertSee('Salin Soal')
            ->assertSee('Perubahan pada salinan soal hanya berlaku di pelatihan ini.')
            ->assertViewHas('quizDocuments', fn (Collection $documents) => $documents->modelKeys() === [$available->id])
            ->assertViewHas('selectedQuizDocumentId', fn ($id) => (int) $id === $available->id);

        foreach ([$inactive, $deleted, $disabled, $empty] as $document) {
            $this->postJson($url, ['quiz_document_id' => $document->id])->assertUnprocessable()->assertJsonValidationErrors('quiz_document_id');
        }
        $this->assertSame(1, $disabledQuiz->questions()->count());
        $this->assertSame(0, $training->questions()->count());
    }

    public function test_imported_question_and_option_images_are_independent_copies(): void
    {
        $disk = Storage::fake('public');
        $disk->put('quiz-images/sourceQuestion.png', 'gambar soal');
        $disk->put('quiz-images/sourceOption.png', 'gambar jawaban');
        [$document, , $source] = $this->documentWithQuestion('Dokumen soal bergambar', 'both', [
            'question' => '', 'question_image' => 'quiz-images/sourceQuestion.png', 'option_b' => null,
            'option_images' => ['b' => 'quiz-images/sourceOption.png'], 'correct_answer' => 'b',
        ]);
        $training = $this->training();
        $secondTraining = $this->training();
        $this->actingAs($this->admin);
        foreach ([$training, $secondTraining] as $destination) {
            $this->post(route('training.questions.import-document', $destination), ['quiz_document_id' => $document->id])
                ->assertSessionHasNoErrors()->assertSessionHas('success');
        }

        $firstCopy = $training->questions()->sole();
        $secondCopy = $secondTraining->questions()->sole();
        $this->assertSame('', $firstCopy->question);
        $this->assertSame(['a' => 'Ya', 'b' => ''], $firstCopy->options());
        $this->assertTrue($firstCopy->hasValidKey());
        $this->assertSame('gambar soal', $disk->get($firstCopy->question_image));
        $this->assertSame('gambar jawaban', $disk->get($firstCopy->optionImage('b')));

        $this->put(route('training.questions.update', [$training, $firstCopy]), [
            'question' => 'Soal pelatihan tanpa gambar', 'remove_question_image' => true,
            'option_a' => 'Ya', 'correct_answer' => 'b',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($firstCopy->fresh()->question_image);
        $disk->assertMissing($firstCopy->question_image);
        $disk->assertExists([$source->question_image, $source->optionImage('b')]);

        $this->delete(route('training.questions.destroy', [$training, $firstCopy]))->assertSessionHas('success');
        $disk->assertMissing($firstCopy->optionImage('b'));
        $disk->assertExists([$source->question_image, $source->optionImage('b')]);

        $source->delete();
        $disk->assertMissing([$source->question_image, $source->optionImage('b')]);
        $disk->assertExists([$secondCopy->question_image, $secondCopy->optionImage('b')]);
    }

    public function test_missing_source_image_rolls_back_the_import_and_cleans_up_copied_images(): void
    {
        $disk = Storage::fake('public');
        $disk->put('quiz-images/sourceQuestion.png', 'gambar soal');
        [$document, $quiz] = $this->documentWithQuestion('Dokumen dengan gambar hilang', 'both', [
            'question_image' => 'quiz-images/sourceQuestion.png',
        ]);
        $quiz->questions()->create([
            'question' => 'Soal kedua', 'option_a' => 'Ya', 'option_b' => 'Tidak', 'correct_answer' => 'a',
            'question_image' => 'quiz-images/missingQuestion.png',
        ]);
        $training = $this->training();

        $this->actingAs($this->admin)->postJson(route('training.questions.import-document', $training), [
            'quiz_document_id' => $document->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('quiz_document_id');

        $this->assertSame(0, $training->questions()->count());
        $this->assertSame(['quiz-images/sourceQuestion.png'], $disk->allFiles('quiz-images'));
    }

    private function training(): Training
    {
        return Training::create([
            'title' => 'Pelatihan berbasis dokumen', 'trainer_id' => $this->admin->id, 'training_date' => now()->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:00', 'has_pretest' => true, 'has_posttest' => true,
        ]);
    }

    private function document(string $name): Document
    {
        return Document::create(['name' => $name, 'divisi_id' => 1, 'document_type' => 1, 'path' => 'sop.pdf']);
    }

    /** @return array{Document, LearningModule, LearningModuleQuestion} */
    private function documentWithQuestion(string $name, string $phase, array $attributes = []): array
    {
        $document = $this->document($name);
        $service = app(MaterialQuizService::class);
        $quiz = $service->sync($document, ['pretest' => $phase !== 'posttest', 'posttest' => $phase !== 'pretest']);
        $question = $quiz->questions()->create([
            'question' => 'Soal '.$name, 'option_a' => 'Ya', 'option_b' => 'Tidak', 'correct_answer' => 'a',
            ...$attributes,
        ]);
        $service->refresh($quiz);

        return [$document, $quiz, $question];
    }
}
