<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * File dokumen yang dirilis tim OD. Wajib untuk dokumen dari Permintaan Dokumen sebelum dirilis, lalu dikirim ke
 * pemohon lewat WhatsApp sebagai link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('od_documents', function (Blueprint $table) {
            $table->string('file_path')->nullable()->after('catatan');
            $table->string('file_name', 200)->nullable()->after('file_path');
        });
    }

    public function down(): void
    {
        Schema::table('od_documents', function (Blueprint $table) {
            $table->dropColumn(['file_path', 'file_name']);
        });
    }
};
