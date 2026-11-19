<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor WhatsApp pengelola Permintaan Dokumen: setiap pengajuan baru dikabarkan ke nomor-nomor ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_request_recipients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('whatsapp', 20)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_request_recipients');
    }
};
