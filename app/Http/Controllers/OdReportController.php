<?php

namespace App\Http\Controllers;

use App\Models\DocumentCategory;
use App\Models\OdActivity;
use App\Models\OdDocument;
use App\Models\OdDocumentStatus;
use App\Models\User;
use App\Services\OdReportService;
use App\Support\OdTeam;
use App\Support\OdWeek;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Weekly dan Monthly OD Report (ringkasannya tampil di Dashboard, lihat OdReportService::dashboard()). Semuanya dihitung dari aktivitas Daily yang sudah disimpan dan dokumen
 * di Approval Dokumen; tidak ada data yang diisi terpisah.
 */
class OdReportController extends Controller
{
    public function __construct(private OdReportService $reports) {}

    public function weekly(Request $request): View
    {
        $user = $request->user();
        [$month, $members, $memberId] = $this->monthAndMember($request);
        $weeks = OdWeek::inMonth($month);
        $weekKey = collect($weeks)->contains(fn (OdWeek $w) => $w->key() === $request->query('minggu')) ? $request->query('minggu') : null;
        $range = $weekKey ? [OdWeek::of($weekKey)] : $weeks;
        $status = in_array($request->query('status'), OdActivity::RESULTS, true) ? $request->query('status') : null;
        $search = trim((string) $request->query('search')) ?: null;

        // whereDate agar hari terakhir window tidak terbuang (kolom date tersimpan "Y-m-d H:i:s").
        $activities = OdActivity::query()->visibleTo($user)->reported()
            ->whereDate('date', '>=', $range[0]->start->toDateString())
            ->whereDate('date', '<=', end($range)->end()->toDateString())
            ->when($memberId, fn (Builder $q) => $q->where('user_id', $memberId))
            ->when($search, fn (Builder $q) => $q->where('name', 'like', '%'.$search.'%'))
            ->with(['user', 'document'])
            ->get();

        $rows = $this->reports->weeklyRows($activities)->when($status, fn (Collection $rows) => $rows->where('result', $status));

        return view('od-report.weekly', [
            'title' => 'Weekly Report',
            'active' => 'od-weekly',
            'month' => $month,
            'months' => $this->months(),
            'weeks' => $weeks,
            'weekKey' => $weekKey,
            'status' => $status,
            'search' => $search,
            'members' => $members,
            'memberId' => $memberId,
            'groups' => $rows->groupBy(fn ($row) => $row->week->key()),
        ]);
    }

    public function monthly(Request $request): View
    {
        $user = $request->user();
        [$month, $members, $memberId] = $this->monthAndMember($request);
        // Bulan = window minggu laporan (sama dengan Weekly), bukan 1–akhir bulan kalender.
        $weeks = OdWeek::inMonth($month);

        $activities = OdActivity::query()->visibleTo($user)->reported()
            ->whereDate('date', '>=', $weeks[0]->start->toDateString())
            ->whereDate('date', '<=', end($weeks)->end()->toDateString())
            ->when($memberId, fn (Builder $q) => $q->where('user_id', $memberId))
            ->get(['id', 'result']);

        $documents = OdDocument::query()->visibleTo($user)->where('month', $month)
            ->when($memberId, fn (Builder $q) => $q->where('pic_user_id', $memberId))
            ->with(['status', 'pic'])->orderBy('submitted_at')->orderBy('id')->get();

        $byJenis = DocumentCategory::ordered()->pluck('code')->merge($documents->pluck('jenis'))->unique()
            ->mapWithKeys(fn ($jenis) => [$jenis => $documents->where('jenis', $jenis)->values()])
            ->filter(fn ($list) => $list->isNotEmpty());

        return view('od-report.monthly', [
            'title' => 'Monthly Report',
            'active' => 'od-monthly',
            'month' => $month,
            'months' => $this->months(),
            'members' => $members,
            'memberId' => $memberId,
            'stats' => $this->reports->stats($activities),
            'documents' => $documents,
            'doneDocs' => $documents->filter(fn ($d) => $d->status->type === OdDocumentStatus::TYPE_DONE)->count(),
            'cancelDocs' => $documents->filter(fn ($d) => $d->status->type === OdDocumentStatus::TYPE_CANCEL)->count(),
            'byJenis' => $byJenis,
        ]);
    }

    /**
     * Bulan (Y-m) dari ?bulan — default bulan minggu laporan hari ini — dan anggota yang dipilih Leader dari ?user
     * (Staff selalu dirinya sendiri).
     *
     * @return array{0: string, 1: \Illuminate\Database\Eloquent\Collection<int, User>|Collection<int, User>, 2: ?int}
     */
    private function monthAndMember(Request $request): array
    {
        $user = $request->user();
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('bulan')) ? $request->query('bulan') : $this->defaultMonth();

        if (! $user->can('manage-od-report')) {
            return [$month, collect([$user]), $user->getKey()];
        }

        $members = OdTeam::members();
        $memberId = $members->contains('id', $request->integer('user')) ? $request->integer('user') : null;

        return [$month, $members, $memberId];
    }

    /** Bulan minggu laporan hari ini (Rabu-nya), bukan bulan kalender — 28 Nov 2026 sudah masuk Desember. */
    private function defaultMonth(): string
    {
        return OdWeek::of(today())->month();
    }

    /** 12 bulan terakhir untuk pilihan bulan, dihitung dari bulan default. @return list<string> */
    private function months(): array
    {
        $default = CarbonImmutable::createFromFormat('Y-m-d', $this->defaultMonth().'-01')->startOfDay();

        return collect(range(0, 11))->map(fn ($back) => $default->subMonths($back)->format('Y-m'))->all();
    }
}
