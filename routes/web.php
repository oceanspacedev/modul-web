<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DivisiController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DokumenTypeController;
use App\Http\Controllers\JobLevelController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SubDivisiController;
use App\Http\Controllers\TrainingController;
use App\Http\Controllers\TrainingPortalController;
use App\Http\Controllers\TrainingQuestionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VideoController;
use Illuminate\Support\Facades\Route;

// # AUTHENTICATION
Route::get('/', [AuthController::class, 'showLogin'])->name('login');
Route::get('/login', fn () => to_route('login', request()->only('redirect')));

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/login/otp/request', [AuthController::class, 'requestOtp'])->middleware('throttle:otp-request')->name('login.otp.request');
Route::post('/login/otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:otp-verify')->name('login.otp.verify');

// # PUBLIC VIDEO GALLERY
Route::get('video', [VideoController::class, 'index'])->name('video.index');
Route::get('video/watch/{video}', [VideoController::class, 'show'])->name('video.show');

// # PUBLIC PARTICIPANT PORTAL (ACCESSIBLE VIA WHATSAPP TOKEN LINK)
Route::prefix('training/portal/{token}')
    ->name('training.portal')
    ->controller(TrainingPortalController::class)
    ->middleware('throttle:portal')
    ->group(function () {
        Route::get('/', 'showPortal')->name('');
        Route::get('status', 'getStatus')->name('.status');
        Route::post('attendance', 'submitAttendance')->name('.attendance');
        Route::get('quiz', 'showQuiz')->name('.quiz');
        Route::post('quiz', 'submitQuiz')->name('.quiz.submit');
        Route::get('retake', 'retakeQuiz')->name('.retake');
        Route::post('retake', 'retakeQuiz')->name('.retake.post');
        Route::get('result', 'showResult')->name('.result');
    });

Route::middleware('auth')->group(function () {
    // # COMMON (EVERY SIGNED-IN USER)
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::view('panduan', 'panduan.index', ['active' => 'panduan'])->name('panduan');
    Route::get('my-trainings', [TrainingPortalController::class, 'myTrainings'])->name('training.my-trainings');
    Route::get('subdivisi/get/{divisi}', [SubDivisiController::class, 'byDivisi'])->name('subdivisi.by-divisi');

    Route::post('video/{video}/comments', [VideoController::class, 'storeComment'])->name('video.comments.store');
    Route::delete('video/comments/{comment}', [VideoController::class, 'destroyComment'])->name('video.comments.destroy');

    // # DASHBOARD
    Route::get('dashboard', [DashboardController::class, 'index'])->middleware('can:view-dashboard')->name('dashboard');

    // # VIDEO MANAGEMENT (authorized by VideoPolicy)
    Route::resource('video-manage', VideoController::class)
        ->only(['create', 'store', 'edit', 'update', 'destroy'])
        ->parameters(['video-manage' => 'video'])
        ->names('video');

    // # DOCUMENTS (authorized by DocumentPolicy)
    Route::get('document/{document}/history', [DocumentController::class, 'history'])->withTrashed()->name('document.history');
    Route::patch('document/{document}/restore', [DocumentController::class, 'restore'])->withTrashed()->name('document.restore');
    Route::resource('document', DocumentController::class)->except('show');

    // # MASTER DATA
    Route::middleware('can:manage-master-data')->group(function () {
        Route::resource('divisi', DivisiController::class)->except(['create', 'show'])->parameters(['divisi' => 'divisi']);
        Route::resource('subdivisi', SubDivisiController::class)->except(['create', 'show'])->parameters(['subdivisi' => 'subdivisi']);
        Route::resource('joblevel', JobLevelController::class)->except(['create', 'show'])->parameters(['joblevel' => 'joblevel']);
        Route::resource('dokumentype', DokumenTypeController::class)->except(['create', 'show'])->parameters(['dokumentype' => 'dokumentype']);
    });

    // # USERS (authorized by UserPolicy)
    Route::controller(UserController::class)->prefix('user')->name('user.')->group(function () {
        Route::get('export', 'export')->name('export');
        Route::get('template', 'template')->name('template');
        Route::post('import', 'import')->name('import');
        Route::get('{user}/edit', 'edit')->withTrashed()->name('edit');
        Route::patch('{user}/restore', 'restore')->withTrashed()->name('restore');
    });
    Route::resource('user', UserController::class)->except(['show', 'edit']);

    // # ROLES & PERMISSIONS
    Route::middleware('can:manage-roles')->group(function () {
        Route::post('roles/assign', [RoleController::class, 'assignUserRole'])->name('roles.assign');
        Route::resource('roles', RoleController::class)->except('show')->parameters(['roles' => 'role']);
    });

    // # TRAINING MANAGEMENT (authorized by TrainingPolicy)
    Route::resource('training', TrainingController::class)->whereNumber('training');

    Route::prefix('training/{training}')
        ->whereNumber('training')
        ->name('training.')
        ->scopeBindings()
        ->group(function () {
            Route::controller(TrainingController::class)->group(function () {
                Route::post('status', 'updateStatus')->name('status');
                Route::post('toggle-quiz', 'toggleQuiz')->name('toggle-quiz');
                Route::post('toggle-attendance', 'toggleAttendance')->name('toggle-attendance');
                Route::post('toggle-proof', 'toggleAttendanceProof')->name('toggle-proof');
                Route::post('toggle-mode', 'toggleQuizMode')->name('toggle-mode');
                Route::post('broadcast-wa', 'broadcastWa')->name('broadcast-wa');
                Route::post('send-wa/{participant}', 'sendSingleWa')->name('send-single-wa');
                Route::post('reset-quiz/{participant}', 'resetParticipantQuiz')->name('reset-quiz');
                Route::post('reset-attendance/{participant}', 'resetParticipantAttendance')->name('reset-attendance');
                Route::post('update-attendance/{participant}', 'updateParticipantAttendance')->name('update-attendance');
                Route::post('grade-essay/{quizResult}', 'gradeEssay')->name('grade-essay');
                Route::get('export', 'export')->name('export');
                Route::post('attach-document', 'attachDocument')->name('attach-document');
                Route::post('detach-document/{document}', 'detachDocument')->name('detach-document');
                Route::post('scan-zoom-ai', 'scanZoomAi')->name('scan-zoom-ai');
                Route::post('save-zoom-off-cam', 'saveZoomOffCam')->name('save-zoom-off-cam');
                Route::post('reset-zoom-off-cam/{participant}', 'resetZoomOffCam')->name('reset-zoom-off-cam');
                Route::post('update-offcam-count/{participant}', 'updateOffCamCount')->name('update-offcam-count');
            });

            // # TRAINING QUIZ QUESTIONS
            Route::controller(TrainingQuestionController::class)->prefix('questions')->name('questions.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('template', 'downloadTemplate')->name('template');
                Route::post('import-excel', 'importExcel')->name('import-excel');
                Route::post('import-text', 'importText')->name('import-text');
                Route::post('generate-ai', 'generateAiQuestions')->name('generate-ai');
                Route::post('save-ai-batch', 'saveAiBatch')->name('save-ai-batch');
                Route::post('delete-all', 'deleteAll')->name('delete-all');
                Route::put('{question}', 'update')->name('update');
                Route::delete('{question}', 'destroy')->name('destroy');
            });
        });
});
