<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Batas memori setiap request web: 128MB, apa pun isi php.ini server. Proses yang butuh lebih (export/import Excel,
// AI) tidak boleh berjalan di request; masukkan ke antrean (app/Jobs), yang batas memorinya diatur per supervisor
// lewat opsi "memory" di config/horizon.php.
ini_set('memory_limit', '128M');

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
