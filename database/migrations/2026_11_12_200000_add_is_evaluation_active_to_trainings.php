<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Form evaluasi dibuka pemateri sendiri, seperti presensi, pretest, dan posttest: tertutup sampai pemateri membukanya
 * (sekarang atau terjadwal lewat evaluation_opens_at), lalu tertutup lagi pada evaluation_closes_at atau saat dikunci.
 * Tidak lagi terbuka otomatis mengikuti status pelatihan atau posttest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->boolean('is_evaluation_active')->default(false)->after('has_evaluation');
        });
    }

    public function down(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->dropColumn('is_evaluation_active');
        });
    }
};
