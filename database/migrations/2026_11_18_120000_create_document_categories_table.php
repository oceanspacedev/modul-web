<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kategori Dokumen jadi data master: jenis dokumen OD (IK, JD, SE, MEMO, ...) dan tahapan pengerjaannya bisa diatur
 * sendiri. Kode kategori = nilai od_documents.jenis. PIC bawaan per jenis pindah dari od_pic_defaults ke kategori.
 */
return new class extends Migration
{
    private const IK_STAGES = [
        'Diajukan' => 'Pengajuan diterima dan menunggu ditinjau oleh tim Organization Development (OD).',
        'Dijadwalkan observasi' => 'Tim OD sudah menetapkan jadwal observasi. Mohon siapkan proses kerja sesuai jadwal.',
        'Dalam proses observasi' => 'Tim OD sedang mengamati langsung proses kerja sebagai bahan penyusunan IK.',
        'Drafting' => 'Tim OD sedang menyusun draf dokumen Instruksi Kerja.',
        'Proses review PIC' => 'Draf IK sedang direview oleh PIC proses. Mohon bantu merespons bila diminta.',
        'Proses review OD Leader' => 'Draf IK sedang direview oleh OD Leader.',
        'Proses Esign' => 'Dokumen IK sedang dalam proses tanda tangan elektronik.',
        'Release' => 'Dokumen IK sudah dirilis dan dapat dicek serta diunduh di cloud dokumen.',
    ];

    private const DEFAULT_STAGES = [
        'Diajukan' => 'Pengajuan diterima dan menunggu ditinjau oleh tim Organization Development (OD).',
        'Drafting' => 'Tim OD sedang menyusun draf dokumen.',
        'Proses review PIC' => 'Draf sedang direview oleh PIC. Mohon bantu merespons bila diminta.',
        'Proses review OD Leader' => 'Draf sedang direview oleh OD Leader.',
        'Proses Esign' => 'Dokumen sedang dalam proses tanda tangan elektronik.',
        'Release' => 'Dokumen sudah dirilis dan dapat dicek serta diunduh di cloud dokumen.',
    ];

    /** Urutan = urutan ekspor Achievement sebelumnya. */
    private const CATEGORIES = [
        ['SOP', 'Standard Operating Procedure', false],
        ['SE', 'Surat Edaran', false],
        ['MEMO', 'Memo Internal', false],
        ['IK', 'Instruksi Kerja', true],
        ['JD', 'Job Description', false],
        ['Dokumen Lainnya', 'Dokumen Lainnya', false],
    ];

    public function up(): void
    {
        Schema::create('document_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('description', 300)->nullable();
            $table->boolean('needs_observation')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('pic_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('document_category_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_category_id')->constrained('document_categories')->cascadeOnDelete();
            $table->string('name', 40);
            $table->string('description', 300)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        $now = now();
        $pics = Schema::hasTable('od_pic_defaults') ? DB::table('od_pic_defaults')->pluck('user_id', 'jenis') : collect();

        foreach (self::CATEGORIES as $index => [$code, $name, $observation]) {
            $id = DB::table('document_categories')->insertGetId([
                'code' => $code,
                'name' => $name,
                'description' => $code === 'IK' ? 'Panduan langkah kerja suatu proses. Tim OD akan observasi langsung.' : null,
                'needs_observation' => $observation,
                'is_active' => true,
                'pic_user_id' => $pics[$code] ?? null,
                'sort' => $index + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $sort = 0;
            foreach ($code === 'IK' ? self::IK_STAGES : self::DEFAULT_STAGES as $stage => $description) {
                DB::table('document_category_stages')->insert([
                    'document_category_id' => $id,
                    'name' => $stage,
                    'description' => $description,
                    'sort' => ++$sort,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        Schema::table('document_requests', function (Blueprint $table) {
            $table->foreignId('document_category_id')->nullable()->after('code')->constrained('document_categories')->restrictOnDelete();
            $table->date('obs_tanggal_1')->nullable()->change();
        });

        DB::table('document_requests')->update([
            'document_category_id' => DB::table('document_categories')->where('code', 'IK')->value('id'),
        ]);

        Schema::dropIfExists('od_pic_defaults');
    }

    public function down(): void
    {
        Schema::create('od_pic_defaults', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 30)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        $now = now();
        DB::table('document_categories')->whereNotNull('pic_user_id')->get(['code', 'pic_user_id'])
            ->each(fn ($c) => DB::table('od_pic_defaults')->insert(['jenis' => $c->code, 'user_id' => $c->pic_user_id, 'created_at' => $now, 'updated_at' => $now]));

        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('document_category_id');
        });

        Schema::dropIfExists('document_category_stages');
        Schema::dropIfExists('document_categories');
    }
};
