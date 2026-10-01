<?php

use App\Http\Controllers\API\AbsentController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DivisiController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DokumenTypeController;
use App\Http\Controllers\JobLevelController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\QuizHistoryController;
use App\Http\Controllers\QuizOptionController;
use App\Http\Controllers\QuizQuestionController;
use App\Http\Controllers\SubDivisiController;
use App\Http\Controllers\UserController;
use App\Models\QuizHistory;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('login.index');
})->name('login');

Route::post('/login', [AuthController::class, 'login']);
Route::get('download/app', [UserController::class, 'download']);

Route::middleware(['auth','isAdmin'])->group(
    function () {
        ##LOGOUT
        Route::post('/logout', [AuthController::class, 'logout']);

        ##DASHBOARD
        Route::get('dashboard', [DashboardController::class, 'index']);

        ##QUIZ_QUESTION
        Route::get('question',[QuizQuestionController::class,'index']);
        Route::get('question/template', [QuizQuestionController::class, 'template']);
        Route::post('question/import', [QuizQuestionController::class, 'import']);
        Route::get('question/delete/{quizQuestion}', [QuizQuestionController::class, 'destroy']);
        Route::get('question/active/{id}', [QuizQuestionController::class, 'restore']);
        Route::get('question/{id}', [QuizQuestionController::class, 'show']);
        Route::get('question/{id}/deleteAll', [QuizQuestionController::class, 'deleteAll']);
        Route::get('question/{id}/activeAll', [QuizQuestionController::class, 'activeAll']);

        ##QUIZ_OPTION
        Route::get('option/get', [QuizOptionController::class, 'get']);

        ##QUIZ
        Route::get('quiz', [QuizController::class, 'index']);
        Route::post('quiz', [QuizController::class, 'store']);
        Route::get('quiz/history', [QuizHistoryController::class, 'index']);
        Route::get('quiz/{quiz}', [QuizController::class, 'edit']);
        Route::post('quiz/{quiz}', [QuizController::class, 'update']);
        Route::get('quiz/history/{quizHistory}', [QuizHistoryController::class, 'show']);
        Route::get('quiz/delete/{quiz}', [QuizController::class, 'destroy']);
        Route::post('quiz/result/export/{quiz}', [QuizController::class, 'export']);
        Route::get('quiz/history/export/{quizHistory}', [QuizController::class, 'exporthistory']);
        Route::get('quiz/history/delete/{quizHistory}', [QuizHistoryController::class, 'destroy']);
        Route::get('quiz/history/exportall/{quiz}', [QuizController::class, 'exportAllHistory']);


        ##DOCUMENT
        Route::get('document', [DocumentController::class, 'index']);
        Route::post('document', [DocumentController::class, 'store']);
        Route::get('document/create', [DocumentController::class, 'create']);
        Route::get('document/{document}', [DocumentController::class, 'edit']);
        Route::post('document/{document}', [DocumentController::class, 'update']);
        Route::get('document/delete/{id}', [DocumentController::class, 'destroy']);
        Route::get('document/active/{id}', [DocumentController::class, 'restore']);

        ##DIVISI
        Route::get('divisi', [DivisiController::class, 'index']);
        Route::post('divisi', [DivisiController::class, 'store']);
        Route::post('divisi/delete', [DivisiController::class, 'destroy']);
        Route::get('divisi/{id}', [DivisiController::class, 'edit']);
        Route::post('divisi/{id}', [DivisiController::class, 'update']);

        #SUBDIVISI
        Route::get('subdivisi', [SubDivisiController::class, 'index']);
        Route::post('subdivisi', [SubDivisiController::class, 'store']);
        Route::post('subdivisi/delete', [SubDivisiController::class, 'destroy']);
        Route::get('subdivisi/{id}', [SubDivisiController::class, 'edit']);
        Route::post('subdivisi/{id}', [SubDivisiController::class, 'update']);

        ##JOBLEVEL
        Route::get('joblevel', [JobLevelController::class, 'index']);
        Route::post('joblevel', [JobLevelController::class, 'store']);
        Route::post('joblevel/delete', [JobLevelController::class, 'destroy']);
        Route::get('joblevel/{id}', [JobLevelController::class, 'edit']);
        Route::post('joblevel/{id}', [JobLevelController::class, 'update']);

        ##DOKUMEN TYPE
        Route::get('dokumentype', [DokumenTypeController::class, 'index']);
        Route::post('dokumentype', [DokumenTypeController::class, 'store']);
        Route::post('dokumentype/delete', [DokumenTypeController::class, 'destroy']);
        Route::get('dokumentype/{id}', [DokumenTypeController::class, 'edit']);
        Route::post('dokumentype/{id}', [DokumenTypeController::class, 'update']);

        ##USER
        Route::get('user', [UserController::class, 'index']);
        Route::post('user', [UserController::class, 'store']);
        Route::post('user/import',[UserController::class,'import']);
        Route::get('user/export', [UserController::class, 'export']);
        Route::get('user/template', [UserController::class, 'template']);
        Route::get('user/create', [UserController::class, 'create']);
        Route::get('user/delete/{user}', [UserController::class, 'destroy']);
        Route::get('user/active/{id}', [UserController::class, 'active']);
        Route::get('user/{id}', [UserController::class, 'edit']);
        Route::post('user/{id}', [UserController::class, 'update']);

        ##HELPERS
        Route::get('subdivisi/get/{id}', [SubDivisiController::class, 'show']);

        ##ABSENT
        Route::get('absent', [AbsentController::class, 'index']);
        Route::post('absent/export', [AbsentController::class, 'exportAbsent']);
    }
);
