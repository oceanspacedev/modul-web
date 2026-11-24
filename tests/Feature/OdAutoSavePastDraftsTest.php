<?php

namespace Tests\Feature;

use App\Models\OdActivity;
use App\Models\OdSetting;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Auto-save draft hari sebelumnya saat membuka Daily Report / Dashboard: hanya draft milik user yang membuka,
 * draft anggota lain tidak boleh ikut "diterbitkan" (dan karena itu tidak ikut terkirim ke spreadsheet).
 */
class OdAutoSavePastDraftsTest extends TestCase
{
    private const URL = 'https://script.google.com/macros/s/TEST/exec';

    private const SHEET_ID = '1gFQgBuIYo_ebic-fP5tLGJgB7pOjW3of2PbwvIIOmwA';

    protected function setUp(): void
    {
        parent::setUp();

        // Rabu, 11 November 2026.
        Carbon::setTestNow('2026-11-11 15:00:00');
        OdSetting::put([
            OdSetting::SHEET_URL => self::URL,
            OdSetting::SHEET_LINK => 'https://docs.google.com/spreadsheets/d/'.self::SHEET_ID.'/edit',
        ]);
        Http::fake([self::URL.'*' => fn (Request $r) => Http::response(['ok' => true, 'a' => count($r['entries']), 'd' => count($r['del']), 'fail' => []])]);
    }

    private function member(string $access, string $name): User
    {
        $user = User::factory()->staff()->create(['nama_lengkap' => $name]);
        $user->setOdReportAccess($access);

        return $user->fresh();
    }

    private function draft(User $user, string $date, string $name): OdActivity
    {
        return OdActivity::create(['user_id' => $user->id, 'date' => $date, 'name' => $name, 'result' => 'Done', 'saved_at' => null]);
    }

    /** @return list<string> nama pekerjaan yang pernah dikirim ke spreadsheet */
    private function sentNames(): array
    {
        return Http::recorded()->filter(fn ($pair) => $pair[0]->method() === 'POST')
            ->flatMap(fn ($pair) => array_column($pair[0]['entries'], 'n'))->values()->all();
    }

    public function test_opening_daily_only_autosaves_the_visiting_users_past_drafts(): void
    {
        $mine = $this->member(User::OD_STAFF, 'STAFF A');
        $other = $this->member(User::OD_STAFF, 'STAFF B');
        $draftMine = $this->draft($mine, '2026-11-09', 'Draft milik A');
        $draftOther = $this->draft($other, '2026-11-09', 'Draft milik B');

        $this->actingAs($mine)->get(route('od-report.daily.index'))->assertOk();

        $this->assertNotNull($draftMine->fresh()->saved_at);
        $this->assertNull($draftOther->fresh()->saved_at);
        $this->assertSame(['Draft milik A'], $this->sentNames());
    }

    public function test_opening_dashboard_only_autosaves_the_visiting_users_past_drafts(): void
    {
        $mine = $this->member(User::OD_LEADER, 'LEADER A');
        $other = $this->member(User::OD_STAFF, 'STAFF B');
        $draftMine = $this->draft($mine, '2026-11-06', 'Rekap minggu lalu');
        $draftOther = $this->draft($other, '2026-11-06', 'Draft orang lain');

        $this->actingAs($mine)->get(route('dashboard'))->assertOk()->assertSee('OD Report');

        $this->assertNotNull($draftMine->fresh()->saved_at);
        $this->assertNull($draftOther->fresh()->saved_at);
        $this->assertSame(['Rekap minggu lalu'], $this->sentNames());
    }

    public function test_draft_today_stays_draft_and_every_user_autosaves_their_own_past_drafts(): void
    {
        $mine = $this->member(User::OD_STAFF, 'STAFF A');
        $other = $this->member(User::OD_STAFF, 'STAFF B');
        $draftToday = $this->draft($mine, '2026-11-11', 'Draft hari ini');
        $draftOther = $this->draft($other, '2026-11-10', 'Draft B kemarin');

        $this->actingAs($mine)->get(route('od-report.daily.index'))->assertOk();
        $this->actingAs($other)->get(route('od-report.daily.index'))->assertOk();

        $this->assertNull($draftToday->fresh()->saved_at);   // draft hari ini tetap sampai "Simpan Laporan"
        $this->assertNotNull($draftOther->fresh()->saved_at);   // B menyimpan draftnya sendiri
        $this->assertSame(['Draft B kemarin'], $this->sentNames());
    }
}
