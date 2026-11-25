<?php

namespace App\Support\Ai;

use Exception;

/**
 * Kesalahan AI yang tidak akan hilang dengan mencoba model lain (API key salah, materi tidak didukung provider,
 * permintaan ditolak). Pesannya ditampilkan apa adanya ke pengguna.
 */
class AiException extends Exception {}
