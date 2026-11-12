@extends('layout.main_template')

@section('content')
@php
    $totalPermissions = $permissions->count();
    $isAdminRole = fn ($role) => $role->name === 'ADMIN';
    $roleGrants = $roles->mapWithKeys(fn ($role) => [
        $role->id => $isAdminRole($role) ? $permissions->pluck('name')->flip() : $role->permissions->pluck('name')->flip(),
    ]);
@endphp

<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Jabatan & Hak Akses" subtitle="Kelola jabatan dan tentukan fitur apa saja yang dapat diakses oleh setiap jabatan." eyebrow="Pengaturan">
            <x-slot:actions>
                <a href="{{ route('roles.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Tambah Jabatan
                </a>
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />

        {{-- RINGKASAN --}}
        <div class="row">
            <div class="col-md-4 col-12 mb-3">
                <div class="stat-tile">
                    <div>
                        <p class="stat-tile-label">Jabatan</p>
                        <p class="stat-tile-value">{{ $roles->count() }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-12 mb-3">
                <div class="stat-tile">
                    <div>
                        <p class="stat-tile-label">Hak Akses Fitur</p>
                        <p class="stat-tile-value">{{ $totalPermissions }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-12 mb-3">
                <div class="stat-tile">
                    <div>
                        <p class="stat-tile-label">Pengguna</p>
                        <p class="stat-tile-value">{{ $totalUsers }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- TAB --}}
        <ul class="nav nav-tabs mb-3" id="roleTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="roles-tab" data-toggle="tab" href="#tab-roles" role="tab" aria-controls="tab-roles" aria-selected="true">
                    Daftar Jabatan & Hak Akses Fitur <span class="badge badge-secondary">{{ $roles->count() }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="users-tab" data-toggle="tab" href="#tab-users" role="tab" aria-controls="tab-users" aria-selected="false">
                    Penetapan Jabatan Pengguna <span class="badge badge-secondary">{{ $totalUsers }}</span>
                </a>
            </li>
        </ul>

        <div class="tab-content" id="roleTabContent">
            {{-- TAB 1: JABATAN & HAK AKSES --}}
            <div class="tab-pane fade show active" id="tab-roles" role="tabpanel" aria-labelledby="roles-tab">
                <div class="role-toolbar">
                    <p class="text-muted small mb-0">
                        {{ $roles->count() }} jabatan &middot; {{ $totalPermissions }} hak akses fitur. ADMIN selalu mendapat semua hak akses.
                    </p>
                    <div class="btn-group filter-tabs" role="group" aria-label="Tampilan">
                        <button type="button" class="btn active" data-role-view="table" aria-pressed="true">
                            <i class="fas fa-table mr-1"></i> Tabel
                        </button>
                        <button type="button" class="btn" data-role-view="cards" aria-pressed="false">
                            <i class="fas fa-th-large mr-1"></i> Kartu
                        </button>
                    </div>
                </div>

                {{-- TAMPILAN TABEL --}}
                <div data-role-panel="table">
                    <div class="card">
                        <div class="card-header">
                            <div class="min-w-0">
                                <h3 class="card-title">Daftar Jabatan</h3>
                                <p class="section-subtitle">Diurutkan dari level tertinggi.</p>
                            </div>
                        </div>
                        <div class="card-body table-responsive p-0">
                            <table class="table table-hover text-nowrap mb-0">
                                <thead>
                                    <tr>
                                        <th>Jabatan</th>
                                        <th class="text-center" style="width: 90px;">Level</th>
                                        <th>Persetujuan Atasan</th>
                                        <th class="text-center" style="width: 110px;">Pengguna</th>
                                        <th style="width: 220px;">Hak Akses</th>
                                        <th class="text-right" style="width: 170px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($roles as $role)
                                        @php($granted = $isAdminRole($role) ? $totalPermissions : $role->permissions->count())
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="role-avatar {{ $isAdminRole($role) ? 'is-admin' : '' }}">
                                                        <i class="fas {{ $isAdminRole($role) ? 'fa-shield-alt' : 'fa-user-tag' }}"></i>
                                                    </span>
                                                    <div>
                                                        <strong class="d-block">{{ $role->name }}</strong>
                                                        <span class="text-muted small">{{ $role->isBuiltIn() ? 'Bawaan' : 'Kustom' }}</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center">{{ $role->level ?? '-' }}</td>
                                            <td>
                                                @if ($role->requires_approval)
                                                    <span class="badge badge-warning">Wajib</span>
                                                @else
                                                    <span class="text-muted">Tidak</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <a href="{{ route('user.index', ['role_id' => $role->id]) }}" class="text-reset" title="Lihat pengguna dengan jabatan {{ $role->name }}">
                                                    {{ $role->users_count }}
                                                </a>
                                            </td>
                                            <td>
                                                <div class="perm-meter">
                                                    <div class="perm-meter-bar">
                                                        <span style="width: {{ $totalPermissions ? round($granted / $totalPermissions * 100) : 0 }}%;"></span>
                                                    </div>
                                                    <span class="perm-meter-label">
                                                        {{ $isAdminRole($role) ? 'Semua' : $granted.' / '.$totalPermissions }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="text-right">
                                                <div class="d-inline-flex align-items-center gap-1">
                                                    <a href="{{ route('roles.edit', $role->id) }}" class="btn btn-default btn-xs">Atur Hak Akses</a>
                                                    @unless ($role->isBuiltIn())
                                                        <form action="{{ route('roles.destroy', $role->id) }}" method="POST" class="d-inline m-0" onsubmit="return confirm(@js('Hapus jabatan '.$role->name.'?'))">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-default btn-xs text-danger" title="Hapus jabatan">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </button>
                                                        </form>
                                                    @endunless
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <div class="min-w-0">
                                <h3 class="card-title">Matriks Hak Akses</h3>
                                <p class="section-subtitle">Fitur yang dapat diakses setiap jabatan. Klik nama jabatan untuk mengubah hak aksesnya.</p>
                            </div>
                        </div>
                        <div class="card-body table-responsive p-0">
                            <table class="table perm-matrix mb-0">
                                <thead>
                                    <tr>
                                        <th class="perm-matrix-feature">Fitur</th>
                                        @foreach ($roles as $role)
                                            <th class="text-center">
                                                <a href="{{ route('roles.edit', $role->id) }}" class="perm-matrix-role">{{ $role->name }}</a>
                                                <span class="d-block text-muted small font-weight-normal">Level {{ $role->level ?? '-' }}</span>
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($permissionGroups as $groupName => $group)
                                        <tr class="perm-matrix-group">
                                            <td class="perm-matrix-feature">
                                                <i class="{{ $group['icon'] }} {{ $group['color'] }} mr-1"></i> {{ $groupName }}
                                            </td>
                                            <td colspan="{{ $roles->count() }}"></td>
                                        </tr>
                                        @foreach ($group['permissions'] as $permName => $label)
                                            <tr>
                                                <td class="perm-matrix-feature">
                                                    <span class="d-block">{{ $label }}</span>
                                                    @if ($label !== $permName)
                                                        <code class="perm-slug">{{ $permName }}</code>
                                                    @endif
                                                </td>
                                                @foreach ($roles as $role)
                                                    <td class="text-center align-middle">
                                                        @if (isset($roleGrants[$role->id][$permName]))
                                                            <i class="fas fa-check-circle perm-yes" title="{{ $role->name }}: boleh"></i>
                                                            <span class="sr-only">Ya</span>
                                                        @else
                                                            <span class="perm-no" title="{{ $role->name }}: tidak">&ndash;</span>
                                                            <span class="sr-only">Tidak</span>
                                                        @endif
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td class="perm-matrix-feature"><strong>Total</strong></td>
                                        @foreach ($roles as $role)
                                            <td class="text-center"><strong>{{ $roleGrants[$role->id]->count() }}</strong><span class="text-muted"> / {{ $totalPermissions }}</span></td>
                                        @endforeach
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- TAMPILAN KARTU --}}
                <div data-role-panel="cards" hidden>
                    <div class="row">
                        @foreach ($roles as $role)
                            @php($granted = $roleGrants[$role->id]->count())
                            <div class="col-xl-4 col-md-6 mb-4">
                                <div class="card role-card h-100 mb-0">
                                    <div class="card-header">
                                        <div class="d-flex align-items-center gap-2 min-w-0">
                                            <span class="role-avatar {{ $isAdminRole($role) ? 'is-admin' : '' }}">
                                                <i class="fas {{ $isAdminRole($role) ? 'fa-shield-alt' : 'fa-user-tag' }}"></i>
                                            </span>
                                            <div class="min-w-0">
                                                <h3 class="card-title">{{ $role->name }}</h3>
                                                <div class="text-muted small">
                                                    Level {{ $role->level ?? '-' }}
                                                    @if ($role->requires_approval)
                                                        &middot; Wajib atasan
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        <a href="{{ route('user.index', ['role_id' => $role->id]) }}" class="badge badge-secondary">
                                            {{ $role->users_count }} pengguna
                                        </a>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-baseline mb-1">
                                            <span class="eyebrow">Hak akses fitur</span>
                                            <span class="small font-weight-bold">
                                                {{ $isAdminRole($role) ? 'Semua' : $granted.' / '.$totalPermissions }}
                                            </span>
                                        </div>
                                        <div class="perm-meter-bar mb-3">
                                            <span style="width: {{ $totalPermissions ? round($granted / $totalPermissions * 100) : 0 }}%;"></span>
                                        </div>

                                        <ul class="role-module-list">
                                            @foreach ($permissionGroups as $groupName => $group)
                                                @php($moduleTotal = count($group['permissions']))
                                                @php($moduleGranted = collect(array_keys($group['permissions']))->filter(fn ($p) => isset($roleGrants[$role->id][$p]))->count())
                                                <li class="{{ $moduleGranted ? '' : 'is-off' }}"
                                                    title="{{ collect($group['permissions'])->filter(fn ($l, $p) => isset($roleGrants[$role->id][$p]))->implode(', ') ?: 'Tidak ada akses' }}">
                                                    <span class="role-module-name"><i class="{{ $group['icon'] }} fa-fw"></i> {{ $groupName }}</span>
                                                    <span class="role-module-count">{{ $moduleGranted }}/{{ $moduleTotal }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                    <div class="card-footer d-flex align-items-center gap-2">
                                        <a href="{{ route('roles.edit', $role->id) }}" class="btn btn-default btn-sm flex-grow-1">Atur Hak Akses</a>
                                        @unless ($role->isBuiltIn())
                                            <form action="{{ route('roles.destroy', $role->id) }}" method="POST" class="d-inline m-0" onsubmit="return confirm(@js('Hapus jabatan '.$role->name.'?'))">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-default btn-sm text-danger" title="Hapus jabatan">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        @endunless
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- TAB 2: PENETAPAN JABATAN PENGGUNA --}}
            <div class="tab-pane fade" id="tab-users" role="tabpanel" aria-labelledby="users-tab">
                <div class="card">
                    <div class="card-header filter-toolbar">
                        {{-- Cari & filter dijalankan server (halaman hanya memuat satu halaman user); #tab-users membuka tab ini lagi. --}}
                        <form action="{{ route('roles.index') }}#tab-users" method="GET" class="role-user-filters w-100 m-0" role="search" id="userRoleFilterForm">
                            <div class="filter-group">
                                <div class="filter-group-icon"><i class="fas fa-user-tag"></i></div>
                                <select id="filterUserRole" name="role" class="custom-select select2" aria-label="Jabatan" style="width: 100%;">
                                    <option value="">Semua jabatan</option>
                                    @foreach ($roles as $r)
                                        <option value="{{ $r->name }}" @selected(request('role') === $r->name)>{{ $r->name }}</option>
                                    @endforeach
                                    <option value="{{ \App\Http\Controllers\RoleController::FILTER_TRAINER }}" @selected(request('role') === \App\Http\Controllers\RoleController::FILTER_TRAINER)>Trainer</option>
                                    <option value="{{ \App\Http\Controllers\RoleController::FILTER_NO_ROLE }}" @selected(request('role') === \App\Http\Controllers\RoleController::FILTER_NO_ROLE)>Belum ada jabatan</option>
                                </select>
                            </div>
                            <x-search-field placeholder="Cari nama, username, ID, No. HP, divisi..."
                                :clear-url="route('roles.index', request()->except(['search', 'page'])).'#tab-users'" />
                        </form>
                    </div>

                    <div class="px-3 py-2 border-bottom bg-light text-muted small">
                        Hak akses mengikuti jabatan. Centang <strong>Trainer</strong> untuk memberi akses tambahan kelola training, video, dan dokumen.
                    </div>

                    <div class="card-body table-responsive p-0">
                        <table class="table table-hover mb-0" id="userRoleTable">
                            <thead>
                                <tr>
                                    <th style="width: 50px;" class="text-center">No</th>
                                    <th>Nama Lengkap</th>
                                    <th>Username / Kontak</th>
                                    <th>Divisi</th>
                                    <th>Jabatan Saat Ini</th>
                                    <th class="text-right">Ubah Jabatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($users as $u)
                                    <tr>
                                        <td class="text-center text-muted">{{ $users->firstItem() + $loop->index }}</td>
                                        <td>
                                            <strong class="d-block">{{ $u->nama_lengkap }}</strong>
                                            <span class="text-muted small">ID: {{ $u->employee_id ?? '-' }}</span>
                                        </td>
                                        <td>
                                            <span class="d-block">{{ $u->username }}</span>
                                            @if ($u->no_hp)
                                                <span class="text-muted small"><i class="fab fa-whatsapp mr-1"></i>{{ $u->no_hp }}</span>
                                            @endif
                                        </td>
                                        <td class="text-muted">{{ $u->divisi->name ?? '-' }}</td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                @forelse ($u->roles as $r)
                                                    <span class="badge {{ $r->name === 'ADMIN' ? 'badge-danger' : 'badge-primary' }}">{{ $r->name }}</span>
                                                @empty
                                                    <span class="badge badge-secondary">Belum ada jabatan</span>
                                                @endforelse
                                                @if ($u->isTrainer())
                                                    <span class="badge badge-success">TRAINER</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="text-right">
                                            <form action="{{ route('roles.assign') }}" method="POST" class="role-assign-form m-0">
                                                @csrf
                                                <input type="hidden" name="user_id" value="{{ $u->id }}">
                                                <select name="role" class="form-control form-control-sm select2" style="width: 140px;" aria-label="Jabatan {{ $u->nama_lengkap }}">
                                                    @foreach ($roles as $r)
                                                        <option value="{{ $r->name }}" @selected($u->hasRole($r->name))>{{ $r->name }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="hidden" name="trainer" value="0">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input" id="trainer{{ $u->id }}" name="trainer" value="1" @checked($u->isTrainer())>
                                                    <label class="custom-control-label small" for="trainer{{ $u->id }}">Trainer</label>
                                                </div>
                                                <button type="submit" class="btn btn-default btn-sm">Simpan</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6">
                                            <x-empty-state icon="fa-search" title="Pengguna tidak ditemukan" text="Coba ubah kata kunci atau filter jabatan." />
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span class="text-muted small">
                            Menampilkan {{ $users->firstItem() ?? 0 }}-{{ $users->lastItem() ?? 0 }} dari {{ $users->total() }} pengguna
                        </span>
                        {{ $users->links('pagination::bootstrap-4') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('footer')
<script>
    $(function () {
        function store(key, value) {
            try { localStorage.setItem(key, value); } catch (e) {}
        }
        function read(key) {
            try { return localStorage.getItem(key); } catch (e) { return null; }
        }

        // Tampilan tabel / kartu, diingat per browser.
        function showView(view) {
            $('[data-role-view]').each(function () {
                const on = this.dataset.roleView === view;
                $(this).toggleClass('active', on).attr('aria-pressed', on);
            });
            $('[data-role-panel]').each(function () {
                this.hidden = this.dataset.rolePanel !== view;
            });
        }
        $('[data-role-view]').on('click', function () {
            showView(this.dataset.roleView);
            store('roles.view', this.dataset.roleView);
        });
        showView(read('roles.view') === 'cards' ? 'cards' : 'table');

        // Kembali ke tab terakhir (mis. setelah Simpan di Penetapan Jabatan).
        $('#roleTab a[data-toggle="tab"]').on('shown.bs.tab', function () {
            sessionStorage.setItem('roles.tab', this.getAttribute('href'));
        });
        let lastTab = location.hash || null;
        try { lastTab = lastTab || sessionStorage.getItem('roles.tab'); } catch (e) {}
        if (lastTab && $('#roleTab a[href="' + lastTab + '"]').length) {
            $('#roleTab a[href="' + lastTab + '"]').tab('show');
        }

        // Filter jabatan langsung dijalankan server; kotak pencarian mencari sendiri saat berhenti mengetik (layout.footer).
        $('#filterUserRole').on('change', function () {
            document.getElementById('userRoleFilterForm').submit();
        });
    });
</script>
@endsection
