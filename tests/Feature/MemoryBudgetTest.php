<?php

namespace Tests\Feature;

use App\Exports\ScoreRecapExport;
use App\Exports\TrainingQuestionsExport;
use App\Jobs\AiTask;
use App\Jobs\GenerateExportFile;
use App\Jobs\GenerateTrainingQuestions;
use App\Jobs\ProcessQuestionImport;
use App\Models\Document;
use App\Models\Role;
use App\Models\Training;
use App\Models\User;
use App\Notifications\BackgroundTaskFinished;
use Illuminate\Queue\Events\WorkerStarting;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

/**
 * Request web harus muat di PHP memory_limit 128MB (ditegakkan di public/index.php), dan job berat harus muat di batas
 * memori supervisor Horizon-nya.
 *
 * Tiap kasus berjalan di proses PHP tersendiri dengan data sebesar konstanta di bawah, lalu memory_limit dipasang
 * sebelum request/job. Yang melewati batas mematikan prosesnya (fatal error), sehingga tesnya gagal. Proses tes sudah
 * memuat PHPUnit dan migrasi (sekitar 50MB), jadi batas ini lebih ketat daripada request sungguhan.
 *
 * Halaman detail pelatihan dan halaman jabatan dipaginasi di server, dan form pelatihan hanya mengirim data user ringkas
 * (barisnya dibuat browser), jadi memorinya tidak lagi naik mengikuti jumlah peserta/user. Konstanta di bawah sengaja
 * jauh di atas pemakaian sehari-hari.
 *
 * Set MEMORY_BUDGET_LOG=/path/file untuk mencatat puncak memori tiap kasus.
 */
#[RunTestsInSeparateProcesses]
class MemoryBudgetTest extends TestCase
{
    private const LIMIT = '128M';

    private const USERS = 10000;

    private const PARTICIPANTS = 1000;

    private const OTHER_TRAININGS = 40;

    private const DOCUMENTS = 300;

    private User $admin;

    private Training $training;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('username', 'admin')->firstOrFail();
        $this->seedLargeDataset();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function pages(): array
    {
        return [
            'dashboard' => ['/dashboard'],
            'daftar pelatihan' => ['/training'],
            'form pelatihan baru' => ['/training/create'],
            'detail pelatihan' => ['/training/{training}'],
            'edit pelatihan' => ['/training/{training}/edit'],
            'statistik langsung pelatihan' => ['/training/{training}/live-stats'],
            'soal pelatihan' => ['/training/{training}/questions'],
            'daftar user' => ['/user'],
            'jabatan dan hak akses' => ['/roles'],
            'daftar dokumen' => ['/document'],
            'daftar video' => ['/video'],
            'daftar modul belajar' => ['/learning-module'],
            'form modul belajar baru' => ['/learning-module/create'],
            'rekap nilai' => ['/rekap-nilai'],
            'notifikasi' => ['/notifikasi'],
            'pelatihan saya' => ['/my-trainings'],
        ];
    }

    #[DataProvider('pages')]
    public function test_page_fits_in_the_web_memory_limit(string $uri): void
    {
        $uri = str_replace('{training}', (string) $this->training->id, $uri);

        ini_set('memory_limit', self::LIMIT);
        memory_reset_peak_usage();
        $response = $this->actingAs($this->admin)->get($uri);

        $this->logPeak($uri, strlen((string) $response->getContent()).' bytes html');
        $response->assertOk();
        $this->assertSame(self::LIMIT, ini_get('memory_limit'));
    }

    /**
     * @return array<string, array{0: string, 1: string}> job => [kode, supervisor Horizon yang menjalankannya]
     */
    public static function jobs(): array
    {
        return [
            'export user' => ['users', 'supervisor-heavy'],
            'export rekap pelatihan' => ['training', 'supervisor-heavy'],
            'export rekap nilai' => ['scores', 'supervisor-heavy'],
            'export soal pelatihan' => ['training-questions', 'supervisor-heavy'],
            'import soal pelatihan' => ['question-import', 'supervisor-heavy'],
            'soal AI dari PDF 18MB' => ['ai-pdf', 'supervisor-ai'],
        ];
    }

