<?php

namespace Tests\Feature;

use App\Exports\ScoreRecapExport;
use App\Exports\TrainingScoresSheet;
use App\Models\LearningModule;
use App\Models\LearningModuleAttempt;
use App\Models\Training;
use App\Models\User;
use App\Models\Video;
use App\Services\MaterialQuizService;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ScoreRecapTest extends TestCase
{
    private User $admin;

    private User $budi;

    private User $siti;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('username', 'admin')->sole();
        $this->budi = User::factory()->staff()->create(['nama_lengkap' => 'BUDI', 'divisi_id' => 1]);
        $this->siti = User::factory()->staff()->create(['nama_lengkap' => 'SITI', 'divisi_id' => 2]);

        // Pelatihan hari ini (masuk rentang) dan tahun lalu (di luar rentang).
        $this->training('K3 Gudang', now());
        $this->training('Pelatihan Lama', now()->subYear());

        // Modul Belajar dikerjakan Budi, kuis video dikerjakan Siti.
        $module = LearningModule::create(['title' => 'Modul SOP', 'passing_score' => 70]);
        $module->questions()->create(['question' => 'APD?', 'option_a' => 'Helm', 'option_b' => 'Topi', 'correct_answer' => 'a']);
        $questionId = $module->questions()->value('id');
        LearningModuleAttempt::record($module, $this->budi, 'pretest', [$questionId => 'b']);
        LearningModuleAttempt::record($module, $this->budi, 'posttest', [$questionId => 'a']);

        $video = Video::create(['title' => 'Video K3', 'video_type' => 'link', 'video_link' => 'https://youtu.be/dQw4w9WgXcQ']);
        $quiz = app(MaterialQuizService::class)->sync($video, ['pretest' => true, 'posttest' => true]);
        $quiz->questions()->create(['question' => 'Rambu?', 'option_a' => 'Awas', 'option_b' => 'Aman', 'correct_answer' => 'a']);
        LearningModuleAttempt::record($quiz, $this->siti, 'posttest', [$quiz->questions()->value('id') => 'b']);
    }

    private function training(string $title, $date): void
    {
        $training = Training::create([
            'title' => $title, 'trainer_id' => $this->admin->id, 'training_date' => $date->toDateString(),
            'start_time' => '09:00:00', 'end_time' => '11:00:00', 'zoom_link' => 'https://zoom.us/test', 'status' => 'completed',
        ]);

        foreach ([$this->budi, $this->siti] as $user) {
            $participant = $training->participants()->create([
                'user_id' => $user->id, 'token' => uniqid('t', true), 'attendance_status' => 'hadir',
            ]);
            $participant->quizResult()->create([
                'training_id' => $training->id, 'user_id' => $user->id, 'total_questions' => 5, 'correct_answers' => 4, 'score' => 80, 'mc_score' => 80, 'essay_status' => 'none', 'submitted_at' => now(),
            ]);
        }
    }

    /**
     * @return array{trainings: list<array>, modules: list<array>}
     */
    private function exportRows(array $payload): array
    {
        Excel::fake();
        $this->actingAs($this->admin)->post(route('score-recap.export'), $payload)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('info');

        $notification = $this->admin->notifications()->firstOrFail();
        $this->assertSame('Export rekap nilai selesai', $notification->data['title']);

        $rows = ['trainings' => [], 'modules' => []];
        Excel::assertStored($notification->data['download_path'], 'local', function (ScoreRecapExport $export) use (&$rows) {
            foreach ($export->sheets() as $sheet) {
                $rows[$sheet instanceof TrainingScoresSheet ? 'trainings' : 'modules'] = $sheet->array();
            }

            return true;
        });

        return $rows;
    }

    public function test_admin_exports_training_and_pretest_posttest_scores_in_one_file(): void
    {
        $this->actingAs($this->admin)->get(route('score-recap.index'))
            ->assertOk()
            ->assertSee('Pretest &amp; posttest Kuis Video', false);

        $rows = $this->exportRows([
            'from' => now()->subDay()->toDateString(),
            'to' => now()->toDateString(),
            'sections' => ['trainings', 'standalone', 'video', 'document'],
        ]);

        $this->assertSame([['K3 Gudang', 'BUDI', 80.0], ['K3 Gudang', 'SITI', 80.0]], array_map(fn ($row) => [$row[1], $row[4], $row[11]], $rows['trainings']));
        $this->assertSame(
            [['Modul Belajar', 'Modul SOP', 'BUDI', 0.0, 100.0, 'Lulus'], ['Kuis Video', 'Video K3', 'SITI', '-', 0.0, 'Belum Lulus']],
            array_map(fn ($row) => [$row[0], $row[1], $row[4], $row[7], $row[9], $row[12]], $rows['modules']),
        );
    }

    public function test_filters_by_division_and_section(): void
    {
        $rows = $this->exportRows([
            'from' => now()->subDay()->toDateString(),
            'to' => now()->toDateString(),
            'sections' => ['trainings', 'video'],
            'divisi_id' => 1,
        ]);

        $this->assertSame(['BUDI'], array_column($rows['trainings'], 4));
        $this->assertSame([], $rows['modules']); // Budi only took the standalone module, which was not selected.
    }

    public function test_the_file_is_generated_and_downloadable(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin)->post(route('score-recap.export'), [
            'from' => now()->subMonth()->toDateString(),
            'to' => now()->toDateString(),
            'sections' => ['trainings', 'standalone'],
        ])->assertSessionHas('info');

        $notification = $this->admin->notifications()->firstOrFail();
        $this->actingAs($this->admin)->get(route('notifications.download', $notification->id))
            ->assertOk()
            ->assertDownload($notification->data['download_name']);
    }

    public function test_sections_follow_the_users_permissions(): void
    {
        $trainingManager = User::factory()->staff()->create();
        $trainingManager->givePermissionTo('manage-trainings');

        $this->actingAs($trainingManager)->get(route('score-recap.index'))
            ->assertOk()
            ->assertSee('section_trainings', false)
            ->assertDontSee('section_video', false);

        $this->actingAs($trainingManager)->post(route('score-recap.export'), [
            'from' => now()->toDateString(), 'to' => now()->toDateString(), 'sections' => ['video'],
        ])->assertSessionHasErrors('sections.0');

        $this->actingAs($this->budi)->getJson(route('score-recap.index'))->assertForbidden();
        $this->actingAs($this->budi)->postJson(route('score-recap.export'), [
            'from' => now()->toDateString(), 'to' => now()->toDateString(), 'sections' => ['trainings'],
        ])->assertForbidden();
    }

    public function test_date_range_must_be_valid(): void
    {
        $this->actingAs($this->admin)->post(route('score-recap.export'), [
            'from' => now()->toDateString(), 'to' => now()->subDay()->toDateString(), 'sections' => ['trainings'],
        ])->assertSessionHasErrors('to');
    }
}
