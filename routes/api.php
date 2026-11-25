<?php

use App\Http\Controllers\Api\HelpdeskController;
use App\Http\Middleware\AuthenticateHelpdesk;
use Illuminate\Support\Facades\Route;

// Integrasi helpdesk v1 (Permintaan Dokumen). base_url di helpdesk: {APP_URL}/api/helpdesk/v1.
Route::prefix('helpdesk/v1')->name('api.helpdesk.')
    // Throttle lebih dulu, supaya percobaan dengan token salah ikut dibatasi.
    ->middleware(['throttle:helpdesk-api', AuthenticateHelpdesk::class])
    ->controller(HelpdeskController::class)
    ->group(function () {
        Route::get('categories', 'categories')->name('categories');
        Route::get('forms/{categoryKey}', 'form')->name('forms.show');
        Route::post('tickets', 'store')->name('tickets.store');
        Route::get('tickets/{ref}', 'show')->name('tickets.show');
        Route::put('webhook', 'registerWebhook')->name('webhook.register');
        Route::post('webhook/test', 'testWebhook')->name('webhook.test');
    });
