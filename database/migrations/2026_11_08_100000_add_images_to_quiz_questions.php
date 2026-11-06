<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gambar opsional untuk soal kuis: satu gambar di pertanyaan dan satu gambar per pilihan jawaban.
 * option_images berisi path per huruf pilihan, mis. {"a": "quiz-images/xxx.jpg"}.
 * Pilihan boleh hanya berupa gambar, jadi option_a/option_b soal modul ikut dibuat nullable
 * (di soal pelatihan sudah nullable sejak tipe essay ditambahkan).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['training_questions', 'learning_module_questions'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('question_image')->nullable()->after('question');
                $table->json('option_images')->nullable()->after('question_image');
            });
        }

        Schema::table('learning_module_questions', function (Blueprint $table) {
            $table->text('option_a')->nullable()->change();
            $table->text('option_b')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('learning_module_questions')->whereNull('option_a')->update(['option_a' => '']);
        DB::table('learning_module_questions')->whereNull('option_b')->update(['option_b' => '']);

        Schema::table('learning_module_questions', function (Blueprint $table) {
            $table->text('option_a')->nullable(false)->change();
            $table->text('option_b')->nullable(false)->change();
        });

        foreach (['training_questions', 'learning_module_questions'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn(['question_image', 'option_images']);
            });
        }
    }
};
