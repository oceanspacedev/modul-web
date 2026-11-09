<?php

use App\Http\Controllers\AreaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DivisiController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DokumenTypeController;
use App\Http\Controllers\LearningModuleController;
use App\Http\Controllers\LearningModulePortalController;
use App\Http\Controllers\LearningModuleQuestionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TrainingController;
use App\Http\Controllers\TrainingPortalController;
use App\Http\Controllers\TrainingQuestionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserSyncController;
use App\Http\Controllers\VideoController;
use App\Http\Middleware\EnsureLearningModuleContext;
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

    Route::post('video/{video}/comments', [VideoController::class, 'storeComment'])->name('video.comments.store');
    Route::delete('video/comments/{comment}', [VideoController::class, 'destroyComment'])->name('video.comments.destroy');

    // # NOTIFIKASI (kotak masuk milik user sendiri)
    Route::controller(NotificationController::class)->prefix('notifikasi')->name('notifications.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('unread-count', 'unreadCount')->name('unread-count');
        Route::post('read-all', 'markAllRead')->name('read-all');
        Route::get('{notification}/download', 'download')->whereUuid('notification')->name('download');
        Route::delete('{notification}', 'destroy')->whereUuid('notification')->name('destroy');
    });

    // # DASHBOARD
    Route::get('dashboard', [DashboardController::class, 'index'])->middleware('can:view-dashboard')->name('dashboard');

    // # VIDEO MANAGEMENT (authorized by VideoPolicy)
    Route::get('video-manage/{video}/source', [VideoController::class, 'source'])->name('video.source');
    Route::resource('video-manage', VideoController::class)
        ->only(['create', 'store', 'edit', 'update', 'destroy'])
        ->parameters(['video-manage' => 'video'])
        ->names('video');

    // # DOCUMENTS (authorized by DocumentPolicy)
    Route::get('document/{document}/history', [DocumentController::class, 'history'])->name('document.history');
    Route::patch('document/{document}/deactivate', [DocumentController::class, 'deactivate'])->name('document.deactivate');
    Route::patch('document/{document}/activate', [DocumentController::class, 'activate'])->name('document.activate');
    Route::patch('document/{document}/restore', [DocumentController::class, 'restore'])->withTrashed()->name('document.restore');
    Route::resource('document', DocumentController::class)->except('show');

    // # MASTER DATA
    Route::middleware('can:manage-master-data')->group(function () {
        Route::resource('divisi', DivisiController::class)->except(['create', 'show'])->parameters(['divisi' => 'divisi']);
        Route::resource('area', AreaController::class)->except(['create', 'show']);
        Route::resource('position', PositionController::class)->except(['create', 'show']);
        Route::resource('dokumentype', DokumenTypeController::class)->except(['create', 'show'])->parameters(['dokumentype' => 'dokumentype']);
    });

    // # USERS (authorized by UserPolicy)
    Route::controller(UserController::class)->prefix('user')->name('user.')->group(function () {
        Route::post('export', 'export')->name('export');
        Route::get('template', 'template')->name('template');
        Route::post('import', 'import')->name('import');
        Route::get('approvers', 'approvers')->name('approvers');
        Route::post('bulk', 'bulk')->name('bulk');
        Route::post('import-json', [UserSyncController::class, 'importJson'])->name('import-json');
        Route::post('sync-dnd', [UserSyncController::class, 'preview'])->name('dnd.preview');
        Route::get('sync-dnd/{token}', [UserSyncController::class, 'show'])->whereUuid('token')->name('dnd.show');
        Route::post('sync-dnd/process', [UserSyncController::class, 'process'])->name('dnd.process');
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
    // no_store: tombol Back tidak menampilkan salinan lama form, jadi user yang baru dibuat ikut terdaftar.
    Route::resource('training', TrainingController::class)->whereNumber('training')
        ->middlewareFor(['create', 'edit'], 'cache.headers:no_store;private');

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
                Route::post('export', 'export')->name('export');
                Route::get('live-stats', 'liveStats')->name('live-stats');
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
                Route::post('export', 'export')->name('export');
                Route::post('import-excel', 'importExcel')->name('import-excel');
                Route::post('import-text', 'importText')->name('import-text');
                Route::post('generate-ai', 'generateAiQuestions')->name('generate-ai');
                Route::post('upload-image', 'uploadImage')->name('upload-image');
                Route::post('save-ai-batch', 'saveAiBatch')->name('save-ai-batch');
                Route::post('delete-all', 'deleteAll')->name('delete-all');
                Route::put('{question}', 'update')->name('update');
                Route::delete('{question}', 'destroy')->name('destroy');
            });
        });

    // # MODUL BELAJAR & KUIS VIDEO/DOKUMEN (authorized by LearningModulePolicy)
    // Controller yang sama dipasang di tiga alamat: Modul Belajar (modul-saya / learning-module), kuis video
    // (video/kuis/...) dan kuis dokumen (document/kuis/...). EnsureLearningModuleContext menjaga modul tetap di
    // alamat konteksnya sendiri.
    $quizParticipantRoutes = function () {
        Route::controller(LearningModulePortalController::class)->group(function () {
            Route::get('/', 'show')->name('show');
            Route::get('attempts/{attempt}', 'attempt')->name('attempt');
            Route::get('items/{item}', 'item')->name('items.show');
            Route::get('items/{item}/open', 'openDocument')->name('items.open');
            Route::post('items/{item}/complete', 'completeItem')->name('items.complete');
            Route::get('{phase}', 'quiz')->whereIn('phase', ['pretest', 'posttest'])->name('quiz');
            Route::post('{phase}', 'submit')->whereIn('phase', ['pretest', 'posttest'])->name('submit');
            Route::post('{phase}/answer', 'answer')->whereIn('phase', ['pretest', 'posttest'])->name('answer');
        });
    };

    $quizManagementRoutes = function () {
        Route::controller(LearningModuleQuestionController::class)->prefix('questions')->name('questions.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('template', 'downloadTemplate')->name('template');
            Route::post('export', 'export')->name('export');
            Route::post('import-excel', 'importExcel')->name('import-excel');
            Route::post('generate-ai', 'generateAi')->name('generate-ai');
            Route::get('ai-status/{token}', 'aiStatus')->whereUuid('token')->name('ai-status');
            Route::post('upload-image', 'uploadImage')->name('upload-image');
            Route::post('save-ai-batch', 'saveAiBatch')->name('save-ai-batch');
            Route::post('delete-all', 'deleteAll')->name('delete-all');
            Route::put('{question}', 'update')->name('update');
            Route::delete('{question}', 'destroy')->name('destroy');
        });

        Route::controller(LearningModuleController::class)->group(function () {
            Route::post('export', 'export')->name('export');
            Route::get('participants/{user}', 'participant')->withoutScopedBindings()->withTrashed()->name('participant');
        });
    };

    $standalone = EnsureLearningModuleContext::class.':standalone';

    Route::resource('learning-module', LearningModuleController::class)
        ->parameters(['learning-module' => 'module'])
        ->whereNumber('module')
        ->middleware($standalone);

    Route::prefix('learning-module/{module}')->whereNumber('module')->name('learning-module.')->middleware($standalone)->scopeBindings()
        ->group(function () use ($quizManagementRoutes) {
            Route::post('publish', [LearningModuleController::class, 'togglePublish'])->name('publish');
            $quizManagementRoutes();
        });

    Route::get('modul-saya', [LearningModulePortalController::class, 'index'])->middleware('can:view-learning-modules')->name('learning.index');
    Route::prefix('modul-saya/{module}')->whereNumber('module')->name('learning.')->middleware($standalone)->scopeBindings()
        ->group($quizParticipantRoutes);

    foreach (['video' => 'video-quiz.', 'document' => 'document-quiz.'] as $context => $name) {
        Route::prefix($context.'/kuis/{module}')->whereNumber('module')->name($name)
            ->middleware(EnsureLearningModuleContext::class.':'.$context)->scopeBindings()
            ->group(function () use ($quizParticipantRoutes, $quizManagementRoutes) {
                $quizParticipantRoutes();
                $quizManagementRoutes();
                Route::get('rekap', [LearningModuleController::class, 'show'])->name('results');
            });
    }
});
