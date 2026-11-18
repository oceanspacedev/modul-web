<?php

namespace Tests\Feature;

use App\Models\OdActivity;
use App\Models\User;
use App\Services\OdSheetSync;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Sinkron Daily Report ke spreadsheet Weekly Report lewat Apps Script aplikasi OD Report lama (format kiriman sama).
 */
class OdSheetSyncTest extends TestCase
{
    private const URL = 'https://script.google.com/macros/s/UJI/exec';

    private User $staff;

    /** @var list<array<string, mixed>> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-11 15:00:00');

        $this->staff = User::factory()->staff()->create(['nama_lengkap' => 'RIZAL HAMDI']);
        $this->staff->setOdReportAccess(User::OD_STAFF);
        $this->staff = $this->staff->fresh();
    }

    private function enable(array $reply = ['ok' => true, 'a' => 1, 'd' => 0, 'fail' => [], 'log' => '']): void
    {
        config([
            'od-report.sheets.url' => self::URL,
            'od-report.sheets.spreadsheet' => 'https://docs.google.com/spreadsheets/d/SHEET123/edit#gid=0',
        ]);
        Http::fake([self::URL => function (Request $request) use ($reply) {
            $this->sent[] = $request->data();

            return Http::response($reply);
        }]);
    }

    private function addAndSave(string $name): OdActivity
    {
        $this->actingAs($this->staff)->post(route('od-report.daily.store'), [
            'date' => '2026-11-11', 'name' => $name, 'result' => 'Done', 'kendala' => 'Data telat',
        ]);
        $this->actingAs($this->staff)->post(route('od-report.daily.save'));

        return OdActivity::where('name', $name)->sole();
    }

    public function test_nothing_is_sent_while_sync_is_not_configured(): void
    {
        Http::fake();

        $this->addAndSave('Drafting IK');

        Http::assertNothingSent();
        $this->actingAs($this->staff)->get(route('od-report.daily.index'))->assertOk();
    }

    public function test_saved_report_is_sent_in_the_old_apps_script_format_once(): void
    {
        $this->enable();

        // Draft belum dikirim.
        $this->actingAs($this->staff)->post(route('od-report.daily.store'), ['date' => '2026-11-11', 'name' => 'Draft saja', 'result' => 'Done']);
        $this->assertSame([], $this->sent);
        OdActivity::query()->delete();

        $activity = $this->addAndSave('Drafting IK Retur');

        $this->assertCount(1, $this->sent);
        $this->assertSame('SHEET123', $this->sent[0]['weeklyId']);
        $this->assertSame([], $this->sent[0]['del']);
        $this->assertSame([
            'key' => $activity->sync_key,
            'u' => 'RIZAL HAMDI',
            'pos' => 'ORGANIZATION DEVELOPMENT STAFF',
            'tab' => "Nov'26",
            'wk' => 2,
            'n' => 'Drafting IK Retur',
            'r' => 'Closed',
            'k' => 'Data telat',
            's' => '-',
            'ke' => '-',
        ], $this->sent[0]['entries'][0]);
        $this->assertNotNull($activity->fresh()->synced_at);

        // Tidak ada perubahan: tidak dikirim lagi.
        app(OdSheetSync::class)->run();
        $this->assertCount(1, $this->sent);
    }

    public function test_edit_resends_the_same_row_and_delete_clears_it(): void
    {
        $this->enable();
        $activity = $this->addAndSave('Review SOP');
        $key = $activity->sync_key;

        $this->travel(1)->minutes();
        $this->actingAs($this->staff)->put(route('od-report.daily.update', $activity), [
            'date' => '2026-11-11', 'name' => 'Review SOP gudang', 'result' => 'Hold',
        ]);
        $this->assertCount(2, $this->sent);
        $this->assertSame([$key, 'Open'], [$this->sent[1]['entries'][0]['key'], $this->sent[1]['entries'][0]['r']]);

        $this->actingAs($this->staff)->delete(route('od-report.daily.destroy', $activity));
        $this->assertCount(3, $this->sent);
        $this->assertSame([$key], $this->sent[2]['del']);
        $this->assertSame(0, DB::table('od_sheet_deletions')->count());
    }

    public function test_apps_script_failure_keeps_changes_pending_without_breaking_the_save(): void
    {
        $this->enable(['ok' => false, 'error' => 'Spreadsheet sedang dipakai sinkron lain, coba lagi']);

        $this->actingAs($this->staff)->post(route('od-report.daily.store'), ['date' => '2026-11-11', 'name' => 'Review SOP', 'result' => 'Done']);
        $this->actingAs($this->staff)->post(route('od-report.daily.save'))->assertRedirect(route('od-report.daily.index'));

        $this->assertNull(OdActivity::sole()->synced_at);
        $sync = app(OdSheetSync::class);
        $this->assertSame(1, $sync->pendingCount());
        $this->assertFalse($sync->status()['state']);
        $this->assertStringContainsString('sedang dipakai', $sync->status()['message']);

        $leader = User::factory()->staff()->create();
        $leader->setOdReportAccess(User::OD_LEADER);
        $this->actingAs($leader->fresh())->get(route('od-report.settings.index'))->assertOk()
            ->assertSee('Bermasalah')->assertSee('Menunggu dikirim: <strong>1</strong>', false);
    }
}
