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
        Schema::table('videos', function (Blueprint $table) {
            $table->string('video_type', 20)->default('file')->after('description');
            $table->text('video_link')->nullable()->after('video_file');
        });

        \Illuminate\Support\Facades\DB::statement("ALTER TABLE `videos` MODIFY `video_file` VARCHAR(255) NULL");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn(['video_type', 'video_link']);
        });
    }
};
