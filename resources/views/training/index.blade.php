@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="m-0 text-dark font-weight-bold" style="font-size: 1.5rem;">
                Jadwal Pelatihan
            </h1>
            <a href="/training/create" class="btn btn-sm btn-success shadow-sm">
                <i class="fas fa-plus mr-1"></i> Tambah Pelatihan
            </a>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                {{ session('success') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                {{ session('error') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        <!-- MAIN CARD -->
        <div class="card mb-4">
            <div class="card-header py-3">
                <div class="row align-items-center">
                    <div class="col-md-7 mb-2 mb-md-0">
                        <div class="btn-group btn-group-sm">
                            <a href="/training" class="btn {{ !request('status') ? 'btn-secondary active' : 'btn-default' }}">Semua ({{ $stats['total'] }})</a>
                            <a href="/training?status=scheduled" class="btn {{ request('status') === 'scheduled' ? 'btn-secondary active' : 'btn-default' }}">Dijadwalkan ({{ $stats['scheduled'] }})</a>
                            <a href="/training?status=ongoing" class="btn {{ request('status') === 'ongoing' ? 'btn-secondary active' : 'btn-default' }}">Berlangsung ({{ $stats['ongoing'] }})</a>
                            <a href="/training?status=completed" class="btn {{ request('status') === 'completed' ? 'btn-secondary active' : 'btn-default' }}">Selesai ({{ $stats['completed'] }})</a>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <form action="/training" method="GET" class="d-flex justify-content-md-end">
                            @if(request('status'))
                                <input type="hidden" name="status" value="{{ request('status') }}">
                            @endif
                            <div class="input-group input-group-sm" style="max-width: 280px;">
                                <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Cari topik atau pemateri...">
                                <div class="input-group-append">
                                    <button type="submit" class="btn btn-default">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="card-body p-0 table-responsive">
                <table class="table table-hover mb-0" style="font-size: 0.92rem;">
                    <thead class="bg-light text-muted">
                        <tr>
                            <th style="width: 60px; padding: 14px 16px;" class="text-center">No</th>
                            <th style="padding: 14px 16px;">Topik Pelatihan</th>
                            <th style="padding: 14px 16px;">Pemateri</th>
                            <th style="padding: 14px 16px;">Jadwal</th>
                            <th style="padding: 14px 16px;">Tautan Sesi</th>
                            <th style="padding: 14px 16px;" class="text-center">Peserta</th>
                            <th style="padding: 14px 16px;" class="text-center">Status</th>
                            <th style="width: 140px; padding: 14px 16px;" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($trainings as $index => $t)
                        <tr>
                            <td class="text-center align-middle text-muted" style="padding: 16px;">
                                {{ $trainings->firstItem() + $index }}
                            </td>
                            <td class="align-middle" style="padding: 16px;">
                                <a href="/training/{{ $t->id }}" class="font-weight-bold text-dark d-block mb-1">
                                    {{ $t->title }}
                                </a>
                                @if($t->description)
                                    <span class="text-muted small d-block text-truncate" style="max-width: 320px;">
                                        {{ $t->description }}
                                    </span>
                                @endif
                            </td>
                            <td class="align-middle" style="padding: 16px;">
                                <span class="font-weight-600 text-dark d-block">{{ $t->trainer->full_name ?? '-' }}</span>
                                <span class="text-muted small">{{ $t->trainer->divisi->name ?? 'Divisi Umum' }}</span>
                            </td>
                            <td class="align-middle" style="padding: 16px;">
                                <span class="d-block">{{ \Carbon\Carbon::parse($t->training_date)->format('d M Y') }}</span>
                                <span class="text-muted small">{{ substr($t->start_time, 0, 5) }} - {{ substr($t->end_time, 0, 5) }} WIB</span>
                            </td>
                            <td class="align-middle" style="padding: 16px;">
                                @if($t->zoom_link)
                                    <a href="{{ $t->zoom_link }}" target="_blank" class="btn btn-xs btn-outline-primary px-2 py-1">
                                        Buka Zoom
                                    </a>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                            <td class="text-center align-middle" style="padding: 16px;">
                                <span class="badge bg-light border px-2 py-1 text-dark">
                                    {{ $t->participants->count() }} orang
                                </span>
                            </td>
                            <td class="text-center align-middle" style="padding: 16px;">
                                @if($t->status === 'scheduled')
                                    <span class="badge badge-warning text-white px-2 py-1">Dijadwalkan</span>
                                @elseif($t->status === 'ongoing')
                                    <span class="badge badge-success px-2 py-1">Berlangsung</span>
                                @elseif($t->status === 'completed')
                                    <span class="badge badge-secondary px-2 py-1">Selesai</span>
                                @else
                                    <span class="badge badge-danger px-2 py-1">Dibatalkan</span>
                                @endif
                            </td>
                            <td class="text-center align-middle" style="padding: 16px;">
                                <div class="d-flex justify-content-center align-items-center" style="gap: 6px;">
                                    <a href="/training/{{ $t->id }}" class="badge bg-info p-2" title="Detail & Nilai">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="/training/{{ $t->id }}/questions" class="badge bg-primary p-2" title="Kelola Kuis">
                                        <i class="fas fa-question"></i>
                                    </a>
                                    <a href="/training/{{ $t->id }}/edit" class="badge bg-warning p-2" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="/training/delete/{{ $t->id }}" onclick="return confirm('Hapus jadwal pelatihan ini?')" class="badge bg-danger p-2 border-0" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <p class="mb-2">Belum ada data jadwal pelatihan yang tersedia.</p>
                                <a href="/training/create" class="btn btn-sm btn-primary">
                                    Tambah Pelatihan Baru
                                </a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($trainings->hasPages())
                <div class="card-footer bg-white border-top py-3 d-flex justify-content-end">
                    {{ $trainings->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </div>
</section>
@endsection
