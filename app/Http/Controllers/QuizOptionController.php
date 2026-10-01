<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuizOptionRequest;
use App\Http\Requests\UpdateQuizOptionRequest;
use App\Models\QuizOption;
use Illuminate\Http\Request;

class QuizOptionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
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
     * @param  \App\Http\Requests\StoreQuizOptionRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreQuizOptionRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\QuizOption  $quizOption
     * @return \Illuminate\Http\Response
     */
    public function show(QuizOption $quizOption)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\QuizOption  $quizOption
     * @return \Illuminate\Http\Response
     */
    public function edit(QuizOption $quizOption)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateQuizOptionRequest  $request
     * @param  \App\Models\QuizOption  $quizOption
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateQuizOptionRequest $request, QuizOption $quizOption)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\QuizOption  $quizOption
     * @return \Illuminate\Http\Response
     */
    public function destroy(QuizOption $quizOption)
    {
        //
    }

    public function get(Request $request)
    {
        $quizOptions = QuizOption::with(['question'])->where('quiz_question_id',$request->id)->get();
        return response()->json($quizOptions);
    }
}
