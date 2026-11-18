<?php

namespace App\Http\Controllers;

use App\Http\Requests\OdDocumentRequest;
use App\Models\DocumentCategory;
use App\Models\OdDocument;
use App\Models\OdDocumentStatus;
use App\Services\OdDocumentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Approval Dokumen OD: dokumen yang ditangani tim OD, termasuk proses dari Permintaan Dokumen.
 * PIC tidak ditampilkan di sini: dokumen milik orang yang menambahkannya (proses dari permintaan mengikuti PIC yang
 * diatur di Permintaan Masuk). Staff hanya melihat dan mengubah dokumen miliknya; Leader semua dokumen.
 */
class OdDocumentController extends Controller
{
    public const SORTS = ['new' => 'Terbaru', 'old' => 'Terlama', 'open' => 'Belum selesai dulu'];

    public function __construct(private OdDocumentService $documents) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $statuses = OdDocumentStatus::ordered()->get();

        $base = OdDocument::query()->visibleTo($user)
            ->when($request->filled('search'), fn (Builder $q) => $q->where('title', 'like', '%'.$request->string('search')->trim().'%'))
            ->when($request->filled('jenis'), fn (Builder $q) => $q->where('jenis', $request->string('jenis')));

        $counts = (clone $base)->selectRaw('od_document_status_id, count(*) as total')->groupBy('od_document_status_id')->pluck('total', 'od_document_status_id');

        $sort = array_key_exists($request->query('sort'), self::SORTS) ? $request->query('sort') : 'new';
        $documents = $base
            ->when($request->filled('status'), fn (Builder $q) => $q->where('od_document_status_id', $request->integer('status')))
            ->with(['status', 'documentRequest'])
            ->withCount(['activities' => fn (Builder $q) => $q->reported()])
            ->when($sort === 'open', fn (Builder $q) => $q->orderByRaw(
                'case when od_document_status_id in ('.$statuses->where('type', OdDocumentStatus::TYPE_OPEN)->pluck('id')->push(0)->implode(',').') then 0 else 1 end'
            ))
            ->orderBy('submitted_at', $sort === 'old' ? 'asc' : 'desc')
            ->orderBy('id', $sort === 'old' ? 'asc' : 'desc')
            ->paginate(25)
            ->withQueryString();

