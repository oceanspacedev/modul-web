<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pretest/posttest langsung pada satu Video Materi atau Dokumen: modul tersembunyi yang dimiliki materi tersebut.
 * Pretest/posttest bisa dinyalakan terpisah dan batas pengulangannya diatur pembuat (null = tanpa batas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_modules', function (Blueprint $table) {
            $table->string('source_type')->nullable()->after('id');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->boolean('has_pretest')->default(true)->after('is_published');
            $table->boolean('has_posttest')->default(true)->after('has_pretest');
            $table->unsignedTinyInteger('pretest_max_attempts')->nullable()->default(1)->after('has_posttest');
            $table->unsignedTinyInteger('posttest_max_attempts')->nullable()->after('pretest_max_attempts');

            $table->unique(['source_type', 'source_id'], 'learning_modules_source_unique');
        });
    }

    public function down(): void
    {
        Schema::table('learning_modules', function (Blueprint $table) {
            $table->dropUnique('learning_modules_source_unique');
            $table->dropColumn(['source_type', 'source_id', 'has_pretest', 'has_posttest', 'pretest_max_attempts', 'posttest_max_attempts']);
        });
    }
};
