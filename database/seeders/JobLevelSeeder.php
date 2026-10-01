<?php

namespace Database\Seeders;

use App\Models\JobLevel;
use Illuminate\Database\Seeder;

class JobLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        JobLevel::insert([
            [
                'name' => 'ADMIN'
            ],
            [
                'name' => 'STAFF'
            ],
            [
                'name' => 'TEAM LEADER'
            ],
            [
                'name' => 'COORDINATOR'
            ],
            [
                'name' => 'MANAGER'
            ],
            [
                'name' => 'CHIEF'
            ],
            [
                'name' => 'BOD'
            ],
            [
                'name' => 'ALL'
            ],
        ]);
    }
}
