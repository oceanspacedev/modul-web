<?php

namespace App\Support\Meetings;

use RuntimeException;

/** Kegagalan memanggil API platform meeting, dengan pesan yang layak ditampilkan ke pengguna. */
class MeetingApiException extends RuntimeException {}
