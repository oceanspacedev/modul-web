<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pengertian tiap jenis dokumen yang tampil di form Permintaan Dokumen ("Apa itu …?"). Hanya mengisi kategori yang
 * pengertiannya masih kosong atau masih teks bawaan, jadi isian OD Leader di halaman Kategori tidak tertimpa.
 */
return new class extends Migration
{
    /** Teks bawaan IK dari migration kategori. */
    private const OLD_IK_DEFAULT = 'Panduan langkah kerja suatu proses. Tim OD akan observasi langsung.';

    private const DESCRIPTIONS = [
        'SOP' => 'Dokumen yang berisi standar dan tahapan prosedur kerja sebagai pedoman dalam melaksanakan suatu proses atau kegiatan agar berjalan secara konsisten.',
        'MEMO' => 'Dokumen komunikasi internal perusahaan yang digunakan untuk menyampaikan informasi, instruksi, pemberitahuan, atau permintaan kepada pihak terkait.',
        'SE' => 'Dokumen resmi perusahaan yang digunakan untuk menyampaikan informasi, kebijakan, ketentuan, atau pemberitahuan kepada karyawan atau pihak terkait secara luas.',
        'IK' => 'Dokumen yang berisi petunjuk teknis dan langkah-langkah secara rinci untuk melaksanakan suatu pekerjaan atau aktivitas tertentu.',
        'JD' => 'Dokumen yang menjelaskan tugas, tanggung jawab, wewenang, serta kualifikasi yang dibutuhkan dalam suatu jabatan sebagai pedoman pelaksanaan pekerjaan.',
    ];

    public function up(): void
    {
        foreach (self::DESCRIPTIONS as $code => $description) {
            DB::table('document_categories')->where('code', $code)
                ->where(fn ($query) => $query->whereNull('description')->orWhere('description', '')->orWhere('description', self::OLD_IK_DEFAULT))
                ->update(['description' => $description]);
        }
    }

    public function down(): void
    {
        foreach (self::DESCRIPTIONS as $code => $description) {
            DB::table('document_categories')->where('code', $code)->where('description', $description)
                ->update(['description' => $code === 'IK' ? self::OLD_IK_DEFAULT : null]);
        }
    }
};
