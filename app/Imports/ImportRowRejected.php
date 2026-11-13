<?php

namespace App\Imports;

use Exception;

/**
 * Baris import ditolak dengan alasan yang aman ditampilkan ke pengimpor. Kegagalan lain (mis. error database) tidak
 * memakai kelas ini, supaya pesan teknisnya tidak ikut masuk notifikasi.
 */
class ImportRowRejected extends Exception {}
