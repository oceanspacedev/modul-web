<?php

namespace Database\Seeders;

use App\Models\Document;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DocumentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Document::insert([
            [
                'name' => 'COMPLETE SELULAR',
                'divisi_id' => 12,
                'sub_divisi_id' => 21,
                'job_level_id' => 8,
                'document_type' => 1,
                'path' => 'modul_complete_selular.pdf'
            ],
            [
                'name' => 'PENGENALAN PROGRAM',
                'divisi_id' => 12,
                'sub_divisi_id' => 21,
                'job_level_id' => 8,
                'document_type' => 1,
                'path' => 'modul-pengenalan-program.pdf'
            ],
            [
                'name' => 'BUDAYA PERUSAHAAN',
                'divisi_id' => 12,
                'sub_divisi_id' => 21,
                'job_level_id' => 8,
                'document_type' => 1,
                'path' => 'modul-budaya-perusahaan.pdf'
            ],
            [
                'name' => 'STOCK OPNAME',
                'divisi_id' => 10,
                'sub_divisi_id' => 21,
                'job_level_id' => 2,
                'document_type' => 2,
                'path' => 'stock_opname.pdf'
            ],
        ]);
    }
}
