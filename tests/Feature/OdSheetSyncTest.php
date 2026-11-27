<?php

namespace Tests\Feature;

use App\Jobs\SyncOdReportSheet;
use App\Models\OdActivity;
use App\Models\OdSetting;
use App\Models\User;
use App\Services\OdSheetSync;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Sinkron Daily Report → spreadsheet Weekly Report (Apps Script).
 */
class OdSheetSyncTest extends TestCase
{
    private const URL = 'https://script.google.com/macros/s/TEST/exec';

    private const SHEET_ID = '1gFQgBuIYo_ebic-fP5tLGJgB7pOjW3of2PbwvIIOmwA';

    protected function setUp(): void
    {
        parent::setUp();

        // Rabu, 11 November 2026 (Week 2 November: 7–13 Nov).
        Carbon::setTestNow('2026-11-11 15:00:00');
        OdSetting::put([
            OdSetting::SHEET_URL => self::URL,
            OdSetting::SHEET_LINK => 'https://docs.google.com/spreadsheets/d/'.self::SHEET_ID.'/edit',
        ]);
    }

    private function staff(): User
    {
        $user = User::factory()->staff()->create(['nama_lengkap' => 'STAFF OD', 'position_id' => null]);
        $user->setOdReportAccess(User::OD_STAFF);

        return $user->fresh();
    }

    /** @var list<string> SYNC ID yang dijawab gagal oleh spreadsheet palsu */
    private array $failKeys = [];

    private function fakeSheet(): void
    {
        Http::fake([self::URL.'*' => function (Request $request) {
            if ($request->method() === 'GET') {
                return Http::response(['ok' => true, 'weekly' => 'Weekly Report OD']);
            }

            $failed = array_values(array_intersect(array_column($request['entries'], 'key'), $this->failKeys));

            return Http::response([
                'ok' => true, 'a' => count($request['entries']) - count($failed), 'd' => count($request['del']),
                'fail' => $failed, 'log' => $failed ? 'Analisa JD: rusak' : '',
            ]);
        }]);
    }

    /** @return list<array<string, mixed>> */
    private function sentBodies(): array
    {
        return Http::recorded()->filter(fn ($pair) => $pair[0]->method() === 'POST')
            ->map(fn ($pair) => $pair[0]->data())->values()->all();
    }

    public function test_simpan_laporan_writes_saved_activities_to_the_weekly_sheet(): void
    {
        $this->fakeSheet();
        $staff = $this->staff();

        $this->actingAs($staff)->post(route('od-report.daily.store'), [
            'date' => '2026-11-11', 'name' => 'Review IK gudang', 'result' => 'On Progress', 'kendala' => 'Menunggu PIC',
        ]);
        Http::assertNothingSent();   // draft belum dikirim

        $this->actingAs($staff)->post(route('od-report.daily.save'));

        $body = $this->sentBodies()[0];
        $activity = OdActivity::sole();
        $this->assertSame(self::SHEET_ID, $body['weeklyId']);
        $this->assertSame([[
            'key' => 'mw-'.$activity->id,
            'pos' => 'ORGANIZATION DEVELOPMENT STAFF',
            'tab' => "Nov'26",
            'wk' => 2,
            'n' => 'Review IK gudang',
            'r' => 'On Progress',
            'k' => 'Menunggu PIC',
            's' => '',
            'ke' => '',
        ]], $body['entries']);
        $this->assertNotNull($activity->sheet_hash);

        // Simpan ulang tanpa perubahan tidak mengirim apa pun lagi.
        $this->artisan('od-report:sync-sheet')->assertSuccessful();
        Http::assertSentCount(1);
    }

    public function test_edit_resends_the_row_and_delete_clears_it(): void
    {
        $this->fakeSheet();
        $staff = $this->staff();
        $activity = OdActivity::create([
            'user_id' => $staff->id, 'date' => '2026-11-10', 'name' => 'Drafting SOP', 'result' => 'Done', 'saved_at' => now(),
        ]);
        $this->assertSame('Closed', $this->sentBodies()[0]['entries'][0]['r']);

        $this->actingAs($staff)->put(route('od-report.daily.update', $activity), [
            'date' => '2026-11-10', 'name' => 'Drafting SOP', 'result' => 'Hold',
        ]);
        $this->assertSame('Open', $this->sentBodies()[1]['entries'][0]['r']);

        $this->actingAs($staff)->delete(route('od-report.daily.destroy', $activity));
        $this->assertSame(['mw-'.$activity->id], $this->sentBodies()[2]['del']);
        $this->assertSame([], $this->sentBodies()[2]['entries']);
    }

