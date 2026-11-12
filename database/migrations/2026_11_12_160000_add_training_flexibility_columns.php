<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pelatihan yang lebih bebas diatur pemateri:
 * - tiap soal bisa dipakai di pretest saja, posttest saja, atau keduanya (used_in). Essay yang sudah ada tetap hanya
 *   di posttest seperti sebelumnya;
 * - pretest boleh punya mode kuis sendiri (pretest_quiz_mode; null = ikut quiz_mode);
 * - form evaluasi pada pelatihan tanpa posttest boleh punya jam buka dan tutup sendiri (null = ikut status pelatihan);
 * - presensi boleh dimatikan sama sekali (has_attendance).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_questions', function (Blueprint $table) {
            $table->string('used_in', 10)->default('both')->after('type');
        });

        DB::table('training_questions')->where('type', 'essay')->update(['used_in' => 'posttest']);

        Schema::table('trainings', function (Blueprint $table) {
            $table->string('pretest_quiz_mode', 20)->nullable()->after('quiz_mode');
            $table->dateTime('evaluation_opens_at')->nullable()->after('has_evaluation');
            $table->dateTime('evaluation_closes_at')->nullable()->after('evaluation_opens_at');
            $table->boolean('has_attendance')->default(true)->after('is_attendance_active');
        });
    }

    public function down(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->dropColumn(['pretest_quiz_mode', 'evaluation_opens_at', 'evaluation_closes_at', 'has_attendance']);
        });

        Schema::table('training_questions', function (Blueprint $table) {
            $table->dropColumn('used_in');
        });
    }
};
