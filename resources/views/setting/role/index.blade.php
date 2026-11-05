@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header :title="new Illuminate\Support\HtmlString('Manajemen Jabatan & Hak Akses')" subtitle="Kelola peran pengguna dan tentukan fitur apa saja yang dapat diakses oleh setiap role." eyebrow="Pengaturan">
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

        {{-- SUMMARY STATS --}}
        <div class="row mb-4">
            <div class="col-md-4 col-sm-6 col-12 mb-3 mb-md-0">
                <div class="stat-tile">
                    <span class="stat-tile-icon"><i class="fas fa-user-tag"></i></span>
                    <div>
                        <p class="stat-tile-label">Total Jabatan Terdaftar</p>
                        <p class="stat-tile-value">{{ $roles->count() }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-sm-6 col-12 mb-3 mb-md-0">
                <div class="stat-tile">
                    <span class="stat-tile-icon"><i class="fas fa-key"></i></span>
                    <div>
                        <p class="stat-tile-label">Fitur / Hak Akses</p>
                        <p class="stat-tile-value">{{ $permissions->count() }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-sm-6 col-12">
                <div class="stat-tile">
                    <span class="stat-tile-icon"><i class="fas fa-users"></i></span>
                    <div>
                        <p class="stat-tile-label">Pengguna Aktif</p>
                        <p class="stat-tile-value">{{ $users->count() }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- NAVIGATION TABS --}}
        <ul class="nav nav-tabs mb-3" id="roleTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="roles-tab" data-toggle="pill" href="#tab-roles" role="tab" aria-controls="tab-roles" aria-selected="true">
                    Daftar Jabatan & Hak Akses Fitur <span class="badge badge-secondary">{{ $roles->count() }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="users-tab" data-toggle="pill" href="#tab-users" role="tab" aria-controls="tab-users" aria-selected="false">
                    Penetapan Jabatan Pengguna <span class="badge badge-secondary">{{ $users->count() }}</span>
                </a>
            </li>
        </ul>

        <div class="tab-content" id="roleTabContent">
            {{-- TAB 1: DAFTAR ROLE --}}
            <div class="tab-pane fade show active" id="tab-roles" role="tabpanel" aria-labelledby="roles-tab">
                <div class="row">
                    @foreach($roles as $role)
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="card h-100 mb-0">
                                <div class="card-header">
                                    <div class="d-flex align-items-center gap-2 min-w-0">
                                        <span class="stat-tile-icon">
                                            <i class="fas {{ $role->name === 'ADMIN' ? 'fa-shield-alt' : 'fa-user-tag' }}"></i>
                                        </span>
                                        <div class="min-w-0">
                                            <h3 class="card-title">{{ $role->name }}</h3>
                                            <div class="text-muted text-xs">
                                                Level {{ $role->level ?? '-' }}
                                                @if($role->requires_approval)
                                                    &middot; Wajib atasan
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <span class="badge badge-secondary">
                                        {{ $role->users_count }} Pengguna
                                    </span>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="eyebrow">Hak akses fitur</span>
                                        <span class="badge badge-primary">
                                            {{ $role->name === 'ADMIN' ? 'Akses Penuh (Semua)' : $role->permissions->count() . ' / ' . $permissions->count() . ' Fitur' }}
                                        </span>
                                    </div>

                                    <div class="p-2 bg-light rounded border" style="max-height: 170px; overflow-y: auto;">
                                        @if($role->name === 'ADMIN')
                                            <div class="text-muted text-sm py-2 text-center">
                                                <i class="fas fa-check-double mr-1"></i> Administrator memiliki akses ke seluruh fitur sistem
                                            </div>
                                        @elseif($role->permissions->isEmpty())
                                            <div class="text-muted text-sm py-2 text-center">
                                                Belum ada hak akses yang dipilih.
                                            </div>
                                        @else
                                            <div class="d-flex flex-wrap gap-1">
                                                @foreach($role->permissions as $perm)
                                                    <span class="badge badge-secondary" title="{{ $perm->name }}">
                                                        {{ $perm->name }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="card-footer d-flex align-items-center gap-2">
                                    <a href="{{ route('roles.edit', $role->id) }}" class="btn btn-default btn-sm flex-grow-1">
                                        Atur Hak Akses
                                    </a>
                                    @if(! $role->isBuiltIn())
                                        <form action="{{ route('roles.destroy', $role->id) }}" method="POST" class="d-inline m-0" onsubmit="return confirm(@js('Hapus jabatan '.$role->name.'?'))">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-default btn-sm text-danger" title="Hapus Jabatan">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- TAB 2: PENETAPAN ROLE PENGGUNA --}}
            <div class="tab-pane fade" id="tab-users" role="tabpanel" aria-labelledby="users-tab">
                <div class="card">
                    <div class="card-header">
                        <div class="min-w-0">
                            <h3 class="card-title">Penetapan Jabatan</h3>
                            <p class="section-subtitle">Atur jabatan masing-masing pengguna (hak akses mengikuti jabatan). Centang Trainer untuk memberi hak akses tambahan kelola training, video, dan dokumen.</p>
                        </div>
                        <div class="card-tools">
                            <input type="text" id="searchUserTable" class="form-control form-control-sm" placeholder="Cari nama / username..." style="width: 250px;">
                        </div>
                    </div>

                    <div class="card-body table-responsive p-0">
                        <table class="table table-hover" id="userRoleTable">
                            <thead>
                                <tr>
                                    <th style="width: 50px;" class="text-center">No</th>
                                    <th>Nama Lengkap</th>
                                    <th>Username / Kontak</th>
                                    <th>Divisi</th>
                                    <th>Jabatan Saat Ini</th>
                                    <th style="width: 360px;" class="text-center">Ubah Jabatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($users as $index => $u)
                                <tr>
                                    <td class="text-center align-middle text-muted">{{ $index + 1 }}</td>
                                    <td class="align-middle">
                                        <strong class="d-block">{{ $u->nama_lengkap }}</strong>
                                        <span class="text-muted small">ID: {{ $u->employee_id ?? '-' }}</span>
                                    </td>
                                    <td class="align-middle text-muted">
                                        <span>{{ $u->username }}</span>
                                        @if($u->no_hp)
                                            <span class="d-block small"><i class="fab fa-whatsapp mr-1"></i>{{ $u->no_hp }}</span>
                                        @endif
                                    </td>
                                    <td class="align-middle text-muted">
                                        {{ $u->divisi->name ?? '-' }}
                                    </td>
                                    <td class="align-middle">
                                        @forelse($u->roles as $r)
                                            <span class="badge {{ $r->name === 'ADMIN' ? 'badge-danger' : 'badge-primary' }}">
                                                {{ $r->name }}
                                            </span>
                                        @empty
                                            <span class="badge badge-secondary">Belum Ada Jabatan</span>
                                        @endforelse
                                        @if($u->isTrainer())
                                            <span class="badge badge-success">TRAINER</span>
                                        @endif
                                    </td>
                                    <td class="align-middle text-center">
                                        <form action="{{ route('roles.assign') }}" method="POST" class="d-flex align-items-center justify-content-center gap-2 m-0">
                                            @csrf
                                            <input type="hidden" name="user_id" value="{{ $u->id }}">
                                            <select name="role" class="form-control form-control-sm" style="width: 140px;">
                                                @foreach($roles as $r)
                                                    <option value="{{ $r->name }}" {{ $u->hasRole($r->name) ? 'selected' : '' }}>
                                                        {{ $r->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <input type="hidden" name="trainer" value="0">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input" id="trainer{{ $u->id }}" name="trainer" value="1" @checked($u->isTrainer())>
                                                <label class="custom-control-label small" for="trainer{{ $u->id }}">Trainer</label>
                                            </div>
                                            <button type="submit" class="btn btn-default btn-sm">
                                                Simpan
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchUserTable');
    const table = document.getElementById('userRoleTable');
    if (searchInput && table) {
        searchInput.addEventListener('keyup', function() {
            const val = this.value.toLowerCase();
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(function(row) {
                const text = row.innerText.toLowerCase();
                row.style.display = text.indexOf(val) > -1 ? '' : 'none';
            });
        });
    }
});
</script>
@endsection
