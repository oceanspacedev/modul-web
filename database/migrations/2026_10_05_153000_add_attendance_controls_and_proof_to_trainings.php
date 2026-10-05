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
            if (!Schema::hasColumn('trainings', 'is_attendance_active')) {
                $table->boolean('is_attendance_active')->default(false)->after('is_quiz_active');
            }
            if (!Schema::hasColumn('trainings', 'require_attendance_proof')) {
                $table->boolean('require_attendance_proof')->default(false)->after('is_attendance_active');
            }
        });

        Schema::table('training_participants', function (Blueprint $table) {
            if (!Schema::hasColumn('training_participants', 'attendance_proof')) {
                $table->string('attendance_proof')->nullable()->after('attendance_notes');
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
            if (Schema::hasColumn('trainings', 'require_attendance_proof')) {
                $table->dropColumn('require_attendance_proof');
            }
            if (Schema::hasColumn('trainings', 'is_attendance_active')) {
                $table->dropColumn('is_attendance_active');
            }
        });

        Schema::table('training_participants', function (Blueprint $table) {
            if (Schema::hasColumn('training_participants', 'attendance_proof')) {
                $table->dropColumn('attendance_proof');
            }
        });
    }
};
