@extends('layout.main_template')

@section('content')
<style>
    .zoom-drop-area {
        border: 1px dashed var(--gray-300);
        border-radius: var(--radius-xl);
        background: var(--gray-50);
        cursor: pointer;
        transition: border-color 0.15s ease, background-color 0.15s ease;
    }
    .zoom-drop-area:hover,
    .zoom-drop-area.is-dragover {
        border-color: var(--primary-500);
        background: var(--primary-50);
    }
</style>

@php
    $headerSubtitle = \Carbon\Carbon::parse($training->training_date)->format('d F Y')
        . ' · ' . substr($training->start_time, 0, 5) . ' - ' . substr($training->end_time, 0, 5) . ' WIB'
        . ($training->zoom_link ? ' · Online via Zoom' : '');
@endphp

<section class="content-header">
    <div class="container-fluid">
        <x-page-header :title="$training->title" :subtitle="$headerSubtitle" eyebrow="Pelatihan / Jadwal Pelatihan">
            <div class="mt-2">
                <x-training-status-badge :status="$training->status" />
            </div>
            <x-slot:actions>
                <a href="/training" class="btn btn-default btn-icon" title="Kembali ke Daftar Pelatihan">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <button type="button" class="btn btn-default" data-toggle="modal" data-target="#scanZoomModal">
                    <i class="fas fa-video-slash"></i> Scan Off Cam
                </button>
                <div class="dropdown d-inline-block">
                    <button class="btn btn-default dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Opsi
                    </button>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="/training/{{ $training->id }}/edit">
                            <i class="fas fa-edit mr-2 text-muted"></i> Edit Informasi Pelatihan
                        </a>
                        <form action="{{ route('training.export', $training) }}" method="POST" class="m-0">
                            @csrf
                            <button type="submit" class="dropdown-item">
                                <i class="fas fa-file-excel mr-2 text-muted"></i> Export Rekap Nilai
                            </button>
                        </form>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#uploadTrainingVideoModal">
                            <i class="fas fa-video mr-2 text-muted"></i> Upload Video Materi
                        </a>
                    </div>
                </div>
                <a href="/training/{{ $training->id }}/questions" class="btn btn-primary">
                    Soal Kuis ({{ $training->questions->count() }})
                </a>
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />

        {{-- RINGKASAN --}}
        @php
            $attendanceRate = $stats['total'] > 0 ? round(($stats['attended'] / $stats['total']) * 100) : 0;
            $quizRate = $stats['total'] > 0 ? round(($stats['quizSubmitted'] / $stats['total']) * 100) : 0;
        @endphp
        <div class="row">
            <div class="col-lg-3 col-6 mb-3">
                <div class="stat-tile h-100">
                    <span class="stat-tile-icon"><i class="fas fa-users"></i></span>
                    <div class="min-w-0">
                        <p class="stat-tile-label">Peserta</p>
                        <p class="stat-tile-value" id="statTotal">{{ $stats['total'] }}</p>
                        <p class="stat-tile-hint"><span id="statAttended">{{ $stats['attended'] }}</span> hadir &middot; <span id="statPending">{{ $stats['pending'] }}</span> belum absen</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6 mb-3">
                <div class="stat-tile h-100">
                    <span class="stat-tile-icon"><i class="fas fa-user-check"></i></span>
                    <div class="min-w-0">
                        <p class="stat-tile-label">Kehadiran</p>
                        <p class="stat-tile-value">{{ $attendanceRate }}%</p>
                        <p class="stat-tile-hint"><span id="statAbsent">{{ $stats['absent'] }}</span> tidak hadir</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6 mb-3">
                <div class="stat-tile h-100">
                    <span class="stat-tile-icon"><i class="fas fa-clipboard-check"></i></span>
                    <div class="min-w-0">
                        <p class="stat-tile-label">Kuis selesai</p>
                        <p class="stat-tile-value"><span id="statQuiz">{{ $stats['quizSubmitted'] }}</span><span class="text-muted text-sm font-weight-normal"> / {{ $stats['total'] }}</span></p>
                        <p class="stat-tile-hint">{{ $quizRate }}% peserta</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6 mb-3">
                <div class="stat-tile h-100">
                    <span class="stat-tile-icon"><i class="fas fa-chart-line"></i></span>
                    <div class="min-w-0">
                        <p class="stat-tile-label">Rata-rata nilai</p>
                        <p class="stat-tile-value" id="statAvg">{{ $stats['avgScore'] }}</p>
                        <p class="stat-tile-hint">dari yang sudah mengerjakan</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- TRAINING DETAIL OVERVIEW & SESSION CONTROL -->
        <div class="row">
            <!-- LEFT COLUMN: DETAIL & MATERI -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Detail Pelatihan</h3>
                    </div>
                    <div class="card-body">
                        <!-- METADATA GRID -->
                        <div class="row">
                            <div class="col-sm-4 mb-3 mb-sm-0">
                                <div class="text-muted text-xs mb-1">Pemateri</div>
                                <div class="font-weight-bold">{{ $training->trainer->nama_lengkap ?? '-' }}</div>
                                <div class="text-muted text-xs">{{ optional($training->trainer)->divisi->name ?? 'Divisi Umum' }}</div>
                            </div>
                            <div class="col-sm-4 mb-3 mb-sm-0">
                                <div class="text-muted text-xs mb-1">Tautan Meeting</div>
                                @if($training->zoom_link)
                                    <a href="{{ $training->zoom_link }}" target="_blank" class="d-inline-block text-truncate" style="max-width: 220px;">
                                        Buka Tautan Zoom
                                    </a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </div>
                            <div class="col-sm-4">
                                <div class="text-muted text-xs mb-1">Jadwal Sesi</div>
                                <div class="font-weight-bold">{{ substr($training->start_time, 0, 5) }} - {{ substr($training->end_time, 0, 5) }} WIB</div>
                            </div>
                        </div>

                        @if($training->description)
                            <div class="border-top mt-3 pt-3">
                                <div class="text-muted text-xs mb-1">Deskripsi & Materi Pelatihan</div>
                                <div class="text-sm">{{ $training->description }}</div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- SECTION MATERI & DOKUMEN PELATIHAN -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            Materi & Dokumen Pelatihan <span class="text-muted font-weight-normal">({{ $training->documents->count() }})</span>
                        </h3>
                        <div class="card-tools d-flex align-items-center gap-1">
                            <button type="button" class="btn btn-xs btn-default" data-toggle="modal" data-target="#attachDocumentModal">
                                Lampirkan Dokumen
                            </button>
                            <a href="/training/{{ $training->id }}/questions?open_ai=1" class="btn btn-xs btn-default">
                                Buat Soal AI
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Rekaman Video -->
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 p-3 mb-3 bg-light rounded border">
                            <div class="d-flex align-items-center min-w-0">
                                <span class="stat-tile-icon mr-3"><i class="fas fa-play"></i></span>
                                <div class="min-w-0">
                                    <div class="font-weight-bold text-sm text-truncate">
                                        {{ $training->video ? $training->video->title : 'Rekaman Video Materi' }}
                                    </div>
                                    <div class="text-muted text-xs">
                                        @if($training->video)
                                            {{ $training->video->views_count }} dilihat &bull; Diunggah {{ $training->video->created_at->format('d M Y') }}
                                        @else
                                            Belum ada video rekaman yang diunggah.
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                @if($training->video)
                                    <a href="{{ route('video.show', $training->video->id) }}" class="btn btn-xs btn-default">
                                        Tonton Video
                                    </a>
                                    <button type="button" class="btn btn-xs btn-default" data-toggle="modal" data-target="#uploadTrainingVideoModal">
                                        Ganti Video
                                    </button>
                                @else
                                    <button type="button" class="btn btn-xs btn-default" data-toggle="modal" data-target="#uploadTrainingVideoModal">
                                        Upload Video Materi
                                    </button>
                                @endif
                            </div>
                        </div>

                        <!-- Dokumen Terlampir -->
                        @if($training->documents->isEmpty())
                            <div class="text-center py-2 text-muted text-sm">
                                Belum ada dokumen materi yang dilampirkan.
                            </div>
                        @else
                            <div class="list-group list-group-flush">
                                @foreach($training->documents as $doc)
                                    @php
                                        $latestVer = $doc->versions->first();
                                        $fileUrl = $latestVer ? $latestVer->file_url : $doc->file_url;
                                    @endphp
                                    <div class="list-group-item px-0 py-2 d-flex align-items-center justify-content-between">
                                        <div class="mr-2 min-w-0">
                                            <a href="{{ $fileUrl }}" target="_blank" class="font-weight-bold text-dark text-sm text-truncate d-block" title="{{ $doc->name }}">
                                                {{ $doc->name }}
                                            </a>
                                            <span class="text-xs text-muted">
                                                {{ $doc->dokumentype->name ?? 'Dokumen' }} &bull; v{{ $doc->version ?? ($latestVer->version_number ?? 1) }}
                                            </span>
                                        </div>
                                        <div class="d-flex align-items-center flex-shrink-0 gap-1">
                                            <a href="{{ $fileUrl }}" target="_blank" class="btn btn-xs btn-default">
                                                Buka
                                            </a>
                                            <form action="/training/{{ $training->id }}/detach-document/{{ $doc->id }}" method="POST" class="d-inline m-0" onsubmit="return confirm(@js('Lepas dokumen '.$doc->name.' dari pelatihan ini?'))">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-default text-danger">
                                                    Lepas
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: KONTROL SESI -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Kontrol Sesi Pelatihan</h3>
                    </div>

                    <!-- STATUS SESI -->
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted text-xs">Status Pelatihan</span>
                            <x-training-status-badge :status="$training->status" />
                        </div>
                        <form action="{{ route('training.status', $training) }}" method="POST" id="formStatusPelatihan">
                            @csrf
                            <select name="status" class="form-control form-control-sm" onchange="document.getElementById('formStatusPelatihan').submit();">
                                @foreach (App\Enums\TrainingStatus::cases() as $status)
                                    <option value="{{ $status->value }}" @selected($training->status === $status)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                        </form>
                    </div>

                    <!-- PENGATURAN AKSES & FITUR -->
                    <div class="list-group list-group-flush border-top">
                        <!-- AKSES ABSENSI -->
                        <div class="list-group-item d-flex align-items-center justify-content-between gap-2">
                            <div class="min-w-0">
                                <div class="text-sm font-weight-bold">Akses Presensi</div>
                                <div class="text-muted text-xs">
                                    {{ $training->is_attendance_active ? 'Peserta dapat mengisi presensi' : 'Presensi ditutup untuk peserta' }}
                                </div>
                            </div>
                            <form action="/training/{{ $training->id }}/toggle-attendance" method="POST" class="m-0">
                                @csrf
                                @if($training->is_attendance_active)
                                    <button type="submit" class="btn btn-default btn-xs text-danger text-nowrap">
                                        Kunci Presensi
                                    </button>
                                @else
                                    <button type="submit" class="btn btn-default btn-xs text-nowrap">
                                        Buka Presensi
                                    </button>
                                @endif
                            </form>
                        </div>

                        <!-- AKSES KUIS -->
                        <div class="list-group-item d-flex align-items-center justify-content-between gap-2">
                            <div class="min-w-0">
                                <div class="text-sm font-weight-bold">Akses Kuis</div>
                                <div class="text-muted text-xs">
                                    {{ $training->is_quiz_active ? 'Kuis aktif untuk dikerjakan' : 'Pengerjaan kuis terkunci' }}
                                </div>
                            </div>
                            <form action="/training/{{ $training->id }}/toggle-quiz" method="POST" class="m-0">
                                @csrf
                                @if($training->is_quiz_active)
                                    <button type="submit" class="btn btn-default btn-xs text-danger text-nowrap">
                                        Kunci Kuis
                                    </button>
                                @else
                                    <button type="submit" class="btn btn-default btn-xs text-nowrap">
                                        Buka Kuis
                                    </button>
                                @endif
                            </form>
                        </div>

                        <!-- SYARAT SCREENSHOT -->
                        <div class="list-group-item d-flex align-items-center justify-content-between gap-2">
                            <div class="min-w-0">
                                <div class="text-sm font-weight-bold">Bukti Foto / SS</div>
                                <div class="text-muted text-xs">
                                    {{ $training->require_attendance_proof ? 'Wajib upload foto selfie/meeting' : 'Presensi tanpa upload foto' }}
                                </div>
                            </div>
                            <form action="/training/{{ $training->id }}/toggle-proof" method="POST" class="m-0">
                                @csrf
                                <button type="submit" class="btn btn-default btn-xs text-nowrap">
                                    {{ $training->require_attendance_proof ? 'Jadikan Opsional' : 'Wajibkan Foto' }}
                                </button>
                            </form>
                        </div>

                        <!-- MODE KUIS -->
                        <div class="list-group-item d-flex align-items-center justify-content-between gap-2">
                            <div class="min-w-0">
                                <div class="text-sm font-weight-bold">Mode Kuis</div>
                                <div class="text-muted text-xs">
                                    {{ $training->quiz_mode === 'game' ? 'Tampilan interaktif (Game)' : 'Tampilan ujian standar (Formal)' }}
                                </div>
                            </div>
                            <form action="/training/{{ $training->id }}/toggle-mode" method="POST" class="m-0">
                                @csrf
                                <button type="submit" class="btn btn-default btn-xs text-nowrap">
                                    {{ $training->quiz_mode === 'game' ? 'Pindah ke Formal' : 'Pindah ke Game' }}
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- TOMBOL BROADCAST WHATSAPP -->
                    <div class="card-footer">
                        <form action="/training/{{ $training->id }}/broadcast-wa" method="POST" class="m-0" onsubmit="return confirm(@js('Kirim notifikasi WhatsApp ke '.$training->participants->count().' peserta?'))">
                            @csrf
                            <button type="submit" class="btn btn-default btn-sm btn-block">
                                <i class="fab fa-whatsapp"></i> Broadcast WA ke Peserta
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABEL REKAP PESERTA & NILAI -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    Daftar Peserta & Nilai
                    <span class="text-muted text-sm font-weight-normal">({{ $training->participants->count() }} orang)</span>
                </h3>
                <div class="card-tools d-flex align-items-center flex-wrap gap-2">
                    <button type="button" class="btn btn-default btn-sm" id="btnToggleManualOffCamMode">
                        <i class="fas fa-user-edit"></i> Input Off Cam Manual
                    </button>
                    <input type="text" id="filterTableInput" class="form-control form-control-sm" placeholder="Cari peserta..." style="width: 200px;">
                </div>
            </div>

            <!-- BANNER NOTIFIKASI MODE MANUAL OFF CAM (MUNCUL KETIKA MODE AKTIF) -->
            <div id="bannerManualOffCamMode" class="alert alert-danger mx-3 mt-3 mb-0 d-none align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-start">
                    <i class="fas fa-video-slash mr-2 mt-1"></i>
                    <div>
                        <strong class="d-block">Mode cepat Off Cam aktif</strong>
                        <span class="text-sm">Klik <strong>"Tandai Off Cam"</strong> untuk mencatat. Gunakan tombol <strong>[+]</strong> untuk menambah frekuensi atau <strong>[-]</strong> untuk mengurangi hitungan jika salah.</span>
                    </div>
                </div>
                <button type="button" class="btn btn-default btn-sm text-nowrap" id="btnExitManualOffCamMode">
                    <i class="fas fa-check"></i> Selesai Mode Manual
                </button>
            </div>

            <div class="card-body table-responsive p-0" style="min-height: 260px;">
                <table class="table table-hover mb-0" id="participantsTable">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 50px;">No</th>
                            <th>Peserta</th>
                            <th>WhatsApp</th>
                            <th>Presensi</th>
                            <th>Kuis</th>
                            <th class="text-center">Nilai</th>
                            <th class="text-center" style="width: 110px;" id="thActionHeader">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($training->participants as $index => $part)
                        @php
                            $user = $part->user;
                            $userName = $user->nama_lengkap ?? ($part->name ?? 'Peserta');
                            $userDivisi = optional(optional($user)->divisi)->name ?? '-';
                            $userEmail = optional($user)->email ?? '-';
                            $userIdKaryawan = optional($user)->employee_id ?? '-';
                            $userNoWa = optional($user)->no_hp ?? '-';
                            $quiz = $part->quizResult;
                        @endphp
                        <tr>
                            <td class="text-center align-middle text-muted">
                                {{ $index + 1 }}
                            </td>
                            <td class="align-middle">
                                <span class="d-block font-weight-bold text-gray-950" title="{{ $userEmail }}">{{ $userName }}</span>
                                <span class="cell-sub">{{ $userIdKaryawan }} &middot; {{ $userDivisi }}</span>
                            </td>
                            <td class="align-middle text-nowrap">
                                <span class="d-block">{{ $userNoWa }}</span>
                                <span class="cell-sub">{{ $part->wa_sent_at ? 'WA terkirim' : 'Belum dikirim' }}</span>
                            </td>
                            <td class="align-middle text-nowrap">
                                @if($part->attendance_status === 'hadir')
                                    <span class="status-dot is-success">Hadir</span>
                                    <span class="cell-sub">
                                        {{ \Carbon\Carbon::parse($part->attended_at)->format('H:i') }} WIB
                                        @if($part->attendance_proof)
                                            &middot; <a href="javascript:void(0)" data-toggle="modal" data-target="#proofModal_{{ $part->id }}">Foto</a>
                                        @endif
                                    </span>
                                @elseif($part->attendance_status === 'tidak_hadir')
                                    <span class="status-dot is-danger">Tidak hadir</span>
                                    @if($part->attendance_notes)
                                        <span class="cell-sub text-truncate" style="max-width: 160px;" title="{{ $part->attendance_notes }}">{{ $part->attendance_notes }}</span>
                                    @endif
                                @else
                                    <span class="status-dot">Belum absen</span>
                                @endif

                                <div class="offcam-badge-box" id="offcamBadge_{{ $part->id }}">
                                    @if($part->is_off_cam && ($part->off_cam_count ?? 1) > 0)
                                        <span class="cell-flag is-danger" title="Terdeteksi Off Cam di Zoom{{ $part->zoom_display_name ? ': '.$part->zoom_display_name : '' }}">
                                            <i class="fas fa-video-slash"></i> Off cam {{ $part->off_cam_count ?: 1 }}x
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="align-middle text-nowrap">
                                @if($quiz)
                                    <span class="d-block">Selesai</span>
                                    <span class="cell-sub">
                                        {{ $quiz->correct_answers }} PG benar
                                        @if($quiz->essay_status === 'graded')
                                            &middot; Essay {{ $quiz->essay_score }}
                                        @endif
                                        &middot; {{ \Carbon\Carbon::parse($quiz->submitted_at)->format('d/m H:i') }}
                                    </span>
                                    @if($quiz->essay_status === 'pending')
                                        <span class="cell-flag is-warning"><i class="fas fa-hourglass-half"></i> Essay perlu dinilai</span>
                                    @endif
                                @else
                                    <span class="text-muted">Belum mengerjakan</span>
                                @endif
                            </td>
                            <td class="text-center align-middle text-nowrap">
                                @if($quiz)
                                    <span class="score-value {{ $quiz->score < 50 ? 'is-danger' : ($quiz->score < 75 ? 'is-warning' : '') }}">{{ $quiz->score }}</span>
                                    @if($quiz->essay_status === 'pending')
                                        <span class="cell-sub">skor PG</span>
                                    @endif
                                    @if($quiz->is_force_submitted)
                                        <span class="cell-flag is-danger" title="Dikirim otomatis karena kecurangan"><i class="fas fa-exclamation-triangle"></i> Curang (Auto-Submit)</span>
                                    @elseif(($quiz->tab_switch_count ?? 0) > 0)
                                        <span class="cell-flag is-warning" title="Peserta berpindah tab saat mengerjakan"><i class="fas fa-exchange-alt"></i> Pindah tab {{ $quiz->tab_switch_count }}x</span>
                                    @endif
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-center align-middle">
                                <!-- DEFAULT ACTION DROPDOWN -->
                                <div class="dropdown default-action-wrapper">
                                    <button class="btn btn-default btn-xs dropdown-toggle" type="button" data-toggle="dropdown" data-boundary="window" aria-haspopup="true" aria-expanded="false">
                                        Aksi
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right" style="max-height: 280px; overflow-y: auto;">
                                        @if($quiz && $quiz->answers)
                                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#answerModal_{{ $quiz->id }}">
                                                Lembar Jawaban {{ $training->questions()->where('type', 'essay')->count() > 0 ? '& Review Essay' : '' }}
                                            </a>
                                            <div class="dropdown-divider"></div>
                                        @endif

                                        <a class="dropdown-item copy-link-btn" href="javascript:void(0)" data-link="{{ url('/training/portal/' . $part->token) }}">
                                            Salin Link Peserta
                                        </a>

                                        <form action="/training/{{ $training->id }}/send-wa/{{ $part->id }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="dropdown-item">
                                                Kirim Notifikasi WA
                                            </button>
                                        </form>

                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editAttendanceModal_{{ $part->id }}">
                                            Atur Presensi Manual
                                        </a>

                                        @if($part->attendance_status !== 'pending')
                                            <form action="/training/{{ $training->id }}/reset-attendance/{{ $part->id }}" method="POST" class="d-inline" onsubmit="return confirm(@js('Kembalikan status presensi '.$userName.' menjadi Belum Absen?'))">
                                                @csrf
                                                <button type="submit" class="dropdown-item text-danger">
                                                    Reset Presensi (Belum Absen)
                                                </button>
                                            </form>
                                        @endif

                                        @if($quiz)
                                            <div class="dropdown-divider"></div>
                                            <form action="/training/{{ $training->id }}/reset-quiz/{{ $part->id }}" method="POST" class="d-inline" onsubmit="return confirm(@js('Reset hasil kuis untuk '.$userName.'?'))">
                                                @csrf
                                                <button type="submit" class="dropdown-item text-danger">
                                                    Reset Kuis Peserta
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>

                                <!-- TOMBOL TANDAI OFF CAM (STEPPER COUNTER 1-KLIK) -->
                                <div class="quick-offcam-action-wrapper d-none" id="offcamActionWrapper_{{ $part->id }}">
                                    @if(!$part->is_off_cam || ($part->off_cam_count ?? 0) <= 0)
                                        <button type="button" class="btn btn-default btn-xs text-danger text-nowrap btn-offcam-step" data-id="{{ $part->id }}" data-action="increment" data-name="{{ $userName }}" title="Tandai peserta ini Off Cam">
                                            <i class="fas fa-video-slash"></i> Tandai Off Cam
                                        </button>
                                    @else
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-default btn-offcam-step" data-id="{{ $part->id }}" data-action="decrement" data-name="{{ $userName }}" title="Kurangi frekuensi (-1)">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                            <span class="btn btn-default text-danger text-nowrap" style="pointer-events: none;">
                                                <i class="fas fa-video-slash"></i> <span id="offcamCountText_{{ $part->id }}">{{ $part->off_cam_count ?: 1 }}x</span> Off
                                            </span>
                                            <button type="button" class="btn btn-default btn-offcam-step" data-id="{{ $part->id }}" data-action="increment" data-name="{{ $userName }}" title="Tambah frekuensi (+1)">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                    @endif
                                </div>

                                {{-- Modal per peserta tetap di dalam sel ini: di luar <td> browser memindahkannya keluar tabel dan memisahkan <form> dari isinya. --}}
                                <!-- MODAL LEMBAR JAWABAN -->
                                @if($quiz && $quiz->answers)
                                <div class="modal fade text-left" id="answerModal_{{ $quiz->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                                    <div class="modal-dialog modal-lg" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">
                                                    Lembar Jawaban: {{ $userName }}
                                                </h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <!-- STATS SUMMARY -->
                                                <div class="row no-gutters mb-4 border rounded text-center">
                                                    <div class="col-3 p-3 border-right">
                                                        <p class="stat-tile-label">Nilai Akhir</p>
                                                        <p class="stat-tile-value">{{ $quiz->score }}</p>
                                                    </div>
                                                    <div class="col-3 p-3 border-right">
                                                        <p class="stat-tile-label">Nilai PG ({{ $quiz->correct_answers }} Benar)</p>
                                                        <p class="stat-tile-value">{{ $quiz->mc_score ?? $quiz->score }}</p>
                                                    </div>
                                                    <div class="col-3 p-3 border-right">
                                                        <p class="stat-tile-label">Nilai Essay</p>
                                                        @if($quiz->essay_status === 'graded')
                                                            <p class="stat-tile-value">{{ $quiz->essay_score }}</p>
                                                        @else
                                                            <p class="text-sm text-muted mb-0 mt-1">Belum Dinilai</p>
                                                        @endif
                                                    </div>
                                                    <div class="col-3 p-3">
                                                        <p class="stat-tile-label">Waktu Submit</p>
                                                        <p class="text-sm font-weight-bold mb-0 mt-1">{{ \Carbon\Carbon::parse($quiz->submitted_at)->format('d/m/Y H:i') }}</p>
                                                    </div>
                                                </div>

                                                <!-- CATATAN ANTI-CURANG AUDIT -->
                                                @if(($quiz->tab_switch_count ?? 0) > 0 || $quiz->is_force_submitted)
                                                <div class="alert alert-{{ $quiz->is_force_submitted ? 'danger' : 'warning' }} mb-4 text-left">
                                                    <div>
                                                        <strong class="d-block mb-1">
                                                            Catatan Integritas: {{ $quiz->is_force_submitted ? 'Kuis Dikunci Otomatis (Melebihi Toleransi Pindah Tab)' : 'Terdeteksi Keluar dari Halaman Kuis' }}
                                                        </strong>
                                                        <div class="text-sm">
                                                            Peserta terdeteksi keluar dari halaman kuis / membuka aplikasi lain sebanyak <strong>{{ $quiz->tab_switch_count }} kali</strong>.
                                                            @if(!empty($quiz->violation_logs) && is_array($quiz->violation_logs))
                                                                <div class="mt-2 pt-2 border-top">
                                                                    <span class="font-weight-bold d-block mb-1 text-xs">Riwayat Deteksi Pelanggaran:</span>
                                                                    <ul class="mb-0 pl-3 text-xs">
                                                                        @foreach($quiz->violation_logs as $log)
                                                                            <li>
                                                                                <strong>{{ $log['type'] ?? 'Pindah Tab Browser' }}</strong>
                                                                                (Pelanggaran ke-{{ $log['count'] ?? '1' }}) pada
                                                                                {{ isset($log['time']) ? \Carbon\Carbon::parse($log['time'])->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') . ' WIB' : '-' }}
                                                                            </li>
                                                                        @endforeach
                                                                    </ul>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                                @endif

                                                <!-- FORM PENILAIAN ESSAY OLEH PEMATERI -->
                                                @if($training->questions()->where('type', 'essay')->count() > 0)
                                                <div class="card mb-4">
                                                    <div class="card-header">
                                                        <h3 class="card-title">Penilaian Soal Essay oleh Pemateri</h3>
                                                    </div>
                                                    <div class="card-body">
                                                        <form action="/training/{{ $training->id }}/grade-essay/{{ $quiz->id }}" method="POST">
                                                            @csrf
                                                            <div class="row align-items-end">
                                                                <div class="col-md-3 mb-2 mb-md-0">
                                                                    <label class="text-xs mb-1">Nilai Essay (0-100)</label>
                                                                    <input type="number" name="essay_score" class="form-control form-control-sm" min="0" max="100" step="1" value="{{ $quiz->essay_score !== null ? round($quiz->essay_score) : '' }}" placeholder="Contoh: 90" required>
                                                                </div>
                                                                <div class="col-md-6 mb-2 mb-md-0">
                                                                    <label class="text-xs mb-1">Catatan Evaluasi (Opsional)</label>
                                                                    <input type="text" name="essay_feedback" class="form-control form-control-sm" value="{{ $quiz->essay_feedback ?? '' }}" placeholder="Contoh: Pemahaman konsep materi baik.">
                                                                </div>
                                                                <div class="col-md-3">
                                                                    <button type="submit" class="btn btn-primary btn-sm btn-block">
                                                                        Simpan Nilai
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            @if($quiz->essay_status === 'graded')
                                                                <div class="text-xs text-success mt-2">
                                                                    Nilai essay tersimpan {{ $quiz->essay_score }}/100. Nilai akhir total dihitung gabungan PG dan Essay.
                                                                </div>
                                                            @else
                                                                <div class="text-xs text-muted mt-2">
                                                                    Nilai essay akan otomatis dikombinasikan dengan nilai PG secara proporsional.
                                                                </div>
                                                            @endif
                                                        </form>
                                                    </div>
                                                </div>
                                                @endif

                                                <h6 class="section-title mb-3">Rincian Pertanyaan & Jawaban Peserta</h6>
                                                @foreach($training->questions as $qIndex => $question)
                                                    @php
                                                        $ansData = $quiz->answers[$question->id] ?? null;
                                                        $userAns = $ansData['user_answer'] ?? null;
                                                        $isEssay = ($question->type === 'essay');
                                                        $isCorrect = $ansData['is_correct'] ?? false;
                                                    @endphp
                                                    <div class="p-3 mb-3 rounded border">
                                                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                                            <div>
                                                                <span class="badge badge-secondary mr-1">
                                                                    #{{ $qIndex + 1 }} {{ $isEssay ? '(Essay)' : '(PG)' }}
                                                                </span>
                                                                <strong>{{ $question->question }}</strong>
                                                                @if($question->imageUrl())
                                                                    <img src="{{ $question->imageUrl() }}" alt="Gambar soal" class="quiz-img quiz-opt-img mt-2">
                                                                @endif
                                                            </div>
                                                            <div>
                                                                @if($isEssay)
                                                                    <span class="badge badge-primary">Tersimpan</span>
                                                                @else
                                                                    <span class="badge {{ $isCorrect ? 'badge-success' : 'badge-danger' }}">
                                                                        {{ $isCorrect ? 'Benar' : 'Salah' }}
                                                                    </span>
                                                                @endif
                                                            </div>
                                                        </div>

                                                        <div class="text-sm mt-2 pt-2 border-top">
                                                            @if($isEssay)
                                                                <div class="mb-2">
                                                                    <span class="text-muted d-block">Jawaban Essay Peserta:</span>
                                                                    <div class="p-2 bg-light rounded border mt-1" style="white-space: pre-wrap;">{{ $userAns ?: '(Tidak ada jawaban)' }}</div>
                                                                </div>
                                                                @if($question->correct_answer)
                                                                    <div class="text-muted"><strong>Pedoman Acuan Jawaban:</strong> {{ $question->correct_answer }}</div>
                                                                @endif
                                                            @else
                                                                <div class="mb-1">
                                                                    Jawaban Peserta: <strong>{{ strtoupper($userAns ?? '-') }}</strong> ({{ $question->{'option_' . $userAns} ?? '-' }})
                                                                    @if($userAns && $question->optionImageUrl($userAns))
                                                                        <img src="{{ $question->optionImageUrl($userAns) }}" alt="Gambar jawaban peserta" class="quiz-img quiz-img-thumb mt-1">
                                                                    @endif
                                                                </div>
                                                                @if($question->correct_answer)
                                                                    <div class="text-success">
                                                                        Kunci Jawaban: <strong>{{ strtoupper($question->correct_answer) }}</strong> ({{ $question->{'option_' . $question->correct_answer} ?? '-' }})
                                                                        @if($question->optionImageUrl($question->correct_answer))
                                                                            <img src="{{ $question->optionImageUrl($question->correct_answer) }}" alt="Gambar kunci jawaban" class="quiz-img quiz-img-thumb mt-1">
                                                                        @endif
                                                                    </div>
                                                                @endif
                                                            @endif

                                                            @if($question->explanation)
                                                                <div class="text-muted mt-2 pt-2 border-top font-italic">Catatan: {{ $question->explanation }}</div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endif

                                <!-- MODAL BUKTI SCREENSHOT -->
                                @if($part->attendance_proof)
                                <div class="modal fade text-left" id="proofModal_{{ $part->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                                    <div class="modal-dialog modal-md modal-dialog-centered" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <div>
                                                    <h5 class="modal-title">Bukti Kehadiran Peserta</h5>
                                                    <small class="text-muted">{{ $userName }} &bull; {{ $userDivisi }}</small>
                                                </div>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body text-center">
                                                <div class="mb-2 text-left text-sm text-muted">
                                                    Waktu Presensi: <strong>{{ \Carbon\Carbon::parse($part->attended_at)->format('d F Y, H:i') }} WIB</strong>
                                                </div>
                                                <div class="bg-light p-2 rounded border">
                                                    <a href="{{ $part->attendance_proof_url }}" target="_blank" title="Buka gambar ukuran asli">
                                                        <img src="{{ $part->attendance_proof_url }}" alt="Screenshot Bukti" class="img-fluid rounded" style="max-height: 380px; object-fit: contain;">
                                                    </a>
                                                </div>
                                                <small class="text-muted mt-2 d-block">
                                                    Klik foto di atas untuk membuka ukuran penuh di tab baru.
                                                </small>
                                            </div>
                                            <div class="modal-footer">
                                                <a href="{{ $part->attendance_proof_url }}" target="_blank" download class="btn btn-default">
                                                    Unduh Foto
                                                </a>
                                                <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endif

                                <!-- MODAL ATUR PRESENSI MANUAL -->
                                <div class="modal fade text-left" id="editAttendanceModal_{{ $part->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                                    <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">
                                                    Atur Presensi Peserta
                                                </h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <form action="/training/{{ $training->id }}/update-attendance/{{ $part->id }}" method="POST">
                                                @csrf
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <strong class="d-block text-sm">{{ $userName }}</strong>
                                                        <span class="text-muted text-xs">{{ $userIdKaryawan }} &bull; {{ $userDivisi }}</span>
                                                    </div>
                                                    <div class="form-group mb-3">
                                                        <label class="mb-1">Status Kehadiran</label>
                                                        <select name="attendance_status" class="form-control form-control-sm">
                                                            <option value="pending" {{ $part->attendance_status === 'pending' ? 'selected' : '' }}>Belum Absen</option>
                                                            <option value="hadir" {{ $part->attendance_status === 'hadir' ? 'selected' : '' }}>Hadir</option>
                                                            <option value="tidak_hadir" {{ $part->attendance_status === 'tidak_hadir' ? 'selected' : '' }}>Tidak Hadir</option>
                                                        </select>
                                                        <small class="text-muted d-block mt-1">Pilih 'Belum Absen' untuk mengizinkan peserta absen kembali.</small>
                                                    </div>
                                                    <div class="form-group mb-0">
                                                        <label class="mb-1">Catatan / Alasan</label>
                                                        <input type="text" name="attendance_notes" class="form-control form-control-sm" value="{{ $part->attendance_notes }}" placeholder="Keterangan izin/sakit/hadir...">
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-primary">Simpan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8">
                                <x-empty-state icon="fa-users" title="Belum ada peserta" text="Belum ada peserta yang terdaftar pada sesi pelatihan ini." />
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

{{-- MODAL UPLOAD VIDEO MATERI PELATIHAN --}}
<div class="modal fade" id="uploadTrainingVideoModal" tabindex="-1" role="dialog" aria-labelledby="uploadTrainingVideoModalLabel" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="uploadTrainingVideoModalLabel">Upload Video Materi Pelatihan</h5>
                    <small class="text-muted">Video akan otomatis masuk ke Galeri Video Materi dengan judul pelatihan ini.</small>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('video.store') }}" method="POST" enctype="multipart/form-data" id="uploadTrainingVideoForm">
                @csrf
                <input type="hidden" name="training_id" value="{{ $training->id }}">
                <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">

                <div class="modal-body">
                    {{-- Judul Video Otomatis --}}
                    <div class="form-group mb-3">
                        <label>
                            Judul Video Materi <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="title" class="form-control" value="{{ $training->title }}" required placeholder="Contoh: {{ $training->title }}">
                        <small class="text-muted">Terisi otomatis sesuai judul sesi pelatihan saat ini.</small>
                    </div>

                    {{-- Pilihan Tipe Video: Upload File vs Tautan Link --}}
                    <div class="form-group mb-3">
                        <label class="d-block">Sumber Video <span class="text-danger">*</span></label>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="custom-control custom-radio">
                                <input type="radio" id="showTypeFile" name="video_type" value="file" class="custom-control-input" checked onchange="toggleVideoTypeInShow('file')">
                                <label class="custom-control-label" for="showTypeFile">
                                    Upload File Video
                                </label>
                            </div>
                            <div class="custom-control custom-radio">
                                <input type="radio" id="showTypeLink" name="video_type" value="link" class="custom-control-input" onchange="toggleVideoTypeInShow('link')">
                                <label class="custom-control-label" for="showTypeLink">
                                    Tautan / Link (YouTube / Drive / Zoom)
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Group 1: File Input --}}
                    <div class="form-group mb-3" id="showFileInputGroup">
                        <label>Pilih File Video Rekaman <span class="text-danger">*</span></label>
                        <div class="custom-file">
                            <input type="file" name="video_file" id="showVideoFileInput" class="custom-file-input" accept="video/mp4,video/webm,video/ogg,video/quicktime,video/x-matroska" required onchange="updateShowFileName(this)">
                            <label class="custom-file-label" id="showVideoFileLabel" for="showVideoFileInput">Pilih file video (MP4, MKV, WEBM, MOV)...</label>
                        </div>
                        <small class="text-muted">Maksimal ukuran file video: 2 GB.</small>
                    </div>

                    {{-- Group 2: Link Input --}}
                    <div class="form-group mb-3" id="showLinkInputGroup" style="display: none;">
                        <label>URL / Tautan Video <span class="text-danger">*</span></label>
                        <input type="url" name="video_link" id="showVideoLinkInput" class="form-control" placeholder="https://www.youtube.com/watch?v=... atau https://drive.google.com/file/d/...">
                        <small class="text-muted">Mendukung link YouTube, Zoom Cloud Recording, atau Google Drive.</small>
                    </div>

                    {{-- Deskripsi Otomatis --}}
                    <div class="form-group mb-3">
                        <label>Deskripsi & Catatan Sesi (Opsional)</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Rangkuman materi atau topik yang dibahas...">Rekaman video materi sesi pelatihan {{ $training->title }} yang dilaksanakan pada {{ \Carbon\Carbon::parse($training->training_date)->format('d F Y') }}.</textarea>
                    </div>

                    {{-- Thumbnail Cover (Opsional) --}}
                    <div class="form-group mb-0">
                        <label>Cover / Thumbnail Video (Opsional)</label>
                        <div class="custom-file">
                            <input type="file" name="thumbnail" id="showThumbInput" class="custom-file-input" accept="image/png,image/jpeg,image/webp" onchange="updateShowThumbLabel(this)">
                            <label class="custom-file-label" id="showThumbLabel" for="showThumbInput">Pilih gambar thumbnail (JPG, PNG, WEBP)...</label>
                        </div>
                        <small class="text-muted">Biarkan kosong jika ingin menggunakan cover video bawaan sistem.</small>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-primary" id="btnSubmitVideo">
                        Simpan ke Video Materi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL ATTACH DOKUMEN MATERI PELATIHAN --}}
