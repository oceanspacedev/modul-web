<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kapan pemateri menjawab pertanyaan pengaturan pelatihan (presensi, bukti foto, jawaban benar/salah, ulang kuis).
 * Pelatihan lama dianggap sudah diatur agar tidak diminta mengisi ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->timestamp('setup_completed_at')->nullable()->after('allow_quiz_retake');
        });

        DB::table('trainings')->update(['setup_completed_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->dropColumn('setup_completed_at');
        });
    }
};
