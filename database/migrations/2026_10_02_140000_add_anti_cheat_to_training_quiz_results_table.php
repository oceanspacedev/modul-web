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
        Schema::table('training_quiz_results', function (Blueprint $table) {
            if (!Schema::hasColumn('training_quiz_results', 'tab_switch_count')) {
                $table->integer('tab_switch_count')->default(0)->after('essay_feedback');
            }
            if (!Schema::hasColumn('training_quiz_results', 'is_force_submitted')) {
                $table->boolean('is_force_submitted')->default(false)->after('tab_switch_count');
            }
            if (!Schema::hasColumn('training_quiz_results', 'violation_logs')) {
                $table->json('violation_logs')->nullable()->after('is_force_submitted');
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
        Schema::table('training_quiz_results', function (Blueprint $table) {
            if (Schema::hasColumn('training_quiz_results', 'violation_logs')) {
                $table->dropColumn('violation_logs');
            }
            if (Schema::hasColumn('training_quiz_results', 'is_force_submitted')) {
                $table->dropColumn('is_force_submitted');
            }
            if (Schema::hasColumn('training_quiz_results', 'tab_switch_count')) {
                $table->dropColumn('tab_switch_count');
            }
        });
    }
};
