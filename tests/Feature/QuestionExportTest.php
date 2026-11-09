<?php

namespace Tests\Feature;

use App\Exports\LearningModuleQuestionsExport;
use App\Exports\TrainingQuestionsExport;
use App\Models\LearningModule;
use App\Models\Training;
use App\Models\User;
use App\Models\Video;
use App\Services\MaterialQuizService;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class QuestionExportTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('username', 'admin')->sole();
    }

    private function training(string $title): Training
    {
        return Training::create([
            'title' => $title,
            'trainer_id' => $this->admin->id,
            'training_date' => now()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'zoom_link' => 'https://zoom.us/test',
            'status' => 'scheduled',
        ]);
    }

    private function module(string $title): LearningModule
    {
        return LearningModule::create(['title' => $title, 'passing_score' => 70]);
    }

    private function seedTrainingQuestions(Training $training): void
    {
        $training->questions()->create([
            'type' => 'multiple_choice', 'question' => 'APD utama?', 'option_a' => 'Helm', 'option_b' => 'Topi',
            'option_c' => 'Sandal', 'correct_answer' => 'c', 'explanation' => 'Pembahasan',
        ]);
        $training->questions()->create([
            'type' => 'essay', 'question' => 'Jelaskan alur evakuasi!', 'correct_answer' => 'Ikuti jalur hijau.',
        ]);
        $training->questions()->create([
            'type' => 'multiple_choice', 'question' => 'Rambu ini artinya?', 'option_a' => 'Awas', 'option_b' => 'Aman',
            'correct_answer' => 'a', 'question_image' => 'quiz-images/rambu.jpg',
        ]);
    }

    public function test_training_questions_export_with_answer_keys_in_template_format(): void
    {
        $training = $this->training('K3 Gudang');
        $this->seedTrainingQuestions($training);

        Excel::fake();
        $this->actingAs($this->admin)->get(route('training.questions.export', $training))->assertOk();

        Excel::assertDownloaded('soal_pelatihan_k3-gudang.xlsx', function (TrainingQuestionsExport $export) {
            $rows = $export->collection()->map(fn ($question) => $export->map($question))->all();

            $headings = $export->headings();

            return $headings[0] === 'tipe_soal'
                && $headings[10] === 'catatan'
                && $rows[0] === ['PG', 'APD utama?', 'C', 'Helm', 'Topi', 'Sandal', '', '', '', 'Pembahasan', '']
                && $rows[1] === ['ESSAY', 'Jelaskan alur evakuasi!', 'Ikuti jalur hijau.', '', '', '', '', '', '', '', '']
                && $rows[2][10] === TrainingQuestionsExport::IMAGE_NOTE;
        });
    }

    public function test_exported_training_questions_can_be_imported_into_another_training(): void
    {
        $source = $this->training('Sumber');
        $this->seedTrainingQuestions($source);
        $target = $this->training('Tujuan');

        $file = UploadedFile::fake()->createWithContent('soal.xlsx', Excel::raw(new TrainingQuestionsExport($source), ExcelFormat::XLSX));
        $this->actingAs($this->admin)->post(route('training.questions.import-excel', $target), ['file' => $file])
            ->assertSessionHas('success');

        $columns = ['type', 'question', 'option_a', 'option_b', 'option_c', 'correct_answer', 'explanation'];
        $this->assertSame(
            $source->questions()->orderBy('id')->get($columns)->toArray(),
            $target->questions()->orderBy('id')->get($columns)->toArray(),
        );
    }

    public function test_module_questions_round_trip_in_order(): void
    {
        $source = $this->module('K3 Dasar');
        $source->questions()->create(['question' => 'Kedua', 'option_a' => 'X', 'option_b' => 'Y', 'correct_answer' => 'b', 'sort_order' => 2]);
        $source->questions()->create(['question' => 'Pertama', 'option_a' => 'Helm', 'option_b' => 'Topi', 'option_e' => 'Rompi', 'correct_answer' => 'e', 'explanation' => 'APD', 'sort_order' => 1]);

        $target = $this->module('Modul Tujuan');
        $file = UploadedFile::fake()->createWithContent('soal.xlsx', Excel::raw(new LearningModuleQuestionsExport($source), ExcelFormat::XLSX));
        $this->actingAs($this->admin)->post(route('learning-module.questions.import-excel', $target), ['file' => $file])
            ->assertSessionHas('success');

        $this->assertSame(['Pertama', 'Kedua'], $target->questions()->pluck('question')->all());
        $this->assertSame(['e', 'b'], $target->questions()->pluck('correct_answer')->all());

        Excel::fake();
        $this->actingAs($this->admin)->get(route('learning-module.questions.export', $source))->assertOk();
        Excel::assertDownloaded('soal_modul_k3-dasar.xlsx', function (LearningModuleQuestionsExport $export) {
            $first = $export->map($export->collection()->first());

            return $first === ['Pertama', 'E', 'Helm', 'Topi', '', '', 'Rompi', 'APD', ''];
        });
    }

    public function test_video_quiz_questions_can_be_exported(): void
    {
        $video = Video::create(['title' => 'K3 Gudang', 'video_type' => 'link', 'video_link' => 'https://youtu.be/dQw4w9WgXcQ']);
        $quiz = app(MaterialQuizService::class)->sync($video, ['pretest' => true, 'posttest' => true]);
        $quiz->questions()->create(['question' => 'APD utama?', 'option_a' => 'Helm', 'option_b' => 'Topi', 'correct_answer' => 'a']);

        $this->actingAs($this->admin)->get(route('video-quiz.questions.export', $quiz))
            ->assertOk()
            ->assertDownload();

        $this->actingAs($this->admin)->get(route('video-quiz.questions.index', $quiz))
            ->assertOk()
            ->assertSee(route('video-quiz.questions.export', $quiz));
    }

    public function test_staff_cannot_export_questions(): void
    {
        $staff = User::factory()->staff()->create();
        $training = $this->training('Rahasia');
        $module = $this->module('Rahasia');

        // JSON requests surface the 403; browsers are sent back with a flash message instead.
        $this->actingAs($staff)->getJson(route('training.questions.export', $training))->assertForbidden();
        $this->actingAs($staff)->getJson(route('learning-module.questions.export', $module))->assertForbidden();
    }
}
