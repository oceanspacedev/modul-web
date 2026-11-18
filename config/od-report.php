<?php

return [
    /*
    | Nomor WhatsApp tim OD yang ditampilkan ke pemohon setelah mengajukan dokumen (format 62…).
    */
    'admin_whatsapp' => env('OD_ADMIN_WHATSAPP'),

    /*
    | Cloud tempat dokumen yang sudah Release bisa diunduh pemohon. Kosongkan untuk menyembunyikan kotak cloud.
    */
    'cloud' => [
        'url' => env('OD_CLOUD_URL'),
        'user' => env('OD_CLOUD_USER'),
        'password' => env('OD_CLOUD_PASSWORD'),
    ],

    /*
    | Sinkron Daily Report ke spreadsheet Weekly Report lewat Apps Script (apps-script.gs aplikasi OD Report lama).
    | url = alamat Web App berakhiran /exec; spreadsheet = link atau ID spreadsheet Weekly Report.
    | Sinkron mati bila salah satunya kosong.
    */
    'sheets' => [
        'url' => env('OD_SHEETS_SYNC_URL'),
        'spreadsheet' => env('OD_SHEETS_SPREADSHEET'),
    ],
];
