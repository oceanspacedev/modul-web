<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Quiz;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
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
        static $document;
        try {
            switch ($request->type) {
                case 'general':
                    if (Str::contains($request->search, 'budaya')) {
                        if (auth()->user()->job_level_id > 2) {
                            $document = Document::where('name', 'like', '%' . $request->search . '%')->whereNotIn('job_level_id', [1, 2])->first();
                        } else {
                            $document = Document::where('name', 'like', '%' . $request->search . '%')->where('job_level_id', 2)->first();
                        }
                    } else {
                        $document = Document::where('name', 'like', '%' . $request->search . '%')->where('document_type', 1)->first();
                    }
                    break;
                case 'job-desc':
                    $document = Document::where('divisi_id', auth()->user()->divisi_id)->where('document_type', 2)->orderBy('name')->get();
                    break;
                case 'service':
                    $document = Document::where('divisi_id', auth()->user()->divisi_id)->where('document_type', 4)->orderBy('name')->get();
                    break;
                case 'operational':
                    $document = Document::where('divisi_id', auth()->user()->divisi_id)->where('document_type', 3)->orderBy('name')->get();
                    break;
                case 'sales':
                    $document = Document::where('divisi_id', auth()->user()->divisi_id)->where('document_type', 5)->orderBy('name')->get();
                    break;
                case 'quiz':
                    $document = Document::where('divisi_id', auth()->user()->divisi_id)->where('document_type', 7)->orderBy('name')->get();
                    break;
                case 'budaya-sales':
                    if (Str::contains($request->search, 'budaya')) {
                            $document = Document::where('name', 'like', '%' . $request->search . '%')->where('document_type', 8)->first();
                    } else {
                        $document = Document::where('name', 'like', '%' . $request->search . '%')->where('document_type', 8)->first();
                    }
                    break;
                default:
                    $document = Document::where('document_type',6)->orderBy('name')->get();
                    break;
            }
            if (!$document) {
                return ResponseFormatter::error(null, 'Dokumen tidak di temukan');
            }

            if (is_countable($document)) {
                foreach ($document as $doc) {
                    $isquizeable = Quiz::where('document_id', $doc->id)->orderBy('start', 'DESC')->first();
                    if ($isquizeable) {
                        $isquiz = (now() > Carbon::parse($isquizeable->start / 1000) && now() < Carbon::parse($isquizeable->end / 1000));
                    }
                    $doc['is_quizable'] = $isquiz ?? false;
                    $doc['quiz_id'] = $isquizeable ? $isquizeable->id : null;
                }
            } else {
                $isquizeable = Quiz::where('document_id', $document->id)->first();
                if ($isquizeable) {
                    $isquiz = (now() > Carbon::parse($isquizeable->start / 1000) && now() < Carbon::parse($isquizeable->end / 1000));
                }
                $document['is_quizable'] = $isquiz ?? false;
                $document['quiz_id'] = $isquizeable ? $isquizeable->id : null;
            }


            return ResponseFormatter::success($document, 'Berhasil');
        } catch (Exception $e) {
            return ResponseFormatter::error(null, $e->getMessage());
        }
    }
}
