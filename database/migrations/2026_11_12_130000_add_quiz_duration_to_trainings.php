<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pretest dan posttest pelatihan bisa diberi batas waktu: durasi (menit) dipilih di Aturan Kuis, dan saat pemateri
 * membuka fase itu sistem mencatat jam tutupnya (dibuka 09.18 dengan durasi 10 menit berarti tutup otomatis 09.28).
 * Durasi null = tanpa batas waktu, ditutup manual seperti sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->unsignedSmallInteger('pretest_duration_minutes')->nullable()->after('pretest_shows_answers');
            $table->dateTime('pretest_closes_at')->nullable()->after('pretest_duration_minutes');
            $table->unsignedSmallInteger('quiz_duration_minutes')->nullable()->after('pretest_closes_at');
            $table->dateTime('quiz_closes_at')->nullable()->after('quiz_duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->dropColumn(['pretest_duration_minutes', 'pretest_closes_at', 'quiz_duration_minutes', 'quiz_closes_at']);
        });
    }
};
