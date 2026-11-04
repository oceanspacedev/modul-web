<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Belajar: pretest (sekali), materi video/dokumen yang wajib diselesaikan, lalu posttest yang bisa diulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Percobaan pertama di MySQL gagal di tengah jalan (nama index terlalu panjang) dan DDL MySQL tidak bisa
        // di-rollback, jadi tabel kosong yang sempat dibuat dibersihkan dulu.
        $this->down();

        Schema::create('learning_modules', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('passing_score')->default(70);
            $table->boolean('shuffle_questions')->default(true);
            $table->boolean('is_published')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // Sasaran modul; tanpa baris berarti untuk semua divisi / jabatan.
        Schema::create('learning_module_divisi', function (Blueprint $table) {
            $table->foreignId('learning_module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('divisi_id')->constrained('divisis')->cascadeOnDelete();
            $table->primary(['learning_module_id', 'divisi_id']);
        });

        Schema::create('learning_module_role', function (Blueprint $table) {
            $table->foreignId('learning_module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->primary(['learning_module_id', 'role_id']);
        });

        Schema::create('learning_module_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_module_id')->constrained()->cascadeOnDelete();
            $table->morphs('materialable');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['learning_module_id', 'materialable_type', 'materialable_id'], 'learning_module_items_unique');
        });

        Schema::create('learning_module_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_module_id')->constrained()->cascadeOnDelete();
            $table->text('question');
            $table->text('option_a');
            $table->text('option_b');
            $table->text('option_c')->nullable();
            $table->text('option_d')->nullable();
            $table->text('option_e')->nullable();
            $table->char('correct_answer', 1);
            $table->text('explanation')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('learning_module_item_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_module_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Nama default melebihi batas 64 karakter MySQL.
            $table->unique(['learning_module_item_id', 'user_id'], 'learning_module_item_progress_unique');
        });

        // Setiap percobaan disimpan; pretest hanya boleh attempt_number 1.
        Schema::create('learning_module_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('phase', 10);
            $table->unsignedInteger('attempt_number');
            $table->unsignedInteger('total_questions');
            $table->unsignedInteger('correct_answers');
            $table->decimal('score', 5, 2);
            $table->boolean('is_passed');
            $table->json('answers');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(['learning_module_id', 'user_id', 'phase', 'attempt_number'], 'learning_module_attempts_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_module_attempts');
        Schema::dropIfExists('learning_module_item_progress');
        Schema::dropIfExists('learning_module_questions');
        Schema::dropIfExists('learning_module_items');
        Schema::dropIfExists('learning_module_role');
        Schema::dropIfExists('learning_module_divisi');
        Schema::dropIfExists('learning_modules');
    }
};