<div class="modal fade" id="attachDocumentModal" tabindex="-1" role="dialog" aria-labelledby="attachDocumentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="attachDocumentModalLabel">Lampirkan Dokumen Materi</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="/training/{{ $training->id }}/attach-document" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="form-group mb-0">
                        <label>Pilih Dokumen <span class="text-danger">*</span></label>
                        <select name="document_ids[]" id="modalDocSelect" class="form-control select2" multiple="multiple" style="width: 100%;" required data-placeholder="-- Pilih dokumen untuk dilampirkan --">
                            @foreach($allDocuments as $d)
                                @php
                                    $alreadyAttached = $training->documents->contains('id', $d->id);
                                @endphp
                                <option value="{{ $d->id }}" {{ $alreadyAttached ? 'selected' : '' }}>
                                    {{ $d->name }} ({{ $d->dokumentype->name ?? 'Dokumen' }}) {{ $alreadyAttached ? '[Sudah Terlampir]' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Simpan Lampiran
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL SCAN PRESENSI ZOOM (DETEKSI OFF CAM) --}}
<div class="modal fade" id="scanZoomModal" tabindex="-1" role="dialog" aria-labelledby="scanZoomModalLabel" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            {{-- MODAL HEADER --}}
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="scanZoomModalLabel">
                        <i class="fas fa-camera-retro text-muted mr-1"></i> Pindai Presensi Zoom: Deteksi Off Cam
                    </h5>
                    <small class="text-muted">
                        Menganalisis screenshot Zoom (hingga 10 file) dengan Vision AI untuk mendeteksi peserta yang mematikan kamera dan mencocokkan nama secara otomatis.
                    </small>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                {{-- TAHAP 1: UPLOAD & KOMPRESI (CLIENT-SIDE SOLUSI 1) --}}
                <div id="zoomStepUpload">
                    <div class="card mb-0">
                        <div class="card-body">
                            <label for="zoomFileInput" class="zoom-drop-area d-block text-center p-4 m-0 w-100" id="zoomDropArea">
                                <span class="stat-tile-icon mb-3"><i class="fas fa-cloud-upload-alt"></i></span>
                                <span class="section-title d-block mb-1">
                                    Klik untuk Memilih atau Tarik Screenshot Zoom ke Sini
                                </span>
                                <p class="text-muted text-sm mb-2">
                                    Mendukung hingga <strong>10 screenshot</strong> sekaligus (Format: JPG, PNG, WEBP).
                                </p>
                                <span class="badge badge-secondary mb-2">
                                    <i class="fas fa-bolt"></i> Solusi 1: Kompresi Otomatis di Browser (~200KB/file, proses super cepat & hemat kuota)
                                </span>
                                <div class="mt-2">
                                    <span class="btn btn-default btn-sm">
                                        <i class="fas fa-folder-open"></i> Pilih Screenshot dari Komputer
                                    </span>
                                </div>
                            </label>
                            <input type="file" id="zoomFileInput" multiple accept="image/png,image/jpeg,image/webp" style="display: none;">

                            <div class="mt-3 text-center p-2 rounded bg-light border">
                                <span class="text-muted text-sm">Tidak punya screenshot? Ingin mendata langsung?</span>
                                <button type="button" class="btn btn-link btn-sm p-0 ml-1" id="btnDirectManualInput">
                                    <i class="fas fa-edit"></i> Catat Peserta Off Cam Secara Manual &rarr;
                                </button>
                            </div>

                            {{-- ALERT ERROR --}}
                            <div class="alert alert-danger mt-3 d-none" id="zoomUploadAlert"></div>

                            {{-- PREVIEW DAFTAR SCREENSHOT --}}
                            <div class="mt-4 d-none" id="zoomPreviewSection">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong class="text-sm">
                                        Screenshot Terpilih (<span id="zoomFileCount">0</span>/10 file):
                                    </strong>
                                    <button type="button" class="btn btn-default btn-xs text-danger" id="btnClearFiles">
                                        <i class="fas fa-trash-alt"></i> Hapus Semua
                                    </button>
                                </div>
                                <div class="row" id="zoomThumbGrid" style="row-gap: 12px;"></div>
                            </div>
                        </div>
                        <div class="card-footer d-flex justify-content-between align-items-center">
                            <button type="button" class="btn btn-default" data-dismiss="modal">
                                Batal
                            </button>
                            <button type="button" class="btn btn-primary" id="btnStartScan" disabled>
                                <i class="fas fa-magic mr-1"></i> Mulai Pindai dengan AI
                            </button>
                        </div>
                    </div>
                </div>

                {{-- TAHAP 2: PROSES LOADING --}}
                <div id="zoomStepLoading" class="d-none text-center py-5">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <h5 class="section-title mb-2" id="zoomLoadingTitle">
                        Menganalisis Screenshot Zoom...
                    </h5>
                    <p class="text-muted text-sm mb-3" id="zoomLoadingSub">
                        Memproses gambar dan mendeteksi kotak video peserta...
                    </p>
                    <div class="progress mx-auto" style="max-width: 450px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div>
                    </div>
                    <div class="mt-3 text-muted text-xs font-italic">
                        Mohon tunggu, AI Vision sedang memindai seluruh tile dan mencocokkan kemiripan nama dengan database peserta kelas ini.
                    </div>
                </div>

                {{-- TAHAP 3: REVIEW & VERIFIKASI MANUAL PENGAWAS --}}
                <div id="zoomStepReview" class="d-none">
                    <div class="alert alert-warning d-flex align-items-start mb-3">
                        <i class="fas fa-user-clock mr-3 mt-1"></i>
                        <div>
                            <strong class="d-block mb-1">
                                Verifikasi Hasil Pindaian AI (<span id="countReviewOffCam">0</span> Peserta Off Cam Terdeteksi)
                            </strong>
                            <span class="text-sm">
                                Hanya peserta yang <strong>mematikan kamera (Off Cam)</strong> yang dicatat. Periksa dan sesuaikan data di bawah sebelum disimpan.
                            </span>
                        </div>
                    </div>

                    {{-- ACTION BAR KONTROL --}}
                    <div class="card mb-3">
                        <div class="card-body py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="d-flex align-items-center flex-wrap gap-3">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="checkSelectAll" checked>
                                    <label class="custom-control-label text-sm" for="checkSelectAll">
                                        Pilih Semua (<span id="textSelectedCount">0</span> terpilih)
                                    </label>
                                </div>
                                <div class="border-left pl-3">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="checkMarkTidakHadir">
                                        <label class="custom-control-label text-muted text-sm" for="checkMarkTidakHadir">
                                            Ubah juga status kehadiran menjadi <strong>"Tidak Hadir"</strong>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <button type="button" class="btn btn-default btn-sm" id="btnAddManualRow">
                                    <i class="fas fa-plus"></i> Tambah Peserta Manual
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- TABEL VERIFIKASI OFF CAM --}}
                    <div class="card mb-3">
                        <div class="card-body table-responsive p-0" style="max-height: 420px;">
                            <table class="table table-hover mb-0 text-sm" id="zoomReviewTable">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="width: 45px;">Pilih</th>
                                        <th class="text-center" style="width: 40px;">No</th>
                                        <th style="min-width: 170px;">Nama di Zoom</th>
                                        <th style="min-width: 250px;">Peserta Terdaftar (Database)</th>
                                        <th class="text-center" style="width: 140px;">Kemiripan AI</th>
                                        <th>Alasan / Bukti Visual</th>
                                        <th class="text-center" style="width: 60px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="zoomReviewTbody">
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- FOOTER REVIEW --}}
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3 pt-2">
                        <button type="button" class="btn btn-default" id="btnBackToUpload">
                            <i class="fas fa-arrow-left"></i> Scan Ulang / Ganti Screenshot
                        </button>
                        <button type="button" class="btn btn-primary" id="btnConfirmSave">
                            <i class="fas fa-check-circle mr-1"></i> Konfirmasi & Simpan Catatan Off Cam
                        </button>
                    </div>
                </div>

                {{-- TAHAP 4: SEMUA ON CAM --}}
                <div id="zoomStepAllOnCam" class="d-none">
                    <x-empty-state icon="fa-check-circle" title="Semua peserta terdeteksi On Cam" text="Tidak ditemukan peserta yang mematikan kamera pada seluruh screenshot Zoom yang diunggah. Semua peserta tampak aktif menyalakan kamera.">
                        <button type="button" class="btn btn-default" data-dismiss="modal">
                            Tutup
                        </button>
                    </x-empty-state>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- MODAL QUICK OFF CAM UNTUK SATU PESERTA --}}
