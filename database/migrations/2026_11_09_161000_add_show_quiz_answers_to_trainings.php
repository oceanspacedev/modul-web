<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Setelah kuis pelatihan selesai, peserta melihat rincian jawaban (benar/salah + kunci) atau hanya nilainya.
 * Mati juga berarti mode game tidak memberi tahu benar/salah per soal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->boolean('show_quiz_answers')->default(true)->after('quiz_mode');
        });
    }

    public function down(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->dropColumn('show_quiz_answers');
        });
    }
};