    public function test_fifth_week_goes_to_the_week_four_block(): void
    {
        $this->fakeSheet();
        $staff = $this->staff();

        // 30 Des 2026 (minggu 26 Des - 1 Jan, Rabu 30 Des) tidak membuat Week 5; ikut Week 4 tab Des'26.
        OdActivity::create([
            'user_id' => $staff->id, 'date' => '2026-12-30', 'name' => 'Rekap akhir tahun', 'result' => 'Done', 'saved_at' => now(),
        ]);

        $entry = $this->sentBodies()[0]['entries'][0];
        $this->assertSame(["Des'26", 4], [$entry['tab'], $entry['wk']]);
    }

    public function test_failed_rows_stay_pending_and_settings_show_the_status(): void
    {
        $staff = $this->staff();
        $leader = $this->leader();

        $this->fakeSheet();
        $activity = OdActivity::create([
            'user_id' => $staff->id, 'date' => '2026-10-30', 'name' => 'Analisa JD', 'result' => 'Done', 'saved_at' => now(),
        ]);
        // 30 Okt 2026 masuk minggu 24–30 Okt (Rabu 28 Okt) → tab Okt'26 week 4.
        $entry = $this->sentBodies()[0]['entries'][0];
        $this->assertSame(["Okt'26", 4], [$entry['tab'], $entry['wk']]);
        $this->assertNotNull($activity->fresh()->sheet_hash);

        $this->failKeys = ['mw-'.$activity->id];
        $activity->update(['name' => 'Analisa JD gudang']);
        $this->assertSame(sha1(json_encode($entry)), $activity->fresh()->sheet_hash);   // masih isi lama

        $this->actingAs($leader)->get(route('od-report.settings.index'))->assertOk()
            ->assertSee('Menunggu dikirim: <b>1</b>', false)
            ->assertSee('Analisa JD: rusak')
            ->assertSee(self::SHEET_ID);
    }

    public function test_nothing_is_sent_when_sync_is_not_configured(): void
    {
        OdSetting::put([OdSetting::SHEET_URL => null]);
        Http::fake();
        $staff = $this->staff();

        OdActivity::create(['user_id' => $staff->id, 'date' => '2026-11-10', 'name' => 'X', 'result' => 'Done', 'saved_at' => now()]);

        Http::assertNothingSent();
        $this->actingAs($staff)->get(route('od-report.daily.index'))->assertOk()->assertDontSee('docs.google.com');
    }

    public function test_settings_page_shows_and_serves_the_apps_script_code(): void
    {
        $leader = $this->leader();

        $this->actingAs($leader)->get(route('od-report.settings.index'))->assertOk()
            ->assertSee('Apps Script &amp; cara pasang', false)
            ->assertSee('function doPost(e)')
            ->assertSee(route('od-report.settings.apps-script'));

        $response = $this->actingAs($leader)->get(route('od-report.settings.apps-script'))->assertOk()
            ->assertDownload('apps-script.gs');
        $this->assertStringContainsString('function upsert_(ss, en)', $response->streamedContent());

        $this->actingAs(User::factory()->staff()->create())
            ->get(route('od-report.settings.apps-script'))
            ->assertRedirect();
    }

    private function leader(): User
    {
        $leader = User::factory()->staff()->create();
        $leader->setOdReportAccess(User::OD_LEADER);

        return $leader->fresh();
    }

    public function test_leader_sets_the_spreadsheet_in_settings_and_changing_it_resends_from_the_start_date(): void
    {
        $this->fakeSheet();
        $staff = $this->staff();
        $old = OdActivity::create(['user_id' => $staff->id, 'date' => '2026-11-02', 'name' => 'Lama', 'result' => 'Done', 'saved_at' => now()]);
        $new = OdActivity::create(['user_id' => $staff->id, 'date' => '2026-11-10', 'name' => 'Baru', 'result' => 'Done', 'saved_at' => now()]);
        $this->assertNotNull($old->fresh()->sheet_hash);
        $sentBefore = count($this->sentBodies());

        $newId = '2AbCdEfGhIjKlMnOpQrStUvWxYz0123456789';
        $this->actingAs($this->leader())->put(route('od-report.settings.sheet.update'), [
            'sheet_link' => "https://docs.google.com/spreadsheets/d/{$newId}/edit#gid=0",
            'sheet_url' => self::URL,
            'sheet_from' => '2026-11-07',
        ])->assertSessionHas('success', 'Tersimpan dan tersambung ke spreadsheet "Weekly Report OD".');

        $this->assertSame("https://docs.google.com/spreadsheets/d/{$newId}/edit", OdSetting::value(OdSetting::SHEET_LINK));
        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && $r['w'] === $newId);

