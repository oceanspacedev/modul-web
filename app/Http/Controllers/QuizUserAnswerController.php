<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuizUserAnswerRequest;
use App\Http\Requests\UpdateQuizUserAnswerRequest;
use App\Models\QuizUserAnswer;

class QuizUserAnswerController extends Controller
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
     * @param  \App\Http\Requests\StoreQuizUserAnswerRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreQuizUserAnswerRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\QuizUserAnswer  $quizUserAnswer
     * @return \Illuminate\Http\Response
     */
    public function show(QuizUserAnswer $quizUserAnswer)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\QuizUserAnswer  $quizUserAnswer
     * @return \Illuminate\Http\Response
     */
    public function edit(QuizUserAnswer $quizUserAnswer)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateQuizUserAnswerRequest  $request
     * @param  \App\Models\QuizUserAnswer  $quizUserAnswer
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateQuizUserAnswerRequest $request, QuizUserAnswer $quizUserAnswer)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\QuizUserAnswer  $quizUserAnswer
     * @return \Illuminate\Http\Response
     */
    public function destroy(QuizUserAnswer $quizUserAnswer)
    {
        //
    }
}
