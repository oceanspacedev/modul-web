{{-- Bagian OD Report di Dashboard (dulu halaman Dashboard OD). Data dari OdReportService::dashboard(). --}}
@if ($od['attention'])
    <div class="alert alert-warning">
        @foreach ($od['attention'] as [$label, $url])
            <div><i class="fas fa-exclamation-circle mr-1"></i> <a href="{{ $url }}" class="alert-link">{{ $label }}</a></div>
        @endforeach
    </div>
@endif

<div class="d-flex align-items-center justify-content-between mb-2">
    <h2 class="h6 mb-0 text-muted">OD Report</h2>
    <a href="{{ route('od-report.daily.index') }}" class="btn btn-primary btn-sm"><i class="fas fa-edit"></i> Isi Daily</a>
</div>

<div class="row">
    @foreach ([
        ['Aktivitas minggu ini', $od['thisWeek'], ($od['trend'] >= 0 ? '+' : '').$od['trend'].'% dari minggu lalu', route('od-report.weekly', ['bulan' => $od['bulan']])],
        ['Penyelesaian bulan ini', $od['month']['percent'].'%', $od['month']['done'].' dari '.$od['month']['total'].' selesai · '.App\Support\OdWeek::monthLabel($od['bulan']), route('od-report.monthly', ['bulan' => $od['bulan']])],
        ['Dokumen berjalan', $od['openDocs'], $od['doneDocs'].' sudah selesai', route('od-report.documents.index')],
        ['Lapor hari ini', $od['reportedToday'].'/'.$od['teamSize'], 'anggota sudah simpan laporan', route('od-report.daily.index')],
    ] as [$label, $value, $hint, $url])
        <div class="col-6 col-xl-3 mb-3">
            <a href="{{ $url }}" class="stat-tile h-100">
                <div class="min-w-0">
                    <p class="stat-tile-label">{{ $label }}</p>
                    <p class="stat-tile-value">{{ $value }}</p>
                    <p class="stat-tile-hint">{{ $hint }}</p>
                </div>
            </a>
        </div>
    @endforeach
</div>

<div class="row">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Aktivitas OD 30 hari terakhir</h3></div>
            <div class="card-body">
                <div class="dash-chart is-main"><canvas id="chartOdActivities"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Aktivitas OD terbaru</h3></div>
            <div class="card-body p-0">
                @forelse ($od['recentActivities'] as $activity)
                    <div class="dash-row px-3 py-2 small">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <span class="min-w-0 text-break">{{ $activity->name }}</span>
                            <span class="badge {{ $activity->badgeClass() }} flex-shrink-0">{{ $activity->result }}</span>
                        </div>
                        <span class="text-muted">{{ $activity->date->format('d/m') }} · {{ $activity->user->nama_lengkap }}</span>
                    </div>
                @empty
                    <x-empty-state icon="fa-tasks" title="Belum ada aktivitas" text="Isi Daily Report lalu simpan." />
                @endforelse
            </div>
        </div>
    </div>
</div>
