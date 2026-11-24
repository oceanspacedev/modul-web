<?php

namespace Tests\Feature;

use App\Models\OdActivity;
use App\Models\OdDocument;
use App\Models\OdSetting;
use App\Models\User;
use App\Notifications\BackgroundTaskFinished;
use App\Services\OdSheetImport;
use App\Services\OdSheetSync;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Import Google Sheets → OD Report (Weekly Report → pekerjaan, Approval Dokumen → dokumen) dari Pengaturan OD.
 */
class OdSheetImportTest extends TestCase
{
    private const SHEET_ID = '1u62t_RHu89i-KXOjEdfJfMIs7VT1w2qA93842nKcHK0';

    private const LINK = 'https://docs.google.com/spreadsheets/d/'.self::SHEET_ID.'/edit?usp=sharing';

    /** Tab yang dikembalikan Google untuk nama tab yang tidak ada. */
    private const FALLBACK = "\"\",\"\",\"Status Pekerjaan\"\n\"\",\"\",\"Open\"\n";

    /** @var array<string, string> isi CSV per nama tab */
    private array $tabs = [];

    private string $export = '';

    private int $exportStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();

        // Rabu, 11 Februari 2026: import Weekly berjalan sampai tab Feb'26.
        Carbon::setTestNow('2026-02-11 10:00:00');

