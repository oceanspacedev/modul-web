<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pretest dan posttest pelatihan bisa dijadwalkan: pemateri mengisi jam buka dan jam tutup lebih dulu (mis. buka
 * 09.40, tutup 09.50), lalu fase itu terbuka dan tertutup sendiri. Jam buka null = terbuka sejak tombol Buka ditekan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->dateTime('pretest_opens_at')->nullable()->after('pretest_duration_minutes');
            $table->dateTime('quiz_opens_at')->nullable()->after('quiz_duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->dropColumn(['pretest_opens_at', 'quiz_opens_at']);
        });
    }
};
