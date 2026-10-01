<?php

namespace Database\Seeders;

use App\Models\SubDivisi;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubDivisiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        SubDivisi::insert([
            [
                'divisi_id' => 3,
                'name' => 'CREATIVE'
            ],
            [
                'divisi_id' => 3,
                'name' => 'DIGITAL MARKETTING'
            ],
            [
                'divisi_id' => 4,
                'name' => 'DATA'
            ],
            [
                'divisi_id' => 4,
                'name' => 'IT SUPPORT'
            ],
            [
                'divisi_id' => 5,
                'name' => 'FINANCE & ACCOUNTING MITRA'
            ],
            [
                'divisi_id' => 5,
                'name' => 'ACCOUNTING'
            ],
            [
                'divisi_id' => 5,
                'name' => 'KAS BESAR'
            ],
            [
                'divisi_id' => 5,
                'name' => 'FINANCE TRANSFER'
            ],
            [
                'divisi_id' => 5,
                'name' => 'FINANCE PAJAK'
            ],
            [
                'divisi_id' => 5,
                'name' => 'FINANCE ACCOUNT RECEIVABLE'
            ],
            [
                'divisi_id' => 5,
                'name' => 'FINANCE ACCOUNT PAYABLE'
            ],
            [
                'divisi_id' => 5,
                'name' => 'ARSIP'
            ],
            [
                'divisi_id' => 6,
                'name' => 'WAREHOUSE'
            ],
            [
                'divisi_id' => 6,
                'name' => 'RETUR'
            ],
            [
                'divisi_id' => 6,
                'name' => 'PURHCHASING'
            ],
            [
                'divisi_id' => 6,
                'name' => 'LOGISTIK'
            ],
            [
                'divisi_id' => 6,
                'name' => 'PROGRAM & REFUND'
            ],
            [
                'divisi_id' => 7,
                'name' => 'PERSONNEL'
            ],
            [
                'divisi_id' => 7,
                'name' => 'TALENT ACQUISITION'
            ],
            [
                'divisi_id' => 7,
                'name' => 'GENERAL AFFAIR'
            ],
            [
                'divisi_id' => 12,
                'name' => 'ALL'
            ],
        ]);
    }
}
