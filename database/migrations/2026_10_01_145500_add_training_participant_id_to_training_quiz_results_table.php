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
        Schema::table('training_quiz_results', function (Blueprint $table) {
            $table->foreignId('training_participant_id')->nullable()->after('user_id')->constrained('training_participants')->cascadeOnDelete();
        });

        // Backfill existing records
        $results = DB::table('training_quiz_results')->get();
        foreach ($results as $res) {
            $participant = DB::table('training_participants')
                ->where('training_id', $res->training_id)
                ->where('user_id', $res->user_id)
                ->first();

            if ($participant) {
                DB::table('training_quiz_results')
                    ->where('id', $res->id)
                    ->update(['training_participant_id' => $participant->id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('training_quiz_results', function (Blueprint $table) {
            $table->dropForeign(['training_participant_id']);
            $table->dropColumn('training_participant_id');
        });
    }
};
