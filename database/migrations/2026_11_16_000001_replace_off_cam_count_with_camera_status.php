<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rekap kamera peserta berubah dari "Off Cam N×" menjadi tiga status (On Cam / Tidak Full On Cam / Full Off Cam) yang
 * diturunkan dari porsi pengecekan kamera menyala.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_participants', function (Blueprint $table) {
            $table->string('camera_status', 20)->nullable()->after('attendance_notes');
            $table->unsignedTinyInteger('camera_on_percent')->nullable()->after('camera_status');
        });

        Schema::table('training_participants', function (Blueprint $table) {
            $table->renameColumn('off_cam_at', 'camera_recorded_at');
        });

        Schema::table('training_participants', function (Blueprint $table) {
            $table->dropColumn(['is_off_cam', 'off_cam_count']);
        });
    }

    public function down(): void
    {
        Schema::table('training_participants', function (Blueprint $table) {
            $table->boolean('is_off_cam')->default(false)->after('attendance_notes');
            $table->unsignedSmallInteger('off_cam_count')->default(0)->after('is_off_cam');
        });

        Schema::table('training_participants', function (Blueprint $table) {
            $table->renameColumn('camera_recorded_at', 'off_cam_at');
        });

        Schema::table('training_participants', function (Blueprint $table) {
            $table->dropColumn(['camera_status', 'camera_on_percent']);
        });
    }
};
