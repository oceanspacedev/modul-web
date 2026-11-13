<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satu hasil kuis per peserta dan fase, dijamin database. Kiriman ganda yang datang bersamaan dulu bisa membuat dua
 * baris; yang dipertahankan adalah baris terbaru, yaitu yang selama ini ditampilkan ke peserta dan pemateri.
 */
return new class extends Migration
{
    public function up(): void
    {
        $keep = DB::table('training_quiz_results')
            ->whereNotNull('training_participant_id')
            ->groupBy('training_participant_id', 'phase')
            ->selectRaw('max(id) as id')
            ->pluck('id');

        DB::table('training_quiz_results')
            ->whereNotNull('training_participant_id')
            ->whereNotIn('id', $keep)
            ->delete();

        // Indeks unik dibuat lebih dulu supaya foreign key training_participant_id selalu punya indeks.
        Schema::table('training_quiz_results', function (Blueprint $table) {
            $table->unique(['training_participant_id', 'phase'], 'training_quiz_results_participant_phase_unique');
        });

        Schema::table('training_quiz_results', function (Blueprint $table) {
            $table->dropIndex('training_quiz_results_participant_phase_index');
        });
    }

    public function down(): void
    {
        Schema::table('training_quiz_results', function (Blueprint $table) {
            $table->index(['training_participant_id', 'phase'], 'training_quiz_results_participant_phase_index');
        });

        Schema::table('training_quiz_results', function (Blueprint $table) {
            $table->dropUnique('training_quiz_results_participant_phase_unique');
        });
    }
};
