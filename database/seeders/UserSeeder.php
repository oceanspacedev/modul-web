<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
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
        $admin = User::create([
            'nama_lengkap' => 'ADMIN',
            'username' => 'admin',
            'password' => 'complete123',
            'divisi_id' => 12,
            'data_access' => User::DATA_ACCESS_ALL,
        ]);

        $admin->assignJabatan(Role::findByName(User::ROLE_ADMIN, 'web'), dataAccessChosen: true);
    }
}
