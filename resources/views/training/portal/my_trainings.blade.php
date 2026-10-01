@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark font-weight-bold" style="font-size: 1.5rem;">
                    Pelatihan Saya
                </h1>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="card mb-4">
            <div class="card-header py-3">
                <h3 class="card-title font-weight-bold">
                    Daftar Sesi Pelatihan yang Anda Ikuti
                </h3>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover mb-0" style="font-size: 0.92rem;">
                    <thead class="bg-light text-muted">
                        <tr>
                            <th class="text-center" style="width: 60px; padding: 14px 16px;">No</th>
                            <th style="padding: 14px 16px;">Topik Pelatihan</th>
                            <th style="padding: 14px 16px;">Pemateri</th>
                            <th style="padding: 14px 16px;">Jadwal Pelaksanaan</th>
                            <th class="text-center" style="padding: 14px 16px;">Kehadiran</th>
                            <th class="text-center" style="padding: 14px 16px;">Nilai Kuis</th>
                            <th class="text-center" style="width: 140px; padding: 14px 16px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($participations as $index => $part)
                        @php
                            $t = $part->training;
                            $quiz = $part->quizResult;
                        @endphp
                        <tr>
                            <td class="text-center align-middle text-muted" style="padding: 16px;">
                                {{ $participations->firstItem() + $index }}
                            </td>
                            <td class="align-middle" style="padding: 16px;">
                                <strong class="d-block text-dark">{{ $t->title }}</strong>
                                @if($t->description)
                                    <span class="text-muted small d-block text-truncate" style="max-width: 280px;">
                                        {{ $t->description }}
                                    </span>
                                @endif
                            </td>
                            <td class="align-middle text-muted" style="padding: 16px;">
                                {{ $t->trainer->full_name ?? '-' }}
                            </td>
                            <td class="align-middle" style="padding: 16px;">
                                <span class="d-block">{{ \Carbon\Carbon::parse($t->training_date)->format('d M Y') }}</span>
                                <span class="text-muted small">{{ substr($t->start_time, 0, 5) }} - {{ substr($t->end_time, 0, 5) }} WIB</span>
                            </td>
                            <td class="text-center align-middle" style="padding: 16px;">
                                @if($part->attendance_status === 'hadir')
                                    <span class="badge badge-success px-2 py-1">Hadir</span>
                                @elseif($part->attendance_status === 'tidak_hadir')
                                    <span class="badge badge-danger px-2 py-1">Tidak Hadir</span>
                                @else
                                    <span class="badge badge-warning text-white px-2 py-1">Belum Konfirmasi</span>
                                @endif
                            </td>
                            <td class="text-center align-middle" style="padding: 16px;">
                                @if($quiz)
                                    <strong class="text-primary font-weight-bold" style="font-size: 1.05rem;">{{ $quiz->score }}</strong>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                            <td class="text-center align-middle" style="padding: 16px;">
                                <a href="/training/portal/{{ $part->token }}" target="_blank" class="btn btn-default btn-xs px-2 py-1">
                                    Buka Sesi
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <p class="mb-0">Anda belum memiliki jadwal pelatihan yang ditugaskan.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($participations->hasPages())
                <div class="card-footer bg-white border-top py-3 d-flex justify-content-end">
                    {{ $participations->links() }}
                </div>
            @endif
        </div>
    </div>
</section>
@endsection
