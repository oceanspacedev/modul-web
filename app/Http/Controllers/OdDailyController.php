<?php

namespace App\Http\Controllers;

use App\Http\Requests\OdActivityRequest;
use App\Models\OdActivity;
use App\Models\OdDocument;
use App\Models\OdDocumentStatus;
use App\Models\User;
use App\Services\OdReportService;
use App\Support\OdTeam;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Daily Report OD: aktivitas ditambahkan sebagai draft, lalu "Simpan Laporan" memasukkannya ke Weekly dan Monthly.
 */
class OdDailyController extends Controller
{
    public function __construct(private OdReportService $reports) {}

    public function index(Request $request): View
    {
        $this->reports->autoSavePastDrafts();

        $user = $request->user();
        $today = CarbonImmutable::today();
        $mine = OdActivity::where('user_id', $user->getKey())->whereDate('date', $today)
            ->with('document')->orderBy('sort')->orderBy('id')->get();

        return view('od-report.daily.index', [
            'title' => 'Daily Report',
            'active' => 'od-daily',
            'today' => $today,
            'drafts' => $mine->filter->isDraft()->values(),
            'saved' => $mine->reject->isDraft()->values(),
            'documents' => $this->documentOptions($user),
            'text' => $request->boolean('teks') ? $this->reports->dailyText($user, $today) : null,
            'teamStatus' => $user->can('manage-od-report') ? $this->teamStatus($today) : collect(),
            'history' => OdActivity::where('user_id', $user->getKey())->reported()->whereDate('date', '<', $today)
                ->latest('date')->latest('id')->limit(10)->get(),
        ]);
    }

    public function store(OdActivityRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->activityData();
        $this->ensureDocumentVisible($user, $data['od_document_id'] ?? null);

        $isToday = CarbonImmutable::parse($data['date'])->isToday();
        OdActivity::create([
            ...$data,
            'user_id' => $user->getKey(),
            // Pekerjaan untuk hari yang sudah lewat langsung tersimpan (tidak ada draft hari kemarin).
            'saved_at' => $isToday ? null : now(),
            'sort' => (int) OdActivity::where('user_id', $user->getKey())->whereDate('date', $data['date'])->max('sort') + 1,
        ]);

        return to_route('od-report.daily.index')->with('success', $isToday ? 'Aktivitas ditambahkan ke draft.' : 'Aktivitas tersimpan.');
    }

    public function edit(Request $request, OdActivity $activity): View
    {
        $this->authorizeEdit($request->user(), $activity);
        $activity->load('user');

        return view('od-report.daily.edit', [
            'title' => 'Ubah Aktivitas',
            'active' => 'od-daily',
            'activity' => $activity,
            'documents' => $this->documentOptions($request->user(), $activity->od_document_id),
        ]);
    }

    public function update(OdActivityRequest $request, OdActivity $activity): RedirectResponse
    {
        $this->authorizeEdit($request->user(), $activity);
        $data = $request->activityData();
        $this->ensureDocumentVisible($request->user(), $data['od_document_id'] ?? null, $activity->od_document_id);

        $activity->update($data);

        return redirect()->to($request->input('back') === 'weekly' ? route('od-report.weekly') : route('od-report.daily.index'))
            ->with('success', 'Aktivitas diperbarui.');
    }

    public function destroy(Request $request, OdActivity $activity): RedirectResponse
    {
        $this->authorizeEdit($request->user(), $activity);
        $activity->delete();

        return back()->with('success', 'Aktivitas dihapus.');
    }

    /** Geser draft ke atas/bawah dalam urutan laporan hari itu. */
    public function move(Request $request, OdActivity $activity): RedirectResponse
    {
        $this->authorizeEdit($request->user(), $activity);
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        $siblings = OdActivity::where('user_id', $activity->user_id)->whereDate('date', $activity->date)
            ->orderBy('sort')->orderBy('id')->get()->values();
        $index = $siblings->search(fn (OdActivity $a) => $a->is($activity));
        $swapWith = $siblings->get($direction === 'up' ? $index - 1 : $index + 1);

        if ($swapWith) {
            $siblings->splice($index, 1);
            $siblings->splice($direction === 'up' ? $index - 1 : $index + 1, 0, [$activity]);
            $siblings->each(fn (OdActivity $a, int $i) => $a->sort === $i + 1 ? null : $a->update(['sort' => $i + 1]));
        }

        return back();
    }

    /** "Simpan Laporan": semua draft hari ini masuk ke Weekly dan Monthly. */
    public function save(Request $request): RedirectResponse
    {
        $count = OdActivity::where('user_id', $request->user()->getKey())->whereDate('date', today())
            ->whereNull('saved_at')->update(['saved_at' => now()]);

        return to_route('od-report.daily.index')->with('success', $count
            ? "{$count} aktivitas tersimpan dan masuk ke Weekly & Monthly."
            : 'Tidak ada draft untuk disimpan.');
    }

    /** "Buka Kembali": laporan hari ini yang sudah disimpan kembali menjadi draft. */
    public function reopen(Request $request): RedirectResponse
    {
        OdActivity::where('user_id', $request->user()->getKey())->whereDate('date', today())
            ->whereNotNull('saved_at')->update(['saved_at' => null]);

        return to_route('od-report.daily.index')->with('success', 'Laporan hari ini dibuka kembali.');
    }

    /**
     * Dokumen yang bisa dihubungkan: dokumen berjalan milik user (Leader: semua), plus dokumen yang sudah terhubung.
     *
     * @return Collection<int, OdDocument>
     */
    private function documentOptions(User $user, ?int $current = null): Collection
    {
        $openIds = OdDocumentStatus::where('type', OdDocumentStatus::TYPE_OPEN)->pluck('id');

        return OdDocument::query()->visibleTo($user)
            ->where(fn (Builder $q) => $q->whereIn('od_document_status_id', $openIds)->when($current, fn ($q) => $q->orWhere('id', $current)))
            ->orderByDesc('submitted_at')->orderByDesc('id')
            ->get(['id', 'title', 'jenis']);
    }

    private function ensureDocumentVisible(User $user, ?int $documentId, ?int $current = null): void
    {
        if ($documentId && $documentId !== $current && ! OdDocument::query()->visibleTo($user)->whereKey($documentId)->exists()) {
            throw new AuthorizationException;
        }
    }

    private function authorizeEdit(User $user, OdActivity $activity): void
    {
        throw_unless($activity->canBeEditedBy($user), AuthorizationException::class);
    }

    /**
     * Status input hari ini per anggota tim (untuk Leader).
     *
     * @return \Illuminate\Support\Collection<int, array{user: User, drafts: int, saved: int}>
     */
    private function teamStatus(CarbonImmutable $date): \Illuminate\Support\Collection
    {
        $today = OdActivity::whereDate('date', $date)->get(['user_id', 'saved_at'])->groupBy('user_id');

        return OdTeam::members()->map(fn (User $member) => [
            'user' => $member,
            'drafts' => $today->get($member->id, collect())->whereNull('saved_at')->count(),
            'saved' => $today->get($member->id, collect())->whereNotNull('saved_at')->count(),
        ]);
    }
}
