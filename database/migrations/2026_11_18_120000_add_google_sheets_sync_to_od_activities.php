<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Sinkron Daily Report ke spreadsheet Weekly Report (Apps Script yang sama dengan aplikasi OD Report lama).
 * sync_key = SYNC ID di kolom H spreadsheet; synced_at = terakhir terkirim. Aktivitas yang diubah setelah synced_at
 * dikirim ulang. Aktivitas yang sudah terkirim lalu dihapus dicatat di od_sheet_deletions sampai barisnya dikosongkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('od_activities', function (Blueprint $table) {
            $table->string('sync_key', 36)->nullable()->unique()->after('sort');
            $table->timestamp('synced_at')->nullable()->after('sync_key');
        });

        DB::table('od_activities')->whereNull('sync_key')->orderBy('id')->each(function ($row) {
            DB::table('od_activities')->where('id', $row->id)->update(['sync_key' => (string) Str::uuid()]);
        });

        Schema::create('od_sheet_deletions', function (Blueprint $table) {
            $table->id();
            $table->string('sync_key', 36)->unique();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('od_sheet_deletions');

        Schema::table('od_activities', function (Blueprint $table) {
            $table->dropUnique(['sync_key']);
            $table->dropColumn(['sync_key', 'synced_at']);
        });
    }
};
