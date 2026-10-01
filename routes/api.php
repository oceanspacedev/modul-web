<?php

use App\Http\Controllers\API\AbsentController;
use App\Http\Controllers\API\DocumentController;
use App\Http\Controllers\API\QuizController;
use App\Http\Controllers\API\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('login', [UserController::class, 'login']);


Route::middleware('auth:sanctum')->group(function () {
    ##USER
    Route::get('user',[UserController::class,'fetch']);
    Route::post('logout', [UserController::class, 'logout']);


    ##DOCUMENT
    Route::get('doc',[DocumentController::class,'index']);

    ##QUIZ
    Route::post('quiz/answer/{quizQuestion}',[QuizController::class,'answer']);
    Route::get('quiz/question/{quiz}',[QuizController::class,'getquestion']);
    Route::get('quiz/isquizable/{quiz}',[QuizController::class,'isquizable']);
    Route::get('quiz/calculate/{quiz}', [QuizController::class, 'calculate']);
    Route::get('quiz/history/', [QuizController::class, 'history']);
    Route::get('quiz/getscore', [QuizController::class, 'getscore']);

    ##ABSENT
    Route::get('check', [AbsentController::class, 'checkAbsent']);
    Route::post('check',[AbsentController::class, 'create']);
});

