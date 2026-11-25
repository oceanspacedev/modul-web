<?php

use App\Http\Controllers\AiSettingController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DivisiController;
use App\Http\Controllers\DocumentBuilderController;
use App\Http\Controllers\DocumentBuilderStepController;
use App\Http\Controllers\DocumentCategoryController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentRequestController;
use App\Http\Controllers\DocumentRequestRecipientController;
use App\Http\Controllers\DokumenTypeController;
use App\Http\Controllers\HelpdeskSettingController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\LearningModuleController;
use App\Http\Controllers\LearningModulePortalController;
use App\Http\Controllers\LearningModuleQuestionController;
use App\Http\Controllers\MeetingAccountController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OdDailyController;
use App\Http\Controllers\OdDocumentController;
use App\Http\Controllers\OdExportController;
use App\Http\Controllers\OdReportController;
use App\Http\Controllers\OdRequestController;
use App\Http\Controllers\OdSettingController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ScoreRecapController;
use App\Http\Controllers\TrainingController;
use App\Http\Controllers\TrainingEvaluationQuestionController;
use App\Http\Controllers\TrainingMeetingChatController;
use App\Http\Controllers\TrainingMeetingController;
use App\Http\Controllers\TrainingMeetingMonitorController;
use App\Http\Controllers\TrainingPortalController;
use App\Http\Controllers\TrainingQuestionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserSyncController;
use App\Http\Controllers\VideoController;
use App\Http\Middleware\EnsureLearningModuleContext;
use App\Http\Middleware\LastUserActivity;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// # AUTHENTICATION
Route::get('/', [AuthController::class, 'showLogin'])->middleware('guest')->name('login');
Route::get('/login', fn () => to_route('login', request()->only('redirect')));

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/login/otp/request', [AuthController::class, 'requestOtp'])->middleware('throttle:otp-request')->name('login.otp.request');
Route::post('/login/otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:otp-verify')->name('login.otp.verify');

// # PUBLIC VIDEO GALLERY
Route::get('video', [VideoController::class, 'index'])->name('video.index');
Route::get('video/watch/{video}', [VideoController::class, 'show'])->name('video.show');

// # PERMINTAAN DOKUMEN (publik; pemohon yang login datanya terisi otomatis)
Route::controller(DocumentRequestController::class)->prefix('permintaan-dokumen')->name('document-request.')->group(function () {
    Route::get('/', 'create')->name('create');
    Route::post('/', 'store')->middleware('throttle:document-request')->name('store');
    Route::get('lacak', 'track')->middleware('throttle:document-request-track')->name('track');
    Route::get('contoh/{category?}', 'example')->whereNumber('category')->name('example');
    Route::get('template/{category}', 'template')->whereNumber('category')->middleware('throttle:document-request-track')->name('template');
    Route::get('contoh/{category}/file', 'exampleFile')->whereNumber('category')->middleware('throttle:document-request-track')->name('example-file');
    // Link file rilis yang dikirim ke pemohon lewat WhatsApp; hanya link bertanda tangan dari aplikasi yang bisa dibuka.
    Route::get('file/{document}', 'file')->whereNumber('document')->middleware(['signed', 'throttle:document-request-track'])->name('file');
});

// # PUBLIC PARTICIPANT PORTAL (ACCESSIBLE VIA WHATSAPP TOKEN LINK)
Route::prefix('training/portal/{token}')
    ->name('training.portal')
    ->controller(TrainingPortalController::class)
    ->middleware('throttle:portal')
    ->group(function () {
        Route::get('/', 'showPortal')->name('');
        // Dipanggil berkala oleh setiap peserta (portal dan halaman kuis): tanpa session, cookie, CSRF, dan catatan
        // aktivitas user, supaya tiap panggilan hanya membaca database. Peserta dikenali dari token di alamatnya.
        Route::get('status', 'getStatus')->name('.status')->withoutMiddleware([
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            PreventRequestForgery::class,
            LastUserActivity::class,
        ]);
        Route::post('attendance', 'submitAttendance')->name('.attendance');
        Route::get('quiz', 'showQuiz')->name('.quiz');
        Route::post('quiz', 'submitQuiz')->name('.quiz.submit');
        Route::get('retake', 'retakeQuiz')->name('.retake');
        Route::post('retake', 'retakeQuiz')->name('.retake.post');
        Route::get('result', 'showResult')->name('.result');
        Route::get('evaluation', 'showEvaluation')->name('.evaluation');
        Route::post('evaluation', 'submitEvaluation')->name('.evaluation.submit');

        // Pretest: alamat sendiri, aksi yang sama dengan fase 'pretest'.
        Route::get('pretest', 'showQuiz')->name('.pretest')->defaults('phase', 'pretest');
        Route::post('pretest', 'submitQuiz')->name('.pretest.submit')->defaults('phase', 'pretest');
        Route::get('pretest/retake', 'retakeQuiz')->name('.pretest.retake')->defaults('phase', 'pretest');
        Route::get('pretest/result', 'showResult')->name('.pretest.result')->defaults('phase', 'pretest');
    });

