<?php

namespace Tests\Feature;

use App\Exports\TrainingParticipantTemplate;
use App\Jobs\BackgroundTask;
use App\Jobs\ProcessTrainingParticipantImport;
use App\Jobs\SendTrainingInvitation;
use App\Models\Divisi;
use App\Models\Position;
use App\Models\Training;
use App\Models\TrainingEvaluation;
use App\Models\TrainingParticipant;
use App\Models\TrainingQuizResult;
use App\Models\User;
use App\Notifications\BackgroundTaskFinished;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Import peserta pelatihan dari Excel (sekaligus memutakhirkan master User) dan mengeluarkan peserta dari pelatihan.
 */
class TrainingParticipantImportTest extends TestCase
{
    private User $admin;

    private Training $training;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Notification::fake();

        $this->admin = User::where('username', 'admin')->firstOrFail();
        $this->training = Training::create([
            'title' => 'Product Knowledge Retail',
            'trainer_id' => $this->admin->id,
            'training_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'zoom_link' => 'https://zoom.us/j/123',
        ]);
    }

    /**
     * Simpan file xlsx dengan judul kolom seperti file karyawan HR, lalu jalankan job import-nya.
     *
     * @param  list<list<mixed>>  $rows
     */
    private function import(array $rows, ?User $actor = null, bool $sendWa = false): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'peserta');
        (new Xlsx($spreadsheet))->save($path);
        Storage::disk('local')->put('imports/peserta.xlsx', file_get_contents($path));
        unlink($path);

        (new ProcessTrainingParticipantImport(($actor ?? $this->admin)->id, $this->training->id, 'imports/peserta.xlsx', $sendWa))->handle();
    }

    private function sentNotification(User $to): BackgroundTaskFinished
    {
        $sent = null;
        Notification::assertSentTo($to, BackgroundTaskFinished::class, function (BackgroundTaskFinished $notification) use (&$sent) {
            $sent = $notification;

            return true;
        });

        return $sent;
    }

    private function participantIds(): array
    {
        return $this->training->participants()->pluck('user_id')->sort()->values()->all();
    }

    public function test_upload_is_queued_on_the_heavy_queue_and_only_for_training_managers(): void
    {
        Queue::fake();
        $file = fn () => UploadedFile::fake()->createWithContent('peserta.xlsx', Excel::raw(new TrainingParticipantTemplate, ExcelFormat::XLSX));
        $trainer = User::factory()->staff()->trainer()->create();

        $this->actingAs($trainer)
            ->post(route('training.participants.import', $this->training), ['file' => $file(), 'send_wa' => '1'])
            ->assertRedirect(route('training.show', $this->training).'#peserta')
            ->assertSessionHas('info', BackgroundTask::QUEUED_MESSAGE);

        Queue::assertPushedOn(BackgroundTask::QUEUE, ProcessTrainingParticipantImport::class, function (ProcessTrainingParticipantImport $job) use ($trainer) {
            Storage::disk('local')->assertExists($job->path);

            return $job->userId === $trainer->id && $job->trainingId === $this->training->id && $job->sendInvitations;
        });

        $this->actingAs(User::factory()->staff()->create())
            ->post(route('training.participants.import', $this->training), ['file' => $file()]);
        Queue::assertPushed(ProcessTrainingParticipantImport::class, 1);
    }

    public function test_template_has_the_hr_columns_plus_the_required_phone_column(): void
    {
        $response = $this->actingAs($this->admin)->get(route('training.participants.template', $this->training))->assertOk();

        $this->assertStringContainsString('template_peserta_product-knowledge-retail.xlsx', $response->headers->get('content-disposition'));
        $this->assertSame(['Employee ID', 'Full Name', 'No HP', 'Job Position', 'Divisi'], (new TrainingParticipantTemplate)->headings());
    }

    public function test_import_adds_participants_by_employee_id_and_refreshes_master_user_data(): void
    {
        $retail = Divisi::create(['name' => 'RETAIL', 'area_id' => 1]);
        $gunawan = User::factory()->staff()->create(['employee_id' => '2016.06.13.04', 'nama_lengkap' => 'GUNAWAN J', 'no_hp' => '081100000001']);
        $rudi = User::factory()->staff()->create(['employee_id' => '2019.01.14.01', 'nama_lengkap' => 'RUDI HARTONO', 'no_hp' => '081100000002']);
        $kevin = User::factory()->staff()->create(['employee_id' => '2019.07.15.02', 'nama_lengkap' => 'KEVIN RISMAYUDA']);
        $resigned = User::factory()->staff()->create(['employee_id' => '2020.01.01.01', 'no_hp' => '081100000009']);
        $resigned->delete();
        TrainingParticipant::createFor($this->training, $rudi->id);

        $this->import([
            ['Employee ID', 'Full Name', 'RSC', 'Job Position', 'DETAIL CABANG', 'No HP', 'Divisi'],
            // No. HP yang diketik sebagai angka (nol di depan hilang) tetap terbaca.
            ['2016.06.13.04', 'gunawan  junaedi', 'WILLIAM SURYA PUTRA', 'RETAIL STORE COORDINATOR (WJ1)', 'OFFICE CIREBON', 81299990001, 'retail'],
            // Sudah peserta: tidak didobel, datanya tetap disamakan.
            ['2019.01.14.01', 'RUDI HARTONO', 'WILLIAM SURYA PUTRA', 'RETAIL STORE COORDINATOR (CJ1)', 'CS KROYA', '0812-9999-0002', ''],
            // User tanpa No. HP di master mendapat nomornya dari file.
            ['2019.07.15.02', 'KEVIN RISMAYUDA', 'UCI SANUSI', 'GADGET SPECIALIST LEADER (WJ2)', 'CS PATROL', '+62 812 9999 0003', ''],
            ['2099.01.01.01', 'ORANG BARU', 'UCI SANUSI', 'HEAD STORE (WJ1)', 'CS CILEDUG', '081299990004', ''],
            ['2019.09.13.02', 'TANPA HP', 'UCI SANUSI', 'GADGET SPECIALIST (WJ1)', 'HPMART SINDANG', '', ''],
            ['2016.06.13.04', 'GUNAWAN JUNAEDI', '', '', '', '081299990001', ''],
            ['2020.01.01.01', 'SUDAH KELUAR', '', '', '', '081100000009', ''],
            [null, null, null, null, null, null, null],
        ]);

        $this->assertSame(collect([$gunawan->id, $rudi->id, $kevin->id])->sort()->values()->all(), $this->participantIds());

        $gunawan->refresh();
        $this->assertSame('GUNAWAN JUNAEDI', $gunawan->nama_lengkap);
        $this->assertSame('081299990001', $gunawan->no_hp);
        $this->assertSame($retail->id, (int) $gunawan->divisi_id);
        $this->assertSame('RETAIL STORE COORDINATOR (WJ1)', $gunawan->position->name);
        $this->assertSame('081299990002', $rudi->fresh()->no_hp);
        $this->assertSame('RETAIL STORE COORDINATOR (CJ1)', $rudi->fresh()->position->name);
        $this->assertSame('081299990003', $kevin->fresh()->no_hp);
        $this->assertSame(1, Position::where('name', 'RETAIL STORE COORDINATOR (WJ1)')->count());
        $this->assertNull(User::where('employee_id', '2099.01.01.01')->first(), 'Karyawan yang belum ada di master tidak dibuat.');

        $notification = $this->sentNotification($this->admin);
        $this->assertSame(BackgroundTaskFinished::STATUS_PARTIAL, $notification->status);
        $this->assertStringContainsString('2 ditambahkan, 1 sudah terdaftar, 3 data user diperbarui', $notification->message);
        $this->assertStringContainsString('Ditolak: 4 baris', $notification->message);
        $this->assertCount(4, $notification->errors);
        $this->assertStringContainsString('Baris 5: Employee ID 2099.01.01.01 tidak ditemukan di master User', $notification->errors[0]);
        $this->assertStringContainsString('Baris 6: No. HP wajib diisi', $notification->errors[1]);
        $this->assertStringContainsString('Baris 7: GUNAWAN JUNAEDI (2016.06.13.04) sudah ada di baris 2', $notification->errors[2]);
        $this->assertStringContainsString('Baris 8:', $notification->errors[3]);
        $this->assertStringContainsString('sudah dinonaktifkan', $notification->errors[3]);
    }

    public function test_a_file_without_a_phone_column_is_refused_entirely(): void
    {
        User::factory()->staff()->create(['employee_id' => '2016.06.13.04', 'no_hp' => '081100000001']);

        // Persis kolom file karyawan HR tanpa No. HP.
        $this->import([
            ['Employee ID', 'Full Name', 'RSC', 'Job Position', 'DETAIL CABANG'],
            ['2016.06.13.04', 'GUNAWAN JUNAEDI', 'WILLIAM SURYA PUTRA', 'RETAIL STORE COORDINATOR (WJ1)', 'OFFICE CIREBON'],
        ]);

        $this->assertSame([], $this->participantIds());
        $notification = $this->sentNotification($this->admin);
        $this->assertSame(BackgroundTaskFinished::STATUS_FAILED, $notification->status);
        $this->assertStringContainsString('Kolom No. HP tidak ditemukan', $notification->message);
        Storage::disk('local')->assertMissing('imports/peserta.xlsx');
    }

    public function test_trainer_without_user_access_adds_participants_but_cannot_change_master_data(): void
    {
        $trainer = User::factory()->staff()->trainer()->create();
        $budi = User::factory()->staff()->create(['employee_id' => 'E-1', 'nama_lengkap' => 'BUDI', 'no_hp' => '081100000001']);
        $sari = User::factory()->staff()->create(['employee_id' => 'E-2', 'nama_lengkap' => 'SARI']);

        $this->import([
            ['Employee ID', 'Full Name', 'No HP', 'Job Position'],
            ['E-1', 'BUDI SANTOSO', '081299990001', 'HEAD STORE'],
            // Tanpa nomor di master dan tidak boleh mengisinya: tidak bisa menerima undangan, jadi ditolak.
            ['E-2', 'SARI', '081299990002', ''],
        ], $trainer);

        $this->assertSame([$budi->id], $this->participantIds());
        $budi->refresh();
        $this->assertSame('BUDI', $budi->nama_lengkap);
        $this->assertSame('081100000001', $budi->no_hp);
        $this->assertNull($budi->position_id);
        $this->assertNull($sari->fresh()->no_hp);
        $this->assertSame(0, Position::count());

        $notification = $this->sentNotification($trainer);
        $this->assertStringContainsString('1 ditambahkan, 0 sudah terdaftar, 0 data user diperbarui', $notification->message);
        $this->assertStringContainsString('Baris 3: SARI (E-2) tidak punya No. HP di master User', $notification->errors[0]);
        $this->assertStringContainsString('Baris 2 (BUDI (E-1), tetap menjadi peserta)', $notification->errors[1]);
        $this->assertStringContainsString('hanya bisa diubah admin', $notification->errors[1]);
        $this->assertStringContainsString('di luar cakupan akses data Anda', $notification->errors[1]);
    }

    public function test_phone_already_used_by_someone_else_is_not_copied(): void
    {
        $owner = User::factory()->staff()->create(['nama_lengkap' => 'PEMILIK NOMOR', 'no_hp' => '081299990001']);
        $budi = User::factory()->staff()->create(['employee_id' => 'E-1', 'no_hp' => '081100000001']);

        $this->import([
            ['Employee ID', 'No HP'],
            ['E-1', '081299990001'],
        ]);

        $this->assertSame([$budi->id], $this->participantIds());
        $this->assertSame('081100000001', $budi->fresh()->no_hp);
        $this->assertSame('081299990001', $owner->fresh()->no_hp);
        $this->assertStringContainsString('sudah dipakai PEMILIK NOMOR', $this->sentNotification($this->admin)->errors[0]);
    }

    public function test_phone_of_an_archived_user_is_not_copied_and_does_not_block_phone_matching(): void
    {
        $archived = User::factory()->staff()->create(['nama_lengkap' => 'SUDAH KELUAR', 'no_hp' => '081299990001']);
        $archived->delete();
        $budi = User::factory()->staff()->create(['employee_id' => 'E-1', 'no_hp' => '081100000001']);
        $sari = User::factory()->staff()->create(['no_hp' => '081299990001']);

        $this->import([
            ['Employee ID', 'No HP'],
            ['E-1', '081299990001'],
            // Tanpa Employee ID: nomor yang juga tersimpan di user nonaktif tetap cocok ke user aktifnya.
            ['', '081299990001'],
        ]);

        $this->assertSame('081100000001', $budi->fresh()->no_hp);
        $this->assertSame(collect([$budi->id, $sari->id])->sort()->values()->all(), $this->participantIds());
    }

    public function test_a_rejected_row_does_not_block_a_later_row_for_the_same_user_or_inflate_the_counts(): void
    {
        User::factory()->staff()->create(['nama_lengkap' => 'PEMILIK NOMOR', 'no_hp' => '081299990001']);
        $kevin = User::factory()->staff()->create(['employee_id' => 'E-1', 'nama_lengkap' => 'KEVIN']);

        $this->import([
            ['Employee ID', 'Full Name', 'No HP'],
            // Nomornya milik orang lain dan Kevin belum punya nomor: ditolak, perubahan namanya ikut dibatalkan.
            ['E-1', 'KEVIN SALAH', '081299990001'],
            ['E-1', 'KEVIN', '081299990002'],
        ]);

        $this->assertSame([$kevin->id], $this->participantIds());
        $this->assertSame('KEVIN', $kevin->fresh()->nama_lengkap);
        $this->assertSame('081299990002', $kevin->fresh()->no_hp);

        $notification = $this->sentNotification($this->admin);
        $this->assertStringContainsString('1 ditambahkan, 0 sudah terdaftar, 1 data user diperbarui', $notification->message);
        $this->assertCount(1, $notification->errors);
        $this->assertStringContainsString('Baris 2: KEVIN (E-1) tidak punya No. HP di master User', $notification->errors[0]);
    }

    public function test_a_file_with_only_the_heading_row_says_it_has_no_participants(): void
    {
        $this->import([['Employee ID', 'Full Name', 'No HP']]);

        $notification = $this->sentNotification($this->admin);
        $this->assertSame(BackgroundTaskFinished::STATUS_FAILED, $notification->status);
        $this->assertStringContainsString('File tidak berisi data peserta', $notification->message);
    }

    public function test_decimal_looking_employee_ids_are_not_rounded_to_another_employee(): void
    {
        $other = User::factory()->staff()->create(['employee_id' => '2019', 'no_hp' => '081100000001']);
        $budi = User::factory()->staff()->create(['employee_id' => '2019.5', 'no_hp' => '081100000002']);

        $this->import([
            ['Employee ID', 'No HP'],
            [2019.5, '081100000002'],
        ]);

        $this->assertSame([$budi->id], $this->participantIds());
        $this->assertModelExists($other);
    }

    public function test_new_positions_are_only_created_by_importers_who_manage_master_data(): void
    {
        $manager = User::factory()->staff()->trainer()->create();
        $manager->givePermissionTo('manage-users');
        $budi = User::factory()->staff()->create(['employee_id' => 'E-1', 'nama_lengkap' => 'BUDI', 'no_hp' => '081100000001', 'approval_id' => $manager->id]);

        $this->import([
            ['Employee ID', 'Full Name', 'No HP', 'Job Position'],
            ['E-1', 'BUDI SANTOSO', '081100000001', 'POSISI KARANGAN'],
        ], $manager);

        // Atasan boleh membetulkan nama anggota timnya, tetapi tidak menambah master Posisi.
        $this->assertSame('BUDI SANTOSO', $budi->fresh()->nama_lengkap);
        $this->assertNull($budi->fresh()->position_id);
        $this->assertSame(0, Position::count());
        $this->assertStringContainsString('tidak ada di master Posisi', $this->sentNotification($manager)->errors[0]);
    }

    public function test_rows_without_employee_id_are_matched_by_phone_and_new_participants_can_be_invited(): void
    {
        Queue::fake([SendTrainingInvitation::class]);
        $existing = User::factory()->staff()->create(['no_hp' => '081100000001']);
        $budi = User::factory()->staff()->create(['no_hp' => '081100000002']);
        $already = TrainingParticipant::createFor($this->training, $existing->id);

        $this->import([
            ['Nama', 'No. HP'],
            ['', '081100000001'],
            ['', '6281100000002'],
            ['', '081199999999'],
        ], sendWa: true);

        $this->assertSame([$existing->id, $budi->id], $this->participantIds());
        Queue::assertPushed(SendTrainingInvitation::class, 1);
        Queue::assertPushed(SendTrainingInvitation::class, fn (SendTrainingInvitation $job) => $job->participant->user_id === $budi->id);
        $this->assertNull($already->fresh()->wa_status);

        $notification = $this->sentNotification($this->admin);
        $this->assertStringContainsString('Undangan WhatsApp untuk 1 peserta baru masuk antrean', $notification->message);
        $this->assertStringContainsString('No. HP 081199999999 tidak ditemukan di master User', $notification->errors[0]);
    }

    public function test_trainer_can_remove_a_participant_with_their_results_and_portal_link(): void
    {
        $trainer = User::factory()->staff()->trainer()->create();
        $leaving = User::factory()->staff()->create(['nama_lengkap' => 'PESERTA TAKEOUT']);
        $staying = User::factory()->staff()->create();
        $participant = TrainingParticipant::createFor($this->training, $leaving->id);
        $other = TrainingParticipant::createFor($this->training, $staying->id);
        Storage::fake();
        Storage::put('attendance/bukti.jpg', 'x');
        $participant->update(['attendance_status' => 'hadir', 'attended_at' => now(), 'attendance_proof' => 'attendance/bukti.jpg']);
        TrainingQuizResult::create([
            'training_id' => $this->training->id, 'user_id' => $leaving->id, 'training_participant_id' => $participant->id,
            'phase' => Training::PHASE_POSTTEST, 'total_questions' => 1, 'correct_answers' => 1, 'score' => 100, 'submitted_at' => now(),
        ]);
        TrainingEvaluation::create([
            'training_id' => $this->training->id, 'user_id' => $leaving->id, 'training_participant_id' => $participant->id,
            'answers' => [], 'submitted_at' => now(),
        ]);

        $this->actingAs($trainer)
            ->get(route('training.show', $this->training))
            ->assertOk()
            ->assertSee('Keluarkan dari Pelatihan')
            ->assertSee('Import Peserta dari Excel');

        $this->actingAs($trainer)
            ->delete(route('training.participants.remove', [$this->training, $participant]))
            ->assertRedirect(route('training.show', $this->training).'#peserta')
            ->assertSessionHas('success', 'PESERTA TAKEOUT dikeluarkan dari pelatihan. Link portal, hasil kuis, presensi, dan evaluasinya ikut dihapus.');

        $this->assertModelMissing($participant);
        Storage::assertMissing('attendance/bukti.jpg');
        $this->assertSame(0, TrainingQuizResult::where('user_id', $leaving->id)->count());
        $this->assertSame(0, TrainingEvaluation::where('user_id', $leaving->id)->count());
        $this->assertModelExists($other);
        $this->get('/training/portal/'.$participant->token)->assertNotFound();

        // Pengingat dan broadcast WhatsApp tidak lagi menjangkau peserta yang dikeluarkan.
        Queue::fake([SendTrainingInvitation::class]);
        $this->assertSame(1, SendTrainingInvitation::dispatchForTraining($this->training->fresh()));
        Queue::assertPushed(SendTrainingInvitation::class, fn (SendTrainingInvitation $job) => $job->participant->is($other));
    }

    public function test_a_participant_of_another_training_cannot_be_removed_through_this_one(): void
    {
        $otherTraining = $this->training->replicate();
        $otherTraining->save();
        $participant = TrainingParticipant::createFor($otherTraining, User::factory()->staff()->create()->id);

        $this->actingAs($this->admin)
            ->delete(route('training.participants.remove', [$this->training, $participant]))
            ->assertNotFound();

        $this->actingAs(User::factory()->staff()->create())
            ->delete(route('training.participants.remove', [$otherTraining, $participant]));

        $this->assertModelExists($participant);
    }
}
