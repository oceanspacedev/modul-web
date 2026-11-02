<?php

namespace App\Http\Controllers;

use App\Exports\UserExport;
use App\Exports\UserTemplate;
use App\Http\Requests\ImportUserRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Imports\UserImport;
use App\Models\Area;
use App\Models\Divisi;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use App\Services\ApprovalLineService;
use App\Services\ApprovalScopeService;
use App\Services\DataAccessService;
use App\Services\DndUserSyncService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

#[Authorize('viewAny', User::class, only: ['index', 'approvers', 'bulk'])]
#[Authorize('import', User::class, only: ['export', 'template', 'import'])]
#[Authorize('create', User::class, only: ['create', 'store'])]
#[Authorize('update', 'user', only: ['edit', 'update'])]
#[Authorize('delete', 'user', only: ['destroy'])]
#[Authorize('restore', 'user', only: ['restore'])]
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $archived = $request->query('tab') === 'arsip';

        $query = $this->visibleUsers($request->user())
            ->with(['roles', 'area', 'divisi', 'position', 'approval'])
            ->filter()
            ->when($archived, fn (Builder $query) => $query->onlyTrashed(), fn (Builder $query) => $query->withoutTrashed())
            ->when($request->filled('area_id'), fn (Builder $query) => $query->where('area_id', $request->integer('area_id')))
            ->when($request->filled('divisi_id'), fn (Builder $query) => $query->where('divisi_id', $request->integer('divisi_id')))
            ->when($request->filled('position_id'), fn (Builder $query) => $query->where('position_id', $request->integer('position_id')))
            ->when($request->filled('approval_id'), fn (Builder $query) => $query->where('approval_id', $request->integer('approval_id')))
            ->when($request->filled('role_id'), fn (Builder $query) => $query->whereHas('roles', fn (Builder $roles) => $roles->whereKey($request->integer('role_id'))));

        $selectableApprovers = ApprovalLineService::selectableApproverIds($request->user(), false);

        return view('setting.user.index', [
            'title' => 'User',
            'active' => 'setting',
            'archived' => $archived,
            'dndConfigured' => DndUserSyncService::isConfigured(),
            'bulkApprovers' => User::when($selectableApprovers !== null, fn (Builder $query) => $query->whereIn('id', $selectableApprovers))
                ->orderBy('nama_lengkap')->get(['id', 'nama_lengkap']),
            'users' => $query->orderBy('nama_lengkap')->paginate(25)->withQueryString(),
            'areas' => Area::orderBy('name')->get(),
            'divisis' => Divisi::orderBy('name')->get(),
            'positions' => Position::orderBy('name')->get(),
            'roles' => Role::orderByDesc('level')->orderBy('name')->get(),
            'approvers' => User::whereIn('id', $this->visibleUsers($request->user())->whereNotNull('approval_id')->select('approval_id'))
                ->orderBy('nama_lengkap')->get(['id', 'nama_lengkap']),
        ]);
    }

    public function import(ImportUserRequest $request): RedirectResponse
    {
        $import = new UserImport;

        try {
            Excel::import($import, $request->file('file'));
        } catch (Throwable $e) {
            report($e);

            return to_route('user.index')->with('error', 'Gagal import user: '.$e->getMessage());
        }

        $errors = $import->errors();
        $message = "Import selesai. Berhasil: {$import->successCount()} user.";

        return to_route('user.index')
            ->with($errors === [] ? 'success' : 'error', $errors === [] ? $message : $message.' Gagal: '.count($errors).' baris.')
            ->with('importErrors', array_slice($errors, 0, 20));
    }

    public function template(): BinaryFileResponse
    {
        return Excel::download(new UserTemplate, 'user_template.xlsx');
    }

    public function export(): BinaryFileResponse
    {
        return Excel::download(new UserExport, 'user_export.xlsx');
    }

    public function create(Request $request): View
    {
        return view('setting.user.create', $this->formData($request->user(), new User));
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $user = User::create($this->userData($request));
            $this->saveAccess($request, $user);
        });

        return to_route('user.index')->with('success', 'Berhasil menambahkan user');
    }

    public function edit(Request $request, User $user): View|RedirectResponse
    {
        if ($user->trashed()) {
            return to_route('user.index')->with('error', 'Tidak bisa mengedit user yang diarsipkan, aktifkan terlebih dahulu');
        }

        $user->load(['roles', 'dataAccessAreas']);

        return view('setting.user.edit', $this->formData($request->user(), $user));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user) {
            $user->update($this->userData($request));
            $this->saveAccess($request, $user);
        });

        return to_route('user.index')->with('success', 'Berhasil mengubah user');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        // Gate::before meloloskan super admin sebelum UserPolicy::delete, jadi larangan arsip diri sendiri dicek di sini.
        abort_if($request->user()->is($user), 403, 'Anda tidak dapat mengarsipkan akun Anda sendiri.');

        $user->delete();

        return to_route('user.index')->with('success', 'Berhasil mengarsipkan user');
    }

    public function restore(User $user): RedirectResponse
    {
        $user->restore();

        return to_route('user.index', ['tab' => 'arsip'])->with('success', 'Berhasil mengaktifkan user');
    }

    /**
     * Opsi Approval Line dan saran Kepala Divisi untuk form user (dipanggil saat divisi diganti).
     */
    public function approvers(Request $request): JsonResponse
    {
        $record = $request->filled('user') ? User::find($request->integer('user')) : null;
        if ($record) {
            Gate::authorize('update', $record);
        }

        $divisiId = $request->filled('divisi_id') ? $request->integer('divisi_id') : null;
        $suggested = ApprovalLineService::suggest($divisiId, $record?->id);

        return response()->json([
            'groups' => ApprovalLineService::groupedOptions($request->user(), $record, $divisiId),
            'suggested' => $suggested ? ['id' => $suggested->id, 'nama_lengkap' => $suggested->nama_lengkap] : null,
        ]);
    }

    /**
     * Aksi massal DnD: Ubah Posisi Massal dan Ubah Approval Line Massal.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['position', 'approval'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'position_id' => ['exclude_unless:action,position', 'required', 'integer', Rule::exists('positions', 'id')->withoutTrashed()],
            'approval_id' => ['exclude_unless:action,approval', 'required', 'integer', Rule::exists('users', 'id')->withoutTrashed()],
        ], [
            'ids.required' => 'Pilih minimal satu user.',
        ]);

        $users = User::whereIn('id', $data['ids'])->get();
        $users->each(fn (User $user) => Gate::authorize('update', $user));

        if ($data['action'] === 'position') {
            User::whereIn('id', $users->pluck('id'))->update(['position_id' => $data['position_id']]);

            return back()->with('success', "Posisi {$users->count()} user berhasil diubah.");
        }

        $approvalId = (int) $data['approval_id'];
        $selectable = ApprovalLineService::selectableApproverIds($request->user(), false);
        abort_if($selectable !== null && ! in_array($approvalId, $selectable, true), 403, 'Approval line di luar cakupan Anda.');

        $skipped = 0;
        foreach ($users as $user) {
            if ($user->id === $approvalId || ApprovalScopeService::createsApprovalCycle($user->id, $approvalId)) {
                $skipped++;

                continue;
            }

            $user->update(['approval_id' => $approvalId]);
        }

        $updated = $users->count() - $skipped;
        $message = "Approval line {$updated} user berhasil diubah.";
        if ($skipped > 0) {
            $message .= " {$skipped} user dilewati karena akan membentuk siklus approval.";
        }

        return back()->with('success', $message);
    }

    /**
     * Daftar user sesuai cakupan actor. Super admin melihat semua; admin badan usaha tertentu melihat karyawan
     * di badan usahanya (tanpa akun ADMIN lain) dan timnya; selain itu hanya tim approval line-nya.
     *
     * @return Builder<User>
     */
    private function visibleUsers(User $actor): Builder
    {
        $query = User::withTrashed();

        if (DataAccessService::hasGlobalAccess($actor)) {
            return $query;
        }

        $teamIds = ApprovalScopeService::getManagedUserIdsOneLevelDown((int) $actor->id);
        $areaIds = DataAccessService::allowedAreaIds($actor) ?? [];

        return $query
            ->where(fn (Builder $scoped) => $scoped->whereIn('id', $teamIds)->orWhereIn('area_id', $areaIds))
            ->whereDoesntHave('roles', fn (Builder $roles) => $roles->where('name', User::ROLE_ADMIN));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(User $actor, User $user): array
    {
        $areas = Area::orderBy('name')->get();
        $canManageDataAccess = $actor->can('manageDataAccess', User::class);

        return [
            'title' => 'User',
            'active' => 'setting',
            'user' => $user,
            'areas' => DataAccessService::isAreaScopedAdmin($actor)
                ? $areas->filter(fn (Area $area) => DataAccessService::canAccessArea($actor, $area->id) || $area->id === $user->area_id)
                : $areas,
            'allAreas' => $areas,
            'divisis' => Divisi::orderBy('name')->get(),
            'positions' => Position::orderBy('name')->get(),
            'roles' => Role::orderByDesc('level')->orderBy('name')->get()
                ->reject(fn (Role $role) => $role->name === User::ROLE_ADMIN && ! $canManageDataAccess),
            'approverGroups' => ApprovalLineService::groupedOptions($actor, $user->exists ? $user : null, $user->divisi_id),
            'canManageDataAccess' => $canManageDataAccess,
            'canManageTrainer' => $actor->can('manageTrainer', User::class),
            'canChangeCredentials' => ! $user->exists || $actor->can('changeCredentials', $user),
            'canChangePhone' => $actor->can('changePhone', [User::class, $user->exists ? $user : null]),
            'approvalLocked' => ! $user->exists && ! DataAccessService::isAdmin($actor),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userData(StoreUserRequest $request): array
    {
        $data = $request->safe()->only([
            'employee_id', 'nama_lengkap', 'email', 'area_id', 'divisi_id', 'position_id', 'approval_id', 'username', 'no_hp',
        ]);

        if ($request->filled('password') && $request->canChangeCredentials()) {
            $data['password'] = $request->validated('password');
        }

        if ($request->managesDataAccess()) {
            $data['data_access'] = $request->validated('data_access');
        }

        return $data;
    }

    private function saveAccess(StoreUserRequest $request, User $user): void
    {
        $user->assignJabatan($request->selectedRole(), $request->managesDataAccess());

        if ($request->managesDataAccess()) {
            $user->dataAccessAreas()->sync(
                $request->validated('data_access') === User::DATA_ACCESS_SELECTED ? $request->validated('data_access_areas') : []
            );
            DataAccessService::clearMemo();
        }

        if ($request->has('trainer') && $request->user()->can('manageTrainer', User::class)) {
            $user->setTrainer($request->boolean('trainer'));
        }
    }
}
