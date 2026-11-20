<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sidik isi baris terakhir yang sudah tertulis di spreadsheet Weekly Report (kosong = belum pernah terkirim).
     */
    public function up(): void
    {
        Schema::table('od_activities', function (Blueprint $table) {
            $table->string('sheet_hash', 40)->nullable()->after('saved_at');
        });
    }

    public function down(): void
    {
        Schema::table('od_activities', function (Blueprint $table) {
            $table->dropColumn('sheet_hash');
        });
    }
};
