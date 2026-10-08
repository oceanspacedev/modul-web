<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('training_participants', function (Blueprint $table) {
            if (!Schema::hasColumn('training_participants', 'is_off_cam')) {
                $table->boolean('is_off_cam')->default(false)->after('attendance_notes');
            }
            if (!Schema::hasColumn('training_participants', 'zoom_display_name')) {
                $table->string('zoom_display_name')->nullable()->after('is_off_cam');
            }
            if (!Schema::hasColumn('training_participants', 'zoom_off_cam_at')) {
                $table->dateTime('zoom_off_cam_at')->nullable()->after('zoom_display_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('training_participants', function (Blueprint $table) {
            if (Schema::hasColumn('training_participants', 'zoom_off_cam_at')) {
                $table->dropColumn('zoom_off_cam_at');
            }
            if (Schema::hasColumn('training_participants', 'zoom_display_name')) {
                $table->dropColumn('zoom_display_name');
            }
            if (Schema::hasColumn('training_participants', 'is_off_cam')) {
                $table->dropColumn('is_off_cam');
            }
        });
    }
};
