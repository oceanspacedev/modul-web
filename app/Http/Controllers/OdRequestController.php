<?php

namespace App\Http\Controllers;

use App\Models\DocumentCategory;
use App\Models\DocumentRequest;
use App\Models\OdDocument;
use App\Services\DocumentRequestService;
use App\Support\OdTeam;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Permintaan Masuk (tim OD): daftar pengajuan dokumen dan halaman untuk memproses tiap proses, jadwal observasi, dan catatan.
 */
class OdRequestController extends Controller
{
    public const SORTS = ['new' => 'Terbaru', 'old' => 'Terlama', 'priority' => 'Prioritas tertinggi', 'progress' => 'Progres terendah', 'name' => 'Nama A-Z'];

    public function index(Request $request): View
    {
        $all = DocumentRequest::query()->visibleTo($request->user())->with(['category.stages', 'processes.status'])->latest()->get();
        $categories = DocumentCategory::ordered()->with('stages')->get();

        $search = mb_strtolower(trim((string) $request->query('search')));
        $filtered = $all
            ->when($search !== '', fn (Collection $c) => $c->filter(fn (DocumentRequest $r) => str_contains(mb_strtolower(implode(' ', [
                $r->code, $r->nama, $r->divisi, $r->departemen, $r->jabatan, $r->email, $r->whatsapp, ...$r->processes->pluck('title'),
            ])), $search)))
            ->when($request->filled('kategori'), fn (Collection $c) => $c->where('document_category_id', (int) $request->query('kategori')))
            ->when($request->filled('departemen'), fn (Collection $c) => $c->where('departemen', $request->query('departemen')))
            ->when($request->filled('prioritas'), fn (Collection $c) => $c->filter(fn (DocumentRequest $r) => $r->topPriority() === $request->query('prioritas')))
            ->when($request->filled('tahap'), fn (Collection $c) => $c->filter(fn (DocumentRequest $r) => $r->processes->contains(fn (OdDocument $p) => $p->publicStage() === $request->query('tahap'))));

        $sort = array_key_exists($request->query('sort'), self::SORTS) ? $request->query('sort') : 'new';
        $priorityRank = fn (DocumentRequest $r) => ($i = array_search($r->topPriority(), OdDocument::PRIORITAS, true)) === false ? 9 : $i;
        $sorted = match ($sort) {
            'old' => $filtered->sortBy('created_at'),
            'priority' => $filtered->sortBy([[$priorityRank, 'asc'], ['created_at', 'desc']]),
            'progress' => $filtered->sortBy([[fn ($r) => $r->progress(), 'asc'], ['created_at', 'desc']]),
            'name' => $filtered->sortBy(fn ($r) => mb_strtolower($r->nama)),
            default => $filtered,
        };

        $page = LengthAwarePaginator::resolveCurrentPage();
        $requests = new LengthAwarePaginator($sorted->forPage($page, 25)->values(), $sorted->count(), 25, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        return view('od-report.requests.index', [
            'title' => 'Permintaan Masuk',
            'active' => 'od-requests',
            'requests' => $requests,
            'sort' => $sort,
            'categories' => $categories,
            'stageOptions' => [...$categories->flatMap->stageNames()->unique()->values(), OdDocument::STAGE_CANCELLED],
            'stats' => [
                'total' => $all->count(),
                'new' => $all->filter(fn ($r) => $r->progress() === 0 && $r->stage() !== OdDocument::STAGE_CANCELLED)->count(),
                'running' => $all->filter(fn ($r) => $r->progress() > 0 && ! $r->isFinished())->count(),
                'finished' => $all->filter->isFinished()->count(),
            ],
        ]);
    }

    public function show(Request $request, DocumentRequest $documentRequest): View
    {
        $this->authorizeView($request, $documentRequest);
        $documentRequest->load(['category.stages', 'processes.status', 'processes.pic', 'histories.user', 'user']);

        return view('od-report.requests.show', [
            'title' => $documentRequest->code,
            'active' => 'od-requests',
            'documentRequest' => $documentRequest,
            'team' => OdTeam::members(),
        ]);
    }

    public function update(Request $request, DocumentRequest $documentRequest, DocumentRequestService $service): RedirectResponse
    {
        $this->authorizeView($request, $documentRequest);

        $data = $request->validate([
            'updated_at' => ['required', 'string'],
            'proses' => ['array'],
            'proses.*.stage' => ['nullable', Rule::in($documentRequest->category->stageNames())],
            'proses.*.prioritas' => ['nullable', Rule::in(OdDocument::PRIORITAS)],
            'proses.*.pic_user_id' => ['nullable', 'integer', Rule::in(OdTeam::query()->pluck('id')->all())],
            'jadwal_observasi' => ['nullable', 'date_format:Y-m-d'],
            'catatan_publik' => ['nullable', 'string', 'max:300'],
            'catatan_admin' => ['nullable', 'string', 'max:2000'],
        ]);

        // Dua anggota tim membuka halaman yang sama: perubahan yang datang belakangan tidak menimpa diam-diam.
        if ($data['updated_at'] !== $documentRequest->updated_at->toIso8601String()) {
            return back()->withInput()->with('error', 'Pengajuan ini baru saja diubah anggota tim lain. Muat ulang halaman lalu ulangi perubahan Anda.');
        }

        $service->update($documentRequest, $data, $request->user());

        return to_route('od-report.requests.show', $documentRequest)->with('success', 'Perubahan disimpan.');
    }

    public function destroy(Request $request, DocumentRequest $documentRequest): RedirectResponse
    {
        throw_unless($request->user()->can('manage-od-report'), AuthorizationException::class);

        $documentRequest->processes()->each(fn (OdDocument $p) => $p->delete());
        $documentRequest->delete();

        return to_route('od-report.requests.index')->with('success', "Pengajuan {$documentRequest->code} dihapus.");
    }

    private function authorizeView(Request $request, DocumentRequest $documentRequest): void
    {
        throw_unless(
            DocumentRequest::query()->visibleTo($request->user())->whereKey($documentRequest->getKey())->exists(),
            AuthorizationException::class
        );
    }
}
