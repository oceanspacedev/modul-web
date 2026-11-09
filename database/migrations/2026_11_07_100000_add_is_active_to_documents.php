<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pisahkan "nonaktif" dari "hapus" pada dokumen: nonaktif kini memakai kolom is_active, sedangkan deleted_at
 * (soft delete) khusus untuk dokumen yang dihapus. Dokumen yang dulu dinonaktifkan lewat deleted_at dipindah ke is_active.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('path');
        });

        DB::table('documents')->whereNotNull('deleted_at')->update(['is_active' => false, 'deleted_at' => null]);
    }

    public function down(): void
    {
        DB::table('documents')->where('is_active', false)->whereNull('deleted_at')->update(['deleted_at' => now()]);

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
