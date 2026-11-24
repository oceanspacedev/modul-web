<?php

namespace Tests\Feature;

use App\Exports\DocumentRequestExport;
use App\Exports\OdAchievementExport;
use App\Exports\OdWeeklyExport;
use App\Jobs\GenerateExportFile;
use App\Models\DocumentRequest;
use App\Models\OdActivity;
use App\Models\OdDocument;
use App\Models\OdDocumentStatus;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

/**
 * Ekspor Excel OD Report mengikuti template tim (Weekly Report dan ACHIEVEMENT BUSDEV).
 */
class OdReportExportTest extends TestCase
{
    private User $leader;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-18 10:00:00');

        $this->leader = User::factory()->staff()->create(['nama_lengkap' => 'LEADER OD']);
        $this->leader->setOdReportAccess(User::OD_LEADER);
        $this->staff = User::factory()->staff()->create(['nama_lengkap' => 'STAFF OD']);
        $this->staff->setOdReportAccess(User::OD_STAFF);
        $this->leader = $this->leader->fresh();
        $this->staff = $this->staff->fresh();
    }

    private function sheet(object $export): Worksheet
    {
        Storage::disk('local')->put('od.xlsx', Excel::raw($export, ExcelFormat::XLSX));

        return IOFactory::load(Storage::disk('local')->path('od.xlsx'))->getSheet(0);
    }

    private function activity(User $user, string $date, string $name, string $result = 'Done'): void
    {
        OdActivity::create(['user_id' => $user->id, 'date' => $date, 'name' => $name, 'result' => $result, 'saved_at' => now()]);
    }

    private function document(User $pic, string $title, string $jenis, string $statusType = OdDocumentStatus::TYPE_OPEN): void
    {
        OdDocument::create([
            'title' => $title, 'jenis' => $jenis, 'badan_usaha' => 'PT SMI', 'pic_user_id' => $pic->id, 'submitted_at' => today(), 'month' => '2026-11',
            'od_document_status_id' => OdDocumentStatus::firstOfType($statusType)->id,
        ]);
    }

    public function test_weekly_export_follows_the_team_template(): void
    {
        $this->activity($this->leader, '2026-11-02', 'review sop gudang');
        $this->activity($this->staff, '2026-11-03', 'drafting ik retur');
        $this->activity($this->staff, '2026-11-04', 'drafting ik retur');
        $this->activity($this->staff, '2026-11-10', '=rumus jahat');

        $export = new OdWeeklyExport($this->leader, '2026-11');
        $sheet = $this->sheet($export);

        $this->assertSame('NOV 1-4 (2026)', $export->title());
        $this->assertSame(['Posisi', 'Week 1 (31 Oktober - 06 November 2026)'], [$sheet->getCell('A1')->getValue(), $sheet->getCell('B1')->getValue()]);
        $this->assertSame('Organization Development Leader', $sheet->getCell('A2')->getValue());
        $this->assertSame('REVIEW SOP GUDANG', $sheet->getCell('B2')->getValue());
        $this->assertSame('Organization Development Staff', $sheet->getCell('A3')->getValue());
        // Pekerjaan yang sama dalam satu minggu jadi satu baris.
        $this->assertSame('DRAFTING IK RETUR', $sheet->getCell('B3')->getValue());
        $this->assertSame('', (string) $sheet->getCell('B4')->getValue());
        $this->assertSame('Week 2 (07 - 13 November 2026)', $sheet->getCell('B5')->getValue());
        $this->assertSame('=RUMUS JAHAT', $sheet->getCell('B6')->getValue());
        $this->assertSame('FFF4B084', $sheet->getStyle('A1')->getFill()->getStartColor()->getARGB());

        // Staff hanya mengekspor pekerjaannya sendiri.
        $staffSheet = $this->sheet(new OdWeeklyExport($this->staff, '2026-11'));
        $this->assertSame('DRAFTING IK RETUR', $staffSheet->getCell('B2')->getValue());
    }

    public function test_achievement_export_groups_documents_per_position_without_cancelled_ones(): void
    {
        $this->document($this->staff, 'IK Retur', 'IK');
        $this->document($this->staff, 'SOP Gudang', 'SOP', OdDocumentStatus::TYPE_DONE);
        $this->document($this->staff, 'Batal saja', 'SE', OdDocumentStatus::TYPE_CANCEL);
        $this->document($this->leader, 'Memo Kebijakan', 'MEMO');

        $export = new OdAchievementExport($this->leader, '2026-11');
        $sheet = $this->sheet($export);

        $this->assertSame("NOV'2026", $export->title());
        $this->assertSame(['NO', 'POSISI', 'ACHIEVEMENT', 'BADAN USAHA', 'Keterangan'], $sheet->rangeToArray('A1:E1', null, false, false)[0]);
        $rows = $sheet->rangeToArray('A2:E4', null, false, false);
        $this->assertSame([1, 'Organization Development Leader', 'Memo Kebijakan', 'PT SMI', 'Proses Approval'], $rows[0]);
        $this->assertSame([1, 'Organization Development Staff', 'SOP Gudang', 'PT SMI', 'Release'], $rows[1]);
        $this->assertSame([2, null, 'IK Retur', 'PT SMI', 'Proses Approval'], $rows[2]);
        $this->assertNull($sheet->getCell('C5')->getValue());
        $this->assertArrayHasKey('B3:B4', $sheet->getMergeCells());
    }

    public function test_request_export_has_one_row_per_process(): void
    {
        $request = DocumentRequest::create([
            'code' => 'IK-20261118-AAAA', 'nama' => 'Budi', 'divisi' => 'GA', 'departemen' => 'Warehouse', 'jabatan' => 'Staff',
            'whatsapp' => '081234567890', 'email' => 'budi@example.com', 'tanggal' => today(), 'obs_tanggal_1' => '2026-11-20',
        ]);
        foreach (['Penerimaan', 'Retur'] as $i => $title) {
            $request->processes()->create([
                'title' => $title, 'jenis' => 'IK', 'pic_user_id' => $this->staff->id, 'submitted_at' => today(), 'month' => '2026-11',
                'stage' => 'Drafting', 'prioritas' => 'High', 'sort' => $i,
                'od_document_status_id' => OdDocumentStatus::firstOfType(OdDocumentStatus::TYPE_OPEN)->id,
            ]);
        }

        $sheet = $this->sheet(new DocumentRequestExport($this->leader));
        $this->assertSame('Kode', $sheet->getCell('A1')->getValue());
        $this->assertSame(['IK-20261118-AAAA', '081234567890', 'Retur', 'Drafting'], [
            $sheet->getCell('A3')->getValue(), $sheet->getCell('I3')->getValue(), $sheet->getCell('L3')->getValue(), $sheet->getCell('P3')->getValue(),
        ]);
    }

    public function test_finished_export_lands_in_the_notification_inbox(): void
    {
        $this->document($this->leader, 'Memo Kebijakan', 'MEMO');

        foreach (['weekly', 'monthly', 'requests'] as $type) {
            $this->actingAs($this->leader)->post(route('od-report.export'), ['type' => $type, 'bulan' => '2026-11'])->assertSessionHas('info');
        }

        $files = $this->leader->notifications()->get()->map(fn ($n) => $n->data['download_name'] ?? null);
        $this->assertCount(3, $files);
        $this->assertContains('Weekly Report November 2026.xlsx', $files);
        $this->assertContains('ACHIEVEMENT BUSDEV NOVEMBER 2026.xlsx', $files);
        $this->assertTrue($files->contains(fn ($name) => str_starts_with((string) $name, 'permintaan_dokumen_')));
    }

    public function test_export_button_queues_the_job_scoped_to_the_requesting_user(): void
    {
        Queue::fake();

        $this->actingAs($this->staff)->post(route('od-report.export'), ['type' => 'weekly', 'bulan' => '2026-11', 'user' => $this->leader->id])
            ->assertSessionHas('info');

        Queue::assertPushed(GenerateExportFile::class, fn (GenerateExportFile $job) => $job->type === GenerateExportFile::OD_WEEKLY
            && $job->userId === $this->staff->id
            && $job->filters === ['month' => '2026-11']);
    }

    public function test_export_carries_the_filters_that_are_active_on_the_weekly_screen(): void
    {
        Queue::fake();

        $this->actingAs($this->leader)->post(route('od-report.export'), [
            'type' => 'weekly', 'bulan' => '2026-11', 'minggu' => '2026-11-14', 'status' => 'Hold', 'search' => 'sop', 'user' => $this->staff->id,
        ])->assertSessionHas('info');

        Queue::assertPushed(GenerateExportFile::class, fn (GenerateExportFile $job) => $job->filters === [
            'month' => '2026-11', 'week' => '2026-11-14', 'status' => 'Hold', 'search' => 'sop', 'member_id' => $this->staff->id,
        ]);
    }

    public function test_weekly_screen_posts_the_filters_shown_next_to_the_export_button(): void
    {
        $this->activity($this->staff, '2026-11-10', 'Drafting IK retur');

        $this->actingAs($this->leader)->get(route('od-report.weekly', [
            'bulan' => '2026-11', 'minggu' => '2026-11-07', 'status' => 'Done', 'search' => 'retur',
        ]))->assertOk()
            ->assertSee('<input type="hidden" name="minggu" value="2026-11-07">', false)
            ->assertSee('<input type="hidden" name="status" value="Done">', false)
            ->assertSee('<input type="hidden" name="search" value="retur">', false);
    }

    public function test_weekly_export_only_contains_the_filtered_week_status_and_search(): void
    {
        $this->activity($this->leader, '2026-11-05', 'Review SOP gudang');
        $this->activity($this->staff, '2026-11-10', 'Drafting IK retur', 'Hold');
        $this->activity($this->staff, '2026-11-16', 'Drafting SOP baru');

        $week = $this->sheet(new OdWeeklyExport($this->leader, '2026-11', null, '2026-11-14'));
        $this->assertSame('Week 3 (14 - 20 November 2026)', $week->getCell('B1')->getValue());
        $this->assertSame('DRAFTING SOP BARU', $week->getCell('B2')->getValue());
        $this->assertNull($week->getCell('B3')->getValue());

        $hold = $this->sheet(new OdWeeklyExport($this->leader, '2026-11', null, null, 'Hold'));
        $this->assertSame('Week 2 (07 - 13 November 2026)', $hold->getCell('B1')->getValue());
        $this->assertSame('DRAFTING IK RETUR', $hold->getCell('B2')->getValue());
        $this->assertNull($hold->getCell('B3')->getValue());

        $search = $this->sheet(new OdWeeklyExport($this->leader, '2026-11', null, null, null, 'ik'));
        $this->assertSame('DRAFTING IK RETUR', $search->getCell('B2')->getValue());
        $this->assertNull($search->getCell('B3')->getValue());

        // Key minggu dari bulan lain tidak dipakai; ekspor kembali ke semua minggu bulan itu.
        $other = $this->sheet(new OdWeeklyExport($this->leader, '2026-11', null, '2026-12-05'));
        $this->assertSame('Week 1 (31 Oktober - 06 November 2026)', $other->getCell('B1')->getValue());
    }

    public function test_export_refuses_a_member_outside_the_od_team(): void
    {
        Queue::fake();
        $outsider = User::factory()->staff()->create(['nama_lengkap' => 'BUKAN TIM OD']);

        $this->actingAs($this->leader)->post(route('od-report.export'), ['type' => 'weekly', 'bulan' => '2026-11', 'user' => $outsider->id])
            ->assertSessionHasErrors('user');

        Queue::assertNothingPushed();

        // Id anggota tim tetap boleh.
        $this->actingAs($this->leader)->post(route('od-report.export'), ['type' => 'monthly', 'bulan' => '2026-11', 'user' => $this->staff->id])
            ->assertSessionHas('info');
        Queue::assertPushed(GenerateExportFile::class, fn (GenerateExportFile $job) => $job->filters === [
            'month' => '2026-11', 'member_id' => $this->staff->id,
        ]);
    }
}
