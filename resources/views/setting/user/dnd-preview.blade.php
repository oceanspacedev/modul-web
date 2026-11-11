@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <x-page-header title="Preview Sinkronisasi DnD" subtitle="Periksa perubahan sebelum data karyawan dari DnD diterapkan." eyebrow="Pengaturan / User">
                <x-slot:actions>
                    <a href="{{ route('user.index') }}" class="btn btn-default">Batal</a>
                </x-slot:actions>
            </x-page-header>

            <div class="row mb-2">
                @foreach ([
                    ['Data di DnD', $total, 'fa-database'],
                    ['User baru', count($plan['create']), 'fa-user-plus'],
                    ['Diperbarui', count($plan['update']), 'fa-user-edit'],
                    ['Tidak berubah', $plan['unchanged'], 'fa-user-check'],
                ] as [$label, $count, $icon])
                    <div class="col-lg-3 col-6 mb-3">
                        <div class="stat-tile">
                            <div>
                                <p class="stat-tile-label">{{ $label }}</p>
                                <p class="stat-tile-value">{{ $count }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="alert alert-light">
                User baru mendapat password acak dan masuk lewat OTP WhatsApp (API DnD tidak mengirim password).
                {{ $plan['local_only'] }} user di modul ini tidak ada di DnD dan tidak akan diubah.
                Jabatan ADMIN dari DnD dibatasi ke badan usahanya sendiri.
            </div>

            @if ($plan['masters'] !== [])
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Master data baru</h3></div>
                    <div class="card-body">
                        @foreach ($plan['masters'] as $type => $names)
                            <p class="mb-2"><strong>{{ $type }}:</strong> {{ implode(', ', $names) }}</p>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($plan['create'] !== [])
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">User baru</h3>
                        <span class="badge badge-secondary">{{ count($plan['create']) }}</span>
                    </div>
                    <div class="card-body table-responsive p-0" style="max-height: 360px;">
                        <table class="table table-sm table-head-fixed text-nowrap mb-0">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>ID Karyawan</th>
                                    <th>Username</th>
                                    <th>Area</th>
                                    <th>Divisi</th>
                                    <th>Posisi</th>
                                    <th>Jabatan</th>
                                    <th>Approval Line</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($plan['create'] as $row)
                                    <tr>
                                        <td>{{ $row['nama_lengkap'] }}</td>
                                        <td>{{ $row['employee_id'] ?? '-' }}</td>
                                        <td>{{ $row['username'] }}</td>
                                        <td>{{ $row['area'] ?? '-' }}</td>
                                        <td>{{ $row['divisi'] ?? '-' }}</td>
                                        <td>{{ $row['position'] ?? '-' }}</td>
                                        <td>{{ $row['role'] ?? '-' }}</td>
                                        <td>{{ $row['supervisor']['nama_lengkap'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if ($plan['update'] !== [])
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">User diperbarui</h3>
                        <span class="badge badge-secondary">{{ count($plan['update']) }}</span>
                    </div>
                    <div class="card-body table-responsive p-0" style="max-height: 360px;">
                        <table class="table table-sm table-head-fixed mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 30%;">User</th>
                                    <th>Perubahan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($plan['update'] as $row)
                                    <tr>
                                        <td>{{ $row['label'] }}</td>
                                        <td>
                                            @foreach ($row['changes'] as $field => $change)
                                                <div class="small"><strong>{{ $field }}:</strong> <span class="text-muted">{{ $change['from'] }}</span> &rarr; {{ $change['to'] }}</div>
                                            @endforeach
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if ($plan['skipped'] !== [])
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Dilewati</h3>
                        <span class="badge badge-secondary">{{ count($plan['skipped']) }}</span>
                    </div>
                    <div class="card-body small">
                        <ul class="mb-0 pl-3">
                            @foreach ($plan['skipped'] as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <form action="{{ route('user.dnd.process') }}" method="POST" class="d-flex justify-content-end gap-2 mb-4" onsubmit="this.querySelector('button').disabled = true">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <a href="{{ route('user.index') }}" class="btn btn-default">Batal</a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sync-alt"></i> Proses Sinkronisasi
                </button>
            </form>
        </div>
    </section>
@endsection
