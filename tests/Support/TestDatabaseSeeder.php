<?php

namespace Tests\Support;

use App\Models\Divisi;
use App\Models\DokumenType;
use App\Models\JobLevel;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\Seeder;

class TestDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Divisi::create(['id' => 1, 'name' => 'Test Division']);
        JobLevel::create(['id' => 1, 'name' => 'ADMIN']);
        JobLevel::create(['id' => 2, 'name' => 'STAFF']);
        DokumenType::create(['id' => 1, 'name' => 'GENERAL']);

        // Legacy feature scenarios expect an administrator and reference IDs.
        // These fixtures are restricted to tests and never seed production users.
        User::factory()->create(['username' => 'admin', 'job_level_id' => 1]);

        $this->call(RoleAndPermissionSeeder::class);
    }
}
