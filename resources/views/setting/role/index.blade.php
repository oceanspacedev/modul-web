@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <h1 class="m-0 font-weight-bold" style="font-size: 1.4rem;">
                    Manajemen Role & Hak Akses
                </h1>
                <small class="text-muted">Kelola peran pengguna dan tentukan fitur apa saja yang dapat diakses oleh setiap role.</small>
            </div>
            <div class="col-sm-6 text-sm-right mt-2 mt-sm-0">
                <a href="{{ route('roles.create') }}" class="btn btn-primary btn-sm font-weight-bold">
                    Tambah Role Baru
                </a>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        {{-- Flash Messages --}}
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        {{-- SUMMARY STATS --}}
        <div class="row mb-3">
            <div class="col-md-4 col-sm-6 col-12">
                <div class="info-box shadow-none border">
                    <div class="info-box-content">
                        <span class="info-box-text text-muted">Total Role Terdaftar</span>
                        <span class="info-box-number h4 font-weight-bold mb-0 text-dark">{{ $roles->count() }} Role</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-sm-6 col-12">
                <div class="info-box shadow-none border">
                    <div class="info-box-content">
                        <span class="info-box-text text-muted">Fitur / Permission</span>
                        <span class="info-box-number h4 font-weight-bold mb-0 text-dark">{{ $permissions->count() }} Hak Akses</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-sm-6 col-12">
                <div class="info-box shadow-none border">
                    <div class="info-box-content">
                        <span class="info-box-text text-muted">Pengguna Aktif</span>
                        <span class="info-box-number h4 font-weight-bold mb-0 text-dark">{{ $users->count() }} User</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- NAVIGATION TABS --}}
        <div class="card card-primary card-outline card-outline-tabs shadow-sm border-0">
            <div class="card-header p-0 border-bottom-0">
                <ul class="nav nav-tabs" id="roleTab" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active font-weight-bold" id="roles-tab" data-toggle="pill" href="#tab-roles" role="tab" aria-controls="tab-roles" aria-selected="true">
                            Daftar Role & Hak Akses Fitur ({{ $roles->count() }})
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link font-weight-bold" id="users-tab" data-toggle="pill" href="#tab-users" role="tab" aria-controls="tab-users" aria-selected="false">
                            Penetapan Role Pengguna ({{ $users->count() }})
                        </a>
                    </li>
                </ul>
            </div>

            <div class="card-body p-4">
                <div class="tab-content" id="roleTabContent">
                    {{-- TAB 1: DAFTAR ROLE --}}
                    <div class="tab-pane fade show active" id="tab-roles" role="tabpanel" aria-labelledby="roles-tab">
                        <div class="row">
                            @foreach($roles as $role)
                                <div class="col-lg-4 col-md-6 mb-4">
                                    <div class="card h-100 shadow-sm border" style="border-radius: 12px; overflow: hidden;">
                                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle d-flex align-items-center justify-content-center text-white mr-2 {{ $role->name === 'Admin' ? 'bg-danger' : ($role->name === 'Trainer' ? 'bg-success' : 'bg-primary') }}" style="width: 36px; height: 36px;">
                                                    <i class="fas {{ $role->name === 'Admin' ? 'fa-shield-alt' : ($role->name === 'Trainer' ? 'fa-chalkboard-teacher' : 'fa-user-tag') }}"></i>
                                                </div>
                                                <h5 class="font-weight-bold mb-0 text-dark">{{ $role->name }}</h5>
                                            </div>
                                            <span class="badge badge-light border font-weight-bold px-2 py-1">
                                                {{ $role->users->count() }} Pengguna
                                            </span>
                                        </div>
                                        <div class="card-body py-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="text-muted small font-weight-bold text-uppercase">Hak Akses Fitur:</span>
                                                <span class="badge badge-info px-2 py-1">
                                                    {{ $role->name === 'Admin' ? 'Akses Penuh (Semua)' : $role->permissions->count() . ' / ' . $permissions->count() . ' Fitur' }}
                                                </span>
                                            </div>
                                            
                                            <div class="p-2 bg-light rounded border mb-3" style="max-height: 170px; overflow-y: auto;">
                                                @if($role->name === 'Admin')
                                                    <div class="text-success small font-weight-bold py-2 text-center">
                                                        <i class="fas fa-check-double mr-1"></i> Administrator memiliki akses ke seluruh fitur sistem
                                                    </div>
                                                @elseif($role->permissions->isEmpty())
                                                    <div class="text-muted small py-2 text-center">
                                                        Belum ada hak akses yang dipilih.
                                                    </div>
                                                @else
                                                    <div class="d-flex flex-wrap" style="gap: 5px;">
                                                        @foreach($role->permissions as $perm)
                                                            <span class="badge badge-white border text-dark text-xs p-1" title="{{ $perm->name }}">
                                                                <i class="fas fa-check text-success mr-1"></i> {{ $perm->name }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center py-2">
                                            <a href="{{ route('roles.edit', $role->id) }}" class="btn btn-default btn-sm font-weight-bold flex-grow-1 mr-1">
                                                Atur Hak Akses
                                            </a>
                                            @if(!in_array($role->name, ['Admin', 'Staff', 'Trainer']))
                                                <form action="{{ route('roles.destroy', $role->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus role {{ $role->name }}?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Hapus Role">
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
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted small">Atur role untuk masing-masing pengguna agar mendapatkan izin fitur sesuai perannya.</span>
                            <input type="text" id="searchUserTable" class="form-control form-control-sm" placeholder="Cari nama / username..." style="width: 250px;">
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover border" id="userRoleTable" style="font-size: 0.92rem;">
                                <thead class="bg-light text-muted">
                                    <tr>
                                        <th style="width: 50px;" class="text-center">No</th>
                                        <th>Nama Lengkap</th>
                                        <th>Username / Kontak</th>
                                        <th>Divisi</th>
                                        <th>Role Spatie Saat Ini</th>
                                        <th style="width: 280px;" class="text-center">Ubah Role</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($users as $index => $u)
                                    <tr>
                                        <td class="text-center align-middle text-muted">{{ $index + 1 }}</td>
                                        <td class="align-middle">
                                            <strong class="text-dark d-block">{{ $u->full_name }}</strong>
                                            <span class="text-muted small">ID: {{ $u->id_karyawan ?? '-' }}</span>
                                        </td>
                                        <td class="align-middle text-muted">
                                            <span>{{ $u->username }}</span>
                                            @if($u->no_wa)
                                                <span class="d-block small text-success"><i class="fab fa-whatsapp mr-1"></i>{{ $u->no_wa }}</span>
                                            @endif
                                        </td>
                                        <td class="align-middle text-muted">
                                            {{ $u->divisi->name ?? '-' }}
                                        </td>
                                        <td class="align-middle">
                                            @forelse($u->roles as $r)
                                                <span class="badge border px-2 py-1 {{ $r->name === 'Admin' ? 'bg-light text-danger border-danger' : ($r->name === 'Trainer' ? 'bg-light text-success border-success' : 'bg-light text-primary border-primary') }}">
                                                    {{ $r->name }}
                                                </span>
                                            @empty
                                                <span class="badge badge-light border text-muted px-2 py-1">Belum Ada Role</span>
                                            @endforelse
                                        </td>
                                        <td class="align-middle text-center">
                                            <form action="{{ route('roles.assign') }}" method="POST" class="d-flex align-items-center justify-content-center" style="gap: 6px;">
                                                @csrf
                                                <input type="hidden" name="user_id" value="{{ $u->id }}">
                                                <select name="role" class="form-control form-control-sm" style="width: 140px;">
                                                    @foreach($roles as $r)
                                                        <option value="{{ $r->name }}" {{ $u->hasRole($r->name) ? 'selected' : '' }}>
                                                            {{ $r->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="btn btn-primary btn-sm font-weight-bold px-2">
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
