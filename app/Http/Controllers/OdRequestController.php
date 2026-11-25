<?php

namespace App\Http\Controllers;

use App\Jobs\SendHelpdeskWebhook;
use App\Models\DocumentCategory;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestComment;
use App\Models\OdDocument;
use App\Models\OdDocumentAttachment;
use App\Services\DocumentRequestService;
use App\Services\OdDocumentService;
use App\Support\OdTeam;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Permintaan Masuk (tim OD): daftar pengajuan dokumen dan halaman untuk memproses tiap proses (status, prioritas, PIC,
 * file rilis), jadwal observasi, catatan internal, komentar untuk pemohon, dan lampiran dari pemohon.
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
                $r->code, $r->nama, $r->divisi, $r->departemen, $r->jabatan, $r->email, $r->whatsapp, $r->badan_usaha, ...$r->processes->pluck('title'),
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
            'stageOptions' => [...OdDocument::REQUEST_STAGES, OdDocument::STAGE_CANCELLED],
            'stats' => [
                'total' => $all->count(),
                'new' => $all->filter(fn ($r) => $r->stage() === OdDocument::STAGE_RECEIVED)->count(),
                'running' => $all->filter(fn ($r) => $r->stage() === OdDocument::STAGE_PROGRESS)->count(),
                'finished' => $all->filter(fn ($r) => $r->stage() === OdDocument::STAGE_DONE)->count(),
            ],
        ]);
    }

    public function show(Request $request, DocumentRequest $documentRequest): View
    {
        $this->authorizeView($request, $documentRequest);
        $documentRequest->load(['category', 'processes.status', 'processes.pic', 'processes.builderDraft', 'processes.attachments', 'histories.user', 'comments.user', 'user']);

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
            'aksi' => ['nullable', Rule::in(['proses'])],
            'proses' => ['array'],
            'proses.*.stage' => ['nullable', Rule::in(OdDocument::REQUEST_STAGES)],
            'proses.*.prioritas' => ['nullable', Rule::in(OdDocument::PRIORITAS)],
            'proses.*.pic_user_id' => ['nullable', 'integer', Rule::in(OdTeam::query()->pluck('id')->all())],
            'proses.*.file' => ['nullable', ...OdDocument::FILE_RULES],
            'jadwal_observasi' => ['nullable', 'date_format:Y-m-d'],
            'catatan_admin' => ['nullable', 'string', 'max:2000'],
        ]);

        // Dua anggota tim membuka halaman yang sama: perubahan yang datang belakangan tidak menimpa diam-diam.
        if ($data['updated_at'] !== $documentRequest->updated_at->toIso8601String()) {
            return back()->withInput()->with('error', 'Pengajuan ini baru saja diubah anggota tim lain. Muat ulang halaman lalu ulangi perubahan Anda.');
        }

        // Tombol Proses: semua proses yang baru diterima (dan boleh diubah user ini) mulai dikerjakan.
        if (($data['aksi'] ?? null) === 'proses') {
            foreach ($documentRequest->processes()->with('status')->get() as $process) {
                if ($process->stage === OdDocument::STAGE_RECEIVED && ! $process->status?->isCancel() && $process->canBeEditedBy($request->user())) {
                    $data['proses'][$process->id] = [...($data['proses'][$process->id] ?? []), 'stage' => OdDocument::STAGE_PROGRESS];
                }
            }
        }

        // Dokumen yang dirilis harus punya file: file itu yang dikirim ke pemohon.
        $final = OdDocument::STAGE_DONE;
        $missing = $documentRequest->processes()->get()
            ->filter(function (OdDocument $process) use ($data, $final) {
                $input = $data['proses'][$process->id] ?? [];

                return ($input['stage'] ?? null) === $final && $process->stage !== $final
                    && $process->needsFileToRelease() && empty($input['file']);
            })
            ->mapWithKeys(fn (OdDocument $process) => ["proses.{$process->id}.file" => OdDocument::FILE_REQUIRED_MESSAGE." ({$process->title})"]);
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages($missing->all());
        }

        $service->update($documentRequest, $data, $request->user());

        return to_route('od-report.requests.show', $documentRequest)->with('success', 'Perubahan disimpan.');
    }

    public function comment(Request $request, DocumentRequest $documentRequest, DocumentRequestService $service): RedirectResponse
    {
        $this->authorizeView($request, $documentRequest);

        $data = $request->validate(['body' => ['required', 'string', 'max:'.DocumentRequestComment::MAX_LENGTH]], [], ['body' => 'komentar']);
        $service->comment($documentRequest, trim($data['body']), $request->user());

        return to_route('od-report.requests.show', $documentRequest)->with('success', 'Komentar terkirim ke pemohon.');
    }

    /**
     * Lampiran atau template terisi dari pemohon, untuk tim OD yang bisa melihat pengajuannya.
     */
    public function attachment(Request $request, DocumentRequest $documentRequest, OdDocumentAttachment $attachment, OdDocumentService $documents): Response
    {
        $this->authorizeView($request, $documentRequest);
        abort_unless($attachment->document?->document_request_id === $documentRequest->getKey(), 404);

        return $documents->attachmentResponse($attachment);
    }

    public function destroy(Request $request, DocumentRequest $documentRequest): RedirectResponse
    {
        throw_unless($request->user()->can('manage-od-report'), AuthorizationException::class);

        $documentRequest->processes()->each(fn (OdDocument $p) => $p->delete());
        $documentRequest->delete();
        // Helpdesk menerima snapshot terakhir berstatus dibatalkan.
        SendHelpdeskWebhook::forRequest($documentRequest);

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
