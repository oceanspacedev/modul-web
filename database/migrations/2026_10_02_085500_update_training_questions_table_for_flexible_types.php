<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            if (!Schema::hasColumn('training_questions', 'type')) {
                $table->string('type')->default('multiple_choice')->after('training_id');
            }
            if (!Schema::hasColumn('training_questions', 'option_e')) {
                $table->text('option_e')->nullable()->after('option_d');
            }
            if (!Schema::hasColumn('training_questions', 'option_f')) {
                $table->text('option_f')->nullable()->after('option_e');
            }
        });

        // Make options and correct_answer nullable using direct statement for database portability without doctrine/dbal
        try {
            DB::statement('ALTER TABLE training_questions MODIFY option_a TEXT NULL');
            DB::statement('ALTER TABLE training_questions MODIFY option_b TEXT NULL');
            DB::statement('ALTER TABLE training_questions MODIFY option_c TEXT NULL');
            DB::statement('ALTER TABLE training_questions MODIFY option_d TEXT NULL');
            DB::statement('ALTER TABLE training_questions MODIFY correct_answer TEXT NULL');
        } catch (\Exception $e) {
            // Log or fallback
        }
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
