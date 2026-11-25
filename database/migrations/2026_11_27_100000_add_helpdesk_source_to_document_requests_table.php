<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asal pengajuan Permintaan Dokumen: form web aplikasi ini atau helpdesk (integrasi eksternal). Pengajuan dari
 * helpdesk menyimpan nomor tiket helpdesk-nya dan perubahannya dikirim balik lewat webhook.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->string('source', 20)->default('web')->after('idempotency_key')->index();
            $table->string('helpdesk_ticket_number', 64)->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn(['source', 'helpdesk_ticket_number']);
        });
    }
};
