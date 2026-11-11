<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pemateri bisa mengizinkan peserta mengulang kuis pelatihan (tanpa batas). Hasil yang disimpan tetap satu baris per
 * peserta: percobaan baru hanya menggantikannya bila nilainya lebih tinggi; attempt_count mencatat jumlah percobaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->boolean('allow_quiz_retake')->default(false)->after('show_quiz_answers');
        });

        Schema::table('training_quiz_results', function (Blueprint $table) {
            $table->unsignedSmallInteger('attempt_count')->default(1)->after('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->dropColumn('allow_quiz_retake');
        });

        Schema::table('training_quiz_results', function (Blueprint $table) {
            $table->dropColumn('attempt_count');
        });
    }
};
