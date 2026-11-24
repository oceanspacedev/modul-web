<?php

namespace App\Http\Controllers;

use App\Jobs\AiTask;
use App\Jobs\GenerateDocumentDraft;
use App\Models\DocumentCategory;
use App\Models\DocumentDraft;
use App\Models\OdDocument;
use App\Models\OdDocumentStatus;
use App\Support\DocumentBuilderContent;
use App\Support\DocumentBuilderTemplates;
use App\Support\DocumentDraftWord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Dokumen Builder (tim OD): pilih kategori dokumen, isi form bertahap, AI menyusun isinya, lalu draf diedit per bagian
 * dan diekspor ke Word atau dicetak ke PDF. Staff hanya mengelola draf buatannya, Leader semua draf.
 */
class DocumentBuilderController extends Controller
{
    public function index(Request $request): View
    {
        $drafts = DocumentDraft::visibleTo($request->user())
            ->with(['category', 'user'])
            ->when($request->filled('kategori'), fn (Builder $q) => $q->where('document_category_id', $request->integer('kategori')))
            ->when($request->filled('search'), fn (Builder $q) => $q->where('title', 'like', '%'.$request->string('search')->trim().'%'))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('document-builder.index', [
            'title' => 'Dokumen Builder',
            'active' => 'document-builder',
            'drafts' => $drafts,
            'categories' => DocumentCategory::ordered()->get(),
        ]);
    }

    /** Pilih kategori dokumen yang mau disusun. */
    public function create(Request $request): View
    {
        return view('document-builder.create', [
            'title' => 'Buat Dokumen',
            'active' => 'document-builder',
            'categories' => DocumentCategory::active()->ordered()->get(),
            'processes' => $this->requestProcesses($request),
        ]);
    }

    /** Form bertahap untuk kategori terpilih; ?permintaan={item} mengisi form dari Permintaan Dokumen. */
    public function form(Request $request, DocumentCategory $category): View|RedirectResponse
    {
        abort_unless($category->is_active, 404);

        if ($request->filled('permintaan')) {
            $process = $this->sourceProcess($request, $category, $request->integer('permintaan'));

            if ($draft = $this->openableDraft($request, $process)) {
                return to_route('document-builder.edit', $draft)->with('info', 'Item permintaan ini sudah punya draf.');
            }

            // Draf item ini ada tapi milik pengguna lain, jadi tidak bisa dibuka (403): PIC menyusun versinya sendiri.
            if ($process->builderDraft) {
                session()->flash('info', 'Draf item ini dibuat pengguna lain dan hanya bisa dibuka pembuatnya. Silakan susun draf Anda sendiri.');
            }

            $badanUsaha = $process->badan_usaha !== '-' ? $process->badan_usaha : null;

            return $this->formView($category, null, DocumentDraft::prefillFrom($process, DocumentBuilderTemplates::for($category)), $badanUsaha, $process);
        }

        $user = $request->user()->loadMissing(['area', 'divisi']);

        return $this->formView($category, null, [
            'department' => $user->divisi?->name,
        ], $user->area?->name);
    }

    public function store(Request $request, DocumentCategory $category): RedirectResponse
    {
        abort_unless($category->is_active, 404);

        $process = $request->filled('permintaan') ? $this->sourceProcess($request, $category, $request->integer('permintaan')) : null;
        if ($draft = $this->openableDraft($request, $process)) {
            return to_route('document-builder.edit', $draft)->with('info', 'Item permintaan ini sudah punya draf.');
        }

        $template = DocumentBuilderTemplates::for($category);
        $validated = $this->validateAnswers($request, $template);

        $draft = DocumentDraft::create([
            'document_category_id' => $category->id,
            'user_id' => $request->user()->getKey(),
            'od_document_id' => $process?->id,
            'title' => $validated['answers']['title'],
            'department' => $validated['answers']['department'] ?? null,
            'company' => $validated['company'] ?? null,
            'answers' => $validated['answers'],
            'content' => DocumentBuilderContent::blank($template['sections']),
        ]);

        return $this->startCompose($draft, $request->input('mode') === 'ai');
    }

    /** Ubah jawaban form lalu susun ulang isi draf dengan AI. */
    public function answers(Request $request, DocumentDraft $draft): View
    {
        $this->authorizeDraft($request, $draft);
        $draft->loadMissing(['category', 'odDocument.documentRequest']);

        return $this->formView($draft->category, $draft, $draft->answers ?? [], $draft->company);
    }

    public function recompose(Request $request, DocumentDraft $draft): RedirectResponse
    {
        $this->authorizeDraft($request, $draft);
        $draft->loadMissing('category');

        $template = $draft->template();
        $validated = $this->validateAnswers($request, $template);

        $draft->update([
            'title' => $validated['answers']['title'],
            'department' => $validated['answers']['department'] ?? null,
            'company' => $validated['company'] ?? null,
            'answers' => $this->mergeAnswers($draft->answers ?? [], $validated['answers'], $template),
        ]);

        if ($request->input('mode') !== 'ai') {
            return to_route('document-builder.edit', $draft)->with('success', 'Jawaban disimpan.');
        }

        return $this->startCompose($draft, true);
    }

