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
            if (!Schema::hasColumn('training_quiz_results', 'mc_score')) {
                $table->decimal('mc_score', 5, 2)->nullable()->after('score');
            }
            if (!Schema::hasColumn('training_quiz_results', 'essay_score')) {
                $table->decimal('essay_score', 5, 2)->nullable()->after('mc_score');
            }
            if (!Schema::hasColumn('training_quiz_results', 'essay_status')) {
                $table->string('essay_status', 30)->default('none')->after('essay_score'); // none, pending, graded
            }
            if (!Schema::hasColumn('training_quiz_results', 'essay_feedback')) {
                $table->text('essay_feedback')->nullable()->after('essay_status');
            }
            if (!Schema::hasColumn('training_quiz_results', 'reviewed_at')) {
                $table->dateTime('reviewed_at')->nullable()->after('essay_feedback');
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
            $table->dropColumn([
                'mc_score',
                'essay_score',
                'essay_status',
                'essay_feedback',
                'reviewed_at',
            ]);
        });
    }
};