Route::middleware('auth')->group(function () {
    // # COMMON (EVERY SIGNED-IN USER)
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::delete('impersonate', [ImpersonationController::class, 'destroy'])->name('impersonate.leave');
    Route::view('panduan', 'panduan.index', ['active' => 'panduan', 'title' => 'Panduan Penggunaan'])->name('panduan');
    Route::get('my-trainings', [TrainingPortalController::class, 'myTrainings'])->name('training.my-trainings');
    Route::get('permintaan-saya', [DocumentRequestController::class, 'mine'])->name('document-request.mine');

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

    // # REKAP NILAI GABUNGAN (bagian yang tampil mengikuti izin kelola, dicek di controller)
    Route::controller(ScoreRecapController::class)->prefix('rekap-nilai')->name('score-recap.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('export', 'export')->name('export');
    });

    // # OD REPORT (tim Organization Development; Staff melihat datanya sendiri, Leader seluruh tim)
    Route::prefix('od-report')->name('od-report.')->middleware('can:access-od-report')->group(function () {
        // Dashboard OD sudah digabung ke Dashboard; alamat lamanya diarahkan ke sana.
        Route::redirect('/', '/dashboard');
        Route::controller(OdReportController::class)->group(function () {
            Route::get('weekly', 'weekly')->name('weekly');
            Route::get('monthly', 'monthly')->name('monthly');
        });

        Route::post('ekspor', OdExportController::class)->name('export');

        Route::controller(OdDailyController::class)->prefix('daily')->name('daily.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::post('simpan', 'save')->name('save');
            Route::post('buka-kembali', 'reopen')->name('reopen');
            Route::get('{activity}/edit', 'edit')->whereNumber('activity')->name('edit');
            Route::put('{activity}', 'update')->whereNumber('activity')->name('update');
            Route::patch('{activity}/geser', 'move')->whereNumber('activity')->name('move');
            Route::delete('{activity}', 'destroy')->whereNumber('activity')->name('destroy');
        });

        Route::controller(OdDocumentController::class)->prefix('dokumen')->name('documents.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('{document}/edit', 'edit')->whereNumber('document')->name('edit');
            Route::put('{document}', 'update')->whereNumber('document')->name('update');
            Route::patch('{document}/status', 'updateStatus')->whereNumber('document')->name('status');
            Route::get('{document}/file', 'file')->whereNumber('document')->name('file');
            Route::delete('{document}', 'destroy')->whereNumber('document')->name('destroy');
        });

        Route::controller(OdRequestController::class)->prefix('permintaan')->name('requests.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('{documentRequest}', 'show')->whereNumber('documentRequest')->name('show');
            Route::put('{documentRequest}', 'update')->whereNumber('documentRequest')->name('update');
            Route::post('{documentRequest}/komentar', 'comment')->whereNumber('documentRequest')->name('comment');
            Route::get('{documentRequest}/lampiran/{attachment}', 'attachment')->whereNumber(['documentRequest', 'attachment'])->name('attachment');
            Route::delete('{documentRequest}', 'destroy')->whereNumber('documentRequest')->name('destroy');
        });

        Route::controller(OdSettingController::class)->prefix('pengaturan')->name('settings.')->middleware('can:manage-od-report')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('status', 'storeStatus')->name('statuses.store');
            Route::put('status/{status}', 'updateStatus')->whereNumber('status')->name('statuses.update');
            Route::delete('status/{status}', 'destroyStatus')->whereNumber('status')->name('statuses.destroy');
            Route::put('google-sheets', 'updateSheet')->name('sheet.update');
            Route::post('sinkron-sheet', 'syncSheet')->name('sheet-sync');
            Route::put('import-sheet/{type}', 'importSheet')->whereIn('type', ['weekly', 'approval'])->name('sheet-import');
        });
    });

    // # DOKUMEN BUILDER (tim OD menyusun SOP, IK, JD, SE, MEMO, ... lewat form bertahap dibantu AI)
    Route::controller(DocumentBuilderController::class)->prefix('dokumen-builder')->name('document-builder.')->middleware('can:access-od-report')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('buat', 'create')->name('create');
        Route::get('buat/{category}', 'form')->whereNumber('category')->name('form');
        Route::post('buat/{category}', 'store')->whereNumber('category')->name('store');
        Route::get('{draft}', 'show')->whereNumber('draft')->name('show');
        Route::get('{draft}/edit', 'edit')->whereNumber('draft')->name('edit');
        Route::put('{draft}', 'update')->whereNumber('draft')->name('update');
        Route::get('{draft}/susun-ulang', 'answers')->whereNumber('draft')->name('answers');
        Route::post('{draft}/susun-ulang', 'recompose')->whereNumber('draft')->name('recompose');
        Route::get('{draft}/proses', 'progress')->whereNumber('draft')->name('progress');
        Route::get('{draft}/ai-status', 'aiStatus')->whereNumber('draft')->name('ai-status');
        Route::get('{draft}/word', 'word')->whereNumber('draft')->name('word');
        Route::delete('{draft}', 'destroy')->whereNumber('draft')->name('destroy');
    });

    // # KATEGORI DOKUMEN (master jenis dokumen OD dan tahapannya; dikelola OD Leader)
    Route::controller(DocumentCategoryController::class)->prefix('kategori-dokumen')->name('document-category.')->middleware('can:manage-od-report')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('{category}/edit', 'edit')->whereNumber('category')->name('edit');
        Route::put('{category}', 'update')->whereNumber('category')->name('update');
        Route::delete('{category}', 'destroy')->whereNumber('category')->name('destroy');
        Route::get('{category}/langkah-builder', [DocumentBuilderStepController::class, 'edit'])->whereNumber('category')->name('builder-steps.edit');
        Route::put('{category}/langkah-builder', [DocumentBuilderStepController::class, 'update'])->whereNumber('category')->name('builder-steps.update');
        Route::delete('{category}/langkah-builder', [DocumentBuilderStepController::class, 'reset'])->whereNumber('category')->name('builder-steps.reset');
        Route::post('penerima', [DocumentRequestRecipientController::class, 'store'])->name('recipients.store');
        Route::delete('penerima/{recipient}', [DocumentRequestRecipientController::class, 'destroy'])->whereNumber('recipient')->name('recipients.destroy');
    });

    // # DASHBOARD
    Route::get('dashboard', [DashboardController::class, 'index'])->middleware('can:open-dashboard')->name('dashboard');

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
        Route::post('{user}/impersonate', [ImpersonationController::class, 'store'])->withTrashed()->name('impersonate');
    });
    Route::resource('user', UserController::class)->except(['show', 'edit']);

    // # AI SETTINGS (admin)
    Route::controller(AiSettingController::class)->prefix('pengaturan-ai')->name('ai-settings.')->middleware('can:manage-ai-settings')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::put('/', 'update')->name('update');
        Route::post('{provider}/models', 'models')->whereIn('provider', ['gemini', 'openai', 'anthropic'])->name('models');
        Route::post('{provider}/chat', 'chat')->whereIn('provider', ['gemini', 'openai', 'anthropic'])->middleware('throttle:20,1')->name('chat');
    });

    // # HELPDESK SETTINGS (admin): kode koneksi integrasi helpdesk
    Route::controller(HelpdeskSettingController::class)->prefix('pengaturan-helpdesk')->name('helpdesk-settings.')->middleware('can:manage-helpdesk-settings')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('kode', 'issue')->name('issue');
        Route::delete('/', 'disconnect')->name('disconnect');
    });

    // # ROLES & PERMISSIONS
    Route::middleware('can:manage-roles')->group(function () {
        Route::post('roles/assign', [RoleController::class, 'assignUserRole'])->name('roles.assign');
        Route::resource('roles', RoleController::class)->except('show')->parameters(['roles' => 'role']);
    });

    // # TRAINING MANAGEMENT (authorized by TrainingPolicy)
    Route::controller(MeetingAccountController::class)->prefix('training/meeting-accounts')->name('training.meeting-accounts.')->group(function () {
        Route::get('/', 'index')->name('index')->middleware('cache.headers:no_store;private');
        Route::post('/', 'store')->name('store');
        Route::patch('{meetingAccount}', 'update')->whereNumber('meetingAccount')->name('update');
        Route::delete('{meetingAccount}', 'destroy')->whereNumber('meetingAccount')->name('destroy');
    });

    // no_store: tombol Back tidak menampilkan salinan lama form, jadi user yang baru dibuat ikut terdaftar.
    Route::get('training/participants/template', [TrainingController::class, 'blankParticipantTemplate'])->name('training.participant-template');
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
                Route::post('toggle-attendance-use', 'toggleAttendanceUse')->name('toggle-attendance-use');
                Route::post('toggle-evaluation-access', 'toggleEvaluationAccess')->name('toggle-evaluation-access');
                Route::post('portal-steps', 'updatePortalSteps')->name('portal-steps');
                Route::post('toggle-proof', 'toggleAttendanceProof')->name('toggle-proof');
                Route::post('toggle-pretest', 'togglePretest')->name('toggle-pretest');
                Route::post('extend-quiz-time', 'extendQuizTime')->name('extend-quiz-time');
                Route::post('quiz-deadline', 'updateQuizDeadline')->name('quiz-deadline');
                Route::post('toggle-evaluation', 'toggleEvaluation')->name('toggle-evaluation');
                Route::post('quiz-rules', 'updateQuizRules')->name('quiz-rules');
                Route::get('setup', 'setup')->name('setup');
                Route::post('setup', 'saveSetup')->name('setup.save');
                Route::post('broadcast-wa', 'broadcastWa')->name('broadcast-wa');
                Route::post('participants/import', 'importParticipants')->name('participants.import');
                Route::get('participants/template', 'participantTemplate')->name('participants.template');
                Route::delete('participants/{participant}', 'removeParticipant')->name('participants.remove');
                Route::post('send-wa/{participant}', 'sendSingleWa')->name('send-single-wa');
                Route::post('send-wa-manual/{participant}', 'sendManualWa')->name('send-manual-wa');
                Route::post('reset-quiz/{participant}', 'resetParticipantQuiz')->name('reset-quiz');
                Route::post('reset-attendance/{participant}', 'resetParticipantAttendance')->name('reset-attendance');
                Route::post('reset-evaluation/{participant}', 'resetParticipantEvaluation')->name('reset-evaluation');
                Route::post('update-attendance/{participant}', 'updateParticipantAttendance')->name('update-attendance');
                // {result}: hasil pretest maupun posttest pelatihan ini (Training::results()).
                Route::get('answer-sheet/{result}', 'answerSheet')->name('answer-sheet');
                Route::post('grade-essay/{result}', 'gradeEssay')->name('grade-essay');
                Route::post('regrade', 'regradeQuiz')->name('regrade');
                Route::post('export', 'export')->name('export');
                Route::get('live-stats', 'liveStats')->name('live-stats');
                Route::post('attach-document', 'attachDocument')->name('attach-document');
                Route::post('detach-document/{document}', 'detachDocument')->name('detach-document');
                Route::post('scan-zoom-ai', 'scanZoomAi')->name('scan-zoom-ai');
                Route::get('scan-zoom-ai/{token}', 'zoomScanStatus')->name('scan-zoom-ai.status');
                Route::post('camera-status', 'saveCameraStatus')->name('camera-status.save');
                Route::post('camera-status/{participant}', 'updateCameraStatus')->name('camera-status.update');
            });

            Route::prefix('meeting')->name('meeting.')->group(function () {
                Route::get('/', [TrainingMeetingController::class, 'show'])->name('show')
                    ->middleware('cache.headers:no_store;private');
                Route::get('activity', [TrainingMeetingController::class, 'activity'])->name('activity')
                    ->middleware('cache.headers:no_store;private');
                Route::post('join', [TrainingMeetingController::class, 'join'])->name('join')
                    ->middleware('throttle:12,1');
                Route::post('attendance-sync', [TrainingMeetingController::class, 'syncAttendance'])->name('attendance-sync')
                    ->middleware('throttle:6,1');
                Route::get('chat', [TrainingMeetingController::class, 'chat'])->name('chat.index')
                    ->middleware('cache.headers:no_store;private');
                Route::post('chat', [TrainingMeetingChatController::class, 'store'])->name('chat');
                Route::get('camera', [TrainingMeetingController::class, 'camera'])->name('camera')
                    ->middleware('cache.headers:no_store;private');
                Route::controller(TrainingMeetingMonitorController::class)->prefix('monitors')->name('monitors.')->group(function () {
                    Route::post('/', 'start')->name('start');
                    Route::post('{monitor}/sync', 'sync')->whereUuid('monitor')->name('sync');
                    Route::post('{monitor}/end', 'end')->whereUuid('monitor')->name('end');
                });
            });

            // # TRAINING EVALUATION FORM QUESTIONS
            Route::controller(TrainingEvaluationQuestionController::class)->prefix('evaluation')->name('evaluation.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::post('defaults', 'addDefaults')->name('defaults');
                Route::post('copy', 'copyFrom')->name('copy');
                Route::put('{evaluationQuestion}', 'update')->name('update');
                Route::post('{evaluationQuestion}/move', 'move')->name('move');
                Route::delete('{evaluationQuestion}', 'destroy')->name('destroy');
            });

            // # TRAINING QUIZ QUESTIONS
            Route::controller(TrainingQuestionController::class)->prefix('questions')->name('questions.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('template', 'downloadTemplate')->name('template');
                Route::post('export', 'export')->name('export');
                Route::post('import-excel', 'importExcel')->name('import-excel');
                Route::post('import-text', 'importText')->name('import-text');
                Route::post('import-document', 'importDocument')->name('import-document');
                Route::post('generate-ai', 'generateAiQuestions')->name('generate-ai');
                Route::get('ai-status/{token}', 'aiStatus')->name('ai-status');
                Route::post('revise-ai', 'reviseAiQuestion')->name('revise-ai');
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
            Route::post('revise-ai', 'reviseAi')->name('revise-ai');
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