    /** Halaman tunggu selama AI menyusun isi draf. */
    public function progress(Request $request, DocumentDraft $draft): View|RedirectResponse
    {
        $this->authorizeDraft($request, $draft);

        // Penyusunan yang sudah selesai tidak perlu ditunggu lagi: halaman proses langsung memberi editor,
        // termasuk untuk draf yang isinya sudah ada tapi belum pernah dipolling.
        if ($this->composeStatus($draft)['status'] === 'done') {
            return to_route('document-builder.edit', $draft);
        }

        return view('document-builder.progress', [
            'title' => 'Menyusun Dokumen',
            'active' => 'document-builder',
            'draft' => $draft->loadMissing('category'),
        ]);
    }

    public function aiStatus(Request $request, DocumentDraft $draft): JsonResponse
    {
        $this->authorizeDraft($request, $draft);

        // Status hanya dibaca, tidak pernah mengubah draf: token dibersihkan oleh GenerateDocumentDraft saat isinya
        // tersimpan, jadi poll kedua atas penyusunan yang berhasil tetap melaporkan "done".
        $status = $this->composeStatus($draft);

        return response()->json(['status' => $status['status'], 'message' => $status['message']], $status['http']);
    }

    public function show(Request $request, DocumentDraft $draft): View
    {
        $this->authorizeDraft($request, $draft);
        $draft->loadMissing(['category', 'user', 'odDocument.documentRequest']);

        return view('document-builder.show', [
            'title' => $draft->title,
            'active' => 'document-builder',
            'draft' => $draft,
            'template' => $draft->template(),
            'content' => $draft->sections(),
            'identity' => DocumentDraftWord::identity($draft),
        ]);
    }

    public function edit(Request $request, DocumentDraft $draft): View
    {
        $this->authorizeDraft($request, $draft);
        $draft->loadMissing(['category', 'odDocument.documentRequest']);

        return view('document-builder.edit', [
            'title' => 'Edit '.$draft->title,
            'active' => 'document-builder',
            'draft' => $draft,
            'template' => $draft->template(),
            'content' => $draft->sections(),
        ]);
    }

    public function update(Request $request, DocumentDraft $draft): RedirectResponse
    {
        $this->authorizeDraft($request, $draft);
        $draft->loadMissing('category');

        $validated = $request->validate([
            'title' => 'required|string|max:250',
            'department' => 'nullable|string|max:150',
            'company' => 'nullable|string|max:150',
            'document_number' => 'nullable|string|max:60',
            'revision' => 'nullable|string|max:20',
            'effective_date' => 'nullable|date',
            'approved_by' => 'nullable|string|max:150',
            'content' => 'nullable|array',
        ]);

        $draft->update([
            ...collect($validated)->except('content')->all(),
            'content' => DocumentBuilderContent::normalize($draft->template()['sections'], $validated['content'] ?? []),
        ]);

        return $request->input('after') === 'show'
            ? to_route('document-builder.show', $draft)->with('success', 'Dokumen disimpan.')
            : back()->with('success', 'Dokumen disimpan.');
    }

