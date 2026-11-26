@extends('layout.main_template')

@section('content')
@php $fmtScore = fn ($score) => rtrim(rtrim(number_format((float) $score, 1, ',', '.'), '0'), ','); @endphp
<div class="content-header">
    <div class="container-fluid">
        <x-page-header title="Dashboard" subtitle="Ringkasan sesuai hak akses Anda.">
            <x-slot:actions>
                <span class="text-muted small">
                    <i class="far fa-calendar-alt mr-1"></i> {{ \Carbon\Carbon::now()->translatedFormat('l, d F Y') }}
                </span>
            </x-slot:actions>
        </x-page-header>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <x-flash />

        @if ($od)
            @include('dashboard.partials.od-report')
        @endif

        @if (! $od && ! $data)
            <x-empty-state icon="fa-th-large" title="Belum ada ringkasan" text="Hak akses Anda belum mencakup ringkasan di Dashboard." />
        @endif

        @if ($data)
        @if ($od)
            <h2 class="h6 mb-2 mt-2 text-muted">Pelatihan & Konten</h2>
        @endif

        {{-- Ringkasan angka --}}
        <div class="row">
            @if ($show['users'])
            <div class="col-xl col-md-4 col-6 mb-3">
                <a href="/user" class="stat-tile h-100">
                    <div class="min-w-0">
                        <p class="stat-tile-label">Total Pengguna</p>
                        <p class="stat-tile-value">{{ $data['totalUser'] }}</p>
                        <p class="stat-tile-hint">Kelola user</p>
                    </div>
                </a>
            </div>
            @endif

            @if ($show['trainings'])
            <div class="col-xl col-md-4 col-6 mb-3">
                <a href="/training" class="stat-tile h-100">
                    <div class="min-w-0">
                        <p class="stat-tile-label">Jadwal Pelatihan</p>
                        <p class="stat-tile-value">{{ $data['totalTraining'] }}</p>
                        <p class="stat-tile-hint">{{ $data['ongoingTraining'] }} berlangsung</p>
                    </div>
                </a>
            </div>
            @endif

            @if ($show['videos'])
            <div class="col-xl col-md-4 col-6 mb-3">
                <a href="/video" class="stat-tile h-100">
                    <div class="min-w-0">
                        <p class="stat-tile-label">Video Materi</p>
                        <p class="stat-tile-value">{{ $data['totalVideo'] }}</p>
                        <p class="stat-tile-hint">Buka galeri</p>
                    </div>
                </a>
            </div>
            @endif

            @if ($show['trainings'])
            <div class="col-xl col-md-4 col-6 mb-3">
                <a href="/training" class="stat-tile h-100">
                    <div class="min-w-0">
                        <p class="stat-tile-label">Peserta Pelatihan</p>
                        <p class="stat-tile-value">{{ $data['totalParticipant'] }}</p>
                        <p class="stat-tile-hint">{{ $data['uniqueParticipant'] }} orang berbeda</p>
                    </div>
                </a>
            </div>
            @endif

            @if ($show['documents'])
            <div class="col-xl col-md-4 col-6 mb-3">
                <a href="/document" class="stat-tile h-100">
                    <div class="min-w-0">
                        <p class="stat-tile-label">Dokumen Modul</p>
                        <p class="stat-tile-value">{{ $data['totalDocument'] }}</p>
                        <p class="stat-tile-hint">Lihat dokumen</p>
                    </div>
                </a>
            </div>
            @endif

            @if ($show['trainings'])
            <div class="col-xl col-md-4 col-6 mb-3">
                <a href="/training" class="stat-tile h-100">
                    <div class="min-w-0">
                        <p class="stat-tile-label">Hasil Kuis Pelatihan</p>
                        <p class="stat-tile-value">{{ $data['totalQuizResult'] }}</p>
                        <p class="stat-tile-hint">{{ $data['totalQuizResult'] > 0 ? 'Rata-rata nilai '.$fmtScore($data['quizAvgScore']) : 'Belum ada yang mengerjakan' }}</p>
                    </div>
                </a>
            </div>
            @endif
        </div>

        {{-- Grafik & analitik --}}
        <div class="row dash-grid">
            @if ($show['trainings'])
            <div class="col-12">
                {{-- 1. Grafik Peserta Pelatihan --}}
                <div class="card dash-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            Peserta per Sesi Pelatihan
                            <span class="dash-count">{{ $data['totalTraining'] }} sesi</span>
                        </h3>
                        <div class="d-flex align-items-center gap-2">
                            <div class="nav btn-group filter-tabs" role="tablist">
                                <a class="btn active" data-toggle="pill" href="#tab-pelatihan-chart" role="tab">Grafik</a>
                                <a class="btn" data-toggle="pill" href="#tab-pelatihan-table" role="tab">Tabel</a>
                            </div>
                            <a href="/training" class="btn btn-default btn-sm">Kelola</a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="tab-content">
                            {{-- Tab 1: Grafik Batang --}}
                            <div class="tab-pane fade show active" id="tab-pelatihan-chart" role="tabpanel">
                                @if(count($data['chartTrainingLabels']) === 0)
                                    <x-empty-state icon="fa-chart-bar" title="Belum ada data pelatihan" text="Grafik muncul setelah ada jadwal pelatihan dengan peserta." />
                                @else
                                    <div class="card-body">
                                        <div class="dash-chart is-main">
                                            <canvas id="chartPelatihan"></canvas>
                                        </div>
                                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3 small text-muted">
                                            <span>Jumlah peserta terdaftar pada {{ count($data['chartTrainingLabels']) }} pelatihan terbaru</span>
                                            <span class="text-dark">{{ $data['chartTrainingParticipants']->sum() }} peserta</span>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- Tab 2: Tabel Ringkasan --}}
                            <div class="tab-pane fade" id="tab-pelatihan-table" role="tabpanel">
                                @if($data['recentTrainings']->isEmpty())
                                    <x-empty-state icon="fa-calendar-times" title="Belum ada jadwal pelatihan" text="Jadwal pelatihan yang dibuat akan tampil di sini." />
                                @else
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Judul Pelatihan</th>
                                                    <th>Pemateri</th>
                                                    <th>Tanggal</th>
                                                    <th class="text-center">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($data['recentTrainings'] as $t)
                                                    <tr>
                                                        <td>
                                                            <a href="{{ url('/training/' . $t->id) }}" class="font-weight-bold text-dark">
                                                                {{ Str::limit($t->title, 28) }}
                                                            </a>
                                                            <div class="small text-muted">
                                                                {{ $t->participants_count }} Peserta &bull; {{ $t->questions_count }} Soal
                                                            </div>
                                                        </td>
                                                        <td class="text-muted">
                                                            {{ $t->trainer ? $t->trainer->nama_lengkap : '-' }}
                                                        </td>
                                                        <td class="text-muted">
                                                            {{ $t->training_date ? $t->training_date->format('d M Y') : '-' }}
                                                        </td>
                                                        <td class="text-center">
                                                            <x-training-status-badge :status="$t->status" />
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            @endif

            @if ($show['videos'])
            <div class="col-xl col-lg-6">
                {{-- 2. Grafik Popularitas Video Materi --}}
                <div class="card dash-card is-compact">
                    <div class="card-header">
                        <h3 class="card-title">Penonton Video Materi</h3>
                        <div class="d-flex align-items-center gap-2">
                            <div class="nav btn-group filter-tabs" role="tablist">
                                <a class="btn active" data-toggle="pill" href="#tab-video-chart" role="tab">Grafik</a>
                                <a class="btn" data-toggle="pill" href="#tab-video-list" role="tab">Daftar</a>
                            </div>
                            <a href="/video" class="btn btn-default btn-sm">Galeri</a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="tab-content">
                            {{-- Tab 1: Grafik Horizontal Bar Penonton Video --}}
                            <div class="tab-pane fade show active" id="tab-video-chart" role="tabpanel">
                                @if(count($data['chartVideoLabels']) === 0)
                                    <x-empty-state icon="fa-film" title="Belum ada video materi" text="Video yang diunggah akan tampil di grafik ini." />
                                @else
                                    <div class="card-body">
                                        <div class="dash-chart">
                                            <canvas id="chartVideoViews"></canvas>
                                        </div>
                                        <p class="small text-muted mt-3 mb-0">Peringkat video materi berdasarkan jumlah tayangan</p>
                                    </div>
                                @endif
                            </div>

                            {{-- Tab 2: Daftar Video --}}
                            <div class="tab-pane fade" id="tab-video-list" role="tabpanel">
                                @if($data['recentVideos']->isEmpty())
                                    <x-empty-state icon="fa-film" title="Belum ada video materi" text="Video yang diunggah akan tampil di sini." />
                                @else
                                    <div>
                                        @foreach($data['recentVideos'] as $vid)
                                            <div class="dash-row d-flex align-items-center px-3 py-2">
                                                <div class="dash-thumb position-relative mr-3 flex-shrink-0">
                                                    @if($vid->thumbnail_url)
                                                        <img src="{{ $vid->thumbnail_url }}" alt="{{ $vid->title }}">
                                                    @else
                                                        <div class="w-100 h-100 d-flex align-items-center justify-content-center text-muted small">
                                                            <i class="fas fa-play"></i>
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="flex-grow-1 min-w-0 pr-2">
                                                    <a href="{{ route('video.show', $vid->id) }}" class="d-block font-weight-bold text-truncate text-dark small" title="{{ $vid->title }}">
                                                        {{ $vid->title }}
                                                    </a>
                                                    <div class="small text-muted">
                                                        <span class="mr-2"><i class="far fa-eye mr-1"></i>{{ $vid->views_count }} tayangan</span>
                                                        <span><i class="far fa-clock mr-1"></i>{{ $vid->created_at->format('d M') }}</span>
                                                    </div>
                                                </div>
                                                <a href="{{ route('video.show', $vid->id) }}" class="btn btn-default btn-xs flex-shrink-0" title="Tonton Video">
                                                    <i class="fas fa-play"></i>
                                                </a>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @if ($show['trainings'])
            <div class="col-xl col-lg-6">
                {{-- 3. Grafik Distribusi Nilai & Evaluasi Kuis --}}
                <div class="card dash-card is-compact">
                    <div class="card-header">
                        <h3 class="card-title">
                            Sebaran Nilai Kuis Pelatihan
                            <span class="dash-count">{{ $data['totalQuizResult'] }} hasil</span>
                        </h3>
                        <div class="d-flex align-items-center gap-2">
                            <div class="nav btn-group filter-tabs" role="tablist">
                                <a class="btn active" data-toggle="pill" href="#tab-kuis-chart" role="tab">Sebaran</a>
                                <a class="btn" data-toggle="pill" href="#tab-kuis-table" role="tab">Riwayat</a>
                            </div>
                            <a href="/training" class="btn btn-default btn-sm">Hasil</a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="tab-content">
                            {{-- Tab 1: Grafik Distribusi Skor --}}
                            <div class="tab-pane fade show active" id="tab-kuis-chart" role="tabpanel">
                                @if($data['totalQuizResult'] === 0)
                                    <x-empty-state icon="fa-clipboard-list" title="Belum ada hasil kuis" text="Grafik muncul setelah peserta mengerjakan kuis pelatihan." />
                                @else
                                    <div class="card-body">
                                        @php
                                            $scoreBands = [
                                                'sangat_baik' => ['Sangat Baik', '85–100'],
                                                'baik' => ['Baik', '70–84'],
                                                'cukup' => ['Cukup', '55–69'],
                                                'kurang' => ['Kurang', '< 55'],
                                            ];
                                            $scoreBandMax = max(1, max($data['scoreBuckets']));
                                        @endphp
                                        <div class="dash-bars">
                                            @foreach($scoreBands as $bandKey => [$bandLabel, $bandRange])
                                                <div>
                                                    <div class="dash-bar-head">
                                                        <span>{{ $bandLabel }} <span class="dash-bar-range">{{ $bandRange }}</span></span>
                                                        <span class="dash-bar-value">{{ $data['scoreBuckets'][$bandKey] }}</span>
                                                    </div>
                                                    <div class="dash-bar-track">
                                                        <span class="dash-bar-fill {{ $bandKey === 'kurang' ? 'is-danger' : '' }}" style="width: {{ round($data['scoreBuckets'][$bandKey] / $scoreBandMax * 100) }}%;"></span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                        {{-- KPI Skor --}}
                                        <div class="row text-center mt-3 pt-3 border-top">
                                            <div class="col-4">
                                                <p class="stat-tile-label">Rata-rata</p>
                                                <span class="dash-kpi-value">{{ $fmtScore($data['quizAvgScore']) }}</span>
                                            </div>
                                            <div class="col-4">
                                                <p class="stat-tile-label">Tertinggi</p>
                                                <span class="dash-kpi-value">{{ $fmtScore($data['quizMaxScore']) }}</span>
                                            </div>
                                            <div class="col-4">
                                                <p class="stat-tile-label">Terendah</p>
                                                <span class="dash-kpi-value">{{ $fmtScore($data['quizMinScore']) }}</span>
                                            </div>
                                        </div>
                                        <p class="small text-muted mt-3 mb-0">Nilai posttest tiap peserta (pretest bila pelatihannya tanpa posttest), skala 0–100.</p>
                                    </div>
                                @endif
                            </div>

                            {{-- Tab 2: Tabel Riwayat Pengerjaan --}}
                            <div class="tab-pane fade" id="tab-kuis-table" role="tabpanel">
                                @if($data['recentQuizResults']->isEmpty())
                                    <x-empty-state icon="fa-clipboard-list" title="Belum ada hasil kuis" text="Riwayat pengerjaan kuis akan tampil di sini." />
                                @else
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Peserta</th>
                                                    <th>Pelatihan</th>
                                                    <th class="text-center">Nilai</th>
                                                    <th class="text-center">Waktu</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($data['recentQuizResults'] as $res)
                                                    <tr>
                                                        <td>
                                                            <span class="font-weight-bold">
                                                                {{ $res->participant && $res->participant->user ? $res->participant->user->nama_lengkap : 'Peserta' }}
                                                            </span>
                                                        </td>
                                                        <td class="text-muted">
                                                            {{ $res->training ? Str::limit($res->training->title, 24) : '-' }}
                                                        </td>
                                                        <td class="text-center">
                                                            @php $score = round($res->score ?? 0); @endphp
                                                            <span class="badge {{ $score >= 70 ? 'badge-success' : 'badge-danger' }}">{{ $score }}</span>
                                                        </td>
                                                        <td class="text-center small text-muted">
                                                            {{ $res->created_at ? $res->created_at->diffForHumans() : '-' }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @if ($show['masterData'])
            <div class="col-xl col-lg-6">
                {{-- 4. Grafik Komposisi Modul & Master Data --}}
                <div class="card dash-card is-compact">
                    <div class="card-header">
                        <h3 class="card-title">Jumlah Konten</h3>
                        <div class="nav btn-group filter-tabs" role="tablist">
                            <a class="btn active" data-toggle="pill" href="#tab-komposisi-chart" role="tab">Donat</a>
                            <a class="btn" data-toggle="pill" href="#tab-komposisi-detail" role="tab">Master Data</a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="tab-content">
                            {{-- Tab 1: Grafik Donat Komposisi --}}
                            <div class="tab-pane fade show active" id="tab-komposisi-chart" role="tabpanel">
                                <div class="card-body">
                                    <div class="dash-chart">
                                        <canvas id="chartContentComposition"></canvas>
                                    </div>
                                    <div class="d-flex flex-wrap justify-content-center gap-3 mt-3 pt-3 border-top small text-muted">
                                        <span class="d-inline-flex align-items-center"><span class="dash-legend-dot is-1"></span>Video: {{ $data['totalVideo'] }}</span>
                                        <span class="d-inline-flex align-items-center"><span class="dash-legend-dot is-2"></span>Dokumen: {{ $data['totalDocument'] }}</span>
                                        <span class="d-inline-flex align-items-center"><span class="dash-legend-dot is-3"></span>Pelatihan: {{ $data['totalTraining'] }}</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Tab 2: Detail Master Data Organisasi --}}
                            <div class="tab-pane fade" id="tab-komposisi-detail" role="tabpanel">
                                <div class="dash-row d-flex justify-content-between align-items-center px-3 py-3">
                                    <div class="d-flex align-items-center min-w-0">
                                        <div class="min-w-0">
                                            <p class="mb-0 font-weight-bold small text-dark">Divisi Perusahaan</p>
                                            <p class="mb-0 small text-muted">Struktur divisi induk</p>
                                        </div>
                                    </div>
                                    <a href="/divisi" class="btn btn-default btn-sm flex-shrink-0">{{ $data['totalDivisi'] }} Divisi</a>
                                </div>

                                <div class="dash-row d-flex justify-content-between align-items-center px-3 py-3">
                                    <div class="d-flex align-items-center min-w-0">
                                        <div class="min-w-0">
                                            <p class="mb-0 font-weight-bold small text-dark">Posisi</p>
                                            <p class="mb-0 small text-muted">Posisi kerja karyawan</p>
                                        </div>
                                    </div>
                                    <a href="/position" class="btn btn-default btn-sm flex-shrink-0">{{ $data['totalPosition'] }} Posisi</a>
                                </div>

                                <div class="dash-row d-flex justify-content-between align-items-center px-3 py-3">
                                    <div class="d-flex align-items-center min-w-0">
                                        <div class="min-w-0">
                                            <p class="mb-0 font-weight-bold small text-dark">Tipe Dokumen</p>
                                            <p class="mb-0 small text-muted">Kategori dokumen sistem</p>
                                        </div>
                                    </div>
                                    <a href="/dokumentype" class="btn btn-default btn-sm flex-shrink-0">{{ $data['totalDokumenType'] }} Tipe</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
        @endif
    </div>
