@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Monthly Report" :subtitle="App\Support\OdWeek::monthLabel($month)" eyebrow="OD Report">
            <x-slot:actions>
                @if (Route::has('od-report.export'))
                    <form action="{{ route('od-report.export') }}" method="POST" class="m-0">
                        @csrf
                        <input type="hidden" name="type" value="monthly">
                        <input type="hidden" name="bulan" value="{{ $month }}">
                        <input type="hidden" name="user" value="{{ $memberId }}">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-file-excel"></i> Ekspor Achievement</button>
                    </form>
                @endif
                <button type="button" class="btn btn-default" onclick="window.print()"><i class="fas fa-print"></i> Cetak</button>
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />

        <div class="card">
            <div class="card-header filter-toolbar">
                <form action="{{ route('od-report.monthly') }}" method="GET" class="d-flex flex-wrap align-items-center gap-2 m-0">
                    @include('od-report._period-filter')
                </form>
            </div>
        </div>

        <div class="row">
            @foreach ([
                ['Aktivitas', $stats['total'], $stats['done'].' selesai · '.$stats['progress'].' proses'],
                ['Penyelesaian', $stats['percent'].'%', $stats['done'].' dari '.$stats['total'].' selesai'],
                ['Dokumen', $documents->count(), ($documents->count() - $doneDocs - $cancelDocs).' proses · '.$cancelDocs.' batal'],
                ['Dokumen selesai', $doneDocs, 'release bulan ini'],
            ] as [$label, $value, $hint])
                <div class="col-6 col-xl-3 mb-3">
                    <div class="stat-tile h-100">
                        <div class="min-w-0">
                            <p class="stat-tile-label">{{ $label }}</p>
                            <p class="stat-tile-value">{{ $value }}</p>
                            <p class="stat-tile-hint">{{ $hint }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @forelse ($byJenis as $jenis => $list)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ $jenis }}</h3>
                    <span class="badge badge-secondary ml-2">{{ $list->count() }}</span>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover mb-0">
                        <tbody>
                            @foreach ($list as $document)
                                <tr>
                                    <td style="white-space: normal;">
                                        {{ $document->title }}
                                        <div class="small text-muted">{{ $document->badan_usaha }} · {{ $document->pic->nama_lengkap ?? '-' }} · {{ $document->submitted_at->format('d/m') }}</div>
                                    </td>
                                    <td class="text-right"><span class="badge {{ $document->status->badgeClass() }}">{{ $document->status->name }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="card">
                <x-empty-state icon="fa-file-alt" title="Belum ada dokumen bulan ini" />
            </div>
        @endforelse
    </div>
</section>
@endsection
