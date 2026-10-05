@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <a href="{{ route('roles.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar Role
                </a>
                <h1 class="m-0 font-weight-bold">
                    <i class="fas fa-plus-circle text-primary mr-2"></i>Tambah Role Baru
                </h1>
                <small class="text-muted">Buat peran baru dan pilih hak akses fitur yang dapat dibuka oleh role ini.</small>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        <form action="{{ route('roles.store') }}" method="POST">
            @csrf
            <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px;">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title font-weight-bold text-dark mb-0">Informasi Role</h5>
                </div>
                <div class="card-body p-4">
                    <div class="form-group mb-0" style="max-width: 500px;">
                        <label for="name" class="font-weight-bold text-dark">
                            Nama Role <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" 
                               value="{{ old('name') }}" placeholder="Contoh: Supervisor, Koordinator, Auditor..." required autofocus>
                        <small class="text-muted">Nama role yang mewakili peran pengguna dalam sistem.</small>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px;">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title font-weight-bold text-dark mb-0">
                            Pilih Fitur yang Dapat Diakses (Permissions)
                        </h5>
                        <small class="text-muted d-block mt-1">Centang fitur yang ingin diberikan kepada role ini.</small>
                    </div>
                    <div>
                        <button type="button" class="btn btn-outline-primary btn-sm font-weight-bold mr-1" id="btnSelectAll">
                            <i class="fas fa-check-square mr-1"></i> Pilih Semua
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnUnselectAll">
                            <i class="far fa-square mr-1"></i> Hapus Semua
                        </button>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row">
                        @foreach($permissionGroups as $groupName => $group)
                            <div class="col-lg-6 mb-4">
                                <div class="border rounded p-3 h-100 bg-white shadow-none">
                                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                        <div class="d-flex align-items-center">
                                            <i class="{{ $group['icon'] }} {{ $group['color'] }} mr-2 fa-lg"></i>
                                            <strong class="text-dark">{{ $groupName }}</strong>
                                        </div>
                                        <button type="button" class="btn btn-link btn-xs text-primary p-0 select-group-btn" data-group="{{ Str::slug($groupName) }}">
                                            Pilih Bagian Ini
                                        </button>
                                    </div>
                                    <div class="pl-1">
                                        @foreach($group['permissions'] as $permName => $permDesc)
                                            <div class="custom-control custom-checkbox my-2">
                                                <input type="checkbox" name="permissions[]" value="{{ $permName }}" 
                                                       class="custom-control-input perm-checkbox group-{{ Str::slug($groupName) }}" 
                                                       id="perm_{{ Str::slug($permName) }}"
                                                       {{ is_array(old('permissions')) && in_array($permName, old('permissions')) ? 'checked' : '' }}>
                                                <label class="custom-control-label font-weight-normal text-dark" for="perm_{{ Str::slug($permName) }}" style="cursor: pointer;">
                                                    <strong>{{ $permDesc }}</strong>
                                                    <span class="d-block text-muted text-xs"><code>{{ $permName }}</code></span>
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="card-footer bg-light py-3 border-top d-flex justify-content-between">
                    <a href="{{ route('roles.index') }}" class="btn btn-secondary px-4">Batal</a>
                    <button type="submit" class="btn btn-primary font-weight-bold px-4 shadow-sm">
                        <i class="fas fa-save mr-1"></i> Simpan Role Baru
                    </button>
                </div>
            </div>
        </form>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnSelectAll = document.getElementById('btnSelectAll');
    const btnUnselectAll = document.getElementById('btnUnselectAll');
    const checkboxes = document.querySelectorAll('.perm-checkbox');

    if (btnSelectAll) {
        btnSelectAll.addEventListener('click', function() {
            checkboxes.forEach(cb => cb.checked = true);
        });
    }

    if (btnUnselectAll) {
        btnUnselectAll.addEventListener('click', function() {
            checkboxes.forEach(cb => cb.checked = false);
        });
    }

    document.querySelectorAll('.select-group-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const grp = this.getAttribute('data-group');
            const groupBoxes = document.querySelectorAll('.group-' + grp);
            const allChecked = Array.from(groupBoxes).every(cb => cb.checked);
            groupBoxes.forEach(cb => cb.checked = !allChecked);
        });
    });
});
</script>
@endsection
