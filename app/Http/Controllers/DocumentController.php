<?php

namespace App\Http\Controllers;

use App\Models\Divisi;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\DokumenType;
use App\Models\JobLevel;
use App\Models\SubDivisi;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $divisis = Divisi::all();
        $doctypes = DokumenType::all();

        $query = Document::with(['divisi', 'subdivisi', 'joblevel', 'dokumentype', 'versions'])->withTrashed();

        if ($request->divisi_id && $request->doctype_id) {
            $query->where('divisi_id', $request->divisi_id)
                  ->where('document_type', $request->doctype_id);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
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

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('document.create', [
            'title' => 'Documents',
            'active' => 'document',
            'divisis' => Divisi::all(),
            'documentypes' => DokumenType::all(),
            'joblevels' => JobLevel::all()->except(1),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'file' => ['required', 'mimes:pdf', 'max:51200'], // max 50MB
                'change_note' => ['nullable', 'string', 'max:500'],
            ]);

            $fileName = pathinfo($request->file('file')->getClientOriginalName(), PATHINFO_FILENAME);
            $slug = Str::slug($fileName);
            $extension = $request->file('file')->getClientOriginalExtension();
            $path = $slug . '-v1-' . time() . '.' . $extension;
            $fileSize = $this->formatBytes($request->file('file')->getSize());
            $changeNote = $request->input('change_note') ?: 'Versi Awal Dokumen';
            $userName = auth()->user()?->full_name ?? 'Admin';

            $data = $request->all();
            unset($data['_token'], $data['file'], $data['change_note']);
            $data['path'] = $path;
            $data['name'] = strtoupper($fileName);
            $data['version'] = 1;

            foreach ($request->get('job_level_id') as $job_level) {
                $data['job_level_id'] = $job_level;
                $doc = Document::create($data);

                // Create version 1 record
                DocumentVersion::create([
                    'document_id' => $doc->id,
                    'version_number' => 1,
                    'file_name' => $fileName . '.' . $extension,
                    'path' => $path,
                    'file_size' => $fileSize,
                    'change_note' => $changeNote,
                    'created_by' => $userName,
                ]);
            }

            // Move file to storage
            $request->file('file')->move(storage_path('app/public/dokumen'), $path);

            return redirect('document')->with(['success' => 'Berhasil menambahkan dokumen baru (Versi 1)']);
        } catch (Exception $e) {
            return redirect('document')->with(['error' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified resource history.
     */
    public function history($id)
    {
        $document = Document::with(['versions', 'divisi', 'subdivisi', 'joblevel', 'dokumentype'])->withTrashed()->findOrFail($id);

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'document' => [
                    'id' => $document->id,
                    'name' => $document->name,
                    'version' => $document->version ?? 1,
                    'divisi' => $document->divisi->name ?? '-',
                    'joblevel' => $document->joblevel->name ?? '-',
                    'path' => $document->path,
                ],
                'versions' => $document->versions->map(function ($ver) {
                    return [
                        'id' => $ver->id,
                        'version_number' => $ver->version_number,
                        'file_name' => $ver->file_name,
                        'file_url' => asset('storage/dokumen/' . $ver->path),
                        'file_size' => $ver->file_size ?: '-',
                        'change_note' => $ver->change_note ?: 'Tidak ada catatan revisi',
                        'created_by' => $ver->created_by ?: 'Admin',
                        'created_at' => $ver->created_at ? $ver->created_at->format('d M Y, H:i') : '-',
                    ];
                }),
            ]);
        }

        return view('document.history', [
            'title' => 'Riwayat Versi: ' . $document->name,
            'active' => 'document',
            'document' => $document,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  Document  $document
     * @return \Illuminate\Http\Response
     */
    public function edit(Document $document)
    {
        $document->load(['versions', 'divisi', 'subdivisi', 'joblevel', 'dokumentype']);

        return view('document.edit', [
            'title' => 'Documents',
            'active' => 'document',
            'divisis' => Divisi::all(),
            'subdivisis' => SubDivisi::all(),
            'documentypes' => DokumenType::all(),
            'joblevels' => JobLevel::all()->except(1),
            'document' => $document,
        ]);
    }

    /**
     * Update the specified resource in storage with versioning.
     *
     * @param  \App\Models\Document  $document
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(Document $document, Request $request)
    {
        try {
            $userName = auth()->user()?->full_name ?? 'Admin';

            // Ensure baseline version 1 exists in history
            if ($document->versions()->count() === 0) {
                DocumentVersion::create([
                    'document_id' => $document->id,
                    'version_number' => 1,
                    'file_name' => $document->name . '.pdf',
                    'path' => $document->path,
                    'file_size' => null,
                    'change_note' => 'Versi Awal Dokumen',
                    'created_by' => $userName,
                    'created_at' => $document->created_at ?? now(),
                ]);
            }

            if ($request->hasFile('file')) {
                $request->validate([
                    'file' => ['required', 'mimes:pdf', 'max:51200'],
                    'change_note' => ['nullable', 'string', 'max:500'],
                ]);

                // Calculate next version
                $currentMax = $document->versions()->max('version_number') ?: ($document->version ?: 1);
                $nextVersion = $currentMax + 1;

                $fileName = pathinfo($request->file('file')->getClientOriginalName(), PATHINFO_FILENAME);
                $slug = Str::slug($fileName);
                $extension = $request->file('file')->getClientOriginalExtension();
                $newPath = $slug . '-v' . $nextVersion . '-' . time() . '.' . $extension;
                $fileSize = $this->formatBytes($request->file('file')->getSize());
                $changeNote = $request->input('change_note') ?: ('Pembaruan Dokumen ke Versi ' . $nextVersion);

                // DO NOT delete the old file! Move new file to storage
                $request->file('file')->move(storage_path('app/public/dokumen'), $newPath);

                // Create new version in historical table
                DocumentVersion::create([
                    'document_id' => $document->id,
                    'version_number' => $nextVersion,
                    'file_name' => $fileName . '.' . $extension,
                    'path' => $newPath,
                    'file_size' => $fileSize,
                    'change_note' => $changeNote,
                    'created_by' => $userName,
                ]);

                // Update document to point to this new version
                $document->update([
                    'name' => strtoupper($fileName),
                    'divisi_id' => $request->divisi_id,
                    'sub_divisi_id' => $request->sub_divisi_id,
                    'job_level_id' => $request->job_level_id,
                    'document_type' => $request->document_type,
                    'path' => $newPath,
                    'version' => $nextVersion,
                ]);

                return redirect('document')->with(['success' => 'Berhasil memperbarui dokumen ke Versi ' . $nextVersion . ' (file versi lama tersimpan di riwayat)']);
            } else {
                // Only updating metadata (category, division, etc.) without replacing file
                $document->update([
                    'divisi_id' => $request->divisi_id,
                    'sub_divisi_id' => $request->sub_divisi_id,
                    'job_level_id' => $request->job_level_id,
                    'document_type' => $request->document_type,
                ]);

                return redirect('document')->with(['success' => 'Berhasil memperbarui informasi dokumen']);
            }
        } catch (Exception $e) {
            return redirect('document')->with(['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage (soft delete).
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $document = Document::findOrFail($id);
            $document->delete();
            return redirect('document')->with(['success' => 'Berhasil menonaktifkan dokumen']);
        } catch (Exception $e) {
            return redirect('document')->with(['error' => $e->getMessage()]);
        }
    }

    /**
     * Restore soft-deleted document.
     */
    public function restore($id)
    {
        try {
            $document = Document::withTrashed()->findOrFail($id);
            $document->restore();
            return redirect('document')->with(['success' => 'Berhasil mengaktifkan kembali dokumen']);
        } catch (Exception $e) {
            return redirect('document')->with(['error' => $e->getMessage()]);
        }
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

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
