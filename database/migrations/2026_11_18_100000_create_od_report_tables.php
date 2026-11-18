<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OD Report (laporan tim Organization Development) dan Permintaan Dokumen (pengajuan IK dari karyawan), dipindah dari
 * dua aplikasi statis terpisah. Satu proses dalam pengajuan adalah satu baris od_documents, jadi dokumen yang
 * diajukan langsung muncul di Approval Dokumen tanpa sinkron dua arah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('od_document_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();
            $table->string('type', 10); // open | done | cancel
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('od_document_statuses')->insert([
            ['name' => 'On Progress', 'type' => 'open', 'sort' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Cancel', 'type' => 'cancel', 'sort' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Release', 'type' => 'done', 'sort' => 3, 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::create('od_pic_defaults', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 30)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->string('nama', 100);
            $table->string('divisi', 120);
            $table->string('departemen', 120);
            $table->string('jabatan', 30);
            $table->string('atasan', 100)->nullable();
            $table->string('whatsapp', 20);
            $table->string('email', 150);
            $table->date('tanggal');
            $table->date('obs_tanggal_1');
            $table->date('obs_tanggal_2')->nullable();
            $table->string('obs_catatan', 300)->nullable();
            $table->date('jadwal_observasi')->nullable();
            $table->text('catatan_admin')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('created_at');
        });

        Schema::create('od_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title', 250);
            $table->string('jenis', 30);
            $table->string('badan_usaha', 120)->default('-');
            $table->foreignId('od_document_status_id')->constrained('od_document_statuses');
            $table->foreignId('pic_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('catatan', 300)->nullable();
            $table->date('submitted_at');
            $table->date('received_at')->nullable();
            $table->char('month', 7);
            // Hanya untuk dokumen yang berasal dari Permintaan Dokumen.
            $table->foreignId('document_request_id')->nullable()->constrained('document_requests')->cascadeOnDelete();
            $table->text('tujuan')->nullable();
            $table->string('prioritas', 10)->nullable();
            $table->string('sistem', 200)->nullable();
            $table->string('stage', 40)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['month', 'pic_user_id']);
        });

        Schema::create('od_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->string('name', 200);
            $table->string('result', 20);
            $table->string('kendala', 300)->nullable();
            $table->string('solusi', 300)->nullable();
            $table->string('keterangan', 300)->nullable();
            $table->foreignId('od_document_id')->nullable()->constrained('od_documents')->nullOnDelete();
            $table->timestamp('saved_at')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'date']);
            $table->index('date');
        });

        Schema::create('document_request_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_request_id')->constrained('document_requests')->cascadeOnDelete();
            $table->foreignId('od_document_id')->nullable()->constrained('od_documents')->nullOnDelete();
            $table->string('status', 40);
            $table->string('note', 300)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['document_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_request_histories');
        Schema::dropIfExists('od_activities');
        Schema::dropIfExists('od_documents');
        Schema::dropIfExists('document_requests');
        Schema::dropIfExists('od_pic_defaults');
        Schema::dropIfExists('od_document_statuses');
    }
};
