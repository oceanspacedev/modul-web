@extends('layout.main_template')

@section('content')
<div class="content-header">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="m-0 font-weight-bold" style="font-size: 1.5rem; color: #0f172a; letter-spacing: -0.02em;">
                    Dashboard
                </h1>
                <p class="text-muted small mt-1 mb-0">Ringkasan seluruh modul, pelatihan, video, dan grafik analitik sistem.</p>
            </div>
            <div class="d-flex align-items-center">
                <span class="badge border text-muted px-3 py-2 font-weight-normal shadow-sm" style="background: #ffffff; border-color: #e2e8f0 !important; font-size: 0.825rem; border-radius: 8px;">
                    <i class="far fa-calendar-alt mr-1 text-primary"></i> {{ \Carbon\Carbon::now()->translatedFormat('l, d F Y') }}
                </span>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        {{-- Flash Alerts --}}
        @if ($message = Session::get('success'))
            <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert" style="border-radius: 10px;">
                <i class="fas fa-check-circle mr-1"></i> {{ $message }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif
        @if ($message = Session::get('error'))
            <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert" style="border-radius: 10px;">
                <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        {{-- =========================================================
             STAT METRIC CARDS (8 CARDS - MODERN BALANCED ACCENTS)
             ========================================================= --}}
        <div class="row">
            {{-- 1. Total User --}}
            <div class="col-xl-3 col-md-6 col-12 mb-3">
                <div class="card h-100 border-0 shadow-sm" style="border: 1px solid #e2e8f0 !important; border-radius: 12px; background: #ffffff;">
                    <a href="/user" class="text-decoration-none text-reset">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-uppercase font-weight-bold" style="letter-spacing: 0.04em; font-size: 0.75rem; color: #64748b;">Total Pengguna</small>
                                <h3 class="font-weight-bold mb-1 mt-1" style="font-size: 1.75rem; color: #0f172a;">{{ $data['totalUser'] }}</h3>
                                <span class="badge border" style="background: #f8fafc; border-color: #e2e8f0 !important; color: #2563eb; font-size: 0.725rem;">
                                    <i class="fas fa-arrow-right mr-1"></i> Kelola User
                                </span>
                            </div>
                            <div class="d-flex align-items-center justify-content-center rounded-circle border" style="width: 50px; height: 50px; background: #eff6ff; border-color: #dbeafe !important; color: #2563eb;">
                                <i class="fas fa-users" style="font-size: 1.25rem;"></i>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            {{-- 2. Total Pelatihan --}}
            <div class="col-xl-3 col-md-6 col-12 mb-3">
                <div class="card h-100 border-0 shadow-sm" style="border: 1px solid #e2e8f0 !important; border-radius: 12px; background: #ffffff;">
                    <a href="/training" class="text-decoration-none text-reset">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-uppercase font-weight-bold" style="letter-spacing: 0.04em; font-size: 0.75rem; color: #64748b;">Jadwal Pelatihan</small>
                                <h3 class="font-weight-bold mb-1 mt-1" style="font-size: 1.75rem; color: #0f172a;">{{ $data['totalTraining'] }}</h3>
                                <span class="badge border" style="background: #f0f9ff; border-color: #e0f2fe !important; color: #0284c7; font-size: 0.725rem;">
                                    <i class="fas fa-clock mr-1"></i> {{ $data['ongoingTraining'] }} Berlangsung
                                </span>
                            </div>
                            <div class="d-flex align-items-center justify-content-center rounded-circle border" style="width: 50px; height: 50px; background: #f0f9ff; border-color: #e0f2fe !important; color: #0284c7;">
                                <i class="fas fa-chalkboard-teacher" style="font-size: 1.25rem;"></i>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            {{-- 3. Total Video Materi --}}
            <div class="col-xl-3 col-md-6 col-12 mb-3">
                <div class="card h-100 border-0 shadow-sm" style="border: 1px solid #e2e8f0 !important; border-radius: 12px; background: #ffffff;">
                    <a href="/video" class="text-decoration-none text-reset">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-uppercase font-weight-bold" style="letter-spacing: 0.04em; font-size: 0.75rem; color: #64748b;">Video Materi</small>
                                <h3 class="font-weight-bold mb-1 mt-1" style="font-size: 1.75rem; color: #0f172a;">{{ $data['totalVideo'] }}</h3>
                                <span class="badge border" style="background: #fff1f2; border-color: #ffe4e6 !important; color: #e11d48; font-size: 0.725rem;">
                                    <i class="fas fa-play mr-1"></i> Buka Galeri
                                </span>
                            </div>
                            <div class="d-flex align-items-center justify-content-center rounded-circle border" style="width: 50px; height: 50px; background: #fff1f2; border-color: #ffe4e6 !important; color: #e11d48;">
                                <i class="fas fa-play-circle" style="font-size: 1.25rem;"></i>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            {{-- 4. Total Peserta Pelatihan --}}
            <div class="col-xl-3 col-md-6 col-12 mb-3">
                <div class="card h-100 border-0 shadow-sm" style="border: 1px solid #e2e8f0 !important; border-radius: 12px; background: #ffffff;">
                    <a href="/training" class="text-decoration-none text-reset">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-uppercase font-weight-bold" style="letter-spacing: 0.04em; font-size: 0.75rem; color: #64748b;">Peserta Pelatihan</small>
                                <h3 class="font-weight-bold mb-1 mt-1" style="font-size: 1.75rem; color: #0f172a;">{{ $data['totalParticipant'] }}</h3>
                                <span class="badge border" style="background: #ecfdf5; border-color: #d1fae5 !important; color: #059669; font-size: 0.725rem;">
                                    <i class="fas fa-check-circle mr-1"></i> Terdaftar
                                </span>
                            </div>
                            <div class="d-flex align-items-center justify-content-center rounded-circle border" style="width: 50px; height: 50px; background: #ecfdf5; border-color: #d1fae5 !important; color: #059669;">
                                <i class="fas fa-user-graduate" style="font-size: 1.25rem;"></i>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            {{-- 5. Total Dokumen Modul --}}
            <div class="col-xl-3 col-md-6 col-12 mb-3">
                <div class="card h-100 border-0 shadow-sm" style="border: 1px solid #e2e8f0 !important; border-radius: 12px; background: #ffffff;">
                    <a href="/document" class="text-decoration-none text-reset">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-uppercase font-weight-bold" style="letter-spacing: 0.04em; font-size: 0.75rem; color: #64748b;">Dokumen Modul</small>
                                <h3 class="font-weight-bold mb-1 mt-1" style="font-size: 1.75rem; color: #0f172a;">{{ $data['totalDocument'] }}</h3>
                                <span class="badge border" style="background: #fffbeb; border-color: #fef3c7 !important; color: #d97706; font-size: 0.725rem;">
                                    <i class="fas fa-folder-open mr-1"></i> Lihat Dokumen
                                </span>
                            </div>
                            <div class="d-flex align-items-center justify-content-center rounded-circle border" style="width: 50px; height: 50px; background: #fffbeb; border-color: #fef3c7 !important; color: #d97706;">
                                <i class="fas fa-file-alt" style="font-size: 1.25rem;"></i>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            {{-- 6. Total Kuis --}}
            <div class="col-xl-3 col-md-6 col-12 mb-3">
                <div class="card h-100 border-0 shadow-sm" style="border: 1px solid #e2e8f0 !important; border-radius: 12px; background: #ffffff;">
                    <a href="/quiz" class="text-decoration-none text-reset">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-uppercase font-weight-bold" style="letter-spacing: 0.04em; font-size: 0.75rem; color: #64748b;">Kuis Modul</small>
                                <h3 class="font-weight-bold mb-1 mt-1" style="font-size: 1.75rem; color: #0f172a;">{{ $data['totalQuiz'] }}</h3>
                                <span class="badge border" style="background: #eef2ff; border-color: #e0e7ff !important; color: #4f46e5; font-size: 0.725rem;">
                                    <i class="fas fa-question-circle mr-1"></i> Soal & Kuis
                                </span>
                            </div>
                            <div class="d-flex align-items-center justify-content-center rounded-circle border" style="width: 50px; height: 50px; background: #eef2ff; border-color: #e0e7ff !important; color: #4f46e5;">
                                <i class="fas fa-award" style="font-size: 1.25rem;"></i>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            {{-- 7. Hasil Evaluasi Kuis --}}
            <div class="col-xl-3 col-md-6 col-12 mb-3">
                <div class="card h-100 border-0 shadow-sm" style="border: 1px solid #e2e8f0 !important; border-radius: 12px; background: #ffffff;">
                    <a href="/training" class="text-decoration-none text-reset">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-uppercase font-weight-bold" style="letter-spacing: 0.04em; font-size: 0.75rem; color: #64748b;">Hasil Evaluasi Kuis</small>
                                <h3 class="font-weight-bold mb-1 mt-1" style="font-size: 1.75rem; color: #0f172a;">{{ $data['totalQuizResult'] }}</h3>
                                <span class="badge border" style="background: #f5f3ff; border-color: #ede9fe !important; color: #7c3aed; font-size: 0.725rem;">
                                    <i class="fas fa-chart-line mr-1"></i> Rata-rata: {{ $data['quizAvgScore'] }}
                                </span>
                            </div>
                            <div class="d-flex align-items-center justify-content-center rounded-circle border" style="width: 50px; height: 50px; background: #f5f3ff; border-color: #ede9fe !important; color: #7c3aed;">
                                <i class="fas fa-clipboard-check" style="font-size: 1.25rem;"></i>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            {{-- 8. Data Presensi --}}
            <div class="col-xl-3 col-md-6 col-12 mb-3">
                <div class="card h-100 border-0 shadow-sm" style="border: 1px solid #e2e8f0 !important; border-radius: 12px; background: #ffffff;">
                    <a href="/absent" class="text-decoration-none text-reset">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-uppercase font-weight-bold" style="letter-spacing: 0.04em; font-size: 0.75rem; color: #64748b;">Presensi Karyawan</small>
                                <h3 class="font-weight-bold mb-1 mt-1" style="font-size: 1.75rem; color: #0f172a;">{{ $data['totalAbsent'] }}</h3>
                                <span class="badge border" style="background: #f0fdf4; border-color: #dcfce7 !important; color: #16a34a; font-size: 0.725rem;">
                                    <i class="fas fa-calendar-day mr-1"></i> {{ $data['todayAbsent'] }} Hari Ini
                                </span>
                            </div>
                            <div class="d-flex align-items-center justify-content-center rounded-circle border" style="width: 50px; height: 50px; background: #f0fdf4; border-color: #dcfce7 !important; color: #16a34a;">
                                <i class="fas fa-calendar-check" style="font-size: 1.25rem;"></i>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        {{-- =========================================================
             SECTION: GRAFIK & ANALITIK (CLEAN & BALANCED MODERN AESTHETIC)
             ========================================================= --}}
        <div class="row mt-2">
            {{-- Kolom Kiri: Grafik Pelatihan & Grafik Evaluasi Kuis --}}
            <div class="col-lg-7 mb-4">
                {{-- 1. Grafik Peserta Pelatihan --}}
                <div class="card shadow-sm border-0 mb-4" style="border: 1px solid #e2e8f0 !important; border-radius: 14px; background: #ffffff;">
                    <div class="card-header bg-white border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center" style="border-color: #f1f5f9 !important;">
                        <div class="d-flex align-items-center mr-2">
                            <h6 class="m-0 font-weight-bold" style="color: #0f172a;">
                                <i class="fas fa-chart-bar mr-2 text-primary"></i> Grafik Peserta Sesi Pelatihan
                            </h6>
                            <span class="badge border ml-2" style="background: #f8fafc; border-color: #e2e8f0 !important; color: #64748b; font-size: 0.75rem;">
                                {{ $data['totalTraining'] }} Sesi
                            </span>
                        </div>
                        <div class="d-flex align-items-center mt-2 mt-sm-0">
                            <ul class="nav nav-pills card-header-pills mr-2 custom-theme-pills" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active py-1 px-3 shadow-none font-weight-bold" data-toggle="pill" href="#tab-pelatihan-chart" role="tab" style="font-size: 0.75rem; border-radius: 6px;">
                                        <i class="fas fa-chart-bar mr-1"></i> Grafik
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link py-1 px-3 shadow-none font-weight-bold" data-toggle="pill" href="#tab-pelatihan-table" role="tab" style="font-size: 0.75rem; border-radius: 6px;">
                                        <i class="fas fa-list mr-1"></i> Tabel
                                    </a>
                                </li>
                            </ul>
                            <a href="/training" class="btn btn-sm btn-outline-primary" style="border-radius: 6px; font-size: 0.75rem;">
                                Kelola <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-3">
                        <div class="tab-content">
                            {{-- Tab 1: Grafik Batang --}}
                            <div class="tab-pane fade show active" id="tab-pelatihan-chart" role="tabpanel">
                                @if(count($data['chartTrainingLabels']) === 0)
                                    <div class="text-center py-4" style="color: #94a3b8;">
                                        <i class="fas fa-chart-bar fa-2x mb-2" style="opacity: 0.3;"></i>
                                        <p class="mb-0 small">Belum ada data pelatihan untuk ditampilkan dalam grafik.</p>
                                    </div>
                                @else
                                    <div style="position: relative; height: 230px; width: 100%;">
                                        <canvas id="chartPelatihan"></canvas>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top small" style="border-color: #f1f5f9 !important; font-size: 0.775rem; color: #64748b;">
                                        <span><i class="fas fa-info-circle mr-1 text-primary"></i> Menampilkan jumlah peserta terdaftar pada setiap sesi pelatihan</span>
                                        <span class="font-weight-bold" style="color: #1e293b;">{{ $data['totalParticipant'] }} Total Peserta</span>
                                    </div>
                                @endif
                            </div>

                            {{-- Tab 2: Tabel Ringkasan --}}
                            <div class="tab-pane fade" id="tab-pelatihan-table" role="tabpanel">
                                @if($data['recentTrainings']->isEmpty())
                                    <div class="text-center py-4" style="color: #94a3b8;">
                                        <i class="fas fa-calendar-times fa-2x mb-2" style="opacity: 0.4;"></i>
                                        <p class="mb-0 small">Belum ada jadwal pelatihan yang dibuat.</p>
                                    </div>
                                @else
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                                            <thead style="background: #f8fafc; color: #64748b;">
                                                <tr>
                                                    <th class="border-0 px-2 py-2">Judul Pelatihan</th>
                                                    <th class="border-0 px-2 py-2">Pemateri</th>
                                                    <th class="border-0 px-2 py-2">Tanggal</th>
                                                    <th class="border-0 px-2 py-2 text-center">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($data['recentTrainings'] as $t)
                                                    <tr>
                                                        <td class="px-2 py-2">
                                                            <a href="{{ url('/training/' . $t->id) }}" class="font-weight-bold text-decoration-none" style="color: #0f172a;">
                                                                {{ Str::limit($t->title, 28) }}
                                                            </a>
                                                            <div class="small" style="font-size: 0.725rem; color: #94a3b8;">
                                                                {{ $t->participants->count() }} Peserta &bull; {{ $t->questions->count() }} Soal
                                                            </div>
                                                        </td>
                                                        <td class="px-2 py-2" style="color: #64748b;">
                                                            {{ $t->trainer ? $t->trainer->full_name : '-' }}
                                                        </td>
                                                        <td class="px-2 py-2" style="color: #64748b;">
                                                            {{ $t->training_date ? $t->training_date->format('d M Y') : '-' }}
                                                        </td>
                                                        <td class="px-2 py-2 text-center">
                                                            @if($t->status === 'ongoing')
                                                                <span class="badge border" style="background: #ecfdf5; color: #065f46; border-color: #a7f3d0; padding: 4px 8px;">Berlangsung</span>
                                                            @elseif($t->status === 'completed')
                                                                <span class="badge border" style="background: #f1f5f9; color: #475569; border-color: #cbd5e1; padding: 4px 8px;">Selesai</span>
                                                            @else
                                                                <span class="badge border" style="background: #eff6ff; color: #1e40af; border-color: #bfdbfe; padding: 4px 8px;">Terjadwal</span>
                                                            @endif
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

                {{-- 2. Grafik Distribusi Nilai & Evaluasi Kuis --}}
                <div class="card shadow-sm border-0" style="border: 1px solid #e2e8f0 !important; border-radius: 14px; background: #ffffff;">
                    <div class="card-header bg-white border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center" style="border-color: #f1f5f9 !important;">
                        <div class="d-flex align-items-center mr-2">
                            <h6 class="m-0 font-weight-bold" style="color: #0f172a;">
                                <i class="fas fa-poll mr-2" style="color: #7c3aed;"></i> Grafik Distribusi Nilai Evaluasi Kuis
                            </h6>
                            <span class="badge border ml-2" style="background: #f8fafc; border-color: #e2e8f0 !important; color: #64748b; font-size: 0.75rem;">
                                {{ $data['totalQuizResult'] }} Peserta
                            </span>
                        </div>
                        <div class="d-flex align-items-center mt-2 mt-sm-0">
                            <ul class="nav nav-pills card-header-pills mr-2 custom-theme-pills" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active py-1 px-3 shadow-none font-weight-bold" data-toggle="pill" href="#tab-kuis-chart" role="tab" style="font-size: 0.75rem; border-radius: 6px;">
                                        <i class="fas fa-chart-bar mr-1"></i> Grafik
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link py-1 px-3 shadow-none font-weight-bold" data-toggle="pill" href="#tab-kuis-table" role="tab" style="font-size: 0.75rem; border-radius: 6px;">
                                        <i class="fas fa-list mr-1"></i> Riwayat
                                    </a>
                                </li>
                            </ul>
                            <a href="/training" class="btn btn-sm btn-outline-secondary" style="border-radius: 6px; font-size: 0.75rem; border-color: #cbd5e1; color: #475569;">
                                Hasil <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-3">
                        <div class="tab-content">
                            {{-- Tab 1: Grafik Distribusi Skor --}}
                            <div class="tab-pane fade show active" id="tab-kuis-chart" role="tabpanel">
                                @if($data['totalQuizResult'] === 0)
                                    <div class="text-center py-4" style="color: #94a3b8;">
                                        <i class="fas fa-clipboard-list fa-2x mb-2" style="opacity: 0.3;"></i>
                                        <p class="mb-0 small">Belum ada evaluasi kuis yang dikerjakan oleh peserta.</p>
                                    </div>
                                @else
                                    <div style="position: relative; height: 230px; width: 100%;">
                                        <canvas id="chartQuizScore"></canvas>
                                    </div>
                                    {{-- KPI Skor Strip --}}
                                    <div class="row text-center mt-3 pt-3 border-top" style="border-color: #f1f5f9 !important;">
                                        <div class="col-4 border-right" style="border-color: #f1f5f9 !important;">
                                            <small class="text-uppercase d-block" style="font-size: 0.7rem; color: #64748b;">Rata-rata Skor</small>
                                            <span class="font-weight-bold" style="font-size: 1.15rem; color: #0f172a;">{{ $data['quizAvgScore'] }}</span>
                                        </div>
                                        <div class="col-4 border-right" style="border-color: #f1f5f9 !important;">
                                            <small class="text-uppercase d-block" style="font-size: 0.7rem; color: #64748b;">Skor Tertinggi</small>
                                            <span class="font-weight-bold text-success" style="font-size: 1.15rem;">{{ $data['quizMaxScore'] }}</span>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-uppercase d-block" style="font-size: 0.7rem; color: #64748b;">Skor Terendah</small>
                                            <span class="font-weight-bold text-danger" style="font-size: 1.15rem;">{{ $data['quizMinScore'] }}</span>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- Tab 2: Tabel Riwayat Pengerjaan --}}
                            <div class="tab-pane fade" id="tab-kuis-table" role="tabpanel">
                                @if($data['recentQuizResults']->isEmpty())
                                    <div class="text-center py-4" style="color: #94a3b8;">
                                        <i class="fas fa-clipboard-list fa-2x mb-2" style="opacity: 0.4;"></i>
                                        <p class="mb-0 small">Belum ada evaluasi kuis yang dikerjakan.</p>
                                    </div>
                                @else
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                                            <thead style="background: #f8fafc; color: #64748b;">
                                                <tr>
                                                    <th class="border-0 px-2 py-2">Peserta</th>
                                                    <th class="border-0 px-2 py-2">Pelatihan</th>
                                                    <th class="border-0 px-2 py-2 text-center">Nilai</th>
                                                    <th class="border-0 px-2 py-2 text-center">Waktu</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($data['recentQuizResults'] as $res)
                                                    <tr>
                                                        <td class="px-2 py-2">
                                                            <span class="font-weight-bold" style="color: #0f172a;">
                                                                {{ $res->participant && $res->participant->user ? $res->participant->user->full_name : 'Peserta' }}
                                                            </span>
                                                        </td>
                                                        <td class="px-2 py-2" style="color: #64748b;">
                                                            {{ $res->training ? Str::limit($res->training->title, 24) : '-' }}
                                                        </td>
                                                        <td class="px-2 py-2 text-center">
                                                            @php $score = round($res->score ?? 0); @endphp
                                                            <span class="badge border font-weight-bold" style="background: {{ $score >= 70 ? '#ecfdf5' : '#fef2f2' }}; color: {{ $score >= 70 ? '#059669' : '#dc2626' }}; border-color: {{ $score >= 70 ? '#a7f3d0' : '#fecaca' }}; padding: 4px 8px;">
                                                                {{ $score }}
                                                            </span>
                                                        </td>
                                                        <td class="px-2 py-2 text-center small" style="color: #94a3b8;">
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

            {{-- Kolom Kanan: Grafik Video Penonton & Grafik Komposisi Master Data --}}
            <div class="col-lg-5 mb-4">
                {{-- 3. Grafik Popularitas Video Materi --}}
                <div class="card shadow-sm border-0 mb-4" style="border: 1px solid #e2e8f0 !important; border-radius: 14px; background: #ffffff;">
                    <div class="card-header bg-white border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center" style="border-color: #f1f5f9 !important;">
                        <div class="d-flex align-items-center mr-2">
                            <h6 class="m-0 font-weight-bold" style="color: #0f172a;">
                                <i class="fas fa-play-circle mr-2 text-danger"></i> Grafik Penonton Video Materi
                            </h6>
                        </div>
                        <div class="d-flex align-items-center mt-2 mt-sm-0">
                            <ul class="nav nav-pills card-header-pills mr-2 custom-theme-pills" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active py-1 px-3 shadow-none font-weight-bold" data-toggle="pill" href="#tab-video-chart" role="tab" style="font-size: 0.75rem; border-radius: 6px;">
                                        <i class="fas fa-chart-bar mr-1"></i> Grafik
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link py-1 px-3 shadow-none font-weight-bold" data-toggle="pill" href="#tab-video-list" role="tab" style="font-size: 0.75rem; border-radius: 6px;">
                                        <i class="fas fa-list mr-1"></i> Daftar
                                    </a>
                                </li>
                            </ul>
                            <a href="/video" class="btn btn-sm btn-outline-danger" style="border-radius: 6px; font-size: 0.75rem;">
                                Galeri <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-3">
                        <div class="tab-content">
                            {{-- Tab 1: Grafik Horizontal Bar Penonton Video --}}
                            <div class="tab-pane fade show active" id="tab-video-chart" role="tabpanel">
                                @if(count($data['chartVideoLabels']) === 0)
                                    <div class="text-center py-4" style="color: #94a3b8;">
                                        <i class="fas fa-film fa-2x mb-2" style="opacity: 0.3;"></i>
                                        <p class="mb-0 small">Belum ada video materi yang diunggah.</p>
                                    </div>
                                @else
                                    <div style="position: relative; height: 230px; width: 100%;">
                                        <canvas id="chartVideoViews"></canvas>
                                    </div>
                                    <div class="text-center mt-2 pt-2 border-top small" style="border-color: #f1f5f9 !important; font-size: 0.775rem; color: #64748b;">
                                        <i class="far fa-eye mr-1 text-danger"></i> Peringkat video materi berdasarkan jumlah tayangan terbanyak
                                    </div>
                                @endif
                            </div>

                            {{-- Tab 2: Daftar Video --}}
                            <div class="tab-pane fade" id="tab-video-list" role="tabpanel">
                                @if($data['recentVideos']->isEmpty())
                                    <div class="text-center py-4" style="color: #94a3b8;">
                                        <i class="fas fa-film fa-2x mb-2" style="opacity: 0.4;"></i>
                                        <p class="mb-0 small">Belum ada video materi yang diunggah.</p>
                                    </div>
                                @else
                                    <div class="list-group list-group-flush">
                                        @foreach($data['recentVideos'] as $vid)
                                            <div class="d-flex align-items-center py-2 px-1 border-bottom" style="border-color: #f1f5f9 !important;">
                                                <div class="position-relative mr-3 rounded overflow-hidden flex-shrink-0" style="width: 58px; height: 40px; background: #1e293b;">
                                                    @if($vid->thumbnail_url)
                                                        <img src="{{ $vid->thumbnail_url }}" alt="{{ $vid->title }}" class="w-100 h-100" style="object-fit: cover;">
                                                    @else
                                                        <div class="w-100 h-100 d-flex align-items-center justify-content-center" style="background: #334155;">
                                                            <i class="fas fa-play text-white-50" style="font-size: 12px;"></i>
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="flex-grow-1 overflow-hidden pr-2">
                                                    <a href="{{ route('video.show', $vid->id) }}" class="d-block font-weight-bold text-truncate text-decoration-none" style="font-size: 0.85rem; color: #0f172a;" title="{{ $vid->title }}">
                                                        {{ $vid->title }}
                                                    </a>
                                                    <div class="small d-flex align-items-center" style="font-size: 0.725rem; color: #64748b;">
                                                        <span class="mr-2"><i class="far fa-eye mr-1 text-danger"></i> {{ $vid->views_count }} views</span>
                                                        <span><i class="far fa-clock mr-1"></i> {{ $vid->created_at->format('d M') }}</span>
                                                    </div>
                                                </div>
                                                <a href="{{ route('video.show', $vid->id) }}" class="btn btn-sm btn-light border flex-shrink-0" title="Tonton Video" style="border-radius: 6px; padding: 4px 8px; border-color: #cbd5e1; color: #e11d48;">
                                                    <i class="fas fa-play" style="font-size: 0.75rem;"></i>
                                                </a>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 4. Grafik Komposisi Modul & Master Data --}}
                <div class="card shadow-sm border-0" style="border: 1px solid #e2e8f0 !important; border-radius: 14px; background: #ffffff;">
                    <div class="card-header bg-white border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center" style="border-color: #f1f5f9 !important;">
                        <div class="d-flex align-items-center mr-2">
                            <h6 class="m-0 font-weight-bold" style="color: #0f172a;">
                                <i class="fas fa-chart-pie mr-2 text-info"></i> Komposisi Konten Sistem
                            </h6>
                        </div>
                        <div class="d-flex align-items-center mt-2 mt-sm-0">
                            <ul class="nav nav-pills card-header-pills mr-2 custom-theme-pills" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active py-1 px-3 shadow-none font-weight-bold" data-toggle="pill" href="#tab-komposisi-chart" role="tab" style="font-size: 0.75rem; border-radius: 6px;">
                                        <i class="fas fa-chart-pie mr-1"></i> Donat
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link py-1 px-3 shadow-none font-weight-bold" data-toggle="pill" href="#tab-komposisi-detail" role="tab" style="font-size: 0.75rem; border-radius: 6px;">
                                        <i class="fas fa-sitemap mr-1"></i> Detail
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body p-3">
                        <div class="tab-content">
                            {{-- Tab 1: Grafik Donat Komposisi --}}
                            <div class="tab-pane fade show active" id="tab-komposisi-chart" role="tabpanel">
                                <div style="position: relative; height: 210px; width: 100%;">
                                    <canvas id="chartContentComposition"></canvas>
                                </div>
                                <div class="d-flex flex-wrap justify-content-center mt-3 pt-2 border-top text-center" style="gap: 8px; border-color: #f1f5f9 !important;">
                                    <span class="badge border px-2 py-1" style="background: #fff1f2; border-color: #ffe4e6; color: #be123c; font-size: 0.725rem;">
                                        <span class="d-inline-block rounded-circle mr-1" style="width: 8px; height: 8px; background: #e11d48;"></span> Video: {{ $data['totalVideo'] }}
                                    </span>
                                    <span class="badge border px-2 py-1" style="background: #fffbeb; border-color: #fef3c7; color: #b45309; font-size: 0.725rem;">
                                        <span class="d-inline-block rounded-circle mr-1" style="width: 8px; height: 8px; background: #d97706;"></span> Dokumen: {{ $data['totalDocument'] }}
                                    </span>
                                    <span class="badge border px-2 py-1" style="background: #f0f9ff; border-color: #e0f2fe; color: #0369a1; font-size: 0.725rem;">
                                        <span class="d-inline-block rounded-circle mr-1" style="width: 8px; height: 8px; background: #0284c7;"></span> Pelatihan: {{ $data['totalTraining'] }}
                                    </span>
                                    <span class="badge border px-2 py-1" style="background: #eef2ff; border-color: #e0e7ff; color: #4338ca; font-size: 0.725rem;">
                                        <span class="d-inline-block rounded-circle mr-1" style="width: 8px; height: 8px; background: #4f46e5;"></span> Kuis: {{ $data['totalQuiz'] }}
                                    </span>
                                    <span class="badge border px-2 py-1" style="background: #f8fafc; border-color: #e2e8f0; color: #334155; font-size: 0.725rem;">
                                        <span class="d-inline-block rounded-circle mr-1" style="width: 8px; height: 8px; background: #0f172a;"></span> Divisi: {{ $data['totalDivisi'] }}
                                    </span>
                                </div>
                            </div>

                            {{-- Tab 2: Detail Master Data Organisasi --}}
                            <div class="tab-pane fade" id="tab-komposisi-detail" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom" style="border-color: #f1f5f9 !important;">
                                    <div class="d-flex align-items-center">
                                        <span class="d-inline-flex align-items-center justify-content-center rounded mr-3 border" style="width: 36px; height: 36px; background: #eff6ff; border-color: #dbeafe; color: #2563eb;">
                                            <i class="fas fa-city"></i>
                                        </span>
                                        <div>
                                            <h6 class="mb-0 font-weight-bold" style="font-size: 0.885rem; color: #0f172a;">Divisi Perusahaan</h6>
                                            <small style="color: #64748b;">Struktur divisi induk</small>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <a href="/divisi" class="badge border px-3 py-2 font-weight-bold" style="font-size: 0.85rem; border-radius: 8px; background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe;">
                                            {{ $data['totalDivisi'] }} Divisi
                                        </a>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom" style="border-color: #f1f5f9 !important;">
                                    <div class="d-flex align-items-center">
                                        <span class="d-inline-flex align-items-center justify-content-center rounded mr-3 border" style="width: 36px; height: 36px; background: #f0f9ff; border-color: #e0f2fe; color: #0284c7;">
                                            <i class="fas fa-building"></i>
                                        </span>
                                        <div>
                                            <h6 class="mb-0 font-weight-bold" style="font-size: 0.885rem; color: #0f172a;">Sub Divisi</h6>
                                            <small style="color: #64748b;">Unit & departemen tim</small>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <a href="/subdivisi" class="badge border px-3 py-2 font-weight-bold" style="font-size: 0.85rem; border-radius: 8px; background: #f0f9ff; color: #0369a1; border-color: #bae6fd;">
                                            {{ $data['totalSubDivisi'] }} Sub Divisi
                                        </a>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center py-2" style="border-color: #f1f5f9 !important;">
                                    <div class="d-flex align-items-center">
                                        <span class="d-inline-flex align-items-center justify-content-center rounded mr-3 border" style="width: 36px; height: 36px; background: #fffbeb; border-color: #fef3c7; color: #d97706;">
                                            <i class="fas fa-bookmark"></i>
                                        </span>
                                        <div>
                                            <h6 class="mb-0 font-weight-bold" style="font-size: 0.885rem; color: #0f172a;">Tipe Dokumen</h6>
                                            <small style="color: #64748b;">Kategori dokumen sistem</small>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <a href="/dokumentype" class="badge border px-3 py-2 font-weight-bold" style="font-size: 0.85rem; border-radius: 8px; background: #fffbeb; color: #b45309; border-color: #fde68a;">
                                            {{ $data['totalDokumenType'] }} Tipe
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
/* Modern Balanced Theme Pills */
.custom-theme-pills .nav-link {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
    transition: all 0.2s ease;
}
.custom-theme-pills .nav-link:hover {
    background: #e2e8f0;
    color: #0f172a;
}
.custom-theme-pills .nav-link.active {
    background: #1e293b !important;
    color: #ffffff !important;
    border-color: #1e293b !important;
}
</style>
@endsection

