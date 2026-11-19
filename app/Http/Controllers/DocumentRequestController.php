<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentRequestRequest;
use App\Models\DocumentCategory;
use App\Models\DocumentRequest;
use App\Models\OdDocument;
use App\Services\DocumentCategoryService;
use App\Services\DocumentRequestService;
use App\Services\OdDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sisi pemohon Permintaan Dokumen: form (publik atau login), lacak dengan kode tiket, contoh dokumen, dan daftar
 * pengajuan milik user yang login.
 */
class DocumentRequestController extends Controller
{
    public function create(Request $request): View
    {
        $user = $request->user()?->loadMissing(['divisi', 'roles']);
        $jabatan = $user?->jabatan?->name;
        $categories = DocumentCategory::active()->ordered()->with('stages')->get();

        return view('document-request.create', [
            'title' => 'Permintaan Dokumen',
            'active' => 'document-request',
            'categories' => $categories,
            // ?kategori=JD memilihkan kategori; satu-satunya kategori aktif langsung terpilih.
            'selectedCategory' => $categories->first(fn (DocumentCategory $c) => strcasecmp($c->code, (string) $request->query('kategori')) === 0)?->id
                ?? ($categories->count() === 1 ? $categories->first()->id : null),
            'prefill' => $user ? [
                'nama' => $user->nama_lengkap,
                'divisi' => $user->divisi?->name,
                'jabatan' => collect(DocumentRequest::JABATAN)->first(fn ($j) => strcasecmp($j, (string) $jabatan) === 0),
                'whatsapp' => $user->no_hp,
                'email' => $user->email,
            ] : [],
        ]);
    }

    public function store(StoreDocumentRequestRequest $request, DocumentRequestService $service): RedirectResponse
    {
        // Honeypot: bot yang mengisi kolom tersembunyi mendapat jawaban sukses palsu.
        if (filled($request->input('website'))) {
            return to_route('document-request.create')->with('success', 'Pengajuan terkirim.');
        }

        $documentRequest = $service->create($request->validated(), $request->user());

        return to_route('document-request.track', ['kode' => $documentRequest->code])->with('submitted', $documentRequest->code);
    }

    public function track(Request $request): View
    {
        $code = strtoupper(trim((string) $request->query('kode')));
        $documentRequest = preg_match(DocumentRequest::CODE_PATTERN, $code)
            ? DocumentRequest::where('code', $code)->with(['category.stages', 'processes.status', 'histories'])->first()
            : null;

        return view('document-request.track', [
            'title' => 'Lacak Permintaan Dokumen',
            'active' => 'document-request',
            'code' => $code,
            'documentRequest' => $documentRequest,
            'cloud' => config('od-report.cloud'),
            'adminWhatsapp' => config('od-report.admin_whatsapp'),
        ]);
    }

    /**
     * File dokumen yang sudah dirilis, dibuka pemohon dari link WhatsApp atau halaman lacak. PDF tampil langsung di
     * browser; file Office diunduh dengan nama aslinya.
     */
    public function file(OdDocument $document, OdDocumentService $documents): Response
    {
        abort_unless($document->isFromRequest() && $document->hasFile() && $document->isReleased(), 404);

        return $documents->fileResponse($document);
    }

    /**
     * Contoh dokumen per kategori. Tanpa kategori: kategori aktif pertama yang punya contoh.
     */
    public function example(?DocumentCategory $category = null): View
    {
        $categories = DocumentCategory::active()->ordered()->get()->filter->hasExample()->values();
        $category ??= $categories->first();
        abort_unless($category?->hasExample(), 404);

        return view('document-request.example', [
            'title' => "Contoh Dokumen {$category->code}",
            'active' => 'document-request',
            'category' => $category,
            'categories' => $categories,
            'cloud' => config('od-report.cloud'),
        ]);
    }

    public function exampleFile(DocumentCategory $category, DocumentCategoryService $categories): Response
    {
        abort_unless($category->hasUploadedExample(), 404);

        return $categories->exampleResponse($category);
    }

    public function mine(Request $request): View
    {
        return view('document-request.mine', [
            'title' => 'Permintaan Saya',
            'active' => 'document-request',
            'requests' => DocumentRequest::where('user_id', $request->user()->getKey())
                ->with(['category.stages', 'processes.status'])->latest()->paginate(20),
        ]);
    }
}
