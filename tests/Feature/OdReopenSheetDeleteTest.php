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
 * "Buka Kembali" harus mengosongkan barisnya di spreadsheet Weekly Report (baris draft tidak ikut dikirim oleh
 * pending()), dan menyimpannya lagi harus mengirim baris yang sama sebagai pembaruan.
 */
class OdReopenSheetDeleteTest extends TestCase
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
        Http::fake([self::URL.'*' => fn (Request $r) => Http::response([
            'ok' => true, 'a' => count($r['entries']), 'd' => count($r['del']), 'fail' => [],
        ])]);
    }

    private function staff(): User
    {
        $user = User::factory()->staff()->create(['nama_lengkap' => 'STAFF OD']);
        $user->setOdReportAccess(User::OD_STAFF);

        return $user->fresh();
    }

    /** @return list<array<string, mixed>> */
    private function sentBodies(): array
    {
        return Http::recorded()->filter(fn ($pair) => $pair[0]->method() === 'POST')
            ->map(fn ($pair) => $pair[0]->data())->values()->all();
    }

    public function test_reopen_deletes_the_published_row_and_saving_it_again_republishes_it(): void
    {
        $staff = $this->staff();
        $activity = OdActivity::create([
            'user_id' => $staff->id, 'date' => '2026-11-11', 'name' => 'Review IK gudang', 'result' => 'Done', 'saved_at' => now(),
        ]);
        $key = 'mw-'.$activity->id;
        $this->assertNotNull($activity->fresh()->sheet_hash);
        $this->assertCount(1, $this->sentBodies());   // barisnya sudah tertulis di sheet

        $this->actingAs($staff)->post(route('od-report.daily.reopen'))
            ->assertRedirect(route('od-report.daily.index'))->assertSessionHas('success');

        $this->assertNull($activity->fresh()->saved_at);
        $this->assertNull($activity->fresh()->sheet_hash);
        $body = $this->sentBodies()[1];
        $this->assertSame([$key], $body['del']);
        $this->assertSame([], $body['entries']);

        // Buka kembali lagi (sudah tidak ada laporan tersimpan hari ini) tidak mengirim apa pun.
        $this->actingAs($staff)->post(route('od-report.daily.reopen'));
        $this->assertCount(2, $this->sentBodies());

        // Disimpan kembali → baris yang sama terkirim lagi sebagai pembaruan, bukan tertinggal selamanya.
        $this->actingAs($staff)->post(route('od-report.daily.save'))->assertSessionHas('success');
        $body = $this->sentBodies()[2];
        $this->assertSame([$key], array_column($body['entries'], 'key'));
        $this->assertSame([], $body['del']);
        $this->assertNotNull($activity->fresh()->sheet_hash);

        // Sinkron ulang tidak mengirim apa pun lagi dan tidak gagal.
        $this->artisan('od-report:sync-sheet')->assertSuccessful();
        $this->artisan('od-report:sync-sheet')->assertSuccessful();
        $this->assertCount(3, $this->sentBodies());
    }

    public function test_reopen_sends_delete_for_exactly_the_reopened_rows(): void
    {
        $staff = $this->staff();
        $other = $this->staff();
        $published = OdActivity::create([
            'user_id' => $staff->id, 'date' => '2026-11-11', 'name' => 'Dua baris dibuka', 'result' => 'Done', 'saved_at' => now(),
        ]);
        $second = OdActivity::create([
            'user_id' => $staff->id, 'date' => '2026-11-11', 'name' => 'Satu lagi', 'result' => 'On Progress', 'saved_at' => now(),
        ]);
        $untouched = OdActivity::create([
            'user_id' => $other->id, 'date' => '2026-11-11', 'name' => 'Punya orang lain', 'result' => 'Done', 'saved_at' => now(),
        ]);

        $this->actingAs($staff)->post(route('od-report.daily.reopen'));

        $bodies = $this->sentBodies();
        $body = end($bodies);
        $this->assertSame([], $body['entries']);
        $this->assertEqualsCanonicalizing(['mw-'.$published->id, 'mw-'.$second->id], $body['del']);
        $this->assertNull($published->fresh()->saved_at);
        $this->assertNull($second->fresh()->saved_at);
        $this->assertNotNull($untouched->fresh()->saved_at);   // laporan orang lain tidak ikut dibuka
    }

    public function test_reopen_of_a_row_that_was_never_written_to_the_sheet_sends_no_delete(): void
    {
        $staff = $this->staff();
        // Tersimpan tapi belum pernah terkirim (sheet_hash kosong) → tidak ada baris yang perlu dihapus.
        $activity = OdActivity::withoutEvents(fn () => OdActivity::create([
            'user_id' => $staff->id, 'date' => '2026-11-11', 'name' => 'Belum pernah terkirim', 'result' => 'Done', 'saved_at' => now(),
        ]));
        $this->assertNull($activity->sheet_hash);

        $this->actingAs($staff)->post(route('od-report.daily.reopen'));

        $this->assertNull($activity->fresh()->saved_at);
        $this->assertCount(0, $this->sentBodies());   // tidak ada baris sheet yang perlu dikosongkan
    }
}
