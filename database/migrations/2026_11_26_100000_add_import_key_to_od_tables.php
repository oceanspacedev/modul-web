<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda baris hasil import Google Sheets (Weekly BusDev → od_activities, Approval Dokumen → od_documents). Kunci
 * yang sama berarti baris sheet yang sama, jadi import ulang memperbarui baris itu, bukan menggandakannya. import_hash
 * = isi sheet saat terakhir di-import: baris yang di sheet tidak berubah tidak menimpa perubahan di aplikasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['od_activities', 'od_documents'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('import_key', 64)->nullable()->unique();
                $table->char('import_hash', 40)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['od_activities', 'od_documents'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->dropUnique("{$name}_import_key_unique");
                $table->dropColumn(['import_key', 'import_hash']);
            });
        }
    }
};
