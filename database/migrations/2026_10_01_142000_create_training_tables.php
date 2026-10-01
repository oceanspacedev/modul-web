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
        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('trainer_id')->constrained('users')->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->date('training_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->text('zoom_link');
            $table->string('status')->default('scheduled'); // scheduled, ongoing, completed, cancelled
            $table->boolean('is_quiz_active')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('training_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained('trainings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('attendance_status')->default('pending'); // pending, hadir, tidak_hadir
            $table->dateTime('attended_at')->nullable();
            $table->string('attendance_notes')->nullable();
            $table->dateTime('wa_sent_at')->nullable();
            $table->string('wa_status')->nullable();
            $table->timestamps();
        });

        Schema::create('training_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained('trainings')->cascadeOnDelete();
            $table->text('question');
            $table->text('option_a');
            $table->text('option_b');
            $table->text('option_c');
            $table->text('option_d');
            $table->string('correct_answer', 1); // a, b, c, d
            $table->text('explanation')->nullable();
            $table->timestamps();
        });

        Schema::create('training_quiz_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained('trainings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('total_questions')->default(0);
            $table->integer('correct_answers')->default(0);
            $table->decimal('score', 5, 2)->default(0);
            $table->json('answers')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('training_quiz_results');
        Schema::dropIfExists('training_questions');
        Schema::dropIfExists('training_participants');
        Schema::dropIfExists('trainings');
    }
};
