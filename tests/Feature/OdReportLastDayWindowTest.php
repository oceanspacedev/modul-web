<?php

namespace Tests\Feature;

use App\Exports\OdWeeklyExport;
use App\Models\OdActivity;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

/**
 * Batas atas window tanggal Weekly/Monthly dan ekspor Weekly. Kolom date disimpan sebagai "Y-m-d H:i:s", jadi
 * perbandingan rentang harus memakai whereDate; kalau tidak, hari terakhir window hilang di sqlite.
 */
class OdReportLastDayWindowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Rabu, 11 November 2026. Week 4 November: 21–27 Nov (hari terakhir window = 27 Nov), akhir bulan = 30 Nov.
        Carbon::setTestNow('2026-11-11 15:00:00');
    }

    private function leader(): User
    {
        $leader = User::factory()->staff()->create(['nama_lengkap' => 'LEADER OD']);
        $leader->setOdReportAccess(User::OD_LEADER);

        return $leader->fresh();
    }

    private function activity(User $user, string $date, string $name, string $result = 'Done'): void
    {
        OdActivity::create(['user_id' => $user->id, 'date' => $date, 'name' => $name, 'result' => $result, 'saved_at' => now()]);
    }

    private function sheet(object $export): Worksheet
    {
        Storage::disk('local')->put('od-window.xlsx', Excel::raw($export, ExcelFormat::XLSX));

        return IOFactory::load(Storage::disk('local')->path('od-window.xlsx'))->getSheet(0);
    }

    public function test_last_day_of_the_week_window_appears_in_the_weekly_page_and_the_export(): void
    {
        $leader = $this->leader();
        $this->activity($leader, '2026-11-27', 'Rekap minggu empat');
        $this->activity($leader, '2026-11-28', 'Pekerjaan minggu Desember');

        $this->actingAs($leader)->get(route('od-report.weekly', ['bulan' => '2026-11']))->assertOk()
            ->assertSee('Rekap minggu empat')
            ->assertDontSee('Pekerjaan minggu Desember');

        $sheet = $this->sheet(new OdWeeklyExport($leader, '2026-11'));
        $this->assertSame('Week 4 (21 - 27 November 2026)', $sheet->getCell('B1')->getValue());
        $this->assertSame('REKAP MINGGU EMPAT', $sheet->getCell('B2')->getValue());
    }

    public function test_the_month_window_edges_are_counted_in_the_monthly_stats(): void
    {
        $leader = $this->leader();
        // Bulan laporan = window minggu: 31 Okt – 27 Nov. Kedua batasnya dihitung, 28 Nov sudah masuk Desember.
        $this->activity($leader, '2026-10-31', 'Awal window');
        $this->activity($leader, '2026-11-27', 'Rekap minggu empat');
        $this->activity($leader, '2026-11-28', 'Pekerjaan minggu Desember');

        $this->actingAs($leader)->get(route('od-report.monthly', ['bulan' => '2026-11']))->assertOk()
            ->assertSee('2 dari 2 selesai')
            ->assertSee('100%');
    }

    public function test_daily_window_boundaries_still_exclude_the_next_week(): void
    {
        $leader = $this->leader();
        $this->activity($leader, '2026-11-27', 'Rekap minggu empat');
        $this->activity($leader, '2026-12-04', 'Pekerjaan minggu berikutnya');

        // Week 4 dipilih lewat ?minggu: window-nya tetap 21–27 Nov.
        $this->actingAs($leader)->get(route('od-report.weekly', ['bulan' => '2026-11', 'minggu' => '2026-11-21']))->assertOk()
            ->assertSee('Rekap minggu empat')
            ->assertDontSee('Pekerjaan minggu berikutnya');
    }
}