<div class="modal fade text-left" id="singleQuickOffCamModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    Tandai Off Cam (Manual)
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="quickParticipantId">
                <div class="mb-3">
                    <span class="text-muted text-xs d-block">Nama Peserta</span>
                    <strong class="d-block" id="quickParticipantName">-</strong>
                </div>
                <div class="form-group mb-3">
                    <label class="mb-1">Nama di Zoom / Catatan Sesi</label>
                    <input type="text" id="quickZoomName" class="form-control form-control-sm" placeholder="Contoh: M. Rizky (Off Cam sesi 1)">
                    <small class="text-muted text-xs">Kosongkan jika ingin otomatis memakai nama peserta.</small>
                </div>
                <div class="custom-control custom-checkbox mb-0">
                    <input type="checkbox" class="custom-control-input" id="quickMarkTidakHadir">
                    <label class="custom-control-label text-sm text-muted" for="quickMarkTidakHadir">
                        Ubah status kehadiran menjadi <strong>"Tidak Hadir"</strong>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="btnSaveQuickOffCam">
                    Simpan Off Cam
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer')
<script>
function toggleVideoTypeInShow(type) {
    var fileGroup = document.getElementById('showFileInputGroup');
    var linkGroup = document.getElementById('showLinkInputGroup');
    var fileInput = document.getElementById('showVideoFileInput');
    var linkInput = document.getElementById('showVideoLinkInput');

    if (type === 'file') {
        fileGroup.style.display = 'block';
        linkGroup.style.display = 'none';
        fileInput.setAttribute('required', 'required');
        linkInput.removeAttribute('required');
    } else {
        fileGroup.style.display = 'none';
        linkGroup.style.display = 'block';
        fileInput.removeAttribute('required');
        linkInput.setAttribute('required', 'required');
    }
}