    /**
     * Job berat harus selesai di batas memori supervisor Horizon yang menjalankannya (opsi "memory", yang dijadikan
     * memory_limit PHP worker oleh AppServiceProvider).
     */
    #[DataProvider('jobs')]
    public function test_queued_job_fits_in_its_worker_memory_limit(string $job, string $supervisor): void
    {
        Storage::fake('local');
        Notification::fake();
        $userId = $this->admin->id;
        $token = AiTask::newToken();
        $documentId = null;

        if ($job === 'question-import') {
            Storage::disk('local')->put('imports/soal.xlsx', Excel::raw(new TrainingQuestionsExport($this->training), ExcelFormat::XLSX));
        }

        if ($job === 'ai-pdf') {
            config(['services.gemini.api_key' => 'test-key']);
            Storage::fake(config('filesystems.default'))->put('dokumen/besar.pdf', str_repeat('x', 18 * 1024 * 1024));
            $documentId = Document::create(['name' => 'PDF besar', 'divisi_id' => 1, 'document_type' => 1, 'path' => 'besar.pdf'])->id;
            Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode(['questions' => [[
                    'question' => 'APD utama?', 'option_a' => 'Helm', 'option_b' => 'Topi', 'option_c' => 'Sandal', 'option_d' => 'Payung',
                    'correct_answer' => 'a',
                ]]])]]]]],
            ])]);
        }

        $limit = config("horizon.defaults.{$supervisor}.memory").'M';
        ini_set('memory_limit', $limit);
        memory_reset_peak_usage();

        match ($job) {
            'users' => (new GenerateExportFile($userId, GenerateExportFile::USERS))->handle(),
            'training' => (new GenerateExportFile($userId, GenerateExportFile::TRAINING, $this->training->id))->handle(),
            'scores' => (new GenerateExportFile($userId, GenerateExportFile::SCORES, null, [
                'from' => now()->subYear()->toDateString(),
                'to' => now()->toDateString(),
                'sections' => [ScoreRecapExport::SECTION_TRAININGS],
                'divisi_id' => null,
            ]))->handle(),
            'training-questions' => (new GenerateExportFile($userId, GenerateExportFile::TRAINING_QUESTIONS, $this->training->id))->handle(),
            'question-import' => (new ProcessQuestionImport($userId, ProcessQuestionImport::TRAINING, $this->training->id, 'imports/soal.xlsx'))->handle(),
            'ai-pdf' => (new GenerateTrainingQuestions($token, [$documentId], 5, 'multiple_choice', 'campuran'))->handle(),
        };

        $this->logPeak('job '.$job.' ('.$limit.')');
        $this->assertSame($limit, ini_get('memory_limit'));

        if ($job === 'ai-pdf') {
            $this->assertSame('done', AiTask::status($token)['status']);
        } else {
            Notification::assertSentTo($this->admin, BackgroundTaskFinished::class, fn (BackgroundTaskFinished $notification) => $notification->status === BackgroundTaskFinished::STATUS_SUCCESS);
        }
    }

    public function test_worker_memory_option_becomes_the_php_memory_limit(): void
    {
        event(new WorkerStarting('redis-long', 'heavy', new WorkerOptions(memory: 512)));

        $this->assertSame('512M', ini_get('memory_limit'));
    }

    public function test_web_entry_point_caps_memory_at_128mb(): void
    {
        $this->assertStringContainsString("ini_set('memory_limit', '".self::LIMIT."');", file_get_contents(public_path('index.php')));
    }

    private function logPeak(string $label, string $extra = ''): void
    {
        if ($log = getenv('MEMORY_BUDGET_LOG')) {
            file_put_contents($log, sprintf("%-44s %6.1f MB %s\n", $label, memory_get_peak_usage(true) / 1048576, $extra), FILE_APPEND);
        }
    }

    private function seedLargeDataset(): void
    {
        $now = now()->toDateTimeString();
        $password = $this->admin->password;
        $staffRole = Role::findByName(User::ROLE_STAFF, 'web')->id;
        $firstUserId = (int) DB::table('users')->max('id') + 1;

        foreach (array_chunk(range($firstUserId, $firstUserId + self::USERS - 1), 500) as $ids) {
            DB::table('users')->insert(array_map(fn (int $id) => [
                'id' => $id,
                'employee_id' => 'EMP'.$id,
                'nama_lengkap' => 'KARYAWAN NOMOR '.$id.' '.Str::upper(Str::random(12)),
                'username' => 'user'.$id,
                'email' => 'user'.$id.'@example.com',
                'no_hp' => '0812'.str_pad((string) $id, 8, '0', STR_PAD_LEFT),
                'password' => $password,
                'divisi_id' => 1,
                'area_id' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ], $ids));

            DB::table('model_has_roles')->insert(array_map(fn (int $id) => [
                'role_id' => $staffRole,
                'model_type' => User::class,
                'model_id' => $id,
            ], $ids));
        }

        $answers = json_encode(array_map(fn (int $i) => ['question_id' => $i, 'answer' => 'a', 'is_correct' => true], range(1, 30)));

        for ($t = 0; $t <= self::OTHER_TRAININGS; $t++) {
            $training = Training::create([
                'title' => 'Pelatihan uji memori '.$t,
                'trainer_id' => $this->admin->id,
                'description' => str_repeat('Deskripsi pelatihan. ', 20),
                'training_date' => now()->subDays($t)->toDateString(),
                'start_time' => '09:00',
                'end_time' => '11:00',
                'zoom_link' => 'https://zoom.us/j/123456789',
                'status' => 'ongoing',
                'is_quiz_active' => true,
                'is_attendance_active' => true,
            ]);

            // Pelatihan pertama yang diuji halamannya; sisanya mengisi daftar, dashboard, dan rekap.
            $count = $t === 0 ? self::PARTICIPANTS : 100;
            $this->training ??= $training;

            $participants = [];
            foreach (range(0, $count - 1) as $i) {
                $participants[] = [
                    'training_id' => $training->id,
                    'user_id' => $firstUserId + $i,
                    'token' => Str::random(40),
                    'attendance_status' => 'hadir',
                    'attended_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach (array_chunk($participants, 250) as $chunk) {
                DB::table('training_participants')->insert($chunk);
            }

            $participantIds = DB::table('training_participants')->where('training_id', $training->id)->pluck('id', 'user_id');
            $results = [];
            foreach ($participantIds as $userId => $participantId) {
                foreach (['pretest', 'posttest'] as $phase) {
                    $results[] = [
                        'training_id' => $training->id,
                        'user_id' => $userId,
                        'training_participant_id' => $participantId,
                        'phase' => $phase,
                        'total_questions' => 30,
                        'correct_answers' => 20,
                        'score' => 40 + ($userId % 60),
                        'answers' => $answers,
                        'submitted_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
            foreach (array_chunk($results, 100) as $chunk) {
                DB::table('training_quiz_results')->insert($chunk);
            }

            DB::table('training_questions')->insert(array_map(fn (int $i) => [
                'training_id' => $training->id,
                'type' => 'multiple_choice',
                'question' => 'Pertanyaan nomor '.$i.' '.str_repeat('isi soal ', 15),
                'option_a' => 'Pilihan A', 'option_b' => 'Pilihan B', 'option_c' => 'Pilihan C', 'option_d' => 'Pilihan D',
                'correct_answer' => 'a',
                'created_at' => $now,
                'updated_at' => $now,
            ], range(1, 30)));
        }

        foreach (array_chunk(range(1, self::DOCUMENTS), 100) as $ids) {
            DB::table('documents')->insert(array_map(fn (int $i) => [
                'id' => $i,
                'name' => 'Dokumen uji memori '.$i,
                'divisi_id' => 1,
                'document_type' => 1,
                'path' => 'dokumen-'.$i.'.pdf',
                'version' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ], $ids));

            $versions = [];
            foreach ($ids as $i) {
                foreach ([1, 2, 3] as $version) {
                    $versions[] = [
                        'document_id' => $i,
                        'version_number' => $version,
                        'file_name' => 'dokumen-'.$i.'-v'.$version.'.pdf',
                        'path' => 'dokumen-'.$i.'-v'.$version.'.pdf',
                        'file_size' => '2 MB',
                        'created_by' => 'admin',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
            DB::table('document_versions')->insert($versions);
        }

        DB::table('videos')->insert(array_map(fn (int $i) => [
            'title' => 'Video uji memori '.$i,
            'video_type' => 'link',
            'video_link' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'views_count' => $i,
            'created_at' => $now,
            'updated_at' => $now,
        ], range(1, 200)));
    }
}