        // Spreadsheet baru: hanya pekerjaan sejak tanggal mulai yang dikirim ulang.
        $body = $this->sentBodies()[$sentBefore];
        $this->assertSame($newId, $body['weeklyId']);
        $this->assertSame(['Baru'], array_column($body['entries'], 'n'));
        $this->assertNull($old->fresh()->sheet_hash);
        $this->assertNotNull($new->fresh()->sheet_hash);
    }

    public function test_settings_reject_invalid_links_and_can_turn_sync_off(): void
    {
        Http::fake();
        $leader = $this->leader();

        $this->actingAs($leader)->put(route('od-report.settings.sheet.update'), [
            'sheet_link' => 'https://example.com/bukan-sheet',
            'sheet_url' => 'https://script.google.com/macros/s/ABC/dev',
        ])->assertSessionHasErrors(['sheet_link', 'sheet_url']);

        $this->actingAs($leader)->put(route('od-report.settings.sheet.update'), ['sheet_link' => '', 'sheet_url' => ''])
            ->assertSessionHas('success', 'Sinkron Google Sheets dimatikan.');
        $this->assertNull(OdSetting::value(OdSetting::SHEET_URL));
        Http::assertNothingSent();

        $staff = $this->staff();
        $this->actingAs($staff)->put(route('od-report.settings.sheet.update'), ['sheet_link' => '', 'sheet_url' => ''])
            ->assertRedirect();
        $this->assertSame(0, OdSetting::count());
    }

    public function test_save_reports_when_the_web_app_cannot_open_the_spreadsheet(): void
    {
        Http::fake([self::URL.'*' => Http::response(['ok' => false, 'error' => 'Spreadsheet tidak bisa dibuka: akses ditolak'])]);

        $this->actingAs($this->leader())->put(route('od-report.settings.sheet.update'), [
            'sheet_link' => self::SHEET_ID,
            'sheet_url' => self::URL,
        ])->assertSessionHas('error', 'Tersimpan, tapi belum tersambung: Spreadsheet tidak bisa dibuka: akses ditolak');
    }

    public function test_editing_a_published_activity_into_another_report_month_clears_the_old_sheet_row(): void
    {
        $this->fakeSheet();
        $staff = $this->staff();
        // 30 Okt 2026 = minggu 24–30 Okt (tab Okt'26), 10 Nov 2026 = minggu 7–13 Nov (tab Nov'26).
        $activity = OdActivity::create([
            'user_id' => $staff->id, 'date' => '2026-10-30', 'name' => 'Analisa JD', 'result' => 'Done', 'saved_at' => now(),
        ]);
        $this->assertNotNull($activity->fresh()->sheet_hash);

        $this->actingAs($staff)->put(route('od-report.daily.update', $activity), [
            'date' => '2026-11-10', 'name' => 'Analisa JD', 'result' => 'Done',
        ])->assertSessionHas('success');

        // Baris di tab bulan lama dikosongkan...
        $bodies = $this->sentBodies();
        $this->assertSame(['mw-'.$activity->id], end($bodies)['del']);
        // ...dan barisnya belum ditulis ke tab baru: laporannya dibuka kembali sampai "Simpan Laporan".
        $this->assertSame([], end($bodies)['entries']);
        $this->assertNull($activity->fresh()->saved_at);
        $this->assertNull($activity->fresh()->sheet_hash);

        // Draft hari yang lewat otomatis tersimpan saat Daily Report dibuka → baru terkirim ke tab November.
        $this->actingAs($staff)->get(route('od-report.daily.index'))->assertOk();
        $this->assertNotNull($activity->fresh()->saved_at);
        $republished = $this->sentBodies();
        $entry = end($republished)['entries'][0];
        $this->assertSame(["Nov'26", 2], [$entry['tab'], $entry['wk']]);
    }

    public function test_editing_a_date_inside_the_same_report_month_does_not_delete_anything(): void
    {
        $this->fakeSheet();
        $staff = $this->staff();
        $activity = OdActivity::create([
            'user_id' => $staff->id, 'date' => '2026-10-24', 'name' => 'Analisa JD', 'result' => 'Done', 'saved_at' => now(),
        ]);
        $sent = count($this->sentBodies());

        // Masih minggu dan tab yang sama (24–30 Okt): cukup diperbarui di tempat, tanpa hapus dan tanpa dibuka kembali.
        $this->actingAs($staff)->put(route('od-report.daily.update', $activity), [
            'date' => '2026-10-26', 'name' => 'Analisa JD gudang', 'result' => 'Done',
        ])->assertSessionHas('success', 'Aktivitas diperbarui.');

        $bodies = $this->sentBodies();
        $this->assertCount($sent + 1, $bodies);
        $this->assertSame([], end($bodies)['del']);
        $this->assertSame('Analisa JD gudang', end($bodies)['entries'][0]['n']);
        $this->assertNotNull($activity->fresh()->saved_at);
    }

    public function test_a_batch_larger_than_the_chunk_size_keeps_the_backlog_moving(): void
    {
        $this->fakeSheet();
        $staff = $this->staff();
        $this->saveMoreThanOneBatch($staff);
        $this->assertSame(0, count($this->sentBodies()));   // tanpa events tidak ada kirim sama sekali

        (new SyncOdReportSheet)->handle(app(OdSheetSync::class));

        // Batch pertama 25 baris, sisanya diantar job berikutnya — tidak ditinggal.
        $this->assertCount(2, $this->sentBodies());
        $this->assertSame(OdSheetSync::CHUNK + 1, OdActivity::reported()->whereNotNull('sheet_hash')->count());
    }

    public function test_everything_rejected_still_walks_the_backlog_but_stops_after_a_few_batches(): void
    {
        $this->rejectEverything();
        $staff = $this->staff();
        $this->saveMoreThanOneBatch($staff);

        (new SyncOdReportSheet)->handle(app(OdSheetSync::class));

        // Berulang kali mencoba (bukan berhenti di batch pertama), tapi ada batasnya.
        $this->assertCount(SyncOdReportSheet::MAX_ROUNDS_WITHOUT_PROGRESS, $this->sentBodies());
        $this->assertSame(0, OdActivity::reported()->whereNotNull('sheet_hash')->count());
    }

    public function test_the_sync_command_fails_when_the_sheet_rejects_rows(): void
    {
        $this->rejectEverything();
        $staff = $this->staff();
        $activity = OdActivity::create([
            'user_id' => $staff->id, 'date' => '2026-11-10', 'name' => 'Analisa JD', 'result' => 'Done', 'saved_at' => now(),
        ]);

        // Satu baris saja: antrean habis (remaining 0) tapi barisnya ditolak → perintah harus gagal.
        $this->artisan('od-report:sync-sheet')->assertFailed();
        $this->assertNull($activity->fresh()->sheet_hash);
    }

    public function test_the_sync_command_fails_when_rows_are_left_behind(): void
    {
        $this->rejectEverything();
        $this->saveMoreThanOneBatch($this->staff());

        $this->artisan('od-report:sync-sheet')->assertFailed();
        $this->assertSame(0, OdActivity::reported()->whereNotNull('sheet_hash')->count());
    }

    /** Simpan lebih dari satu batch (OdSheetSync::CHUNK) pekerjaan tanpa memicu sinkron. */
    private function saveMoreThanOneBatch(User $staff): void
    {
        OdActivity::withoutEvents(function () use ($staff) {
            foreach (range(1, OdSheetSync::CHUNK + 1) as $i) {
                OdActivity::create([
                    'user_id' => $staff->id, 'date' => '2026-11-11', 'name' => 'Pekerjaan '.$i, 'result' => 'Done', 'saved_at' => now(),
                ]);
            }
        });
    }

    /** Web App menjawab semua baris gagal. */
    private function rejectEverything(): void
    {
        Http::fake([self::URL.'*' => fn (Request $request) => Http::response([
            'ok' => true, 'a' => 0, 'd' => count($request['del']),
            'fail' => array_column($request['entries'], 'key'), 'log' => 'Tab tidak ditemukan',
        ])]);
    }
}
