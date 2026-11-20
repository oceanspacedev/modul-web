<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Langkah form Dokumen Builder yang diatur OD Leader per Kategori Dokumen. Selama kategori belum punya baris di sini,
 * builder memakai langkah bawaan dari App\Support\DocumentBuilderTemplates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_builder_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_category_id')->constrained('document_categories')->cascadeOnDelete();
            // Kunci jawaban di document_drafts.answers; tetap sama walau label diubah.
            $table->string('key', 40);
            $table->string('label', 100);
            $table->string('hint', 150)->nullable();
            $table->string('placeholder', 200)->nullable();
            $table->string('type', 10)->default('textarea'); // input | textarea
            $table->boolean('required')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['document_category_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_builder_questions');
    }
};