        Http::fake(['docs.google.com/*' => function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            if (str_contains($request->url(), '/gviz/tq')) {
                return Http::response($this->tabs[$query['sheet']] ?? self::FALLBACK, 200, ['Content-Type' => 'text/csv; charset=utf-8']);
            }

            return $this->exportStatus === 200
                ? Http::response($this->export, 200, ['Content-Type' => 'text/csv; charset=utf-8'])
                : Http::response('<html>Login</html>', $this->exportStatus, ['Content-Type' => 'text/html']);
        }]);
    }

    private function leader(): User
    {
        $user = User::factory()->staff()->create(['nama_lengkap' => 'LEADER OD', 'position_id' => null]);
        $user->setOdReportAccess(User::OD_LEADER);

        return $user->fresh();
    }

    private function staff(string $name = 'STAFF OD'): User
    {
        $user = User::factory()->staff()->create(['nama_lengkap' => $name, 'position_id' => null]);
        $user->setOdReportAccess(User::OD_STAFF);

        return $user->fresh();
    }

    /** @param list<list<string>> $rows */
    private static function csv(array $rows): string
    {
        return collect($rows)->map(fn ($row) => collect($row)->map(fn ($cell) => '"'.str_replace('"', '""', $cell).'"')->implode(','))->implode("\n");
    }

    private function januaryTab(string $firstStatus = 'Closed'): string
    {
        return self::csv([
            ['POSISI', 'WEEK', 'DETAIL PEKERJAAN', 'STATUS', 'KENDALA', 'SOLUSI', 'KETERANGAN'],
            ['ORGANIZATION DEVELOPMENT LEADER', '1', 'Review SOP gudang', $firstStatus, '', '', ''],
            ['', '', 'Review SOP gudang', 'Open', 'Menunggu PIC', 'Follow up', ''],
            ['', '2', 'Rapat mingguan', '', '', '', ''],
            ['', '', 'Rekap data training', '', '', '', ''],
            ['', '', '', 'Closed', '', '', ''],
            ['ORGANIZATION DEVELOPMENT STAFF', '1', 'Drafting IK retur', 'On Progress', '', '', ''],
            ['WEBSITE DEVELOPER', '1', '', '', '', '', ''],
            ['', '2', '', '', '', '', ''],
        ]);
    }

    private function importWeekly(User $leader, array $extra = []): void
    {
        $this->actingAs($leader)
            ->put(route('od-report.settings.sheet-import', 'weekly'), ['link' => self::LINK, 'from' => '2026-01', ...$extra])
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_leader_imports_weekly_sheet_as_saved_activities_without_duplicates(): void
    {
        Notification::fake();
        $leader = $this->leader();
        $staff = $this->staff('STAFF A');
        $this->staff('STAFF B');
        $this->tabs["Jan'26"] = $this->januaryTab();

        $this->importWeekly($leader);

        // Leader dikenali otomatis dari posisinya; Staff ada dua sehingga harus dipetakan dulu (sementara dilewati).
        $activities = OdActivity::orderBy('id')->get();
        $this->assertSame(['Review SOP gudang', 'Review SOP gudang', 'Rapat mingguan', 'Rekap data training'], $activities->pluck('name')->all());
        $this->assertTrue($activities->every(fn ($a) => $a->user_id === $leader->id && ! $a->isDraft()));
        // Week 1 Januari 2026 = 3–9 Jan, Week 2 = 10–16 Jan; pekerjaan dicatat di hari Senin.
        $this->assertSame(['2026-01-05', '2026-01-05', '2026-01-12', '2026-01-12'], $activities->map(fn ($a) => $a->date->toDateString())->all());
        $this->assertSame([OdActivity::RESULT_DONE, OdActivity::RESULT_HOLD, OdActivity::RESULT_PROGRESS, OdActivity::RESULT_DONE], $activities->pluck('result')->all());
        $this->assertSame('Menunggu PIC', $activities[1]->kendala);

        $this->assertSame([
            'ORGANIZATION DEVELOPMENT LEADER' => $leader->id,
            'ORGANIZATION DEVELOPMENT STAFF' => null,
        ], OdSheetImport::positionMap());
        Notification::assertSentTo($leader, BackgroundTaskFinished::class, fn ($n) => $n->status === BackgroundTaskFinished::STATUS_PARTIAL
            && str_contains($n->message, '4 pekerjaan baru')
            && str_contains($n->message, "Jan'26")
            && str_contains(implode(' ', $n->errors), 'ORGANIZATION DEVELOPMENT STAFF'));

        // Import ulang dengan pemetaan Staff: tidak ada yang tergandakan, hanya pekerjaan Staff yang baru.
        $this->importWeekly($leader, ['positions' => ['ORGANIZATION DEVELOPMENT STAFF' => $staff->id]]);

        $this->assertSame(5, OdActivity::count());
        $this->assertSame(1, OdActivity::where('user_id', $staff->id)->count());
        $this->assertSame($staff->id, OdSheetImport::positionMap()['ORGANIZATION DEVELOPMENT STAFF']);
    }

    public function test_reimport_updates_changed_rows_but_keeps_app_edits_of_unchanged_rows(): void
    {
        $leader = $this->leader();
        $this->tabs["Jan'26"] = $this->januaryTab();
        $this->importWeekly($leader);

        $rapat = OdActivity::where('name', 'Rapat mingguan')->firstOrFail();
        $rapat->update(['result' => OdActivity::RESULT_DONE, 'keterangan' => 'Diubah di aplikasi']);

        $this->tabs["Jan'26"] = $this->januaryTab('On Progress');
        $this->importWeekly($leader);

        $this->assertSame(4, OdActivity::count());
        $this->assertSame(OdActivity::RESULT_PROGRESS, OdActivity::where('name', 'Review SOP gudang')->orderBy('id')->first()->result);
        $this->assertSame('Diubah di aplikasi', $rapat->fresh()->keterangan);
    }

    public function test_imported_activities_are_not_sent_back_to_the_synced_weekly_sheet(): void
    {
        OdSetting::put([
            OdSetting::SHEET_URL => 'https://script.google.com/macros/s/TEST/exec',
            OdSetting::SHEET_LINK => self::LINK,
        ]);
        Http::fake(['script.google.com/*' => Http::response(['ok' => true, 'a' => 0, 'd' => 0, 'fail' => []])]);
        $leader = $this->leader();
        $this->tabs["Jan'26"] = $this->januaryTab();

        $this->importWeekly($leader);

        $this->assertSame(4, OdActivity::count());
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'script.google.com'));
        $this->assertSame(0, app(OdSheetSync::class)->pending()->count());
    }

    public function test_missing_month_tabs_are_reported_instead_of_imported(): void
    {
        Notification::fake();
        $leader = $this->leader();

        $this->importWeekly($leader);

        $this->assertSame(0, OdActivity::count());
        Notification::assertSentTo($leader, BackgroundTaskFinished::class, fn ($n) => str_contains($n->message, 'Bulan: tidak ada'));
    }

    public function test_leader_imports_approval_documents_and_reimport_does_not_duplicate(): void
    {
        $leader = $this->leader();
        $this->export = self::csv([
            ['Tanggal ', 'DOKUMEN', 'Status', 'Catatan', '', ''],
            ['01/02/2025', '007-SOP-RETAIL AUTO EV- PROSEDUR PERHITUNGAN UANG [CV CS]', 'Selesai', '', '', ''],
            ['12/06/2025', 'SMI-001-IK-WAREHOUSE-INSTRUKSI KERJA PELAPORAN SURAT JALAN', 'Dalam proses', 'pak adi', '', 'NOTE: pakai email lain'],
            ['', 'MEMO INTERNAL SKEMA SALARY TIM ONLINE [PT MSI', '', '', '', ''],
            ['13/06/2025', 'BERITA ACARA WRITE OFF STOCK', 'Diblokir', '', '', ''],
            ['', '', '', '', '', ''],
        ]);

        $this->actingAs($leader)
            ->put(route('od-report.settings.sheet-import', 'approval'), ['link' => 'https://docs.google.com/spreadsheets/d/14-3-nIZNL3qd2r1C8OpyyphGyaVCqh5t5Gb8jgHFu_g/edit?usp=sharing'])
            ->assertSessionHas('success');

        $documents = OdDocument::with('status')->orderBy('id')->get();
        $this->assertSame(['SOP', 'IK', 'MEMO', 'Dokumen Lainnya'], $documents->pluck('jenis')->all());
        $this->assertSame(['CV CS', 'PT SMI', 'PT MSI', '-'], $documents->pluck('badan_usaha')->all());
        $this->assertSame(['Release', 'On Progress', 'On Progress', 'On Progress'], $documents->pluck('status.name')->all());
        $this->assertSame(['2025-02-01', '2025-06-12', '2025-06-12', '2025-06-13'], $documents->map(fn ($d) => $d->submitted_at->toDateString())->all());
        $this->assertSame('pak adi · NOTE: pakai email lain', $documents[1]->catatan);
        $this->assertTrue($documents->every(fn ($d) => $d->pic_user_id === $leader->id && $d->document_request_id === null && $d->month === $d->submitted_at->format('Y-m')));

        // Dokumen yang dihapus di aplikasi tidak muncul lagi; sisanya tidak tergandakan.
        $documents[3]->delete();
        $this->actingAs($leader)->put(route('od-report.settings.sheet-import', 'approval'), ['link' => self::LINK]);

        $this->assertSame(3, OdDocument::count());
        $this->assertSame(4, OdDocument::withTrashed()->count());
        $this->assertStringContainsString('0 dokumen baru', app(OdSheetImport::class)->status('approval')['message']);
    }

    public function test_private_sheet_fails_with_a_clear_message(): void
    {
        Notification::fake();
        $leader = $this->leader();
        $this->exportStatus = 401;

        $this->actingAs($leader)->put(route('od-report.settings.sheet-import', 'approval'), ['link' => self::LINK]);

        $this->assertSame(0, OdDocument::count());
        Notification::assertSentTo($leader, BackgroundTaskFinished::class, fn ($n) => $n->status === BackgroundTaskFinished::STATUS_FAILED
            && str_contains($n->message, 'Siapa saja yang memiliki link'));
        $this->assertFalse(app(OdSheetImport::class)->status('approval')['ok']);
    }

    public function test_only_leaders_can_import_and_the_link_is_validated(): void
    {
        $leader = $this->leader();

        $this->actingAs($this->staff())
            ->put(route('od-report.settings.sheet-import', 'approval'), ['link' => self::LINK])
            ->assertRedirect();
        $this->assertNull(OdSetting::value(OdSetting::IMPORT_APPROVAL_LINK));

        $this->actingAs($leader)
            ->put(route('od-report.settings.sheet-import', 'weekly'), ['link' => 'https://example.com/sheet', 'from' => '2026-01'])
            ->assertSessionHasErrorsIn('import-weekly', 'link');

        $this->actingAs($leader)->get(route('od-report.settings.index'))
            ->assertOk()
            ->assertSee('Import dari Google Sheets')
            ->assertSee('value="2026-01"', false);
        Http::assertNothingSent();
    }
}
