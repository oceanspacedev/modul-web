<?php

namespace Tests\Feature;

use App\Exports\ScoreRecapExport;
use App\Exports\TrainingScoresSheet;
use App\Models\LearningModule;
use App\Models\LearningModuleAttempt;
use App\Models\Training;
use App\Models\TrainingEvaluation;
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
                // Baris pelatihan dipetakan ke judul kolomnya, karena kolomnya mengikuti pengaturan pelatihan.
                $rows[$sheet instanceof TrainingScoresSheet ? 'trainings' : 'modules'] = $sheet instanceof TrainingScoresSheet
                    ? array_map(fn (array $row) => array_combine($sheet->headings(), $row), $sheet->array())
                    : $sheet->array();
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

        $this->assertSame([['K3 Gudang', 'BUDI', 80.0], ['K3 Gudang', 'SITI', 80.0]], array_map(fn ($row) => [$row['Pelatihan'], $row['Nama Peserta'], $row['Nilai Posttest (0-100)']], $rows['trainings']));
        $this->assertSame(
            [['Modul Belajar', 'Modul SOP', 'BUDI', 0.0, 100.0, 'Lulus'], ['Kuis Video', 'Video K3', 'SITI', '-', 0.0, 'Belum Lulus']],
            array_map(fn ($row) => [$row[0], $row[1], $row[4], $row[7], $row[9], $row[12]], $rows['modules']),
        );
    }

    public function test_training_columns_follow_the_active_settings_of_the_trainings(): void
    {
        $payload = ['from' => now()->subDay()->toDateString(), 'to' => now()->toDateString(), 'sections' => ['trainings']];
        $base = ['Tanggal', 'Pelatihan', 'Pemateri', 'ID Karyawan', 'Nama Peserta', 'Divisi'];
        // Pretest dan posttest memakai kolom yang sama persis.
        $phase = fn (string $label) => ["Status {$label}", "Nilai {$label} (0-100)", "Waktu Submit {$label}"];
        $posttest = $phase('Posttest');

        // Pelatihan posttest saja: tidak ada kolom pretest atau evaluasi.
        $this->assertSame([...$base, 'Kehadiran', ...$posttest], array_keys($this->exportRows($payload)['trainings'][0]));

        // Pretest dan form evaluasi dinyalakan, evaluasi diurutkan sesudah posttest: kolomnya ikut, dalam urutan itu.
        $training = Training::where('title', 'K3 Gudang')->sole();
        $training->update(['has_pretest' => true, 'has_evaluation' => true, 'portal_steps' => ['pretest', 'session', 'attendance', 'posttest', 'evaluation']]);
        $budi = $training->participants()->where('user_id', $this->budi->id)->sole();
        $budi->pretestResult()->create([
            'training_id' => $training->id, 'user_id' => $this->budi->id, 'phase' => 'pretest', 'total_questions' => 5, 'correct_answers' => 1,
            'score' => 20, 'mc_score' => 20, 'essay_status' => 'none', 'submitted_at' => now(),
        ]);
        TrainingEvaluation::create([
            'training_id' => $training->id, 'training_participant_id' => $budi->id, 'user_id' => $this->budi->id, 'submitted_at' => now(),
            'answers' => $training->evaluationQuestions()->get()->filter->isScale()->mapWithKeys(fn ($question) => [$question->id => 4])->all(),
        ]);

        $this->admin->notifications()->delete();
        $rows = $this->exportRows($payload)['trainings'];
        $this->assertSame([
            ...$base,
            ...$phase('Pretest'),
            'Kehadiran',
            ...$posttest,
            'Status Evaluasi', 'Rata-rata Evaluasi (1-5)', 'Waktu Isi Evaluasi',
        ], array_keys($rows[0]));

        $this->assertSame(['Sudah Mengerjakan', 20.0, 80.0, 'Sudah Mengisi', 4.0], array_values(array_intersect_key($rows[0], array_flip([
            'Status Pretest', 'Nilai Pretest (0-100)', 'Nilai Posttest (0-100)', 'Status Evaluasi', 'Rata-rata Evaluasi (1-5)',
        ]))));
        $this->assertSame(['Belum Mengerjakan', '-', 80.0, 'Belum Mengisi', '-'], array_values(array_intersect_key($rows[1], array_flip([
            'Status Pretest', 'Nilai Pretest (0-100)', 'Nilai Posttest (0-100)', 'Status Evaluasi', 'Rata-rata Evaluasi (1-5)',
        ]))));

        // Soal essay di posttest: nilainya dipecah menjadi PG, essay, dan akhir; pretest tetap satu kolom nilai.
        $training->questions()->create(['type' => 'essay', 'question' => 'Jelaskan APD.', 'used_in' => 'posttest']);
        $this->admin->notifications()->delete();
        $headings = array_keys($this->exportRows($payload)['trainings'][0]);
        $this->assertContains('Nilai Pretest (0-100)', $headings);
        $this->assertNotContains('Nilai PG Pretest (0-100)', $headings);
        $this->assertSame(
            ['Status Posttest', 'Nilai PG Posttest (0-100)', 'Status Essay Posttest', 'Nilai Essay Posttest (0-100)', 'Nilai Akhir Posttest (0-100)', 'Waktu Submit Posttest'],
            array_values(array_filter($headings, fn (string $heading) => str_contains($heading, 'Posttest'))),
        );
        $training->questions()->delete();

        // Pelatihan lain di rentang yang sama tanpa pretest/evaluasi mengisi kolom itu dengan "-".
        $this->training('Sosialisasi Ringkas', now());
        $this->admin->notifications()->delete();
        $plain = collect($this->exportRows($payload)['trainings'])->firstWhere('Pelatihan', 'Sosialisasi Ringkas');
        $this->assertSame(['-', '-', 'Sudah Mengerjakan'], [$plain['Status Pretest'], $plain['Status Evaluasi'], $plain['Status Posttest']]);
    }

    public function test_filters_by_division_and_section(): void
    {
        $rows = $this->exportRows([
            'from' => now()->subDay()->toDateString(),
            'to' => now()->toDateString(),
            'sections' => ['trainings', 'video'],
            'divisi_id' => 1,
        ]);

        $this->assertSame(['BUDI'], array_column($rows['trainings'], 'Nama Peserta'));
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
