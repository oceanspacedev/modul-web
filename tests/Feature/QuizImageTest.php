<?php

namespace Tests\Feature;

use App\Models\Divisi;
use App\Models\LearningModule;
use App\Models\LearningModuleQuestion;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\TrainingQuestion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Gambar pada soal kuis dan pilihan jawaban: form manual, upload di preview soal AI, dan tampilan peserta.
 */
class QuizImageTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->admin = User::where('username', 'admin')->sole();
    }

    private function training(): Training
    {
        return Training::create([
            'title' => 'Pelatihan Rambu K3',
            'trainer_id' => $this->admin->id,
            'training_date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'zoom_link' => 'https://zoom.us/test',
            'status' => 'scheduled',
        ]);
    }

    private function image(string $name = 'rambu.png'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 200, 150);
    }

    private function uploadPath(string $url): string
    {
        $response = $this->actingAs($this->admin)->post($url, ['image' => $this->image()])->assertOk();
        Storage::disk('public')->assertExists($response->json('path'));
        $this->assertNotEmpty($response->json('url'));

        return $response->json('path');
    }

    public function test_training_manual_form_stores_replaces_and_removes_images(): void
    {
        $training = $this->training();

        $this->actingAs($this->admin)->post("/training/{$training->id}/questions", [
            'type' => 'multiple_choice',
            'question' => 'Rambu mana yang berarti wajib helm?',
            'option_a' => 'Rambu biru',
            'option_b' => '',
            'correct_answer' => 'b',
            'question_image' => $this->image('soal.png'),
            'option_images' => ['b' => $this->image('b.png')],
        ])->assertSessionHasNoErrors();

        $question = TrainingQuestion::sole();
        $this->assertNotNull($question->question_image);
        $oldOptionImage = $question->optionImage('b');
        Storage::disk('public')->assertExists([$question->question_image, $oldOptionImage]);
        $this->assertSame(['a' => 'Rambu biru', 'b' => ''], $question->options());

        // Hapus gambar soal, ganti gambar pilihan B.
        $this->actingAs($this->admin)->put(route('training.questions.update', [$training, $question]), [
            'type' => 'multiple_choice',
            'question' => 'Rambu mana yang berarti wajib helm?',
            'option_a' => 'Rambu biru',
            'correct_answer' => 'b',
            'remove_question_image' => '1',
            'option_images' => ['b' => $this->image('b2.png')],
        ])->assertSessionHasNoErrors();

        $oldQuestionImage = $question->question_image;
        $question->refresh();
        $this->assertNull($question->question_image);
        $this->assertNotSame($oldOptionImage, $question->optionImage('b'));
        Storage::disk('public')->assertMissing([$oldQuestionImage, $oldOptionImage]);
        Storage::disk('public')->assertExists($question->optionImage('b'));

        // Gambar ikut terhapus bersama soalnya.
        $newOptionImage = $question->optionImage('b');
        $this->actingAs($this->admin)->delete(route('training.questions.destroy', [$training, $question]));
        Storage::disk('public')->assertMissing($newOptionImage);
    }

    public function test_training_option_needs_text_or_image(): void
    {
        $training = $this->training();

        $this->actingAs($this->admin)->post("/training/{$training->id}/questions", [
            'type' => 'multiple_choice',
            'question' => 'Soal tanpa pilihan B',
            'option_a' => 'Ya',
            'option_b' => '',
            'correct_answer' => 'a',
        ])->assertSessionHasErrors('option_b');

        $this->actingAs($this->admin)->post("/training/{$training->id}/questions", [
            'type' => 'multiple_choice',
            'question' => 'Kunci ke pilihan kosong',
            'option_a' => 'Ya',
            'option_b' => 'Tidak',
            'correct_answer' => 'd',
        ])->assertSessionHasErrors('correct_answer');

        $this->assertSame(0, TrainingQuestion::count());
    }

    public function test_training_ai_review_saves_uploaded_images(): void
    {
        $training = $this->training();
        $uploadUrl = route('training.questions.upload-image', $training);

        $this->actingAs($this->admin)->post($uploadUrl, ['image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('image');

        $questionImage = $this->uploadPath($uploadUrl);
        $optionImage = $this->uploadPath($uploadUrl);

        $this->actingAs($this->admin)->postJson("/training/{$training->id}/questions/save-ai-batch", ['questions' => [[
            'type' => 'multiple_choice',
            'question' => 'Rambu apa ini?',
            'option_a' => 'Dilarang merokok',
            'option_b' => 'Wajib helm',
            'correct_answer' => 'b',
            'question_image' => $questionImage,
            'option_images' => ['b' => $optionImage],
        ]]])->assertOk()->assertJson(['success' => true]);

        $question = TrainingQuestion::sole();
        $this->assertSame($questionImage, $question->question_image);
        $this->assertSame(['b' => $optionImage], $question->option_images);

        // Path di luar folder gambar kuis ditolak.
        $this->actingAs($this->admin)->postJson("/training/{$training->id}/questions/save-ai-batch", ['questions' => [[
            'type' => 'multiple_choice',
            'question' => 'Soal nakal',
            'option_a' => 'A',
            'option_b' => 'B',
            'correct_answer' => 'a',
            'question_image' => '../.env',
        ]]])->assertUnprocessable();
        $this->assertSame(1, TrainingQuestion::count());

        // Hapus semua soal ikut menghapus file gambarnya.
        $this->actingAs($this->admin)->post("/training/{$training->id}/questions/delete-all");
        Storage::disk('public')->assertMissing([$questionImage, $optionImage]);
    }

    public function test_question_can_be_only_an_image(): void
    {
        $training = $this->training();
        $module = LearningModule::create(['title' => 'K3 Rambu', 'passing_score' => 70]);

        // Form manual: tanpa teks dan tanpa gambar ditolak, gambar saja diterima.
        $this->actingAs($this->admin)->post("/training/{$training->id}/questions", [
            'type' => 'multiple_choice',
            'question' => '',
            'option_a' => 'Ya',
            'option_b' => 'Tidak',
            'correct_answer' => 'a',
        ])->assertSessionHasErrors('question');

        $this->actingAs($this->admin)->post("/training/{$training->id}/questions", [
            'type' => 'multiple_choice',
            'question' => '',
            'question_image' => $this->image(),
            'option_images' => ['a' => $this->image('a.png'), 'b' => $this->image('b.png')],
            'correct_answer' => 'a',
        ])->assertSessionHasNoErrors();

        $question = TrainingQuestion::sole();
        $this->assertSame('', $question->question);
        $this->assertNotNull($question->question_image);

        // Edit soal hanya gambar tanpa mengirim gambar baru: gambar lama tetap dipakai.
        $this->actingAs($this->admin)->put(route('training.questions.update', [$training, $question]), [
            'type' => 'multiple_choice',
            'question' => '',
            'correct_answer' => 'b',
        ])->assertSessionHasNoErrors();
        $this->assertSame('b', $question->fresh()->correct_answer);

        // Menghapus satu-satunya gambar soal tanpa teks ditolak.
        $this->actingAs($this->admin)->put(route('training.questions.update', [$training, $question]), [
            'type' => 'multiple_choice',
            'question' => '',
            'correct_answer' => 'b',
            'remove_question_image' => '1',
        ])->assertSessionHasErrors('question');

        $this->actingAs($this->admin)->post($module->url('questions.store'), [
            'question' => '',
            'question_image' => $this->image(),
            'option_a' => 'Ya',
            'option_b' => 'Tidak',
            'correct_answer' => 'a',
        ])->assertSessionHasNoErrors();
        $this->assertSame('', LearningModuleQuestion::sole()->question);

        // Preview AI: gambar saja diterima, kosong semua ditolak.
        $image = $this->uploadPath(route('training.questions.upload-image', $training));
        $this->actingAs($this->admin)->postJson("/training/{$training->id}/questions/save-ai-batch", ['questions' => [[
            'type' => 'multiple_choice',
            'question' => '',
            'question_image' => $image,
            'option_a' => 'Ya',
            'option_b' => 'Tidak',
            'correct_answer' => 'a',
        ]]])->assertOk();
        $this->assertSame('', TrainingQuestion::latest('id')->first()->question);

        $this->actingAs($this->admin)->postJson("/training/{$training->id}/questions/save-ai-batch", ['questions' => [[
            'type' => 'multiple_choice',
            'question' => '',
            'option_a' => 'Ya',
            'option_b' => 'Tidak',
            'correct_answer' => 'a',
        ]]])->assertUnprocessable();

        $moduleImage = $this->uploadPath($module->url('questions.upload-image'));
        $this->actingAs($this->admin)->postJson($module->url('questions.save-ai-batch'), ['questions' => [[
            'question' => '',
            'question_image' => $moduleImage,
            'option_a' => 'Ya',
            'option_b' => 'Tidak',
            'correct_answer' => 'a',
        ]]])->assertOk();

        $this->actingAs($this->admin)->postJson($module->url('questions.save-ai-batch'), ['questions' => [[
            'question' => '',
            'option_a' => 'Ya',
            'option_b' => 'Tidak',
            'correct_answer' => 'a',
        ]]])->assertUnprocessable();
        $this->assertSame(2, LearningModuleQuestion::count());
    }

    public function test_training_participant_sees_question_and_option_images(): void
    {
        $training = $this->training();
        $training->update(['is_quiz_active' => true]);
        $divisi = Divisi::first() ?? Divisi::create(['name' => 'Divisi Test']);
        $participant = TrainingParticipant::create([
            'training_id' => $training->id,
            'user_id' => User::factory()->create(['divisi_id' => $divisi->id])->id,
            'token' => 'tok-'.uniqid(),
        ]);
        $training->questions()->create([
            'type' => 'multiple_choice',
            'question' => 'Rambu apa ini?',
            'option_a' => 'Dilarang merokok',
            'option_b' => null,
            'correct_answer' => 'b',
            'question_image' => 'quiz-images/soalabc.png',
            'option_images' => ['b' => 'quiz-images/opsib.png'],
        ]);

        $this->get("/training/portal/{$participant->token}/quiz")
            ->assertOk()
            ->assertSee('quiz-images/soalabc.png')
            ->assertSee('quiz-images/opsib.png')
            ->assertSee('Gambar pilihan B');

        $training->update(['quiz_mode' => 'game']);
        $html = $this->get("/training/portal/{$participant->token}/quiz")->assertOk()->getContent();
        preg_match('/const QUIZ_QUESTIONS = (\[.*?\]);/s', $html, $match);
        $questions = json_decode($match[1], true);
        $this->assertStringContainsString('quiz-images/soalabc.png', $questions[0]['question_image_url']);
        $this->assertStringContainsString('quiz-images/opsib.png', $questions[0]['option_image_urls']['b']);
    }

    public function test_module_manual_form_and_ai_review_accept_images(): void
    {
        $module = LearningModule::create(['title' => 'K3 Rambu', 'passing_score' => 70]);

        $this->actingAs($this->admin)->post($module->url('questions.store'), [
            'question' => 'Pilih rambu wajib helm',
            'option_a' => 'Rambu merah',
            'correct_answer' => 'b',
            'option_images' => ['b' => $this->image()],
        ])->assertSessionHasNoErrors();

        $question = LearningModuleQuestion::sole();
        Storage::disk('public')->assertExists($question->optionImage('b'));
        $this->assertSame(['a', 'b'], array_keys($question->options()));

        $this->actingAs($this->admin)->post($module->url('questions.store'), [
            'question' => 'Kunci ke pilihan kosong',
            'option_a' => 'Ya',
            'option_b' => 'Tidak',
            'correct_answer' => 'c',
        ])->assertSessionHasErrors('correct_answer');

        $optionImage = $this->uploadPath($module->url('questions.upload-image'));
        $this->actingAs($this->admin)->postJson($module->url('questions.save-ai-batch'), ['questions' => [[
            'question' => 'Rambu apa ini?',
            'option_a' => 'Dilarang merokok',
            'option_b' => null,
            'correct_answer' => 'b',
            'option_images' => ['b' => $optionImage],
        ]]])->assertOk()->assertJson(['success' => true]);

        $this->assertSame(['b' => $optionImage], LearningModuleQuestion::latest('id')->first()->option_images);

        // Pilihan B tanpa teks dan tanpa gambar ditolak.
        $this->actingAs($this->admin)->postJson($module->url('questions.save-ai-batch'), ['questions' => [[
            'question' => 'Tanpa B',
            'option_a' => 'Ya',
            'correct_answer' => 'a',
        ]]])->assertUnprocessable();
    }
}
