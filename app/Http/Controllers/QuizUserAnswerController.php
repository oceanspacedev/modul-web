<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuizUserAnswerRequest;
use App\Http\Requests\UpdateQuizUserAnswerRequest;
use App\Models\QuizUserAnswer;
use Illuminate\Http\Response;

class QuizUserAnswerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(StoreQuizUserAnswerRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @return Response
     */
    public function show(QuizUserAnswer $quizUserAnswer)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit(QuizUserAnswer $quizUserAnswer)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @return Response
     */
    public function update(UpdateQuizUserAnswerRequest $request, QuizUserAnswer $quizUserAnswer)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return Response
     */
    public function destroy(QuizUserAnswer $quizUserAnswer)
    {
        //
    }
}
