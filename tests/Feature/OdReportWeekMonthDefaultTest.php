<?php

namespace Tests\Feature;

use App\Models\OdActivity;
use App\Models\User;
use App\Services\OdReportService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Default bulan Weekly/Monthly memakai bulan minggu laporan (Rabu-nya), bukan bulan kalender, dan tautan dari
 * Dashboard ikut membawa bulan itu supaya halaman yang dibuka menampilkan baris yang sama dengan kartu.
 */
class OdReportWeekMonthDefaultTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Jumat, 1 Januari 2027: minggu laporannya 19 Des 2026 – 1 Jan 2027 (Week 4 Desember), beda dari bulan kalender.
        Carbon::setTestNow('2027-01-01 09:00:00');
    }

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

    public function test_weekly_page_without_bulan_shows_the_current_report_week(): void
    {
        $leader = $this->leader();
        $this->activity($leader, '2026-12-21', 'Rekap dalam minggu ini');
        $this->activity($leader, '2027-01-01', 'Rekap hari terakhir minggu');

        $this->actingAs($leader)->get(route('od-report.weekly'))->assertOk()
            ->assertSee('Desember 2026')
            ->assertSee('Week 4')
            ->assertSee('Rekap dalam minggu ini')
            ->assertSee('Rekap hari terakhir minggu');

        // Bulan kalender hari ini (Januari 2027) justru tidak memuat minggu ini.
        $this->actingAs($leader)->get(route('od-report.weekly', ['bulan' => '2027-01']))->assertOk()
            ->assertDontSee('Rekap dalam minggu ini');
    }

    public function test_monthly_page_defaults_to_the_same_week_month(): void
    {
        $leader = $this->leader();
        $this->activity($leader, '2026-12-21', 'Rekap Desember');

        // Desember (bulan minggu laporan) yang dihitung, bukan Januari yang cuma bulan kalender hari ini.
        $this->actingAs($leader)->get(route('od-report.monthly'))->assertOk()->assertSee('1 dari 1 selesai');
    }

    public function test_dashboard_links_point_at_the_month_the_cards_counted(): void
    {
        $leader = $this->leader();
        $this->activity($leader, '2027-01-01', 'Tahan awal tahun', OdActivity::RESULT_HOLD);

        $od = app(OdReportService::class)->dashboard($leader);
        $this->assertSame('2026-12', $od['bulan']);

        $holdLink = collect($od['attention'])->map(fn (array $item) => $item[1])
            ->first(fn (string $url) => str_contains($url, 'bulan=2026-12') && str_contains($url, 'status=Hold'));
        $this->assertNotNull($holdLink, 'Tautan Hold harus membawa bulan minggu laporan.');
        $this->actingAs($leader)->get($holdLink)->assertOk()->assertSee('Tahan awal tahun');

        $this->actingAs($leader)->get(route('dashboard'))->assertOk()
            ->assertSee('1 pekerjaan berstatus Hold bulan ini')
            ->assertSee('/od-report/weekly?bulan=2026-12', false)
            // Kartu "Penyelesaian bulan ini" menghitung window Desember, jadi tautannya membawa bulan yang sama.
            ->assertSee('/od-report/monthly?bulan=2026-12', false);
    }
}
