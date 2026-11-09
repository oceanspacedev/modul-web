<?php

namespace Tests\Support;

use App\Models\Area;
use App\Models\Divisi;
use App\Models\DokumenType;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\Seeder;

class TestDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Area::create(['id' => 1, 'name' => 'TEST AREA']);
        Divisi::create(['id' => 1, 'name' => 'Test Division', 'area_id' => 1]);
        DokumenType::create(['id' => 1, 'name' => 'GENERAL']);

        $this->call(RoleAndPermissionSeeder::class);

        // Legacy feature scenarios expect an administrator and reference IDs.
        // These fixtures are restricted to tests and never seed production users.
        User::factory()->admin()->create(['username' => 'admin', 'area_id' => 1]);
    }
}
