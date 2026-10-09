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
        Schema::table('trainings', function (Blueprint $table) {
            if (! Schema::hasColumn('trainings', 'quiz_mode')) {
                $table->string('quiz_mode', 20)->default('formal')->after('is_quiz_active');
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
        Schema::table('trainings', function (Blueprint $table) {
            if (Schema::hasColumn('trainings', 'quiz_mode')) {
                $table->dropColumn('quiz_mode');
            }
        });
    }
};
