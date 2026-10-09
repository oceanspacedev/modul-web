<?php

namespace Database\Seeders;

use App\Models\Training;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
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
            // Kuis
            'manage-quizzes' => 'Mengelola Kuis, Bank Soal, & Penilaian',
            // Presensi
            'manage-presence' => 'Mengakses Data Presensi Karyawan',
            // Pengaturan
            'manage-users' => 'Mengelola Akun Pengguna',
            'manage-roles' => 'Mengelola Hak Akses Role & Permission',
            'manage-master-data' => 'Mengelola Divisi, Sub Divisi, & Job Level',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // 1. Role Admin: has all permissions
        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(Permission::all());

        // 2. Role Trainer: manages training, videos, quizzes, comments
        $trainerRole = Role::firstOrCreate(['name' => 'Trainer', 'guard_name' => 'web']);
        $trainerRole->syncPermissions([
            'view-dashboard',
            'view-videos',
            'manage-videos',
            'comment-videos',
            'manage-trainings',
            'view-my-trainings',
            'manage-quizzes',
            'manage-documents',
            'view-documents',
        ]);

        // 3. Role Staff / Peserta: basic viewing and participation
        $staffRole = Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web']);
        $staffRole->syncPermissions([
            'view-dashboard',
            'view-videos',
            'comment-videos',
            'view-my-trainings',
            'view-documents',
        ]);

        // Assign roles to existing users based on job_level_id or trainer status
        $users = User::all();
        foreach ($users as $user) {
            if ($user->job_level_id == 1) {
                if (! $user->hasRole('Admin')) {
                    $user->assignRole('Admin');
                }
            } else {
                $isTrainer = Training::where('trainer_id', $user->id)->exists();
                if ($isTrainer) {
                    if (! $user->hasRole('Trainer')) {
                        $user->assignRole('Trainer');
                    }
                } else {
                    if (! $user->hasRole('Staff') && $user->roles()->count() === 0) {
                        $user->assignRole('Staff');
                    }
                }
            }
        }
    }
}
