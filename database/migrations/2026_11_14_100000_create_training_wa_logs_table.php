<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat undangan WhatsApp per peserta: setiap kiriman otomatis (WAGHub) yang berhasil maupun gagal, dan setiap kali
 * pemateri membuka kirim manual. training_participants.wa_status hanya menyimpan percobaan terakhir, jadi tanpa tabel
 * ini tidak terlihat berapa kali dan kapan undangan sebenarnya terkirim.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_wa_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_participant_id')->constrained('training_participants')->cascadeOnDelete();
            $table->string('channel', 20);
            $table->boolean('success');
            $table->string('phone', 30)->nullable();
            $table->string('detail')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['training_participant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_wa_logs');
    }
};
