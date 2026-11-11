<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kuis pelatihan memakai batas percobaan seperti pretest/posttest materi dan modul: quiz_max_attempts
 * (null = tanpa batas) menggantikan saklar allow_quiz_retake (ya = tanpa batas, tidak = 1 kali).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->unsignedTinyInteger('quiz_max_attempts')->nullable()->default(1)->after('show_quiz_answers');
        });

        DB::table('trainings')->where('allow_quiz_retake', true)->update(['quiz_max_attempts' => null]);

        Schema::table('trainings', function (Blueprint $table) {
            $table->dropColumn('allow_quiz_retake');
        });
    }

    public function down(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->boolean('allow_quiz_retake')->default(false)->after('show_quiz_answers');
        });

        DB::table('trainings')->where(fn ($query) => $query->whereNull('quiz_max_attempts')->orWhere('quiz_max_attempts', '>', 1))
            ->update(['allow_quiz_retake' => true]);

        Schema::table('trainings', function (Blueprint $table) {
            $table->dropColumn('quiz_max_attempts');
        });
    }
};
