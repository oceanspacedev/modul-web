<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Link meeting tidak wajib: pelatihan tatap muka (tanpa Zoom) bisa dibuat tanpa mengisi tautan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->text('zoom_link')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('trainings')->whereNull('zoom_link')->update(['zoom_link' => '']);

        Schema::table('trainings', function (Blueprint $table) {
            $table->text('zoom_link')->nullable(false)->change();
        });
    }
};
