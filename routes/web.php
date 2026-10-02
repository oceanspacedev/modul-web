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
use App\Http\Controllers\TrainingController;
use App\Http\Controllers\TrainingPortalController;
use App\Http\Controllers\TrainingQuestionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VideoController;
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

## PUBLIC VIDEO ROUTES
Route::get('video', [VideoController::class, 'index'])->name('video.index');
Route::get('video/watch/{id}', [VideoController::class, 'show'])->name('video.show');

Route::middleware(['auth','isAdmin'])->group(
    function () {
        ##LOGOUT
        Route::post('/logout', [AuthController::class, 'logout']);

        ##DASHBOARD
        Route::get('dashboard', [DashboardController::class, 'index']);

        ##VIDEO MANAGEMENT
        Route::get('video-manage/create', [VideoController::class, 'create'])->name('video.create');
        Route::post('video-manage', [VideoController::class, 'store'])->name('video.store');
        Route::get('video-manage/{id}/edit', [VideoController::class, 'edit'])->name('video.edit');
        Route::post('video-manage/update/{id}', [VideoController::class, 'update'])->name('video.update');
        Route::post('video-manage/delete/{id}', [VideoController::class, 'destroy'])->name('video.destroy');

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
        Route::post('quiz/result/export/{quiz?}', [QuizController::class, 'export']);
        Route::get('quiz/history/export/{quizHistory}', [QuizController::class, 'exporthistory']);
        Route::get('quiz/history/delete/{quizHistory}', [QuizHistoryController::class, 'destroy']);
        Route::get('quiz/history/exportall/{quiz}', [QuizController::class, 'exportAllHistory']);


        ##DOCUMENT
        Route::get('document', [DocumentController::class, 'index']);
        Route::post('document', [DocumentController::class, 'store']);
        Route::get('document/create', [DocumentController::class, 'create']);
        Route::get('document/history/{id}', [DocumentController::class, 'history']);
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

        ##TRAINING MANAGEMENT
        Route::get('training', [TrainingController::class, 'index'])->name('training.index');
        Route::get('training/create', [TrainingController::class, 'create'])->name('training.create');
        Route::post('training', [TrainingController::class, 'store'])->name('training.store');
        Route::get('training/{id}', [TrainingController::class, 'show'])->name('training.show');
        Route::get('training/{id}/edit', [TrainingController::class, 'edit'])->name('training.edit');
        Route::post('training/{id}/update', [TrainingController::class, 'update'])->name('training.update');
        Route::post('training/{id}/status', [TrainingController::class, 'updateStatus'])->name('training.status');
        Route::post('training/{id}/toggle-quiz', [TrainingController::class, 'toggleQuiz'])->name('training.toggle-quiz');
        Route::post('training/{id}/toggle-mode', [TrainingController::class, 'toggleQuizMode'])->name('training.toggle-mode');
        Route::post('training/{id}/broadcast-wa', [TrainingController::class, 'broadcastWa'])->name('training.broadcast-wa');
        Route::post('training/{id}/send-wa/{participantId}', [TrainingController::class, 'sendSingleWa'])->name('training.send-single-wa');
        Route::post('training/{id}/reset-quiz/{participantId}', [TrainingController::class, 'resetParticipantQuiz'])->name('training.reset-quiz');
        Route::post('training/{id}/grade-essay/{quizResultId}', [TrainingController::class, 'gradeEssay'])->name('training.grade-essay');
        Route::get('training/{id}/export', [TrainingController::class, 'export'])->name('training.export');
        Route::get('training/delete/{id}', [TrainingController::class, 'destroy'])->name('training.destroy');

        ##TRAINING QUIZ QUESTIONS
        Route::get('training/{trainingId}/questions', [TrainingQuestionController::class, 'index'])->name('training.questions.index');
        Route::get('training/{trainingId}/questions/template', [TrainingQuestionController::class, 'downloadTemplate'])->name('training.questions.template');
        Route::post('training/{trainingId}/questions/import-excel', [TrainingQuestionController::class, 'importExcel'])->name('training.questions.import-excel');
        Route::post('training/{trainingId}/questions/import-text', [TrainingQuestionController::class, 'importText'])->name('training.questions.import-text');
        Route::post('training/{trainingId}/questions/delete-all', [TrainingQuestionController::class, 'deleteAll'])->name('training.questions.delete-all');
        Route::post('training/{trainingId}/questions', [TrainingQuestionController::class, 'store'])->name('training.questions.store');
        Route::post('training/{trainingId}/questions/{questionId}/update', [TrainingQuestionController::class, 'update'])->name('training.questions.update');
        Route::get('training/{trainingId}/questions/{questionId}/delete', [TrainingQuestionController::class, 'destroy'])->name('training.questions.destroy');
    }
);

## LOGGED-IN USER TRAINING PORTAL
Route::middleware(['auth'])->group(function () {
    Route::get('my-trainings', [TrainingPortalController::class, 'myTrainings'])->name('training.my-trainings');
});

## PUBLIC PARTICIPANT PORTAL (ACCESSIBLE VIA WHATSAPP TOKEN LINK)
Route::get('training/portal/{token}', [TrainingPortalController::class, 'showPortal'])->name('training.portal');
Route::post('training/portal/{token}/attendance', [TrainingPortalController::class, 'submitAttendance'])->name('training.portal.attendance');
Route::get('training/portal/{token}/quiz', [TrainingPortalController::class, 'showQuiz'])->name('training.portal.quiz');
Route::post('training/portal/{token}/quiz', [TrainingPortalController::class, 'submitQuiz'])->name('training.portal.quiz.submit');
Route::get('training/portal/{token}/retake', [TrainingPortalController::class, 'retakeQuiz'])->name('training.portal.retake');
Route::post('training/portal/{token}/retake', [TrainingPortalController::class, 'retakeQuiz'])->name('training.portal.retake.post');
Route::get('training/portal/{token}/result', [TrainingPortalController::class, 'showResult'])->name('training.portal.result');

