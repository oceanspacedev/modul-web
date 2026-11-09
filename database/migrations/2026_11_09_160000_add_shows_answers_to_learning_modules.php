<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Setelah pretest/posttest selesai, peserta melihat rincian jawaban (benar/salah + kunci) atau hanya nilainya.
 * Mati juga berarti mode game tidak memberi tahu benar/salah per soal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_modules', function (Blueprint $table) {
            $table->boolean('pretest_shows_answers')->default(true)->after('question_seconds');
            $table->boolean('posttest_shows_answers')->default(true)->after('pretest_shows_answers');
        });
    }

    public function down(): void
    {
        Schema::table('learning_modules', function (Blueprint $table) {
            $table->dropColumn(['pretest_shows_answers', 'posttest_shows_answers']);
        });
    }
};