        return view('od-report.documents.index', [
            'title' => 'Approval Dokumen',
            'active' => 'od-documents',
            'documents' => $documents,
            'statuses' => $statuses,
            'categories' => DocumentCategory::ordered()->get(),
            'counts' => $counts,
            'sort' => $sort,
        ]);
    }

    public function store(OdDocumentRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->safe()->only(['title', 'jenis', 'badan_usaha', 'catatan']);
        $status = OdDocumentStatus::findOrFail($request->validated('od_document_status_id'));

        $document = new OdDocument([
            ...$data,
            'badan_usaha' => ($data['badan_usaha'] ?? null) ?: '-',
            // PIC tidak dipilih di Approval Dokumen: dokumen milik orang yang menambahkannya.
            'pic_user_id' => $user->getKey(),
            'submitted_at' => today(),
            'month' => today()->format('Y-m'),
        ]);
        $document->applyStatus($status);
        $document->save();

        if ($request->hasFile('file')) {
            $this->documents->attachFile($document, $request->file('file'));
        }

        return to_route('od-report.documents.index')->with('success', 'Dokumen ditambahkan.');
    }

    public function edit(Request $request, OdDocument $document): View
    {
        $this->authorizeEdit($request, $document);

        $document->load([
            'status', 'documentRequest', 'category.stages',
            'activities' => fn ($q) => $q->reported()->with('user')->orderByDesc('date'),
        ]);

        return view('od-report.documents.edit', [
            'title' => 'Ubah Dokumen',
            'active' => 'od-documents',
            'document' => $document,
            'statuses' => OdDocumentStatus::ordered()->get(),
            'categories' => DocumentCategory::ordered()->get(),
        ]);
    }

    public function update(OdDocumentRequest $request, OdDocument $document): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeEdit($request, $document);

        $data = $request->safe()->only(['title', 'jenis', 'badan_usaha', 'catatan']);
        if ($document->isFromRequest()) {
            // Jenis dokumen dari permintaan mengikuti kategori pengajuannya (tahapannya bergantung pada kategori).
            unset($data['jenis']);
        }
        $document->fill([...$data, 'badan_usaha' => ($data['badan_usaha'] ?? null) ?: '-']);

        $status = OdDocumentStatus::findOrFail($request->validated('od_document_status_id'));
        $stage = $request->validated('stage');

        if ($this->releasesWithoutFile($document, $document->isFromRequest() ? $stage : null, $status, $request->hasFile('file'))) {
            throw ValidationException::withMessages(['file' => OdDocument::FILE_REQUIRED_MESSAGE]);
        }
        if ($request->hasFile('file')) {
            $this->documents->attachFile($document, $request->file('file'));
        }

        if ($document->isFromRequest() && $stage && $stage !== $document->stage) {
            $this->documents->changeStage($document, $stage, $user);
        } elseif ($status->isNot($document->status)) {
            $this->documents->changeStatus($document, $status, $user);
        } else {
            $document->save();
        }

        return to_route('od-report.documents.index')->with('success', 'Dokumen diperbarui.');
    }

    public function updateStatus(Request $request, OdDocument $document): RedirectResponse
    {
        $this->authorizeEdit($request, $document);

        $validated = $request->validateWithBag('release', [
            'od_document_status_id' => ['required', 'integer', Rule::exists('od_document_statuses', 'id')],
            'file' => ['nullable', ...OdDocument::FILE_RULES],
        ], [], ['file' => 'file dokumen']);
        $status = OdDocumentStatus::findOrFail($validated['od_document_status_id']);

        if ($this->releasesWithoutFile($document, null, $status, $request->hasFile('file'))) {
            throw ValidationException::withMessages(['file' => OdDocument::FILE_REQUIRED_MESSAGE." ({$document->title})"])->errorBag('release');
        }
        if ($request->hasFile('file')) {
            $this->documents->attachFile($document, $request->file('file'));
        }

        $this->documents->changeStatus($document, $status, $request->user());

        return back()->with('success', 'Status "'.$document->title.'" menjadi '.$document->status->name.'.');
    }

    public function destroy(Request $request, OdDocument $document): RedirectResponse
    {
        $this->authorizeEdit($request, $document);

        if ($document->isFromRequest()) {
            return back()->with('error', 'Dokumen dari permintaan tidak dihapus. Ubah statusnya menjadi batal.');
        }

        $document->activities()->update(['od_document_id' => null]);
        $document->delete();

        return back()->with('success', 'Dokumen dihapus.');
    }

    /**
     * File dokumen untuk tim OD (siapa pun yang bisa melihat dokumennya).
     */
    public function file(Request $request, OdDocument $document): Response
    {
        throw_unless(OdDocument::query()->visibleTo($request->user())->whereKey($document->getKey())->exists(), AuthorizationException::class);
        abort_unless($document->hasFile(), 404);

        return $this->documents->fileResponse($document);
    }

    /**
     * Dokumen dari permintaan baru akan dirilis lewat perubahan ini, tapi belum punya file dan tidak mengunggah.
     */
    private function releasesWithoutFile(OdDocument $document, ?string $stage, OdDocumentStatus $status, bool $uploading): bool
    {
        $alreadyReleased = $document->status?->isDone() || $document->isReleased();

        return ! $uploading && ! $alreadyReleased && $document->needsFileToRelease() && $document->wouldRelease($stage, $status);
    }

    /** Staff hanya boleh mengubah dokumen miliknya. */
    private function authorizeEdit(Request $request, OdDocument $document): void
    {
        throw_unless($document->canBeEditedBy($request->user()), AuthorizationException::class);
    }
}
