<?php

namespace Tests\Feature;

use App\Exports\LearningModuleQuestionTemplate;
use App\Exports\TrainingQuestionTemplate;
use App\Exports\UserTemplate;
use App\Jobs\BackgroundTask;
use App\Jobs\GenerateExportFile;
use App\Jobs\ProcessQuestionImport;
use App\Jobs\ProcessUserImport;
use App\Models\LearningModule;
use App\Models\Training;
use App\Models\User;
use App\Notifications\BackgroundTaskFinished;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class BackgroundProcessingTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->admin = User::where('username', 'admin')->firstOrFail();
        $this->admin->forceFill(['email' => 'admin@example.com', 'no_hp' => '081234567890'])->save();
    }

    private function userTemplateUpload(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('users.xlsx', Excel::raw(new UserTemplate, ExcelFormat::XLSX));
    }

    public function test_user_import_is_stored_and_queued_on_the_heavy_queue(): void
    {
        Queue::fake();

        $this->actingAs($this->admin)->post('/user/import', ['file' => $this->userTemplateUpload()])
            ->assertRedirect('/user')
            ->assertSessionHas('info');

        Queue::assertPushedOn(BackgroundTask::QUEUE, ProcessUserImport::class, function (ProcessUserImport $job) {
            Storage::disk('local')->assertExists($job->path);

            return $job->userId === $this->admin->id;
        });
    }

    public function test_user_import_job_reports_the_result_on_every_channel_and_removes_the_upload(): void
    {
        Notification::fake();
        Storage::disk('local')->put('imports/users.xlsx', Excel::raw(new UserTemplate, ExcelFormat::XLSX));

        (new ProcessUserImport($this->admin->id, 'imports/users.xlsx'))->handle();

        Storage::disk('local')->assertMissing('imports/users.xlsx');
        Notification::assertSentTo($this->admin, BackgroundTaskFinished::class, function (BackgroundTaskFinished $notification, array $channels) {
            return $notification->kind === 'user-import'
                && str_starts_with($notification->message, 'Import selesai.')
                && $channels === ['database', 'mail', WhatsAppChannel::class];
        });
    }

    public function test_a_crashing_import_sends_a_failure_notification(): void
    {
        Notification::fake();

        (new ProcessUserImport($this->admin->id, 'imports/missing.xlsx'))->handle();

        Notification::assertSentTo($this->admin, BackgroundTaskFinished::class, fn (BackgroundTaskFinished $notification) => $notification->status === BackgroundTaskFinished::STATUS_FAILED
            && $notification->title === 'Import user gagal');
    }

    public function test_a_job_killed_by_the_worker_still_notifies_the_user(): void
    {
        Notification::fake();
        Storage::disk('local')->put('imports/users.xlsx', 'x');

        (new ProcessUserImport($this->admin->id, 'imports/users.xlsx'))->failed(null);

        Storage::disk('local')->assertMissing('imports/users.xlsx');
        Notification::assertSentTo($this->admin, BackgroundTaskFinished::class, fn (BackgroundTaskFinished $notification) => $notification->status === BackgroundTaskFinished::STATUS_FAILED);
    }

    public function test_channels_follow_the_users_contact_details(): void
    {
        $notification = new BackgroundTaskFinished('user-import', BackgroundTaskFinished::STATUS_SUCCESS, 'Judul', 'Pesan');

        $this->assertSame(['database'], $notification->via(User::factory()->make(['email' => null, 'no_hp' => null])));
        $this->assertSame(['database', 'mail'], $notification->via(User::factory()->make(['email' => 'a@example.com', 'no_hp' => null])));
    }

    public function test_finished_export_is_sent_to_whatsapp_and_is_downloadable_only_by_its_owner(): void
    {
        $this->actingAs($this->admin)->post('/user/export')->assertSessionHas('info');

        $notification = $this->admin->notifications()->firstOrFail();
        $this->assertSame(GenerateExportFile::USERS, $notification->data['kind']);
        Storage::disk('local')->assertExists($notification->data['download_path']);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'whatsapp.test')
            && str_contains($request['message']['text'], 'EXPORT USER SELESAI')
            && $request['recipient']['value'] === '081234567890');

        $other = User::factory()->staff()->create();
        $this->actingAs($other)->get(route('notifications.download', $notification->id))->assertNotFound();

        $this->actingAs($this->admin)->get(route('notifications.download', $notification->id))
            ->assertOk()
            ->assertDownload($notification->data['download_name']);
    }

    private function training(): Training
    {
        return Training::create([
            'title' => 'K3 Dasar',
            'trainer_id' => $this->admin->id,
            'training_date' => now()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'zoom_link' => 'https://zoom.us/test',
            'status' => 'scheduled',
        ]);
    }

    public function test_question_import_is_stored_and_queued_on_the_heavy_queue(): void
    {
        Queue::fake();
        $training = $this->training();
        $module = LearningModule::create(['title' => 'Modul K3', 'passing_score' => 70]);

        $this->actingAs($this->admin)
            ->post(route('training.questions.import-excel', $training), ['file' => UploadedFile::fake()->createWithContent('soal.xlsx', Excel::raw(new TrainingQuestionTemplate, ExcelFormat::XLSX))])
            ->assertSessionHas('info');
        $this->actingAs($this->admin)
            ->post(route('learning-module.questions.import-excel', $module), ['file' => UploadedFile::fake()->createWithContent('soal.xlsx', Excel::raw(new LearningModuleQuestionTemplate, ExcelFormat::XLSX))])
            ->assertSessionHas('info');

        foreach ([[ProcessQuestionImport::TRAINING, $training->id], [ProcessQuestionImport::LEARNING_MODULE, $module->id]] as [$type, $id]) {
            Queue::assertPushedOn(BackgroundTask::QUEUE, ProcessQuestionImport::class, function (ProcessQuestionImport $job) use ($type, $id) {
                if ($job->type !== $type) {
                    return false;
                }
                Storage::disk('local')->assertExists($job->path);

                return $job->userId === $this->admin->id && $job->modelId === $id;
            });
        }

        $this->assertSame(0, $training->questions()->count());
    }

    public function test_question_import_job_reports_how_many_questions_were_added_and_removes_the_upload(): void
    {
        Notification::fake();
        $training = $this->training();
        Storage::disk('local')->put('imports/soal.xlsx', Excel::raw(new TrainingQuestionTemplate, ExcelFormat::XLSX));

        (new ProcessQuestionImport($this->admin->id, ProcessQuestionImport::TRAINING, $training->id, 'imports/soal.xlsx'))->handle();

        $count = $training->questions()->count();
        $this->assertGreaterThan(0, $count);
        Storage::disk('local')->assertMissing('imports/soal.xlsx');
        Notification::assertSentTo($this->admin, BackgroundTaskFinished::class, fn (BackgroundTaskFinished $notification) => $notification->kind === ProcessQuestionImport::TRAINING
            && $notification->status === BackgroundTaskFinished::STATUS_SUCCESS
            && str_contains($notification->message, "Berhasil mengimpor {$count} soal")
            && str_contains($notification->message, 'K3 Dasar'));
    }

    public function test_an_invalid_row_cancels_the_whole_question_import_and_names_the_row(): void
    {
        Notification::fake();
        $training = $this->training();
        Storage::disk('local')->put('imports/soal.csv', "tipe_soal,pertanyaan,kunci_jawaban,pilihan_a,pilihan_b\n"
            ."PG,APD utama?,A,Helm,Topi\n"
            ."PG,Warna jalur evakuasi?,Z,Merah,Hijau\n");

        (new ProcessQuestionImport($this->admin->id, ProcessQuestionImport::TRAINING, $training->id, 'imports/soal.csv'))->handle();

        $this->assertSame(0, $training->questions()->count());
        Storage::disk('local')->assertMissing('imports/soal.csv');
        Notification::assertSentTo($this->admin, BackgroundTaskFinished::class, fn (BackgroundTaskFinished $notification) => $notification->status === BackgroundTaskFinished::STATUS_FAILED
            && $notification->title === 'Import soal gagal'
            && str_contains($notification->message, 'Baris ke-3')
            && str_contains($notification->message, 'Tidak ada soal dari file ini yang disimpan.'));
    }
}
