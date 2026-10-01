<?php

namespace Database\Seeders;

use App\Models\DokumenType;
use Illuminate\Database\Seeder;

class DokumenTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DokumenType::insert([
            [
                'name' => 'GENERAL',
            ],
            [
                'name' => 'JOB DESC'
            ],
            [
                'name' => 'OPERASIONAL',
            ],
            [
                'name' => 'PELAYANAN',
            ],
            [
                'name' => 'PENJUALAN',
            ],
            [
                'name' => 'KATALOG',
            ],
        ]);
    }
}
