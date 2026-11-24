<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentCategoryRequest;
use App\Models\DocumentCategory;
use App\Models\DocumentDraft;
use App\Models\DocumentRequestRecipient;
use App\Models\OdDocument;
use App\Services\DocumentCategoryService;
use App\Support\OdTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Data master Kategori Dokumen (OD Leader): jenis dokumen yang bisa diajukan di Permintaan Dokumen, tahapan
 * pengerjaannya, dan PIC bawaannya.
 */
class DocumentCategoryController extends Controller
{
    public function __construct(private DocumentCategoryService $categories) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        return view('document-category.index', [
            'title' => 'Kategori Dokumen',
            'active' => 'document-category',
            'categories' => DocumentCategory::ordered()
                ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")))
                ->with(['stages', 'pic'])->withCount(['documentRequests' => fn ($q) => $q->withTrashed()])->get(),
            'documentCounts' => OdDocument::withTrashed()->selectRaw('jenis, count(*) as total')->groupBy('jenis')->pluck('total', 'jenis'),
            // Draf Dokumen Builder juga mengunci kategori; dihitung sekali di sini supaya daftarnya tidak N+1.
            'draftCounts' => DocumentDraft::withTrashed()->selectRaw('document_category_id, count(*) as total')->groupBy('document_category_id')->pluck('total', 'document_category_id'),
            'recipients' => DocumentRequestRecipient::orderBy('name')->get(),
            'fallbackWhatsapp' => config('od-report.admin_whatsapp'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(DocumentCategoryRequest::identityRules(), [], ['code' => 'kode', 'name' => 'nama']);

        $category = $this->categories->create($data);

        return to_route('document-category.edit', $category)->with('success', "Kategori {$category->code} ditambahkan. Atur tahapannya di bawah.");
    }

    public function edit(DocumentCategory $category): View
    {
        $category->load('stages');

        return view('document-category.edit', [
            'title' => 'Ubah Kategori Dokumen',
            'active' => 'document-category',
            'category' => $category,
            'inUse' => $category->isInUse(),
            'team' => OdTeam::members(),
            'stageUsage' => OdDocument::withTrashed()->where('jenis', $category->code)
                ->selectRaw('stage, count(*) as total')->groupBy('stage')->pluck('total', 'stage'),
        ]);
    }

    public function update(DocumentCategoryRequest $request, DocumentCategory $category): RedirectResponse
    {
        $data = $request->validated();
        // validated() bisa mengubah urutan baris tahap; urutan yang benar = urutan kiriman form.
        $data['stages'] = array_map(fn ($key) => $data['stages'][$key], array_keys($request->input('stages')));

        $this->categories->update($category, $data);

        return to_route('document-category.edit', $category)->with('success', 'Kategori disimpan.');
    }

    public function destroy(DocumentCategory $category): RedirectResponse
    {
        if ($category->isInUse()) {
            return back()->with('error', "Kategori {$category->code} sudah dipakai ({$category->usageSummary()}) sehingga tidak bisa dihapus. Nonaktifkan saja supaya tidak muncul di form.");
        }

        $this->categories->replaceExample($category, null);
        $category->delete();

        return to_route('document-category.index')->with('success', "Kategori {$category->code} dihapus.");
    }
}
