<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Form evaluasi pelatihan (umpan balik peserta tentang pemateri dan materi), diisi sebelum posttest bila dinyalakan.
 * Nama, jabatan, divisi, tanggal, dan materi tidak disimpan di sini: semuanya sudah ada lewat peserta dan pelatihan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->boolean('has_evaluation')->default(false)->after('has_posttest');
        });

        Schema::create('training_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained('trainings')->cascadeOnDelete();
            $table->foreignId('training_participant_id')->unique()->constrained('training_participants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->json('ratings');
            $table->text('comment')->nullable();
            $table->dateTime('submitted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_evaluations');

        Schema::table('trainings', function (Blueprint $table) {
            $table->dropColumn('has_evaluation');
        });
    }
};