</section>
@endsection

@section('footer')
<!-- Chart.js Plugin -->
<script src="{{ asset('template') }}/plugins/chart.js/Chart.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // Warna grafik dibaca dari token CSS (resources/css/app.css) saat digambar, supaya ikut tema terang/gelap.
    function chartPalette() {
        var styles = getComputedStyle(document.documentElement);
        var token = function (name, fallback) {
            return styles.getPropertyValue(name).trim() || fallback;
        };
        var dark = (window.ModulTheme ? window.ModulTheme.get() : document.documentElement.getAttribute('data-theme')) === 'dark';

        return {
            text: token('--gray-500', '#71717a'),
            grid: token('--gray-100', '#f4f4f5'),
            zeroLine: token('--gray-200', '#e4e4e7'),
            surface: token('--surface', '#ffffff'),
            brand: token('--primary-600', '#00d639'),
            brandStrong: token('--primary-700', '#00852e'),
            done: '#16a34a', // hijau "Done": terbaca di kedua tema, tidak ada token dengan nilai ini
            neutral: token('--gray-400', '#a1a1aa'),
            // --gray-300 nyaris hilang di kartu gelap (1,6:1); di tema gelap pakai abu-abu yang masih terlihat.
            neutralSoft: dark ? '#66666f' : token('--gray-300', '#d4d4d8'),
            dark: dark,
            // Tooltip = chip berwarna kebalikan halaman (gelap di tema terang, terang di tema gelap).
            tooltipBg: token('--inverse-surface', '#18181b'),
            tooltipTitle: dark ? token('--on-inverse', '#18181b') : token('--white', '#ffffff'),
            tooltipBody: token('--gray-300', '#d4d4d8'),
        };
    }

    // "#rrggbb" / "#rgb" → "rgba(r,g,b,a)" untuk isian transparan di bawah garis.
    function withAlpha(color, alpha) {
        var hex = String(color).replace('#', '');
        if (hex.length === 3) hex = hex.replace(/(.)/g, '$1$1');
        if (!/^[0-9a-f]{6}$/i.test(hex)) return color;
        var n = parseInt(hex, 16);
        return 'rgba(' + (n >> 16) + ',' + ((n >> 8) & 255) + ',' + (n & 255) + ',' + alpha + ')';
    }

    function paintTooltip(tooltips, c) {
        tooltips.backgroundColor = c.tooltipBg;
        tooltips.titleFontColor = c.tooltipTitle;
        tooltips.bodyFontColor = c.tooltipBody;
    }

    function paintValueAxis(axis, c) {
        axis.gridLines.color = c.grid;
        axis.gridLines.zeroLineColor = c.zeroLine;
        axis.ticks.fontColor = c.text;
    }

    // Setiap grafik didaftarkan bersama fungsi pewarnanya; dipakai saat dibuat dan saat tema berganti.
    var themedCharts = [];
    function createChart(canvas, config, paint) {
        paint(config.data, config.options, chartPalette());
        var chart = new Chart(canvas.getContext('2d'), config);
        themedCharts.push({ chart: chart, paint: paint });
        return chart;
    }

    // Global Chart.js styling
    Chart.defaults.global.defaultFontFamily = "'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif";
    Chart.defaults.global.defaultFontColor = chartPalette().text;

    document.addEventListener('themechange', function () {
        var c = chartPalette();
        Chart.defaults.global.defaultFontColor = c.text;
        themedCharts.forEach(function (entry) {
            entry.paint(entry.chart.data, entry.chart.options, c);
            entry.chart.update();
        });
    });

    @if ($od)
    // -------------------------------------------------------------
    // OD Report: aktivitas 30 hari terakhir (Done vs Proses)
    // -------------------------------------------------------------
    var odChart = @json($od['chart']);
    createChart(document.getElementById('chartOdActivities'), {
        type: 'line',
        data: {
            labels: odChart.map(function (d) { return d.label; }),
            datasets: [
                { label: 'Done', data: odChart.map(function (d) { return d.done; }), pointRadius: 2, lineTension: .3 },
                { label: 'Proses', data: odChart.map(function (d) { return d.open; }), pointRadius: 2, lineTension: .3 },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: { position: 'bottom', labels: {} },
            tooltips: {},
            scales: {
                xAxes: [{ gridLines: { display: false }, ticks: { maxTicksLimit: 10 } }],
                yAxes: [{ gridLines: {}, ticks: { beginAtZero: true, precision: 0 } }],
            },
        },
    }, function (data, options, c) {
        data.datasets[0].borderColor = c.done;
        data.datasets[0].backgroundColor = withAlpha(c.done, .08);
        data.datasets[1].borderColor = c.brandStrong;
        data.datasets[1].backgroundColor = withAlpha(c.brandStrong, .06);
        options.legend.labels.fontColor = c.text;
        options.scales.xAxes[0].ticks.fontColor = c.text;
        options.scales.yAxes[0].gridLines.color = c.grid;
        options.scales.yAxes[0].ticks.fontColor = c.text;
        // Garis nol dan tooltip bawaan Chart.js berwarna hitam: di tema gelap ikuti palet seperti grafik lain.
        if (c.dark) {
            options.scales.yAxes[0].gridLines.zeroLineColor = c.zeroLine;
            paintTooltip(options.tooltips, c);
        } else {
            options.scales.yAxes[0].gridLines.zeroLineColor = 'rgba(0,0,0,0.25)';
            options.tooltips.backgroundColor = 'rgba(0,0,0,0.8)';
            options.tooltips.titleFontColor = '#fff';
            options.tooltips.bodyFontColor = '#fff';
        }
    });
    @endif

    @if ($data)
    @if ($show['trainings'])
    // -------------------------------------------------------------
    // 1. Chart Peserta Pelatihan (Bar Chart - primary green)
    // -------------------------------------------------------------
    var ctxPelatihan = document.getElementById('chartPelatihan');
    if (ctxPelatihan) {
        var labelsPelatihan = {!! json_encode($data['chartTrainingLabels']) !!};
        var dataPelatihan = {!! json_encode($data['chartTrainingParticipants']) !!};

        window.chartPelatihan = createChart(ctxPelatihan, {
            type: 'bar',
            data: {
                labels: labelsPelatihan,
                datasets: [{
                    label: 'Jumlah Peserta',
                    data: dataPelatihan,
                    borderWidth: 0,
                    maxBarThickness: 40,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { display: false },
                tooltips: {
                    cornerRadius: 8,
                    xPadding: 12,
                    yPadding: 10,
                    callbacks: {
                        label: function(tooltipItem) {
                            return ' Peserta: ' + tooltipItem.yLabel + ' orang';
                        }
                    }
                },
                scales: {
                    xAxes: [{
                        gridLines: { display: false, drawBorder: false },
                        ticks: { fontSize: 11 }
                    }],
                    yAxes: [{
                        gridLines: { drawBorder: false },
                        ticks: {
                            beginAtZero: true,
                            precision: 0,
                            fontSize: 11
                        }
                    }]
                }
            }
        }, function (data, options, c) {
            data.datasets[0].backgroundColor = c.brand;
            data.datasets[0].hoverBackgroundColor = c.brandStrong;
            paintTooltip(options.tooltips, c);
            options.scales.xAxes[0].ticks.fontColor = c.text;
            paintValueAxis(options.scales.yAxes[0], c);
        });
    }

    @endif

    @if ($show['videos'])
    // -------------------------------------------------------------
    // 3. Chart Popularitas Video (Horizontal Bar - primary green)
    // -------------------------------------------------------------
    var ctxVideo = document.getElementById('chartVideoViews');
    if (ctxVideo) {
        var labelsVideo = {!! json_encode($data['chartVideoLabels']) !!};
        var dataVideo = {!! json_encode($data['chartVideoViews']) !!};

        window.chartVideo = createChart(ctxVideo, {
            type: 'horizontalBar',
            data: {
                labels: labelsVideo,
                datasets: [{
                    label: 'Total Views',
                    data: dataVideo,
                    borderWidth: 0,
                    maxBarThickness: 24,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { display: false },
                tooltips: {
                    cornerRadius: 8,
                    xPadding: 12,
                    yPadding: 10,
                    callbacks: {
                        label: function(tooltipItem) {
                            return ' ' + tooltipItem.xLabel + ' kali ditonton';
                        }
                    }
                },
                scales: {
                    xAxes: [{
                        gridLines: { drawBorder: false },
                        ticks: {
                            beginAtZero: true,
                            precision: 0,
                            fontSize: 11
                        }
                    }],
                    yAxes: [{
                        gridLines: { display: false, drawBorder: false },
                        ticks: { fontSize: 10 }
                    }]
                }
            }
        }, function (data, options, c) {
            data.datasets[0].backgroundColor = c.brand;
            data.datasets[0].hoverBackgroundColor = c.brandStrong;
            paintTooltip(options.tooltips, c);
            paintValueAxis(options.scales.xAxes[0], c);
            options.scales.yAxes[0].ticks.fontColor = c.text;
        });
    }

    @endif

    @if ($show['masterData'])
    // -------------------------------------------------------------
    // 4. Chart Komposisi Konten Sistem (Doughnut - primary green + zinc grays)
    // -------------------------------------------------------------
    var ctxContent = document.getElementById('chartContentComposition');
    if (ctxContent) {
        var labelsContent = {!! json_encode($data['chartContentLabels']) !!};
        var dataContent = {!! json_encode($data['chartContentValues']) !!};

        window.chartContent = createChart(ctxContent, {
            type: 'doughnut',
            data: {
                labels: labelsContent,
                datasets: [{
                    data: dataContent,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutoutPercentage: 65,
                legend: {
                    display: false
                },
                tooltips: {
                    cornerRadius: 8,
                    xPadding: 12,
                    yPadding: 10,
                    callbacks: {
                        label: function(tooltipItem, data) {
                            var label = data.labels[tooltipItem.index] || '';
                            var val = data.datasets[0].data[tooltipItem.index] || 0;
                            return ' ' + label + ': ' + val + ' item';
                        }
                    }
                }
            }
        }, function (data, options, c) {
            // Urutan warna sama dengan .dash-legend-dot.is-1/2/3 di app.css
            data.datasets[0].backgroundColor = [
                c.brand,       // video - primary
                c.neutral,     // dokumen - gray 400
                c.neutralSoft  // pelatihan - gray 300
            ];
            // Celah antar irisan = warna kartu
            data.datasets[0].borderColor = c.surface;
            paintTooltip(options.tooltips, c);
        });
    }

    @endif

    // Resize charts on tab change to prevent rendering distortion
    $('a[data-toggle="pill"]').on('shown.bs.tab', function(e) {
        if (window.chartPelatihan) window.chartPelatihan.resize();
        if (window.chartVideo) window.chartVideo.resize();
        if (window.chartContent) window.chartContent.resize();
    });
    @endif
});
</script>
@endsection