function updateShowFileName(input) {
    if (input.files && input.files[0]) {
        document.getElementById('showVideoFileLabel').innerText = input.files[0].name;
    }
}

function updateShowThumbLabel(input) {
    if (input.files && input.files[0]) {
        document.getElementById('showThumbLabel').innerText = input.files[0].name;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const copyButtons = document.querySelectorAll('.copy-link-btn');
    copyButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const link = this.getAttribute('data-link');
            navigator.clipboard.writeText(link).then(() => {
                alert('Tautan akses peserta berhasil disalin:\n' + link);
            }).catch(err => {
                prompt('Salin tautan:', link);
            });
        });
    });

    const filterInput = document.getElementById('filterTableInput');
    const table = document.getElementById('participantsTable');
    if (filterInput && table) {
        filterInput.addEventListener('keyup', function() {
            const filter = filterInput.value.toLowerCase();
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            for (let i = 0; i < rows.length; i++) {
                const text = rows[i].textContent || rows[i].innerText;
                if (text.toLowerCase().indexOf(filter) > -1) {
                    rows[i].style.display = "";
                } else {
                    rows[i].style.display = "none";
                }
            }
        });
    }

    const videoForm = document.getElementById('uploadTrainingVideoForm');
    if (videoForm) {
        let isSubmitting = false;
        videoForm.addEventListener('submit', function(e) {
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }
            isSubmitting = true;
            const submitBtn = document.getElementById('btnSubmitVideo');
            if (submitBtn) {
                submitBtn.style.pointerEvents = 'none';
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm mr-1" role="status" aria-hidden="true"></span> Mengunggah Video, mohon tunggu...';
                setTimeout(function() {
                    submitBtn.disabled = true;
                }, 50);
            }
            videoForm.style.pointerEvents = 'none';
        });
    }
});

