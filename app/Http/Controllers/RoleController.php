<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\DataAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
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
            'Pengaturan Sistem' => [
                'icon' => 'fas fa-sliders-h',
                'color' => 'text-secondary',
                'permissions' => [
                    'manage-users' => 'Mengelola Akun Pengguna',
                    'manage-roles' => 'Mengelola Jabatan & Hak Akses',
                    'manage-master-data' => 'Mengelola Area, Divisi, Posisi, & Tipe Dokumen',
                ],
            ],
        ];
    }

    /**
     * Display a listing of roles and user assignments.
     */
    public function index(): View
    {
        $roles = Role::with('permissions')->withCount('users')->orderByDesc('level')->orderBy('name')->get();
        $permissions = Permission::all();
        $users = User::with(['roles', 'permissions', 'divisi', 'position'])->orderBy('nama_lengkap')->get();
        $permissionGroups = self::getPermissionGroups();

        return view('setting.role.index', [
            'title' => 'Manajemen Jabatan & Hak Akses',
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
    public function create(): View
    {
        return view('setting.role.create', [
            'title' => 'Tambah Jabatan Baru',
            'active' => 'setting',
            'permissionGroups' => self::getPermissionGroups(),
        ]);
    }

    /**
     * Store a newly created role in storage.
     */
    public function store(RoleRequest $request): RedirectResponse
    {
        $role = Role::create([
            'name' => $request->validated('name'),
            'guard_name' => 'web',
            'level' => $request->validated('level'),
            'requires_approval' => $request->validated('requires_approval'),
        ]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return redirect()->route('roles.index')->with('success', "Jabatan '{$role->name}' berhasil dibuat dengan hak akses yang dipilih!");
    }

    /**
     * Show the form for editing the specified role and its permissions.
     */
    public function edit(Role $role): View
    {
        $role->load('permissions');
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
    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        // Nama jabatan bawaan DnD dipakai untuk pencocokan sinkronisasi dan aturan akses, jadi tidak boleh diganti.
        if ($role->isBuiltIn() && $request->validated('name') !== $role->name) {
            return back()->with('error', "Nama jabatan bawaan '{$role->name}' tidak dapat diubah.");
        }

        $role->update([
            'name' => $request->validated('name'),
            'level' => $request->validated('level'),
            'requires_approval' => $request->validated('requires_approval'),
        ]);

        if ($role->name === User::ROLE_ADMIN) {
            // Admin always has all permissions
            $role->syncPermissions(Permission::all());
        } else {
            $role->syncPermissions($request->permissions ?? []);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return redirect()->route('roles.index')->with('success', "Hak akses jabatan '{$role->name}' berhasil diperbarui!");
    }

    /**
     * Remove the specified role.
     */
    public function destroy(Role $role): RedirectResponse
    {
        if ($role->isBuiltIn()) {
            return back()->with('error', "Jabatan bawaan '{$role->name}' tidak boleh dihapus.");
        }

        if ($role->users()->count() > 0) {
            return back()->with('error', "Jabatan '{$role->name}' tidak bisa dihapus karena masih digunakan oleh {$role->users()->count()} pengguna.");
        }

        $name = $role->name;
        $role->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return redirect()->route('roles.index')->with('success', "Jabatan '{$name}' berhasil dihapus.");
    }

    /**
     * Ganti jabatan seorang user dan hak akses tambahan trainer-nya.
     */
    public function assignUserRole(Request $request): RedirectResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|exists:roles,name',
            'trainer' => 'sometimes|boolean',
        ]);

        $user = User::findOrFail($request->user_id);
        $role = Role::findByName($request->role, 'web');

        // Jabatan ADMIN (dan akun ADMIN) hanya diatur super admin.
        if (($role->name === User::ROLE_ADMIN || $user->isAdmin()) && ! $request->user()->can('manageDataAccess', User::class)) {
            return back()->with('error', 'Hanya super admin yang dapat memberikan atau mengubah jabatan ADMIN.');
        }

        if ($role->name !== User::ROLE_ADMIN && DataAccessService::hasGlobalAccess($user) && ! DataAccessService::anotherGlobalAdminExists($user)) {
            return back()->with('error', 'Minimal harus ada satu super admin. Jadikan karyawan lain super admin terlebih dahulu.');
        }

        $user->assignJabatan($role);

        if ($request->has('trainer')) {
            $user->setTrainer($request->boolean('trainer'));
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return back()->with('success', "Jabatan {$user->nama_lengkap} berhasil diubah menjadi '{$role->name}'.");
    }
}
