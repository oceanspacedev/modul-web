<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengaturan integrasi dengan sistem lain yang diubah admin dari aplikasi (bukan di .env), berbentuk key → value.
     * Dipakai bersama semua integrasi lewat awalan key, mis. "helpdesk.token_hash"; nilai rahasia disimpan terenkripsi.
     */
    public function up(): void
    {
        Schema::create('integration_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_settings');
    }
};
