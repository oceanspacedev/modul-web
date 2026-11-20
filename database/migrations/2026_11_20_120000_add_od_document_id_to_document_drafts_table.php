<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Draf Dokumen Builder bisa berasal dari satu item Permintaan Dokumen (od_documents yang punya document_request_id),
 * supaya data pengajuan terisi otomatis dan item yang sama tidak dibuatkan draf dua kali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_drafts', function (Blueprint $table) {
            $table->foreignId('od_document_id')->nullable()->after('user_id')->constrained('od_documents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('document_drafts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('od_document_id');
        });
    }
};
