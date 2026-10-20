@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <section class="content">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="font-weight-bold text-dark m-0" style="font-size: 1.3rem;">Preview Sinkronisasi DnD</h3>
                    <a href="{{ route('user.index') }}" class="btn btn-sm btn-default">Batal</a>
                </div>

                <div class="row">
                    @foreach ([
                        ['Data di DnD', $total, 'bg-info'],
                        ['User baru', count($plan['create']), 'bg-success'],
                        ['Diperbarui', count($plan['update']), 'bg-warning'],
                        ['Tidak berubah', $plan['unchanged'], 'bg-secondary'],
                    ] as [$label, $count, $color])
                        <div class="col-lg-3 col-6">
                            <div class="small-box {{ $color }}">
                                <div class="inner">
                                    <h3>{{ $count }}</h3>
                                    <p>{{ $label }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="alert alert-light border small">
                    User baru mendapat password acak dan masuk lewat OTP WhatsApp (API DnD tidak mengirim password).
                    {{ $plan['local_only'] }} user di modul ini tidak ada di DnD dan tidak akan diubah.
                    Jabatan ADMIN dari DnD dibatasi ke badan usahanya sendiri.
                </div>

                @if ($plan['masters'] !== [])
                    <div class="card border shadow-sm">
                        <div class="card-header bg-white"><h3 class="card-title font-weight-bold">Master data baru</h3></div>
                        <div class="card-body">
                            @foreach ($plan['masters'] as $type => $names)
                                <p class="mb-2"><strong>{{ $type }}:</strong> {{ implode(', ', $names) }}</p>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($plan['create'] !== [])
                    <div class="card border shadow-sm">
                        <div class="card-header bg-white"><h3 class="card-title font-weight-bold">User baru ({{ count($plan['create']) }})</h3></div>
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
                    <div class="card border shadow-sm">
                        <div class="card-header bg-white"><h3 class="card-title font-weight-bold">User diperbarui ({{ count($plan['update']) }})</h3></div>
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
                    <div class="card border shadow-sm">
                        <div class="card-header bg-white"><h3 class="card-title font-weight-bold">Dilewati ({{ count($plan['skipped']) }})</h3></div>
                        <div class="card-body small">
                            <ul class="mb-0 pl-3">
                                @foreach ($plan['skipped'] as $line)
                                    <li>{{ $line }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                <form action="{{ route('user.dnd.process') }}" method="POST" class="mb-4" onsubmit="this.querySelector('button').disabled = true">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <button type="submit" class="btn btn-success font-weight-bold px-4">
                        <i class="fas fa-sync-alt mr-1"></i> Proses Sinkronisasi
                    </button>
                </form>
            </div>
        </section>
    </section>
@endsection
