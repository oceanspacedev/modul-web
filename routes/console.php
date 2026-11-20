<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('training:send-reminders')->everyFifteenMinutes();
Schedule::command('dnd:sync-users')->dailyAt(config('services.dnd.sync_time'))->withoutOverlapping();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('notifications:prune')->dailyAt('02:00');
// Jaring pengaman sinkron Daily Report → Google Sheets (biasanya sudah terkirim lewat queue saat Simpan Laporan).
Schedule::command('od-report:sync-sheet')->everyThirtyMinutes()->withoutOverlapping();
