<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    /**
     * Map of permissions grouped by module with human-readable labels
     */
    public static function getPermissionGroups(): array
    {
        return [
            'Dashboard' => [
                'icon' => 'fas fa-tachometer-alt',
                'color' => 'text-primary',
                'permissions' => [
                    'view-dashboard' => 'Melihat Dashboard & Statistik',
                ],
            ],
            'Video Materi' => [
                'icon' => 'fas fa-play-circle',
                'color' => 'text-danger',
                'permissions' => [
                    'view-videos' => 'Menonton Video Materi',
                    'manage-videos' => 'Mengunggah, Mengedit, & Menghapus Video',
                    'comment-videos' => 'Mengirim Komentar & Pertanyaan di Video',
                ],
            ],
            'Pelatihan (Training)' => [
                'icon' => 'fas fa-chalkboard-teacher',
                'color' => 'text-info',
                'permissions' => [
                    'manage-trainings' => 'Mengelola Jadwal Pelatihan & Peserta',
                    'view-my-trainings' => 'Mengakses Menu Pelatihan Saya',
                ],
            ],
            'Dokumen Modul' => [
                'icon' => 'fas fa-file-alt',
                'color' => 'text-warning',
                'permissions' => [
                    'manage-documents' => 'Mengunggah & Mengelola Dokumen',
                    'view-documents' => 'Melihat & Mengunduh Dokumen Modul',
                ],
            ],
            'Kuis & Evaluasi' => [
                'icon' => 'fas fa-award',
                'color' => 'text-purple',
                'permissions' => [
                    'manage-quizzes' => 'Mengelola Kuis, Bank Soal, & Hasil Ujian',
                ],
            ],
            'Presensi / Presence' => [
                'icon' => 'fas fa-calendar-check',
                'color' => 'text-success',
                'permissions' => [
                    'manage-presence' => 'Mengakses & Merekap Presensi Karyawan',
                ],
            ],
            'Pengaturan Sistem' => [
                'icon' => 'fas fa-sliders-h',
                'color' => 'text-secondary',
                'permissions' => [
                    'manage-users' => 'Mengelola Akun Pengguna',
                    'manage-roles' => 'Mengelola Hak Akses Role & Permission',
                    'manage-master-data' => 'Mengelola Divisi, Sub Divisi, & Job Level',
                ],
            ],
        ];
    }

    /**
     * Display a listing of roles and user assignments.
     */
    public function index()
    {
        $roles = Role::with(['permissions', 'users'])->get();
        $permissions = Permission::all();
        $users = User::with(['roles', 'divisi', 'joblevel'])->orderBy('full_name')->get();
        $permissionGroups = self::getPermissionGroups();

        return view('setting.role.index', [
            'title' => 'Manajemen Role & Hak Akses',
            'active' => 'setting',
            'roles' => $roles,
            'permissions' => $permissions,
            'users' => $users,
            'permissionGroups' => $permissionGroups,
        ]);
    }

    /**
     * Show the form for creating a new role.
     */
    public function create()
    {
        return view('setting.role.create', [
            'title' => 'Tambah Role Baru',
            'active' => 'setting',
            'permissionGroups' => self::getPermissionGroups(),
        ]);
    }

    /**
     * Store a newly created role in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name',
        ], [
            'name.required' => 'Nama Role wajib diisi.',
            'name.unique' => 'Nama Role tersebut sudah ada.',
        ]);

        $role = Role::create([
            'name' => trim($request->name),
            'guard_name' => 'web',
        ]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return redirect()->route('roles.index')->with('success', "Role '{$role->name}' berhasil dibuat dengan hak akses yang dipilih!");
    }

    /**
     * Show the form for editing the specified role and its permissions.
     */
    public function edit($id)
    {
        $role = Role::with('permissions')->findOrFail($id);
        $rolePermissions = $role->permissions->pluck('name')->toArray();

        return view('setting.role.edit', [
            'title' => 'Atur Hak Akses: '.$role->name,
            'active' => 'setting',
            'role' => $role,
            'rolePermissions' => $rolePermissions,
            'permissionGroups' => self::getPermissionGroups(),
        ]);
    }

    /**
     * Update the specified role and sync permissions.
     */
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:100|unique:roles,name,'.$role->id,
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        // Don't rename Admin if it's the core system role
        if ($role->name === 'Admin' && $request->name !== 'Admin') {
            return back()->with('error', 'Nama role Admin sistem bawaan tidak dapat diubah.');
        }

        $role->update(['name' => trim($request->name)]);

        if ($role->name === 'Admin') {
            // Admin always has all permissions
            $role->syncPermissions(Permission::all());
        } else {
            $role->syncPermissions($request->permissions ?? []);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return redirect()->route('roles.index')->with('success', "Hak akses role '{$role->name}' berhasil diperbarui!");
    }

    /**
     * Remove the specified role.
     */
    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        if (in_array($role->name, ['Admin', 'Staff', 'Trainer'])) {
            return back()->with('error', "Role bawaan sistem '{$role->name}' tidak boleh dihapus.");
        }

        if ($role->users()->count() > 0) {
            return back()->with('error', "Role '{$role->name}' tidak bisa dihapus karena masih digunakan oleh {$role->users()->count()} pengguna.");
        }

        $name = $role->name;
        $role->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return redirect()->route('roles.index')->with('success', "Role '{$name}' berhasil dihapus.");
    }

    /**
     * Assign or update a user's role.
     */
    public function assignUserRole(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|exists:roles,name',
        ]);

        $user = User::findOrFail($request->user_id);
        $user->syncRoles([$request->role]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return back()->with('success', "Role pengguna {$user->full_name} berhasil diubah menjadi '{$request->role}'.");
    }
}
