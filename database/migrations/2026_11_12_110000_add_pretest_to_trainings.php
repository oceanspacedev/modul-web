<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pelatihan bisa punya pretest dan posttest seperti video, dokumen, dan modul belajar. Kuis yang sudah ada menjadi
 * posttest (is_quiz_active, quiz_max_attempts, show_quiz_answers tetap miliknya); pretest punya saklar dan aturan
 * sendiri. Hasil kuis diberi fase; data lama semuanya posttest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->boolean('has_pretest')->default(false)->after('is_quiz_active');
            $table->boolean('has_posttest')->default(true)->after('has_pretest');
            $table->boolean('is_pretest_active')->default(false)->after('has_posttest');
            $table->unsignedTinyInteger('pretest_max_attempts')->nullable()->default(1)->after('quiz_max_attempts');
            $table->boolean('pretest_shows_answers')->default(false)->after('pretest_max_attempts');
        });

        Schema::table('training_quiz_results', function (Blueprint $table) {
            $table->string('phase', 10)->default('posttest')->after('training_participant_id');
            $table->index(['training_participant_id', 'phase'], 'training_quiz_results_participant_phase_index');
        });
    }

    public function down(): void
    {
        Schema::table('training_quiz_results', function (Blueprint $table) {
            $table->dropIndex('training_quiz_results_participant_phase_index');
            $table->dropColumn('phase');
        });

        Schema::table('trainings', function (Blueprint $table) {
            $table->dropColumn(['has_pretest', 'has_posttest', 'is_pretest_active', 'pretest_max_attempts', 'pretest_shows_answers']);
        });
    }
};
