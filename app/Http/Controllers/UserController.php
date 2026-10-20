<?php

namespace App\Http\Controllers;

use App\Exports\UserExport;
use App\Exports\UserTemplate;
use App\Http\Requests\ImportUserRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Imports\UserImport;
use App\Models\Divisi;
use App\Models\JobLevel;
use App\Models\SubDivisi;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

#[Authorize('viewAny', User::class, only: ['index', 'export', 'template'])]
#[Authorize('create', User::class, only: ['create', 'store', 'import'])]
#[Authorize('update', 'user', only: ['edit', 'update'])]
#[Authorize('delete', 'user', only: ['destroy'])]
#[Authorize('restore', 'user', only: ['restore'])]
class UserController extends Controller
{
    public function index(): View
    {
        return view('setting.user.index', [
            'title' => 'User',
            'active' => 'setting',
            'users' => User::with(['joblevel', 'divisi', 'subdivisi', 'roles'])->filter()->withTrashed()->orderBy('full_name')->get(),
        ]);
    }

    public function import(ImportUserRequest $request): RedirectResponse
    {
        try {
            Excel::import(new UserImport, $request->file('file'));
        } catch (Throwable $e) {
            report($e);

            return to_route('user.index')->with('error', 'Gagal import user: '.$e->getMessage());
        }

        return to_route('user.index')->with('success', 'Berhasil import user');
    }

    public function template(): BinaryFileResponse
    {
        return Excel::download(new UserTemplate, 'user_template.xlsx');
    }

    public function export(): BinaryFileResponse
    {
        return Excel::download(new UserExport, 'user_export.xlsx');
    }

    public function create(): View
    {
        return view('setting.user.create', [
            'title' => 'User',
            'active' => 'setting',
            'joblevels' => JobLevel::all(),
            'divisis' => Divisi::all(),
            'roles' => Role::all(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create($request->safe()->except('role'));
        $user->assignRole($request->validated('role'));

        return to_route('user.index')->with('success', 'Berhasil menambahkan user');
    }

    public function edit(User $user): View|RedirectResponse
    {
        if ($user->trashed()) {
            return to_route('user.index')->with('error', 'Tidak bisa mengedit user yang nonaktif, aktifkan terlebih dahulu');
        }

        $user->load(['joblevel', 'divisi', 'subdivisi.divisi', 'roles']);

        return view('setting.user.edit', [
            'title' => 'User',
            'active' => 'setting',
            'user' => $user,
            'joblevels' => JobLevel::all(),
            'divisis' => Divisi::all(),
            'subdivisis' => SubDivisi::where('divisi_id', $user->divisi_id)->get(),
            'roles' => Role::all(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->safe()->except(['role', 'password']);
        $data['sub_divisi_id'] ??= null;

        if ($request->filled('password')) {
            $data['password'] = $request->validated('password');
        }

        $user->update($data);

        if ($request->filled('role')) {
            $user->syncRoles([$request->validated('role')]);
        }

        return to_route('user.index')->with('success', 'Berhasil mengubah user');
    }

    public function destroy(User $user): RedirectResponse
    {
        $user->delete();

        return to_route('user.index')->with('success', 'Berhasil menonaktifkan user');
    }

    public function restore(User $user): RedirectResponse
    {
        $user->restore();

        return to_route('user.index')->with('success', 'Berhasil mengaktifkan user');
    }
}
