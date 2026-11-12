<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_questions', function (Blueprint $table) {
            $table->foreignId('source_learning_module_question_id')->nullable()
                ->constrained('learning_module_questions')->nullOnDelete();
            $table->unique(['training_id', 'source_learning_module_question_id'], 'training_questions_source_unique');
        });
    }

    public function down(): void
    {
        Schema::table('training_questions', function (Blueprint $table) {
            $table->dropUnique('training_questions_source_unique');
            $table->dropConstrainedForeignId('source_learning_module_question_id');
        });
    }
};
