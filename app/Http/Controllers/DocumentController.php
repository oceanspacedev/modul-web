<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Models\Divisi;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\DokumenType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

#[Authorize('viewAny', Document::class, only: ['index'])]
#[Authorize('view', 'document', only: ['history'])]
#[Authorize('create', Document::class, only: ['create', 'store'])]
#[Authorize('update', 'document', only: ['edit', 'update'])]
#[Authorize('delete', 'document', only: ['destroy'])]
#[Authorize('restore', 'document', only: ['restore'])]
class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        $divisis = Divisi::all();
        $doctypes = DokumenType::all();

        $query = Document::with(['divisi', 'role', 'dokumentype', 'versions'])->withTrashed();

        if ($request->filled('divisi_id')) {
            $query->where('divisi_id', $request->divisi_id);
        }

        if ($request->filled('doctype_id')) {
            $query->where('document_type', $request->doctype_id);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        $documents = $query->orderBy('name')->get();

        return view('document.index', [
            'title' => 'Documents',
            'active' => 'document',
            'divisis' => $divisis,
            'doctypes' => $doctypes,
            'documents' => $documents,
        ]);
    }

    public function create(): View
    {
        return view('document.create', [
            'title' => 'Documents',
            'active' => 'document',
            'divisis' => Divisi::all(),
            'documentypes' => DokumenType::all(),
            'roles' => $this->targetRoles(),
        ]);
    }

    public function store(StoreDocumentRequest $request): RedirectResponse
    {
        $file = $request->file('file');
        $fileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        // The extension comes from the detected MIME type, never from the client-supplied name.
        $path = Str::slug($fileName).'-v1-'.time().'.'.$file->extension();
        $fileSize = $this->formatBytes($file->getSize());
        $changeNote = $request->validated('change_note') ?: 'Versi Awal Dokumen';
        $userName = $request->user()->nama_lengkap;

        $file->storeAs('dokumen', $path, 'public');

        DB::transaction(function () use ($request, $file, $fileName, $path, $fileSize, $changeNote, $userName) {
            foreach ($request->validated('role_id') as $roleId) {
                $document = Document::create([
                    ...$request->safe()->only(['document_type', 'divisi_id']),
                    'role_id' => $roleId ?: null,
                    'name' => strtoupper($fileName),
                    'path' => $path,
                    'version' => 1,
                ]);

                $document->versions()->create([
                    'version_number' => 1,
                    'file_name' => $fileName.'.'.$file->extension(),
                    'path' => $path,
                    'file_size' => $fileSize,
                    'change_note' => $changeNote,
                    'created_by' => $userName,
                ]);
            }
        });

        return to_route('document.index')->with('success', 'Berhasil menambahkan dokumen baru (Versi 1)');
    }

    /**
     * Version history of a document, loaded by the history modal on the index page.
     */
    public function history(Document $document): JsonResponse
    {
        $document->load(['versions', 'divisi', 'role']);

        return response()->json([
            'document' => [
                'id' => $document->id,
                'name' => $document->name,
                'version' => $document->version ?? 1,
                'divisi' => $document->divisi->name ?? '-',
                'jabatan' => $document->role->name ?? 'SEMUA JABATAN',
                'path' => $document->path,
            ],
            'versions' => $document->versions->map(fn (DocumentVersion $version) => [
                'id' => $version->id,
                'version_number' => $version->version_number,
                'file_name' => $version->file_name,
                'file_url' => asset('storage/dokumen/'.$version->path),
                'file_size' => $version->file_size ?: '-',
                'change_note' => $version->change_note ?: 'Tidak ada catatan revisi',
                'created_by' => $version->created_by ?: 'Admin',
                'created_at' => $version->created_at ? $version->created_at->format('d M Y, H:i') : '-',
            ]),
        ]);
    }

    public function edit(Document $document): View
    {
        $document->load(['versions', 'divisi', 'role', 'dokumentype']);

        return view('document.edit', [
            'title' => 'Documents',
            'active' => 'document',
            'divisis' => Divisi::all(),
            'documentypes' => DokumenType::all(),
            'roles' => $this->targetRoles(),
            'document' => $document,
        ]);
    }

    /**
     * Update metadata, and when a new PDF is uploaded store it as the next version (old files are kept).
     */
    public function update(UpdateDocumentRequest $request, Document $document): RedirectResponse
    {
        $userName = $request->user()->nama_lengkap;
        $metadata = $request->safe()->only(['divisi_id', 'document_type']);
        $metadata['role_id'] = (int) $request->validated('role_id') ?: null;

        // Ensure baseline version 1 exists in history
        if (! $document->versions()->exists()) {
            $document->versions()->create([
                'version_number' => 1,
                'file_name' => $document->name.'.pdf',
                'path' => $document->path,
                'file_size' => null,
                'change_note' => 'Versi Awal Dokumen',
                'created_by' => $userName,
                'created_at' => $document->created_at ?? now(),
            ]);
        }

        if (! $request->hasFile('file')) {
            $document->update($metadata);

            return to_route('document.index')->with('success', 'Berhasil memperbarui informasi dokumen');
        }

        $file = $request->file('file');
        $nextVersion = ($document->versions()->max('version_number') ?: ($document->version ?: 1)) + 1;
        $fileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $newPath = Str::slug($fileName).'-v'.$nextVersion.'-'.time().'.'.$file->extension();

        $file->storeAs('dokumen', $newPath, 'public');

        DB::transaction(function () use ($request, $document, $file, $fileName, $newPath, $nextVersion, $metadata, $userName) {
            $document->versions()->create([
                'version_number' => $nextVersion,
                'file_name' => $fileName.'.'.$file->extension(),
                'path' => $newPath,
                'file_size' => $this->formatBytes($file->getSize()),
                'change_note' => $request->validated('change_note') ?: ('Pembaruan Dokumen ke Versi '.$nextVersion),
                'created_by' => $userName,
            ]);

            $document->update([
                ...$metadata,
                'name' => strtoupper($fileName),
                'path' => $newPath,
                'version' => $nextVersion,
            ]);
        });

        return to_route('document.index')->with('success', 'Berhasil memperbarui dokumen ke Versi '.$nextVersion.' (file versi lama tersimpan di riwayat)');
    }

    /**
     * Deactivate (soft delete) a document.
     */
    public function destroy(Document $document): RedirectResponse
    {
        $document->delete();

        return to_route('document.index')->with('success', 'Berhasil menonaktifkan dokumen');
    }

    /**
     * Reactivate a soft-deleted document.
     */
    public function restore(Document $document): RedirectResponse
    {
        $document->restore();

        return to_route('document.index')->with('success', 'Berhasil mengaktifkan kembali dokumen');
    }

    /**
     * Helper to format bytes to human readable format.
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision).' '.$units[$pow];
    }

    /**
     * Jabatan sasaran dokumen (tanpa ADMIN yang sudah bisa melihat semua dokumen).
     */
    private function targetRoles()
    {
        return Role::where('name', '!=', User::ROLE_ADMIN)->orderByDesc('level')->orderBy('name')->get();
    }
}
