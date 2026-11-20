<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dokumen Builder: draf dokumen (SOP, IK, JD, SE, MEMO, ...) yang disusun tim OD lewat form bertahap, dibantu AI.
 * Jawaban form dan isi dokumen disimpan sebagai JSON; bentuk isinya mengikuti template kategori
 * (lihat App\Support\DocumentBuilderTemplates).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_category_id')->constrained('document_categories')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 250);
            $table->string('department', 150)->nullable();
            $table->string('company', 150)->nullable();
            $table->string('document_number', 60)->nullable();
            $table->string('revision', 20)->nullable();
            $table->date('effective_date')->nullable();
            $table->string('approved_by', 150)->nullable();
            $table->json('answers')->nullable();
            $table->json('content')->nullable();
            // Token proses AI yang sedang berjalan; halaman proses hanya boleh membaca status token milik draf ini.
            $table->uuid('ai_token')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_drafts');
    }
};
