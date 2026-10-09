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
        Schema::table('training_questions', function (Blueprint $table) {
            if (! Schema::hasColumn('training_questions', 'type')) {
                $table->string('type')->default('multiple_choice')->after('training_id');
            }
            if (! Schema::hasColumn('training_questions', 'option_e')) {
                $table->text('option_e')->nullable()->after('option_d');
            }
            if (! Schema::hasColumn('training_questions', 'option_f')) {
                $table->text('option_f')->nullable()->after('option_e');
            }
        });

        Schema::table('training_questions', function (Blueprint $table) {
            foreach (['option_a', 'option_b', 'option_c', 'option_d', 'correct_answer'] as $column) {
                $table->text($column)->nullable()->change();
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
        Schema::table('training_questions', function (Blueprint $table) {
            if (Schema::hasColumn('training_questions', 'type')) {
                $table->dropColumn('type');
            }
            if (Schema::hasColumn('training_questions', 'option_f')) {
                $table->dropColumn('option_f');
            }
            if (Schema::hasColumn('training_questions', 'option_e')) {
                $table->dropColumn('option_e');
            }
        });
    }
};
