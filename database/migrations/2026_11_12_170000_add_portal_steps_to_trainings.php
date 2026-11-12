<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Urutan langkah peserta di portal pelatihan (ikuti sesi, pretest, presensi, form evaluasi, posttest) bisa diatur
 * pemateri per pelatihan. Null = urutan bawaan: pretest, sesi, presensi, evaluasi, posttest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->json('portal_steps')->nullable()->after('has_attendance');
        });
    }

    public function down(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->dropColumn('portal_steps');
        });
    }
};
