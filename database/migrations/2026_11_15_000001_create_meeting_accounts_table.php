<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Akun penyelenggara untuk integrasi platform meeting (Zoom, Google Meet, Teams, ...). Isi kredensial
        // berbeda tiap platform, jadi disimpan sebagai satu JSON terenkripsi.
        Schema::create('meeting_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('name', 100);
            $table->text('credentials');
            $table->timestamps();
            $table->index(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_accounts');
    }
};
