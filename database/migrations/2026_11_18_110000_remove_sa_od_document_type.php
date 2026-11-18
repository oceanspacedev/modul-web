<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Jenis dokumen SA tidak dipakai tim OD. Dokumen SA yang sudah tercatat dipindah ke "Dokumen Lainnya" dan PIC
 * bawaannya dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('od_documents')->where('jenis', 'SA')->update(['jenis' => 'Dokumen Lainnya']);
        DB::table('od_pic_defaults')->where('jenis', 'SA')->delete();
    }

    public function down(): void
    {
        // Jenis asal dokumen yang dipindah tidak disimpan, jadi tidak bisa dikembalikan.
    }
};
