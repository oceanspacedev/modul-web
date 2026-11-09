<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mode tampilan pretest/posttest: formal (semua soal satu halaman) atau game ala Quizizz (satu soal per layar, timer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_modules', function (Blueprint $table) {
            $table->string('quiz_mode', 10)->default('formal')->after('shuffle_questions');
            $table->unsignedSmallInteger('question_seconds')->default(30)->after('quiz_mode');
        });
    }

    public function down(): void
    {
        Schema::table('learning_modules', function (Blueprint $table) {
            $table->dropColumn(['quiz_mode', 'question_seconds']);
        });
    }
};