    public function word(Request $request, DocumentDraft $draft): BinaryFileResponse
    {
        $this->authorizeDraft($request, $draft);
        $draft->loadMissing('category');

        $path = tempnam(sys_get_temp_dir(), 'docx');
        DocumentDraftWord::save($draft, $path);

        return response()->download($path, $draft->fileBaseName().'.docx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend();
    }

    public function destroy(Request $request, DocumentDraft $draft): RedirectResponse
    {
        $this->authorizeDraft($request, $draft);

        $draft->delete();

        return to_route('document-builder.index')->with('success', 'Dokumen dihapus.');
    }

    private function formView(DocumentCategory $category, ?DocumentDraft $draft, array $answers, ?string $company, ?OdDocument $source = null): View
    {
        return view('document-builder.form', [
            'title' => ($draft ? 'Susun Ulang ' : 'Buat ').$category->name,
            'active' => 'document-builder',
            'category' => $category,
            'draft' => $draft,
            'source' => $source ?? $draft?->odDocument,
            'template' => DocumentBuilderTemplates::for($category),
            'answers' => $answers,
            'company' => $company,
            'aiReady' => filled(config('services.gemini.api_key')),
        ]);
    }

    /**
     * Item Permintaan Dokumen yang masih berjalan dan bisa disusun user ini (Staff: yang PIC-nya dia, Leader: semua).
     *
     * @return Collection<int, OdDocument>
     */
    private function requestProcesses(Request $request): Collection
    {
        return OdDocument::query()
            ->whereNotNull('document_request_id')
            ->visibleTo($request->user())
            ->whereHas('status', fn (Builder $q) => $q->where('type', OdDocumentStatus::TYPE_OPEN))
            ->whereHas('category', fn (Builder $q) => $q->where('is_active', true))
            ->with(['documentRequest', 'category', 'builderDraft'])
            ->latest('id')
            ->limit(50)
            ->get();
    }

    /** Item permintaan sumber draf: harus dari permintaan, berkategori sama, dan boleh ditangani user ini. */
    private function sourceProcess(Request $request, DocumentCategory $category, int $id): OdDocument
    {
        $process = OdDocument::with(['documentRequest', 'builderDraft'])->whereNotNull('document_request_id')->findOrFail($id);

        abort_unless($process->jenis === $category->code, 404);
        throw_unless($process->canBeEditedBy($request->user()), AuthorizationException::class);

        return $process;
    }

    /**
     * Draf terbaru satu item permintaan yang boleh dibuka user ini. Draf buatan pengguna lain tidak pernah ditawarkan
     * sebagai tautan karena halaman draf hanya untuk pembuatnya (DocumentDraft::canBeManagedBy). Satu item boleh punya
     * lebih dari satu draf (od_document_id tanpa unique), jadi PIC yang itemnya didrafkan orang lain bisa menyusun
     * versinya sendiri.
     */
    private function openableDraft(Request $request, ?OdDocument $process): ?DocumentDraft
    {
        $draft = $process?->builderDraft;

        return $draft && $draft->canBeManagedBy($request->user()) ? $draft : null;
    }

    /** @return array{answers: array<string, string>, company?: string|null} */
    private function validateAnswers(Request $request, array $template): array
    {
        $rules = ['company' => 'nullable|string|max:150', 'mode' => 'required|in:ai,manual'];
        $attributes = [];

        foreach ($template['questions'] as $question) {
            $max = match ($question['key']) {
                'title' => 250,
                'department' => 150,
                default => 2000,
            };
            // Isi manual cukup dengan judul; AI butuh semua jawaban wajib.
            $isRequired = $question['key'] === 'title' || ($question['required'] && $request->input('mode') === 'ai');
            $rules['answers.'.$question['key']] = [$isRequired ? 'required' : 'nullable', 'string', 'max:'.$max];
            $attributes['answers.'.$question['key']] = Str::lower($question['label']);
        }

        $validated = $request->validate($rules, [], $attributes);

        $validated['answers'] = collect($validated['answers'] ?? [])
            ->map(fn ($answer) => trim((string) $answer))
            ->filter(fn ($answer) => $answer !== '')
            ->all();

        return $validated;
    }

    /**
     * Gabungkan jawaban hasil validasi dengan jawaban yang tersimpan. Kunci milik template sekarang selalu ikut
     * kiriman form (jadi jawaban yang dikosongkan benar-benar hilang), sedangkan jawaban dari langkah yang sudah
     * dihapus atau diganti namanya OD Leader dipertahankan supaya tidak ikut terhapus saat menyusun ulang.
     *
     * @param  array<string, string>  $existing
     * @param  array<string, string>  $answers
     * @param  array{questions: list<array{key: string}>}  $template
     * @return array<string, string>
     */
    private function mergeAnswers(array $existing, array $answers, array $template): array
    {
        return array_merge(array_diff_key($existing, array_flip(array_column($template['questions'], 'key'))), $answers);
    }

    /** Jalankan AI di antrean (bila diminta dan tersedia), atau langsung ke editor untuk diisi manual. */
    private function startCompose(DocumentDraft $draft, bool $useAi): RedirectResponse
    {
        if (! $useAi) {
            return to_route('document-builder.edit', $draft)->with('success', 'Draf dibuat. Silakan isi setiap bagian.');
        }

        if (! filled(config('services.gemini.api_key'))) {
            return to_route('document-builder.edit', $draft)->with('error', 'AI belum dikonfigurasi (GEMINI_API_KEY). Silakan isi dokumen secara manual.');
        }

        $token = AiTask::newToken();
        $draft->update(['ai_token' => $token]);
        GenerateDocumentDraft::dispatch($token, $draft->id);

        return to_route('document-builder.progress', $draft);
    }

    /**
     * Status penyusunan AI satu draf, untuk halaman proses maupun polling-nya.
     *
     * Token draf hanyalah penunjuk hasil di cache. Hasil yang tidak ada lagi di cache — tokennya dibersihkan job saat
     * selesai, atau catatannya kedaluwarsa — tidak serta-merta kegagalan: isi draf yang sudah tersusun membuktikan
     * penyusunannya berhasil.
     *
     * @return array{status: string, message: string|null, http: int}
     */
    private function composeStatus(DocumentDraft $draft): array
    {
        $draft->loadMissing('category');
        $status = $draft->ai_token ? AiTask::status($draft->ai_token) : null;

        if ($status) {
            return ['status' => $status['status'], 'message' => $status['message'] ?? null, 'http' => 200];
        }

        if (! $draft->ai_token || $draft->hasContent()) {
            return ['status' => 'done', 'message' => null, 'http' => 200];
        }

        return [
            'status' => 'failed',
            'message' => 'Proses AI tidak ditemukan atau sudah kedaluwarsa. Silakan susun ulang.',
            'http' => 404,
        ];
    }

    private function authorizeDraft(Request $request, DocumentDraft $draft): void
    {
        throw_unless($draft->canBeManagedBy($request->user()), AuthorizationException::class);
    }
}
