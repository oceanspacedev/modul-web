<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * File contoh per Kategori Dokumen, diunggah OD Leader dan dibuka pemohon dari form Permintaan Dokumen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_categories', function (Blueprint $table) {
            $table->string('example_path')->nullable()->after('description');
            $table->string('example_name', 200)->nullable()->after('example_path');
        });
    }

    public function down(): void
    {
        Schema::table('document_categories', function (Blueprint $table) {
            $table->dropColumn(['example_path', 'example_name']);
        });
    }
};
