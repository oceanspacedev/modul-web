@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header :title="'Atur Hak Akses: '.$role->name" eyebrow="Pengaturan / Jabatan & Hak Akses">
            <p class="page-subtitle">Tentukan fitur-fitur mana saja yang diizinkan untuk diakses oleh role <strong>{{ $role->name }}</strong>.</p>
            <x-slot:actions>
                <a href="{{ route('roles.index') }}" class="btn btn-default">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />

        @if($role->name === 'ADMIN')
            <div class="alert alert-info flash mb-4">
                <i class="fas fa-shield-alt"></i>
                <div class="flex-fill">
                    <strong>Jabatan Administrator Sistem</strong>
                    <p class="mb-0">ADMIN memiliki akses ke semua fitur. Cakupan data karyawannya diatur per user (Semua Badan Usaha atau Badan Usaha Tertentu).</p>
                </div>
            </div>
        @endif

        <form action="{{ route('roles.update', $role->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Informasi Jabatan</h3>
                </div>
                <div class="card-body">
                    <div class="form-group" style="max-width: 500px;">
                        <label for="name">
                            Nama Jabatan <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $role->name) }}" {{ $role->isBuiltIn() ? 'readonly' : '' }} required>
                        @if($role->isBuiltIn())
                            <small class="form-text text-muted">Nama jabatan bawaan DnD tidak dapat diubah.</small>
                        @else
                            <small class="form-text text-muted">Gunakan nama yang sama dengan jabatan di DnD agar sinkronisasi cocok.</small>
                        @endif
                    </div>
                    <div class="form-group" style="max-width: 500px;">
                        <label for="level">Level Grade</label>
                        <input type="number" min="0" name="level" id="level" class="form-control @error('level') is-invalid @enderror" value="{{ old('level', $role->level) }}">
                        <small class="form-text text-muted">Label grade seperti DnD (Staff=10, Team Leader=20, Coordinator=30, Manager=40, Chief=50, BOD=60, Admin=100).</small>
                    </div>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="requires_approval" name="requires_approval" value="1" @checked(old('requires_approval', $role->requires_approval))>
                        <label class="custom-control-label" for="requires_approval">Wajib memiliki approval line (atasan)</label>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="min-w-0">
                        <h3 class="card-title">Fitur yang Dapat Diakses</h3>
                        <p class="section-subtitle">Centang atau hilangkan centang untuk menyesuaikan akses role ini.</p>
                    </div>
                    @if($role->name !== 'ADMIN')
                    <div>
                        <button type="button" class="btn btn-default btn-sm mr-1" id="btnSelectAll">
                            <i class="fas fa-check-square"></i> Pilih Semua
                        </button>
                        <button type="button" class="btn btn-default btn-sm" id="btnUnselectAll">
                            <i class="far fa-square"></i> Hapus Semua
                        </button>
                    </div>
                    @endif
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($permissionGroups as $groupName => $group)
                            <div class="col-lg-6 mb-4">
                                <div class="border rounded p-3 h-100">
                                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                        <div class="d-flex align-items-center">
                                            <i class="{{ $group['icon'] }} text-muted mr-2"></i>
                                            <strong>{{ $groupName }}</strong>
                                        </div>
                                        @if($role->name !== 'ADMIN')
                                        <button type="button" class="btn btn-link btn-xs p-0 select-group-btn" data-group="{{ Str::slug($groupName) }}">
                                            Pilih Bagian Ini
                                        </button>
                                        @endif
                                    </div>
                                    <div class="pl-1">
                                        @foreach($group['permissions'] as $permName => $permDesc)
                                            @php
                                                $isChecked = in_array($permName, old('permissions', $rolePermissions));
                                            @endphp
                                            <div class="custom-control custom-checkbox my-2">
                                                <input type="checkbox" name="permissions[]" value="{{ $permName }}" 
                                                       class="custom-control-input perm-checkbox group-{{ Str::slug($groupName) }}" 
                                                       id="perm_{{ Str::slug($permName) }}"
                                                       {{ $isChecked || $role->name === 'ADMIN' ? 'checked' : '' }}
                                                       {{ $role->name === 'ADMIN' ? 'disabled' : '' }}>
                                                <label class="custom-control-label font-weight-normal" for="perm_{{ Str::slug($permName) }}">
                                                    {{ $permDesc }}
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
                <div class="card-footer d-flex justify-content-end gap-2">
                    <a href="{{ route('roles.index') }}" class="btn btn-default">Kembali</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Perubahan Hak Akses
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
    const checkboxes = document.querySelectorAll('.perm-checkbox:not(:disabled)');

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
            const groupBoxes = document.querySelectorAll('.group-' + grp + ':not(:disabled)');
            const allChecked = Array.from(groupBoxes).every(cb => cb.checked);
            groupBoxes.forEach(cb => cb.checked = !allChecked);
        });
    });
});
</script>
@endsection
