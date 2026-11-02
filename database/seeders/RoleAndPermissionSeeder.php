<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Permission jabatan non-admin bawaan (setara role "Staff" sebelumnya).
     */
    private const STAFF_PERMISSIONS = [
        'view-dashboard',
        'view-videos',
        'comment-videos',
        'view-my-trainings',
        'view-documents',
    ];

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Feature permissions map
        $permissions = [
            // Dashboard
            'view-dashboard' => 'Mengakses Halaman Dashboard & Statistik',

            // Video Materi
            'view-videos' => 'Menonton Video Materi',
            'manage-videos' => 'Mengunggah, Mengedit, & Menghapus Video',
            'comment-videos' => 'Mengirim Komentar & Pertanyaan di Video',

            // Pelatihan
            'manage-trainings' => 'Mengelola Jadwal Pelatihan & Peserta',
            'view-my-trainings' => 'Mengakses Menu Pelatihan Saya',

            // Dokumen
            'manage-documents' => 'Mengelola & Mengunggah Dokumen Modul',
            'view-documents' => 'Melihat & Mengunduh Dokumen Modul',

            // Pengaturan
            'manage-users' => 'Mengelola Akun Pengguna',
            'manage-roles' => 'Mengelola Jabatan & Hak Akses',
            'manage-master-data' => 'Mengelola Area, Divisi, Posisi, & Tipe Dokumen',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Jabatan DnD. ADMIN selalu memiliki semua permission; jabatan lain yang belum punya permission
        // mendapat permission STAFF, selanjutnya diatur dari menu Jabatan & Hak Akses.
        foreach (Role::JABATAN as $name => $level) {
            $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            $role->update(['level' => $role->level ?? $level]);

            if ($name === User::ROLE_ADMIN) {
                $role->syncPermissions(Permission::all());
            } elseif ($role->permissions()->doesntExist()) {
                $role->syncPermissions(self::STAFF_PERMISSIONS);
            }
        }

        // Setiap user memiliki satu jabatan; user tanpa jabatan menjadi STAFF.
        $staff = Role::findByName(User::ROLE_STAFF, 'web');
        User::doesntHave('roles')->each(fn (User $user) => $user->assignJabatan($staff));
    }
}
