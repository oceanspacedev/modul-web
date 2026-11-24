<?php

namespace Tests\Feature;

use App\Exports\OdWeeklyExport;
use App\Models\OdActivity;
use App\Models\User;
use App\Services\OdReportService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

/**
 * Weekly, Monthly, ekspor Weekly dan kartu Dashboard memakai satu definisi bulan: window minggu laporan
 * (OdWeek::inMonth), bukan 1–akhir bulan kalender. 28 November 2026 sudah Week 1 Desember.
 */
class OdReportMonthWindowTest extends TestCase
{
    private function leader(): User
    {
        $leader = User::factory()->staff()->create(['nama_lengkap' => 'LEADER OD']);
        $leader->setOdReportAccess(User::OD_LEADER);

        return $leader->fresh();
    }

    private function activity(User $user, string $date, string $name, string $result = OdActivity::RESULT_DONE): void
    {
        OdActivity::create(['user_id' => $user->id, 'date' => $date, 'name' => $name, 'result' => $result, 'saved_at' => now()]);
    }

    private function sheet(object $export): Worksheet
    {
        Storage::disk('local')->put('od-window.xlsx', Excel::raw($export, ExcelFormat::XLSX));

        return IOFactory::load(Storage::disk('local')->path('od-window.xlsx'))->getSheet(0);
    }

    public function test_a_month_edge_job_is_counted_in_the_same_month_everywhere(): void
    {
        Carbon::setTestNow('2026-11-18 10:00:00');   // Rabu Week 3 November
        $leader = $this->leader();
        $this->activity($leader, '2026-11-26', 'Rekap minggu empat');
        $this->activity($leader, '2026-11-28', 'Tutup buku November', OdActivity::RESULT_HOLD);

        // November berhenti di 27 Nov di Weekly...
        $this->actingAs($leader)->get(route('od-report.weekly', ['bulan' => '2026-11']))->assertOk()
            ->assertSee('Rekap minggu empat')->assertDontSee('Tutup buku November');

        // ...di Monthly (angkanya ikut window yang sama)...
        $this->actingAs($leader)->get(route('od-report.monthly', ['bulan' => '2026-11']))->assertOk()
            ->assertSee('1 dari 1 selesai')->assertSee('100%');
        $this->actingAs($leader)->get(route('od-report.monthly', ['bulan' => '2026-12']))->assertOk()
            ->assertSee('0 dari 1 selesai')->assertSee('0%');

        // ...dan di ekspor Weekly: 28 Nov masuk blok Week 1 Desember.
        $november = $this->sheet(new OdWeeklyExport($leader, '2026-11'));
        $this->assertSame('Week 4 (21 - 27 November 2026)', $november->getCell('B1')->getValue());
        $this->assertSame('REKAP MINGGU EMPAT', $november->getCell('B2')->getValue());
        $this->assertNull($november->getCell('B3')->getValue());

        $desember = $this->sheet(new OdWeeklyExport($leader, '2026-12'));
        $this->assertSame('Week 1 (28 November - 04 Desember 2026)', $desember->getCell('B1')->getValue());
        $this->assertSame('TUTUP BUKU NOVEMBER', $desember->getCell('B2')->getValue());
    }

    public function test_the_dashboard_month_tile_uses_the_report_month_window_and_links_to_it(): void
    {
        Carbon::setTestNow('2026-11-28 09:00:00');   // Sabtu: minggu laporannya sudah masuk Desember
        $leader = $this->leader();
        $this->activity($leader, '2026-11-27', 'Rekap minggu empat');
        $this->activity($leader, '2026-11-28', 'Mulai Desember');

        $od = app(OdReportService::class)->dashboard($leader);
        $this->assertSame('2026-12', $od['bulan']);
        $this->assertSame(['total' => 1, 'done' => 1, 'progress' => 0, 'hold' => 0, 'percent' => 100], $od['month']);
        $this->assertSame(1, $od['thisWeek']);   // tile minggu ikut menghitung pekerjaan 28 Nov

        $this->actingAs($leader)->get(route('dashboard'))->assertOk()
            ->assertSee('1 dari 1 selesai · Desember 2026', false)
            ->assertSee('/od-report/monthly?bulan=2026-12', false);

        // Kartu dan halaman Monthly untuk bulan yang sama menampilkan angka yang sama.
        $this->actingAs($leader)->get(route('od-report.monthly', ['bulan' => '2026-12']))->assertOk()
            ->assertSee('1 dari 1 selesai')->assertSee('100%');
        $this->actingAs($leader)->get(route('od-report.weekly', ['bulan' => '2026-12']))->assertOk()
            ->assertSee('Mulai Desember')->assertDontSee('Rekap minggu empat');
    }
}
