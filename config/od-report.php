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
];
