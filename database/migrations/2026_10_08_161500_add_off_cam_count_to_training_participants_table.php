<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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
            if (!Schema::hasColumn('training_participants', 'off_cam_count')) {
                $table->unsignedSmallInteger('off_cam_count')->default(0)->after('is_off_cam');
            }
        });

        // Initialize existing off_cam rows with off_cam_count = 1
        DB::table('training_participants')
            ->where('is_off_cam', true)
            ->where('off_cam_count', 0)
            ->update(['off_cam_count' => 1]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('training_participants', function (Blueprint $table) {
            if (Schema::hasColumn('training_participants', 'off_cam_count')) {
                $table->dropColumn('off_cam_count');
            }
        });
    }
};
