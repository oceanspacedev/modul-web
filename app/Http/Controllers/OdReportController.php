<?php

namespace App\Http\Controllers;

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
 * Dashboard, Weekly dan Monthly OD Report. Semuanya dihitung dari aktivitas Daily yang sudah disimpan dan dokumen
 * di Approval Dokumen; tidak ada data yang diisi terpisah.
 */
class OdReportController extends Controller
{
    public function __construct(private OdReportService $reports) {}

    public function dashboard(Request $request): View
    {
        $this->reports->autoSavePastDrafts();

        $user = $request->user();
        $today = CarbonImmutable::today();
        $week = OdWeek::of($today);
        $from = $today->subDays(29);

        $activities = OdActivity::query()->visibleTo($user)->reported()
            ->whereDate('date', '>=', $from->min($week->start->subWeek())->min($today->startOfMonth()))
            ->get(['id', 'user_id', 'date', 'result']);

        $thisWeek = $activities->filter(fn ($a) => $a->date->betweenIncluded($week->start, $week->end()))->count();
        $lastWeek = $activities->filter(fn ($a) => $a->date->betweenIncluded($week->start->subWeek(), $week->start->subDay()))->count();
        $month = $this->reports->stats($activities->filter(fn ($a) => $a->date->isSameMonth($today)));

        $documents = OdDocument::query()->visibleTo($user)->with('status')->get(['id', 'od_document_status_id']);
        $team = $user->can('manage-od-report') ? OdTeam::members() : collect([$user]);
        $savedToday = OdActivity::reported()->whereDate('date', $today)->distinct()->pluck('user_id');

        $chart = collect(range(29, 0))->map(fn ($back) => $today->subDays($back))->map(fn (CarbonImmutable $day) => [
            'label' => $day->format('d/m'),
            'done' => $activities->filter(fn ($a) => $a->date->isSameDay($day) && $a->result === OdActivity::RESULT_DONE)->count(),
            'open' => $activities->filter(fn ($a) => $a->date->isSameDay($day) && $a->result !== OdActivity::RESULT_DONE)->count(),
        ]);

        return view('od-report.dashboard', [
            'title' => 'Dashboard OD',
            'active' => 'od-dashboard',
            'today' => $today,
            'thisWeek' => $thisWeek,
            'trend' => $lastWeek ? (int) round(($thisWeek - $lastWeek) / $lastWeek * 100) : ($thisWeek ? 100 : 0),
            'month' => $month,
            'openDocs' => $documents->filter(fn ($d) => $d->status->isOpen())->count(),
            'doneDocs' => $documents->filter(fn ($d) => $d->status->isDone())->count(),
            'reportedToday' => $team->whereIn('id', $savedToday)->count(),
            'teamSize' => $team->count(),
            'attention' => $this->attention($user, $today, $month),
            'chart' => $chart,
            'recentActivities' => OdActivity::query()->visibleTo($user)->reported()->with(['user', 'document'])
                ->latest('date')->latest('id')->limit(8)->get(),
        ]);
    }

    public function weekly(Request $request): View
    {
        $user = $request->user();
        [$month, $members, $memberId] = $this->monthAndMember($request);
        $weeks = OdWeek::inMonth($month);
        $weekKey = collect($weeks)->contains(fn (OdWeek $w) => $w->key() === $request->query('minggu')) ? $request->query('minggu') : null;
        $range = $weekKey ? [OdWeek::of($weekKey)] : $weeks;

        $activities = OdActivity::query()->visibleTo($user)->reported()
            ->whereBetween('date', [$range[0]->start->toDateString(), end($range)->end()->toDateString()])
            ->when($memberId, fn (Builder $q) => $q->where('user_id', $memberId))
            ->when($request->filled('search'), fn (Builder $q) => $q->where('name', 'like', '%'.$request->string('search')->trim().'%'))
            ->with(['user', 'document'])
            ->get();

        $rows = $this->reports->weeklyRows($activities)
            ->when(in_array($request->query('status'), OdActivity::RESULTS, true), fn (Collection $rows) => $rows->where('result', $request->query('status')));

        return view('od-report.weekly', [
            'title' => 'Weekly Report',
            'active' => 'od-weekly',
            'month' => $month,
            'months' => $this->months(),
            'weeks' => $weeks,
            'weekKey' => $weekKey,
            'members' => $members,
            'memberId' => $memberId,
            'groups' => $rows->groupBy(fn ($row) => $row->week->key()),
        ]);
    }

    public function monthly(Request $request): View
    {
        $user = $request->user();
        [$month, $members, $memberId] = $this->monthAndMember($request);
        $start = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')->startOfDay();

        $activities = OdActivity::query()->visibleTo($user)->reported()
            ->whereBetween('date', [$start->toDateString(), $start->endOfMonth()->toDateString()])
            ->when($memberId, fn (Builder $q) => $q->where('user_id', $memberId))
            ->get(['id', 'result']);

        $documents = OdDocument::query()->visibleTo($user)->where('month', $month)
            ->when($memberId, fn (Builder $q) => $q->where('pic_user_id', $memberId))
            ->with(['status', 'pic'])->orderBy('submitted_at')->orderBy('id')->get();

        $byJenis = collect(OdDocument::JENIS)->merge($documents->pluck('jenis'))->unique()
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
     * Bulan (Y-m) dari ?bulan, dan anggota yang dipilih Leader dari ?user (Staff selalu dirinya sendiri).
     *
     * @return array{0: string, 1: \Illuminate\Database\Eloquent\Collection<int, User>|Collection<int, User>, 2: ?int}
     */
    private function monthAndMember(Request $request): array
    {
        $user = $request->user();
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('bulan')) ? $request->query('bulan') : today()->format('Y-m');

        if (! $user->can('manage-od-report')) {
            return [$month, collect([$user]), $user->getKey()];
        }

        $members = OdTeam::members();
        $memberId = $members->contains('id', $request->integer('user')) ? $request->integer('user') : null;

        return [$month, $members, $memberId];
    }

    /** 12 bulan terakhir untuk pilihan bulan. @return list<string> */
    private function months(): array
    {
        return collect(range(0, 11))->map(fn ($back) => today()->startOfMonth()->subMonths($back)->format('Y-m'))->all();
    }

    /**
     * Hal yang perlu perhatian user (dari aplikasi lama): draft belum disimpan, laporan hari ini kosong, Hold.
     *
     * @param  array{hold: int}  $month
     * @return list<array{0: string, 1: string}>
     */
    private function attention(User $user, CarbonImmutable $today, array $month): array
    {
        $mineToday = OdActivity::where('user_id', $user->getKey())->whereDate('date', $today)->get(['saved_at']);
        $items = [];

        if ($drafts = $mineToday->whereNull('saved_at')->count()) {
            $items[] = ["{$drafts} aktivitas belum disimpan", route('od-report.daily.index')];
        }
        if ($mineToday->isEmpty()) {
            $items[] = ['Laporan hari ini belum diisi', route('od-report.daily.index')];
        }
        if ($month['hold']) {
            $items[] = ["{$month['hold']} pekerjaan berstatus Hold bulan ini", route('od-report.weekly', ['status' => OdActivity::RESULT_HOLD])];
        }

        return $items;
    }
}
