<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        User::insert([
            [
                'full_name' => 'admin',
                'username' => 'admin',
                'password' => bcrypt('complete123'),
                'divisi_id' => 12,
                'sub_divisi_id' => null,
                'job_level_id' => 1,
            ],
        ]);
    }
}
