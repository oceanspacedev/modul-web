<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuizOptionRequest;
use App\Http\Requests\UpdateQuizOptionRequest;
use App\Models\QuizOption;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class QuizOptionController extends Controller
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
    public function store(StoreQuizOptionRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @return Response
     */
    public function show(QuizOption $quizOption)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit(QuizOption $quizOption)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @return Response
     */
    public function update(UpdateQuizOptionRequest $request, QuizOption $quizOption)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return Response
     */
    public function destroy(QuizOption $quizOption)
    {
        //
    }

    public function get(Request $request)
    {
        $quizOptions = QuizOption::with(['question'])->where('quiz_question_id', $request->id)->get();

        return response()->json($quizOptions);
    }
}