// ---------------------------------------------------------------
// LIVE STATS POLLING — update counter admin setiap 10 detik
// ---------------------------------------------------------------
(function () {
    const STATS_URL = @js(route('training.live-stats', $training));

    function updateStat(id, value) {
        const el = document.getElementById(id);
        if (el && el.textContent != value) {
            el.textContent = value;
            el.style.transition = 'color 0.3s';
            const orig = el.style.color;
            el.style.color = 'var(--primary-600)';
            setTimeout(function() { el.style.color = orig; }, 600);
        }
    }

    function pollStats() {
        fetch(STATS_URL, { headers: { 'Accept': 'application/json' } })
            .then(function(res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function(data) {
                updateStat('statTotal',    data.total);
                updateStat('statAttended', data.attended);
                updateStat('statAbsent',   data.absent);
                updateStat('statPending',  data.pending);
                updateStat('statQuiz',     data.quizSubmitted);
                updateStat('statAvg',      data.avgScore);
            })
            .catch(function() { /* silent fail */ });
    }

    setInterval(pollStats, 10000);
})();

if (typeof $ !== 'undefined') {
    $(function() {
        if ($.fn.select2) {
            $('#modalDocSelect').select2({
                dropdownParent: $('#attachDocumentModal'),
                placeholder: '-- Pilih dokumen untuk dilampirkan --',
                width: '100%'
            });
        }
    });
}

// ===============================================================
// ZOOM ATTENDANCE AI SCANNER (OFF-CAM DETECTOR) - CLIENT JS
// ===============================================================
(function () {
    const TRAINING_ID = {{ $training->id }};
    const CSRF_TOKEN = '{{ csrf_token() }}';
@php
    $participantsForJs = $training->participants->map(function($p) {
        return [
            'id' => $p->id,
            'name' => $p->user->nama_lengkap ?? ($p->name ?? 'Peserta'),
            'id_karyawan' => $p->user->employee_id ?? '-',
            'divisi' => optional(optional($p->user)->divisi)->name ?? '-',
            'is_off_cam' => (bool)$p->is_off_cam,
        ];
    })->values();
@endphp
    const OFFICIAL_PARTICIPANTS = {!! json_encode($participantsForJs) !!};

    // DOM Elements
    const dropArea = document.getElementById('zoomDropArea');
    const fileInput = document.getElementById('zoomFileInput');
    const previewSection = document.getElementById('zoomPreviewSection');
    const thumbGrid = document.getElementById('zoomThumbGrid');
    const fileCountEl = document.getElementById('zoomFileCount');
    const btnClearFiles = document.getElementById('btnClearFiles');
    const btnStartScan = document.getElementById('btnStartScan');
    const uploadAlert = document.getElementById('zoomUploadAlert');

    const stepUpload = document.getElementById('zoomStepUpload');
    const stepLoading = document.getElementById('zoomStepLoading');
    const stepReview = document.getElementById('zoomStepReview');
    const stepAllOnCam = document.getElementById('zoomStepAllOnCam');

    const reviewTbody = document.getElementById('zoomReviewTbody');
    const countReviewOffCam = document.getElementById('countReviewOffCam');
    const textSelectedCount = document.getElementById('textSelectedCount');
    const checkSelectAll = document.getElementById('checkSelectAll');
    const checkMarkTidakHadir = document.getElementById('checkMarkTidakHadir');
    const btnAddManualRow = document.getElementById('btnAddManualRow');
    const btnBackToUpload = document.getElementById('btnBackToUpload');
    const btnConfirmSave = document.getElementById('btnConfirmSave');

    let selectedFiles = []; // array of { file, base64, origSize, compSize }
    let loadingInterval = null;

    // Helper: Format bytes to KB/MB
    function formatBytes(bytes) {
        if (!bytes || bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    // Helper: Client-side Canvas Image Compression (Solusi 1)
    function compressImage(file, maxWidth = 1600, quality = 0.8) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = e => {
                const img = new Image();
                img.src = e.target.result;
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    let w = img.width;
                    let h = img.height;
                    if (w > maxWidth) {
                        h = Math.round((h * maxWidth) / w);
                        w = maxWidth;
                    }
                    canvas.width = w;
                    canvas.height = h;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, w, h);
                    const base64 = canvas.toDataURL('image/jpeg', quality);
                    const compSize = Math.round((base64.length - 'data:image/jpeg;base64,'.length) * 0.75);
                    resolve({
                        file: file,
                        name: file.name,
                        origSize: file.size,
                        compSize: compSize,
                        base64: base64
                    });
                };
                img.onerror = err => reject(err);
            };
            reader.onerror = err => reject(err);
        });
    }

    // Drag and Drop Events
    if (dropArea && fileInput) {
        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, e => {
                e.preventDefault();
                e.stopPropagation();
                dropArea.classList.add('is-dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, e => {
                e.preventDefault();
                e.stopPropagation();
                dropArea.classList.remove('is-dragover');
            }, false);
        });

        dropArea.addEventListener('drop', e => {
            const dt = e.dataTransfer;
            if (dt && dt.files && dt.files.length) {
                handleFilesSelected(dt.files);
            }
        });

        fileInput.addEventListener('change', e => {
            if (e.target.files && e.target.files.length) {
                handleFilesSelected(e.target.files);
            }
        });
    }

    // Process selected files
    async function handleFilesSelected(files) {
        uploadAlert.classList.add('d-none');
        uploadAlert.textContent = '';

        const validFiles = Array.from(files).filter(f => f.type.startsWith('image/'));
        if (validFiles.length === 0) {
            uploadAlert.textContent = 'Harap pilih file gambar screenshot yang valid (JPG, PNG, WEBP).';
            uploadAlert.classList.remove('d-none');
            return;
        }

        // Limit to 10 files
        const remainingSlots = 10 - selectedFiles.length;
        if (remainingSlots <= 0) {
            uploadAlert.textContent = 'Batas maksimal 10 screenshot sudah tercapai.';
            uploadAlert.classList.remove('d-none');
            return;
        }

        const filesToProcess = validFiles.slice(0, remainingSlots);
        if (validFiles.length > remainingSlots) {
            uploadAlert.textContent = `Hanya ${remainingSlots} screenshot tambahan yang dapat dimasukkan (maks. 10).`;
            uploadAlert.classList.remove('d-none');
        }

        btnStartScan.disabled = true;
        btnStartScan.innerHTML = '<span class="spinner-border spinner-border-sm mr-1"></span> Mengompresi screenshot...';

        for (const f of filesToProcess) {
            try {
                const compressed = await compressImage(f, 1600, 0.82);
                selectedFiles.push(compressed);
            } catch (err) {
                console.error('Compression error:', err);
            }
        }

        renderThumbnails();
    }

    // Render file thumbnails with compression savings
    function renderThumbnails() {
        if (!previewSection || !thumbGrid) return;

        thumbGrid.innerHTML = '';
        fileCountEl.textContent = selectedFiles.length;

        if (selectedFiles.length === 0) {
            previewSection.classList.add('d-none');
            btnStartScan.disabled = true;
            btnStartScan.innerHTML = '<i class="fas fa-magic mr-1"></i> Mulai Pindai dengan AI';
            return;
        }

        previewSection.classList.remove('d-none');
        btnStartScan.disabled = false;
        btnStartScan.innerHTML = `<i class="fas fa-magic mr-1"></i> Mulai Pindai ${selectedFiles.length} Screenshot dengan AI`;

        selectedFiles.forEach((item, idx) => {
            const savingsPercent = Math.round(((item.origSize - item.compSize) / item.origSize) * 100);
            const col = document.createElement('div');
            col.className = 'col-md-3 col-sm-6';
            col.innerHTML = `
                <div class="card h-100 mb-0 overflow-hidden">
                    <div class="position-relative bg-light" style="height: 110px;">
                        <img src="${item.base64}" alt="Screenshot ${idx + 1}" style="width: 100%; height: 100%; object-fit: cover;">
                        <span class="badge badge-dark position-absolute" style="top: 6px; left: 6px;">
                            #${idx + 1}
                        </span>
                        <button type="button" class="btn btn-default btn-xs text-danger position-absolute remove-thumb-btn" data-index="${idx}" style="top: 6px; right: 6px;" title="Hapus screenshot">
                            &times;
                        </button>
                    </div>
                    <div class="p-2 text-xs">
                        <span class="d-block text-truncate font-weight-bold" title="${item.name}">${item.name}</span>
                        <div class="d-flex justify-content-between align-items-center text-muted mt-1">
                            <span><del>${formatBytes(item.origSize)}</del> &rarr; <strong>${formatBytes(item.compSize)}</strong></span>
                            <span class="badge badge-success">-${savingsPercent > 0 ? savingsPercent : 0}%</span>
                        </div>
                    </div>
                </div>
            `;
            thumbGrid.appendChild(col);
        });

        // Attach remove events
        thumbGrid.querySelectorAll('.remove-thumb-btn').forEach(btn => {
            btn.addEventListener('click', e => {
                e.stopPropagation();
                const index = parseInt(btn.getAttribute('data-index'), 10);
                selectedFiles.splice(index, 1);
                renderThumbnails();
            });
        });
    }

    // Clear all files
    if (btnClearFiles) {
        btnClearFiles.addEventListener('click', () => {
            selectedFiles = [];
            if (fileInput) fileInput.value = '';
            renderThumbnails();
        });
    }

    // Start AI Scan Action
    if (btnStartScan) {
        btnStartScan.addEventListener('click', () => {
            if (selectedFiles.length === 0) return;

            // Switch to loading step
            stepUpload.classList.add('d-none');
            stepLoading.classList.remove('d-none');
            stepReview.classList.add('d-none');
            stepAllOnCam.classList.add('d-none');

            // Rotating status text
            const loadingMessages = [
                'Mengirim ' + selectedFiles.length + ' screenshot Zoom ke Google Gemini Vision...',
                'AI sedang membaca kotak-kotak galeri Zoom...',
                'Mendeteksi peserta yang mematikan kamera (layar hitam, inisial, avatar)...',
                'Membaca teks nama di setiap kotak yang Off Cam...',
                'Mencocokkan nama akun Zoom dengan daftar resmi peserta pelatihan...',
                'Menghitung skor kemiripan dan menyusun hasil verifikasi...'
            ];
            let msgIdx = 0;
            const subEl = document.getElementById('zoomLoadingSub');
            if (subEl) subEl.textContent = loadingMessages[0];

            loadingInterval = setInterval(() => {
                msgIdx = (msgIdx + 1) % loadingMessages.length;
                if (subEl) subEl.textContent = loadingMessages[msgIdx];
            }, 3000);

            // Payload: array of base64 strings
            const payload = {
                images: selectedFiles.map(item => item.base64)
            };

            fetch(`/training/${TRAINING_ID}/scan-zoom-ai`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(res => res.json().then(data => ({ ok: res.ok, data })))
            .then(result => {
                clearInterval(loadingInterval);
                stepLoading.classList.add('d-none');

                if (!result.ok || !result.data.success) {
                    stepUpload.classList.remove('d-none');
                    const errMsg = (result.data && result.data.message) ? result.data.message : 'Terjadi kendala saat memproses screenshot dengan AI.';
                    uploadAlert.textContent = errMsg;
                    uploadAlert.classList.remove('d-none');
                    return;
                }

                const offCamList = (result.data.data && result.data.data.off_cam_participants) ? result.data.data.off_cam_participants : [];
                if (offCamList.length === 0) {
                    stepAllOnCam.classList.remove('d-none');
                } else {
                    renderReviewTable(offCamList);
                    stepReview.classList.remove('d-none');
                }
            })
            .catch(err => {
                clearInterval(loadingInterval);
                stepLoading.classList.add('d-none');
                stepUpload.classList.remove('d-none');
                uploadAlert.textContent = 'Gagal terhubung ke server atau API Gemini. Silakan coba kembali.';
                uploadAlert.classList.remove('d-none');
                console.error(err);
            });
        });
    }

    // Render Review Table
    function renderReviewTable(detectedList) {
        if (!reviewTbody) return;
        reviewTbody.innerHTML = '';
        countReviewOffCam.textContent = detectedList.length;

        detectedList.forEach((item, idx) => {
            const tr = createReviewRow(idx + 1, item);
            reviewTbody.appendChild(tr);
        });

        updateSelectedCount();
    }

    // Create a row for the review table
    function createReviewRow(rowNo, item) {
        const tr = document.createElement('tr');
        tr.className = 'review-item-row align-middle';

        // Check if item is matched to a participant
        const isMatched = !!item.participant_id;
        const isChecked = isMatched;

        // Build select options
        let optionsHtml = '<option value="">-- Bukan Peserta Pelatihan (Abaikan) --</option>';
        OFFICIAL_PARTICIPANTS.forEach(p => {
            const selected = (p.id == item.participant_id) ? 'selected' : '';
            optionsHtml += `<option value="${p.id}" ${selected}>${p.name} (${p.id_karyawan}) - ${p.divisi}</option>`;
        });

        // Similarity badge styling
        const score = item.similarity_score || 0;
        let scoreBadgeClass = 'badge-secondary';
        let scoreLabel = 'Rendah';
        if (score >= 80) {
            scoreBadgeClass = 'badge-success';
            scoreLabel = 'Sangat Mirip';
        } else if (score >= 65) {
            scoreBadgeClass = 'badge-warning';
            scoreLabel = 'Mirip';
        }

        tr.innerHTML = `
            <td class="text-center align-middle">
                <input type="checkbox" class="row-checkbox" ${isChecked ? 'checked' : ''} style="cursor: pointer;">
            </td>
            <td class="text-center align-middle text-muted row-number">
                ${rowNo}
            </td>
            <td class="align-middle">
                <input type="text" class="form-control form-control-sm zoom-name-input" value="${escapeHtml(item.zoom_name || '')}" placeholder="Nama di Zoom...">
            </td>
            <td class="align-middle">
                <select class="form-control form-control-sm participant-select">
                    ${optionsHtml}
                </select>
            </td>
            <td class="text-center align-middle">
                <span class="badge ${scoreBadgeClass} similarity-badge" title="${escapeHtml(item.match_reason || '')}">
                    ${score}% &bull; ${scoreLabel}
                </span>
            </td>
            <td class="align-middle text-xs text-muted">
                <div class="evidence-text text-dark">${escapeHtml(item.visual_evidence || 'Layar mati/avatar')}</div>
                <div class="reason-text text-muted">${escapeHtml(item.match_reason || '-')}</div>
            </td>
            <td class="text-center align-middle">
                <button type="button" class="btn btn-default btn-xs text-danger btn-delete-row" title="Hapus baris">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </td>
        `;

        // Event: when participant select changes
        const selectEl = tr.querySelector('.participant-select');
        const checkEl = tr.querySelector('.row-checkbox');
        const badgeEl = tr.querySelector('.similarity-badge');

        selectEl.addEventListener('change', () => {
            if (selectEl.value) {
                checkEl.checked = true;
                if (!item.participant_id || selectEl.value != item.participant_id) {
                    badgeEl.className = 'badge badge-primary similarity-badge';
                    badgeEl.innerHTML = '100% &bull; Manual';
                }
            } else {
                checkEl.checked = false;
            }
            updateSelectedCount();
        });

        checkEl.addEventListener('change', updateSelectedCount);

        tr.querySelector('.btn-delete-row').addEventListener('click', () => {
            tr.remove();
            renumberReviewRows();
            updateSelectedCount();
        });

        return tr;
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Renumber rows after delete
    function renumberReviewRows() {
        if (!reviewTbody) return;
        const rows = reviewTbody.querySelectorAll('.review-item-row');
        rows.forEach((r, i) => {
            const numEl = r.querySelector('.row-number');
            if (numEl) numEl.textContent = i + 1;
        });
        countReviewOffCam.textContent = rows.length;
    }

    // Update selected count indicator
    function updateSelectedCount() {
        if (!reviewTbody) return;
        const checkedBoxes = reviewTbody.querySelectorAll('.row-checkbox:checked');
        const totalRows = reviewTbody.querySelectorAll('.review-item-row').length;
        textSelectedCount.textContent = checkedBoxes.length;

        if (checkSelectAll) {
            checkSelectAll.checked = (totalRows > 0 && checkedBoxes.length === totalRows);
        }

        if (btnConfirmSave) {
            btnConfirmSave.disabled = (checkedBoxes.length === 0);
        }
    }

    // Master checkbox toggle
    if (checkSelectAll) {
        checkSelectAll.addEventListener('change', e => {
            const isChecked = e.target.checked;
            reviewTbody.querySelectorAll('.row-checkbox').forEach(cb => {
                cb.checked = isChecked;
            });
            updateSelectedCount();
        });
    }

    // Add manual row button
    if (btnAddManualRow) {
        btnAddManualRow.addEventListener('click', () => {
            const currentTotal = reviewTbody.querySelectorAll('.review-item-row').length;
            const newTr = createReviewRow(currentTotal + 1, {
                zoom_name: 'Input Manual',
                participant_id: null,
                similarity_score: 100,
                match_reason: 'Ditambahkan manual oleh pengawas',
                visual_evidence: 'Pengamatan pengawas',
                confidence: 'high'
            });
            reviewTbody.appendChild(newTr);
            renumberReviewRows();
            updateSelectedCount();
            newTr.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    }

    // Back to upload step
    if (btnBackToUpload) {
        btnBackToUpload.addEventListener('click', () => {
            stepReview.classList.add('d-none');
            stepUpload.classList.remove('d-none');
        });
    }

    // Confirm and Save action
    if (btnConfirmSave) {
        btnConfirmSave.addEventListener('click', () => {
            const rows = reviewTbody.querySelectorAll('.review-item-row');
            const itemsToSave = [];

            rows.forEach(r => {
                const isChecked = r.querySelector('.row-checkbox').checked;
                const participantId = r.querySelector('.participant-select').value;
                const zoomName = r.querySelector('.zoom-name-input').value;
                const reason = r.querySelector('.reason-text').textContent;

                if (isChecked && participantId) {
                    itemsToSave.push({
                        participant_id: participantId,
                        zoom_name: zoomName || '-',
                        reason: reason || 'Terdeteksi Off Cam di Zoom'
                    });
                }
            });

            if (itemsToSave.length === 0) {
                alert('Tidak ada peserta yang dipilih untuk dicatat sebagai Off Cam.');
                return;
            }

            const markTidakHadir = checkMarkTidakHadir ? checkMarkTidakHadir.checked : false;

            btnConfirmSave.disabled = true;
            btnConfirmSave.innerHTML = '<span class="spinner-border spinner-border-sm mr-1"></span> Menyimpan data presensi...';

            fetch(`/training/${TRAINING_ID}/save-zoom-off-cam`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    off_cam_items: itemsToSave,
                    mark_as_tidak_hadir: markTidakHadir
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message || 'Catatan Off Cam berhasil disimpan!');
                    window.location.reload();
                } else {
                    alert('Gagal menyimpan: ' + (data.message || 'Terjadi kesalahan.'));
                    btnConfirmSave.disabled = false;
                    btnConfirmSave.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Konfirmasi & Simpan Catatan Off Cam';
                }
            })
            .catch(err => {
                console.error(err);
                alert('Gagal terhubung ke server saat menyimpan.');
                btnConfirmSave.disabled = false;
                btnConfirmSave.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Konfirmasi & Simpan Catatan Off Cam';
            });
        });
    }

    // Direct manual input mode function
    function openManualInputMode() {
        stepUpload.classList.add('d-none');
        stepLoading.classList.add('d-none');
        stepAllOnCam.classList.add('d-none');
        stepReview.classList.remove('d-none');

        // If table is currently empty, add an initial manual row
        if (reviewTbody.querySelectorAll('.review-item-row').length === 0) {
            const newTr = createReviewRow(1, {
                zoom_name: '',
                participant_id: null,
                similarity_score: 100,
                match_reason: 'Dicatat manual oleh pengawas',
                visual_evidence: 'Pengamatan langsung pengawas',
                confidence: 'high'
            });
            reviewTbody.appendChild(newTr);
            renumberReviewRows();
            updateSelectedCount();
        }
    }

    const btnDirectManualInput = document.getElementById('btnDirectManualInput');
    if (btnDirectManualInput) {
        btnDirectManualInput.addEventListener('click', openManualInputMode);
    }

    const btnOpenManualTable = document.getElementById('btnOpenManualTable');
    if (btnOpenManualTable) {
        btnOpenManualTable.addEventListener('click', () => {
            if (typeof $ !== 'undefined') {
                $('#scanZoomModal').modal('show');
            }
            openManualInputMode();
        });
    }

    // ===============================================================
    // MODE CEPAT INPUT OFF CAM MANUAL (1-KLIK DI TABEL PESERTA)
    // ===============================================================
    let isManualOffCamModeActive = false;

    function setManualOffCamMode(active) {
        isManualOffCamModeActive = active;
        const defaultWrappers = document.querySelectorAll('.default-action-wrapper');
        const quickWrappers = document.querySelectorAll('.quick-offcam-action-wrapper');
        const banner = document.getElementById('bannerManualOffCamMode');
        const btnToggle = document.getElementById('btnToggleManualOffCamMode');
        const thAction = document.getElementById('thActionHeader');

        if (active) {
            defaultWrappers.forEach(el => el.classList.add('d-none'));
            quickWrappers.forEach(el => el.classList.remove('d-none'));
            if (banner) {
                banner.classList.remove('d-none');
                banner.classList.add('d-flex');
            }
            if (btnToggle) {
                btnToggle.className = 'btn btn-outline-danger btn-sm';
                btnToggle.innerHTML = '<i class="fas fa-times"></i> Selesai Mode Manual';
            }
            if (thAction) {
                thAction.textContent = 'Status Kamera (Klik)';
                thAction.style.width = '160px';
            }
        } else {
            defaultWrappers.forEach(el => el.classList.remove('d-none'));
            quickWrappers.forEach(el => el.classList.add('d-none'));
            if (banner) {
                banner.classList.add('d-none');
                banner.classList.remove('d-flex');
            }
            if (btnToggle) {
                btnToggle.className = 'btn btn-default btn-sm';
                btnToggle.innerHTML = '<i class="fas fa-user-edit"></i> Input Off Cam Manual';
            }
            if (thAction) {
                thAction.textContent = 'Aksi';
                thAction.style.width = '110px';
            }
        }
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('#btnToggleManualOffCamMode')) {
            e.preventDefault();
            setManualOffCamMode(!isManualOffCamModeActive);
        } else if (e.target.closest('#btnExitManualOffCamMode')) {
            e.preventDefault();
            setManualOffCamMode(false);
        }
    });

    // 1-Click Stepper Listener on Participants Table
    const participantsTable = document.getElementById('participantsTable');
    if (participantsTable) {
        participantsTable.addEventListener('click', function (e) {
            const btn = e.target.closest('.btn-offcam-step');
            if (!btn) return;

            const partId = btn.getAttribute('data-id');
            const partName = btn.getAttribute('data-name') || 'Peserta';
            const action = btn.getAttribute('data-action') || 'increment';

            if (!partId) return;

            const wrapper = document.getElementById(`offcamActionWrapper_${partId}`);
            if (btn.disabled) return;
            btn.disabled = true;

            fetch(`/training/${TRAINING_ID}/update-offcam-count/${partId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ action: action })
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                if (data.success) {
                    const count = data.off_cam_count || 0;

                    // Update action stepper UI in participant row
                    if (wrapper) {
                        if (count <= 0) {
                            wrapper.innerHTML = `
                                <button type="button" class="btn btn-default btn-xs text-danger text-nowrap btn-offcam-step" data-id="${partId}" data-action="increment" data-name="${partName}" title="Tandai peserta ini Off Cam">
                                    <i class="fas fa-video-slash"></i> Tandai Off Cam
                                </button>
                            `;
                        } else {
                            wrapper.innerHTML = `
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-default btn-offcam-step" data-id="${partId}" data-action="decrement" data-name="${partName}" title="Kurangi frekuensi (-1)">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <span class="btn btn-default text-danger text-nowrap" style="pointer-events: none;">
                                        <i class="fas fa-video-slash"></i> <span id="offcamCountText_${partId}">${count}x</span> Off
                                    </span>
                                    <button type="button" class="btn btn-default btn-offcam-step" data-id="${partId}" data-action="increment" data-name="${partName}" title="Tambah frekuensi (+1)">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            `;
                        }
                    }

                    // Update badge di kolom Presensi
                    const badgeBox = document.getElementById(`offcamBadge_${partId}`);
                    if (badgeBox) {
                        if (count > 0) {
                            badgeBox.innerHTML = `
                                <span class="cell-flag is-danger" title="Terdeteksi Off Cam di Zoom">
                                    <i class="fas fa-video-slash"></i> Off cam ${count}x
                                </span>
                            `;
                        } else {
                            badgeBox.innerHTML = '';
                        }
                    }
                } else {
                    alert('Gagal mengupdate frekuensi Off Cam: ' + (data.message || 'Terjadi kesalahan'));
                }
            })
            .catch(err => {
                console.error(err);
                btn.disabled = false;
                alert('Gagal menghubungi server.');
            });
        });
    }

    // Live search di tabel peserta
    const filterTableInput = document.getElementById('filterTableInput');
    if (filterTableInput) {
        filterTableInput.addEventListener('input', function () {
            const query = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('#participantsTable tbody tr');
            rows.forEach(tr => {
                const text = tr.textContent.toLowerCase();
                if (text.includes(query)) {
                    tr.style.display = '';
                } else {
                    tr.style.display = 'none';
                }
            });
        });
    }
})();
</script>
@endsection
