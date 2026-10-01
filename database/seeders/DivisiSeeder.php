<?php

namespace Database\Seeders;

use App\Models\Divisi;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DivisiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Divisi::insert([
            [
                'name' => 'EO'
            ],
            [
                'name' => 'BUSDEV'
            ],
            [
                'name' => 'MARCOMM'
            ],
            [
                'name' => 'MIS'
            ],
            [
                'name' => 'FAM'
            ],
            [
                'name' => 'SCM'
            ],
            [
                'name' => 'HCM'
            ],
            [
                'name' => 'CIA'
            ],
            [
                'name' => 'ONLINE'
            ],
            [
                'name' => 'RETAIL'
            ],
            [
                'name' => 'DS'
            ],
            [
                'name' => 'ALL'
            ]
        ]);
    }
}
