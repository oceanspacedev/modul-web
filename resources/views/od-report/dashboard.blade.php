@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Dashboard OD" :subtitle="$today->locale('id')->translatedFormat('l, d F Y')" eyebrow="Report">
            <x-slot:actions>
                <a href="{{ route('od-report.daily.index') }}" class="btn btn-primary"><i class="fas fa-edit"></i> Isi Daily</a>
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />

        @if ($attention)
            <div class="alert alert-warning">
                @foreach ($attention as [$label, $url])
                    <div><i class="fas fa-exclamation-circle mr-1"></i> <a href="{{ $url }}" class="alert-link">{{ $label }}</a></div>
                @endforeach
            </div>
        @endif

        <div class="row">
            @foreach ([
                ['Aktivitas minggu ini', $thisWeek, ($trend >= 0 ? '+' : '').$trend.'% dari minggu lalu', route('od-report.weekly')],
                ['Penyelesaian bulan ini', $month['percent'].'%', $month['done'].' dari '.$month['total'].' selesai', route('od-report.monthly')],
                ['Dokumen berjalan', $openDocs, $doneDocs.' sudah selesai', route('od-report.documents.index')],
                ['Lapor hari ini', $reportedToday.'/'.$teamSize, 'anggota sudah simpan laporan', route('od-report.daily.index')],
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
                    <div class="card-header"><h3 class="card-title">Aktivitas 30 hari terakhir</h3></div>
                    <div class="card-body">
                        <div class="dash-chart is-main"><canvas id="chartOdActivities"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Aktivitas terbaru</h3></div>
                    <div class="card-body p-0">
                        @forelse ($recentActivities as $activity)
                            <div class="px-3 py-2 border-bottom small">
                                <div class="d-flex justify-content-between gap-2">
                                    <span>{{ $activity->name }}</span>
                                    <span class="badge {{ $activity->badgeClass() }}">{{ $activity->result }}</span>
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
    </div>
</section>
@endsection

@section('footer')
<script src="{{ asset('template') }}/plugins/chart.js/Chart.bundle.min.js"></script>
<script>
    $(function () {
        const chart = @json($chart);
        Chart.defaults.global.defaultFontFamily = "'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif";
        Chart.defaults.global.defaultFontColor = '#71717a';
        new Chart(document.getElementById('chartOdActivities').getContext('2d'), {
            type: 'line',
            data: {
                labels: chart.map(d => d.label),
                datasets: [
                    { label: 'Done', data: chart.map(d => d.done), borderColor: '#16a34a', backgroundColor: 'rgba(22,163,74,.08)', pointRadius: 2, lineTension: .3 },
                    { label: 'Proses', data: chart.map(d => d.open), borderColor: '#e6007a', backgroundColor: 'rgba(230,0,122,.06)', pointRadius: 2, lineTension: .3 },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { position: 'bottom' },
                scales: {
                    xAxes: [{ gridLines: { display: false }, ticks: { maxTicksLimit: 10 } }],
                    yAxes: [{ gridLines: { color: '#f4f4f5' }, ticks: { beginAtZero: true, precision: 0 } }],
                },
            },
        });
    });
</script>
@endsection
