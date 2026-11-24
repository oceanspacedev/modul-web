<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisi Permintaan Dokumen: badan usaha pemohon, lampiran per dokumen (dan template terisi untuk kategori bertemplate
 * seperti Jobdesk), jenis pengajuan (baru/revisi), file template per kategori, dan komentar tim OD untuk pemohon.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->string('badan_usaha', 120)->nullable()->after('email');
        });

        Schema::table('od_documents', function (Blueprint $table) {
            $table->string('jenis_pengajuan', 10)->nullable()->after('tujuan');
        });

        Schema::table('document_categories', function (Blueprint $table) {
            $table->string('template_path')->nullable()->after('example_name');
            $table->string('template_name', 200)->nullable()->after('template_path');
        });

        Schema::create('od_document_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('od_document_id')->constrained('od_documents')->cascadeOnDelete();
            $table->string('kind', 10);
            $table->string('path');
            $table->string('name', 200);
            $table->unsignedInteger('size')->default(0);
            $table->timestamps();
        });

        Schema::create('document_request_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_request_id')->constrained('document_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['document_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_request_comments');
        Schema::dropIfExists('od_document_attachments');

        Schema::table('document_categories', function (Blueprint $table) {
            $table->dropColumn(['template_path', 'template_name']);
        });

        Schema::table('od_documents', function (Blueprint $table) {
            $table->dropColumn('jenis_pengajuan');
        });

        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropColumn('badan_usaha');
        });
    }
};
