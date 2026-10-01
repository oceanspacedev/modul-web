<?php

namespace App\Http\Controllers;

use App\Exports\QuizHistoryExport;
use App\Exports\QuizResultExport;
use App\Models\Document;
use App\Models\Quiz;
use App\Models\QuizHistory;
use App\Models\QuizQuestion;
use Exception;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

#ADD QuizAllHistoryExport
use App\Exports\QuizAllHistoryExport;

class QuizController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

        return view('quiz.index', [
            'title' => 'Quiz',
            'active' => 'quiz',
            'documents' => Document::withTrashed()
                ->whereHas('question')
                ->get(),
            'quizs' => Quiz::withTrashed()
                ->with(['document' => function ($q) {
                    $q->withTrashed();
                }, 'document.joblevel' => function ($q) {
                    $q->withTrashed();
                }])
                ->orderBy('start', 'DESC')
                ->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreQuizRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            Quiz::create([
                'document_id' => $request->document_id,
                'quiz_question_id' => QuizQuestion::where('document_id', $request->document_id)->first()->id,
                'start' => $request->start,
                'end' => $request->end,
            ]);
            return redirect('quiz')->with(['success' => 'Berhasil menambahkan quiz baru']);
        } catch (Exception $e) {
            return back()->with(['error' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Quiz  $quiz
     * @return \Illuminate\Http\Response
     */
    public function show(Quiz $quiz)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Quiz  $quiz
     * @return \Illuminate\Http\Response
     */
    public function edit(Quiz $quiz)
    {
        return view('quiz.edit', [
            'title' => 'Quiz',
            'active' => 'quiz',
            'quiz' => $quiz,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateQuizRequest  $request
     * @param  \App\Models\Quiz  $quiz
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Quiz $quiz)
    {
        try {
            $quiz->update([
                'start' => $request->start,
                'end' => $request->end,
            ]);
            return redirect('quiz')->with(['success' => 'Berhasil merubah quiz']);
        } catch (Exception $e) {
            return back()->with(['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Quiz  $quiz
     * @return \Illuminate\Http\Response
     */
    public function destroy(Quiz $quiz)
    {
        try {
            $quiz->forceDelete();
            return redirect('quiz')->with(['success' => 'Berhasil menghapus quiz']);
        } catch (Exception $e) {
            return back()->with(['error' => $e->getMessage()]);
        }
    }

    public function export(Request $request)
    {
        try {

            $quiz = Quiz::find($request->dataid);

            return Excel::download(new QuizResultExport($quiz->id, $quiz->document->divisi->id, $request->kkm, $request->denda), 'QUIZ RESULT ' . $quiz->document->name . '.xlsx');
        } catch (Exception $e) {
            return back()->with(['error' => $e->getMessage()]);
        }
    }

    public function exporthistory(Request $request, QuizHistory $quizHistory)
    {
        try {
            return Excel::download(new QuizHistoryExport($quizHistory->quiz_id, $quizHistory->user_id), 'QUIZ HISTORY ' . $quizHistory->quiz->document->name . ' ' . $quizHistory->user->full_name . '.xlsx');
        } catch (Exception $e) {
            return back()->with(['error' => $e->getMessage()]);
        }
    }

    public function exportAllHistory(Request $request, Quiz $quiz)
    {
        try {
            return Excel::download(new QuizAllHistoryExport($quiz->id), 'QUIZ HISTORY ' . $quiz->document->name . ' ' . '.xlsx');
        } catch (Exception $e) {
            return back()->with(['error' => $e->getMessage()]);
        }
    }
}
