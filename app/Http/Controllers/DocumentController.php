<?php

namespace App\Http\Controllers;

use App\Models\Divisi;
use App\Models\Document;
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

        if ($request->divisi_id && $request->doctype_id) {
            $documents = Document::where('divisi_id', $request->divisi_id)
             ->where('document_type', $request->doctype_id)
             ->orderBy('name')
             ->withTrashed()
            ->get();

        } else {
            $documents = Document::with(['divisi', 'subdivisi', 'joblevel', 'dokumentype'])->withTrashed()->filter()->orderBy('name')->get();
        }

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
                'file' => ['required', 'mimes:pdf', 'max:2048']
            ]);
            $data = $request->all();
            unset($data['_token']);
            $fileName = pathinfo($request->file('file')->getClientOriginalName(), PATHINFO_FILENAME);
            $slug = Str::slug($fileName);
            $data['path'] = $slug . '.' . $request->file('file')->getClientOriginalExtension();
            unset($data['file']);
            // if (!Str::contains($slug, 'budaya-perusahaan') && Storage::exists('public/dokumen/' . $data['path'])) {
            //     return redirect('document')->with(['error' => 'Dokumen ' . $data['path'] . ' sudah ada']);
            // }
            $data['name'] = strtoupper($fileName);
            foreach ($request->get('job_level_id') as $job_level) {
                $data['job_level_id'] = $job_level;
                Document::create($data);
            }
            $request->file('file')->move(storage_path('app/public/dokumen'), $data['path']);
            return redirect('document')->with(['success' => 'Berhasil menambahkan dokumen']);
        } catch (Exception $e) {
            return redirect('document')->with(['error' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Document $document)
    {
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
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Document $document, Request $request)
    {
        try {
            if ($request->file) {
                Storage::move('public/dokumen/' . $document->path, 'public/deleted-' . time() . '-' . $document->path);
                $fileName = pathinfo($request->file('file')->getClientOriginalName(), PATHINFO_FILENAME);
                $slug = Str::slug($fileName);
                $path = $slug . '.' . $request->file('file')->getClientOriginalExtension();
                $request->file('file')->move(storage_path('app/public/dokumen'), $path);

                $document->update([
                    'name' => strtoupper($fileName),
                    'divisi_id' => $request->divisi_id,
                    'sub_divisi_id' => $request->sub_divisi_id,
                    'job_level_id' => $request->job_level_id,
                    'document_type' => $request->document_type,
                    'path' => $path,
                ]);
            } else {
                $document->update([
                    'name' =>  $document->name,
                    'divisi_id' => $request->divisi_id,
                    'sub_divisi_id' => $request->sub_divisi_id,
                    'job_level_id' => $request->job_level_id,
                    'document_type' => $request->document_type,
                    'path' => $document->path,
                ]);
            }
            return redirect('document')->with(['success' => 'Berhasil merubah dokumen']);
        } catch (Exception $e) {
            return redirect('document')->with(['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $document = Document::find($id);
            $document->delete();
            return redirect('document')->with(['success' => 'Berhasil menonaktifkan dokumen']);
        } catch (Exception $e) {
            return redirect('document')->with(['error' => $e->getMessage()]);
        }
    }

    public function restore($id)
    {
        try {
            $document = Document::withTrashed()->find($id);
            if (!Storage::exists('public/dokumen/' . $document->path)) {
                throw 'Document tidak di temukan';
            }
            $document->path = preg_replace('/^deleted-/', '', $document->path);
            Storage::move('public/dokumen/deleted-' . $document->path, 'public/dokumen/' . $document->path);
            $document->save();
            $document->restore();
            return redirect('document')->with(['success' => 'Berhasil mengaktifkan dokumen']);
        } catch (Exception $e) {
            return redirect('document')->with(['error' => $e->getMessage()]);
        }
    }
}