@section('footer')
<!-- Chart.js Plugin -->
<script src="{{ asset('template') }}/plugins/chart.js/Chart.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // Global Chart.js styling
    Chart.defaults.global.defaultFontFamily = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';
    Chart.defaults.global.defaultFontColor = '#64748b';

    // -------------------------------------------------------------
    // 1. Chart Peserta Pelatihan (Bar Chart - Sapphire / Indigo)
    // -------------------------------------------------------------
    var ctxPelatihan = document.getElementById('chartPelatihan');
    if (ctxPelatihan) {
        var labelsPelatihan = {!! json_encode($data['chartTrainingLabels']) !!};
        var dataPelatihan = {!! json_encode($data['chartTrainingParticipants']) !!};

        window.chartPelatihan = new Chart(ctxPelatihan.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labelsPelatihan,
                datasets: [{
                    label: 'Jumlah Peserta',
                    data: dataPelatihan,
                    backgroundColor: 'rgba(37, 99, 235, 0.85)',
                    borderColor: '#2563eb',
                    borderWidth: 1.5,
                    hoverBackgroundColor: '#1d4ed8',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { display: false },
                tooltips: {
                    backgroundColor: '#0f172a',
                    titleFontColor: '#ffffff',
                    bodyFontColor: '#cbd5e1',
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
                        ticks: { fontColor: '#64748b', fontSize: 11 }
                    }],
                    yAxes: [{
                        gridLines: { color: '#f1f5f9', zeroLineColor: '#e2e8f0', drawBorder: false },
                        ticks: {
                            beginAtZero: true,
                            precision: 0,
                            fontColor: '#64748b',
                            fontSize: 11
                        }
                    }]
                }
            }
        });
    }

    // -------------------------------------------------------------
    // 2. Chart Distribusi Nilai Kuis (Clean Grade Palette)
    // -------------------------------------------------------------
    var ctxQuiz = document.getElementById('chartQuizScore');
    if (ctxQuiz) {
        var scoreBuckets = {!! json_encode($data['scoreBuckets']) !!};
        var labelsQuiz = ['Sangat Baik (85-100)', 'Baik (70-84)', 'Cukup (55-69)', 'Kurang (<55)'];
        var dataQuiz = [
            scoreBuckets.sangat_baik,
            scoreBuckets.baik,
            scoreBuckets.cukup,
            scoreBuckets.kurang
        ];

        window.chartQuiz = new Chart(ctxQuiz.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labelsQuiz,
                datasets: [{
                    label: 'Peserta',
                    data: dataQuiz,
                    backgroundColor: [
                        'rgba(16, 185, 129, 0.85)', // emerald
                        'rgba(59, 130, 246, 0.85)',  // blue
                        'rgba(245, 158, 11, 0.85)',  // amber
                        'rgba(244, 63, 94, 0.85)'    // rose
                    ],
                    borderColor: [
                        '#10b981',
                        '#3b82f6',
                        '#f59e0b',
                        '#f43f5e'
                    ],
                    borderWidth: 1.5,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { display: false },
                tooltips: {
                    backgroundColor: '#0f172a',
                    titleFontColor: '#ffffff',
                    bodyFontColor: '#cbd5e1',
                    cornerRadius: 8,
                    xPadding: 12,
                    yPadding: 10,
                    callbacks: {
                        label: function(tooltipItem) {
                            return ' Total: ' + tooltipItem.yLabel + ' orang';
                        }
                    }
                },
                scales: {
                    xAxes: [{
                        gridLines: { display: false, drawBorder: false },
                        ticks: { fontColor: '#64748b', fontSize: 10 }
                    }],
                    yAxes: [{
                        gridLines: { color: '#f1f5f9', zeroLineColor: '#e2e8f0', drawBorder: false },
                        ticks: {
                            beginAtZero: true,
                            precision: 0,
                            fontColor: '#64748b',
                            fontSize: 11
                        }
                    }]
                }
            }
        });
    }

    // -------------------------------------------------------------
    // 3. Chart Popularitas Video (Horizontal Bar - Ruby / Rose)
    // -------------------------------------------------------------
    var ctxVideo = document.getElementById('chartVideoViews');
    if (ctxVideo) {
        var labelsVideo = {!! json_encode($data['chartVideoLabels']) !!};
        var dataVideo = {!! json_encode($data['chartVideoViews']) !!};

        window.chartVideo = new Chart(ctxVideo.getContext('2d'), {
            type: 'horizontalBar',
            data: {
                labels: labelsVideo,
                datasets: [{
                    label: 'Total Views',
                    data: dataVideo,
                    backgroundColor: 'rgba(225, 29, 72, 0.85)',
                    borderColor: '#e11d48',
                    borderWidth: 1.5,
                    hoverBackgroundColor: '#be123c',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { display: false },
                tooltips: {
                    backgroundColor: '#0f172a',
                    titleFontColor: '#ffffff',
                    bodyFontColor: '#cbd5e1',
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
                        gridLines: { color: '#f1f5f9', zeroLineColor: '#e2e8f0', drawBorder: false },
                        ticks: {
                            beginAtZero: true,
                            precision: 0,
                            fontColor: '#64748b',
                            fontSize: 11
                        }
                    }],
                    yAxes: [{
                        gridLines: { display: false, drawBorder: false },
                        ticks: { fontColor: '#64748b', fontSize: 10 }
                    }]
                }
            }
        });
    }

    // -------------------------------------------------------------
    // 4. Chart Komposisi Konten Sistem (Doughnut - Harmonious Palette)
    // -------------------------------------------------------------
    var ctxContent = document.getElementById('chartContentComposition');
    if (ctxContent) {
        var labelsContent = {!! json_encode($data['chartContentLabels']) !!};
        var dataContent = {!! json_encode($data['chartContentValues']) !!};

        window.chartContent = new Chart(ctxContent.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: labelsContent,
                datasets: [{
                    data: dataContent,
                    backgroundColor: [
                        '#e11d48', // video ruby
                        '#d97706', // document amber
                        '#0284c7', // training sky
                        '#4f46e5', // quiz indigo
                        '#0f172a'  // divisi slate
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
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
                    backgroundColor: '#0f172a',
                    titleFontColor: '#ffffff',
                    bodyFontColor: '#cbd5e1',
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
        });
    }

    // Resize charts on tab change to prevent rendering distortion
    $('a[data-toggle="pill"]').on('shown.bs.tab', function(e) {
        if (window.chartPelatihan) window.chartPelatihan.resize();
        if (window.chartQuiz) window.chartQuiz.resize();
        if (window.chartVideo) window.chartVideo.resize();
        if (window.chartContent) window.chartContent.resize();
    });
});
</script>
@endsection
