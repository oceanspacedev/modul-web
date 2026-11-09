<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\Role;
use App\Models\User;
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
                'role_id' => null,
                'document_type' => 1,
                'path' => 'modul_complete_selular.pdf',
            ],
            [
                'name' => 'PENGENALAN PROGRAM',
                'divisi_id' => 12,
                'role_id' => null,
                'document_type' => 1,
                'path' => 'modul-pengenalan-program.pdf',
            ],
            [
                'name' => 'BUDAYA PERUSAHAAN',
                'divisi_id' => 12,
                'role_id' => null,
                'document_type' => 1,
                'path' => 'modul-budaya-perusahaan.pdf',
            ],
            [
                'name' => 'STOCK OPNAME',
                'divisi_id' => 10,
                'role_id' => Role::findByName(User::ROLE_STAFF, 'web')->id,
                'document_type' => 2,
                'path' => 'stock_opname.pdf',
            ],
        ]);
    }
}
