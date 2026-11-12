<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Presensi bisa dijadwalkan dan berbatas waktu seperti pretest, posttest, dan form evaluasi: pemateri mengisi jam
 * buka dan jam tutup, lalu presensi terbuka dan tertutup sendiri. Jam buka null = terbuka sejak tombol Buka ditekan;
 * jam tutup null = terbuka sampai dikunci manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->dateTime('attendance_opens_at')->nullable()->after('is_attendance_active');
            $table->dateTime('attendance_closes_at')->nullable()->after('attendance_opens_at');
        });
    }

    public function down(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->dropColumn(['attendance_opens_at', 'attendance_closes_at']);
        });
    }
};
