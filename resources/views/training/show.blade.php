@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-3 align-items-center">
            <div class="col-sm-7">
                <div class="d-flex align-items-center">
                    <a href="/training" class="btn btn-default btn-sm mr-3">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h1 class="m-0 text-dark font-weight-bold" style="font-size: 1.5rem;">
                            {{ $training->title }}
                        </h1>
                        <span class="text-muted small">
                            Pelaksanaan: {{ \Carbon\Carbon::parse($training->training_date)->format('d F Y') }} &bull; {{ substr($training->start_time, 0, 5) }} - {{ substr($training->end_time, 0, 5) }} WIB
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-sm-5 text-right mt-2 mt-sm-0">
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-danger font-weight-bold" data-toggle="modal" data-target="#uploadTrainingVideoModal">
                        <i class="fas fa-video mr-1"></i> Upload Video Materi
                    </button>
                    <a href="/training/{{ $training->id }}/questions" class="btn btn-default">
                        Soal Kuis ({{ $training->questions->count() }})
                    </a>
                    <a href="/training/{{ $training->id }}/export" class="btn btn-default">
                        Export Nilai
                    </a>
                    <a href="/training/{{ $training->id }}/edit" class="btn btn-default">
                        Edit Pelatihan
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                {{ session('success') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                {{ session('error') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        <!-- TRAINING DETAIL OVERVIEW -->
        <div class="card mb-4">
            <div class="card-body p-4">
                <div class="row">
                    <div class="col-lg-8 pr-lg-4 mb-3 mb-lg-0">
                        <div class="d-flex align-items-center mb-3">
                            <span class="text-muted small mr-2">Status:</span>
                            @if($training->status === 'scheduled')
                                <span class="badge badge-warning text-white px-2 py-1 mr-3">Dijadwalkan</span>
                            @elseif($training->status === 'ongoing')
                                <span class="badge badge-success px-2 py-1 mr-3">Berlangsung</span>
                            @elseif($training->status === 'completed')
                                <span class="badge badge-secondary px-2 py-1 mr-3">Selesai</span>
                            @else
                                <span class="badge badge-danger px-2 py-1 mr-3">Dibatalkan</span>
                            @endif

                            <span class="text-muted small mr-2">Akses Kuis:</span>
                            @if($training->is_quiz_active)
                                <span class="badge badge-success px-2 py-1 mr-3"><i class="fas fa-check-circle mr-1"></i> Terbuka</span>
                            @else
                                <span class="badge badge-secondary px-2 py-1 mr-3"><i class="fas fa-lock mr-1"></i> Terkunci</span>
                            @endif

                            <span class="text-muted small mr-2">Akses Absensi:</span>
                            @if($training->is_attendance_active)
                                <span class="badge badge-success px-2 py-1 mr-3"><i class="fas fa-user-check mr-1"></i> Terbuka</span>
                            @else
                                <span class="badge badge-secondary px-2 py-1 mr-3"><i class="fas fa-user-slash mr-1"></i> Terkunci</span>
                            @endif

                            <span class="text-muted small mr-2">Bukti Screenshot:</span>
                            @if($training->require_attendance_proof)
                                <span class="badge badge-info px-2 py-1 mr-3"><i class="fas fa-camera mr-1"></i> Wajib</span>
                            @else
                                <span class="badge badge-light border text-muted px-2 py-1 mr-3"><i class="fas fa-camera mr-1"></i> Opsional</span>
                            @endif

                            <span class="text-muted small mr-2">Tampilan Kuis:</span>
                            @if($training->quiz_mode === 'game')
                                <span class="badge badge-info px-2 py-1"><i class="fas fa-gamepad mr-1"></i> Game (Quizizz)</span>
                            @else
                                <span class="badge badge-light border text-dark px-2 py-1"><i class="fas fa-file-alt mr-1"></i> Ujian Formal</span>
                            @endif
                        </div>

                        <div class="row text-sm mb-3">
                            <div class="col-sm-4 mb-2 mb-sm-0">
                                <span class="text-muted small d-block">Pemateri:</span>
                                <strong>{{ $training->trainer->full_name ?? '-' }}</strong>
                            </div>
                            <div class="col-sm-4 mb-2 mb-sm-0">
                                <span class="text-muted small d-block">Tautan Meeting:</span>
                                @if($training->zoom_link)
                                    <a href="{{ $training->zoom_link }}" target="_blank" class="text-primary text-truncate d-block" style="max-width: 200px;">
                                        {{ $training->zoom_link }}
                                    </a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </div>
                            <div class="col-sm-4">
                                <span class="text-muted small d-block">Waktu:</span>
                                <strong>{{ substr($training->start_time, 0, 5) }} - {{ substr($training->end_time, 0, 5) }} WIB</strong>
                            </div>
                        </div>

                        @if($training->description)
                            <div class="p-3 bg-light rounded text-sm text-muted border mt-2">
                                <strong class="d-block text-dark mb-1">Deskripsi / Silabus:</strong>
                                {{ $training->description }}
                            </div>
                        @endif

                        {{-- Section Rekaman Video Materi --}}
                        @if($training->video)
                            <div class="p-3 bg-light rounded text-sm border mt-3 d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <div class="rounded mr-3 bg-danger text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                                        <i class="fas fa-play" style="font-size: 1.1rem;"></i>
                                    </div>
                                    <div>
                                        <span class="badge badge-success px-2 py-1 mb-1">Video Materi Tersedia</span>
                                        <h6 class="mb-0 font-weight-bold text-dark">{{ $training->video->title }}</h6>
                                        <small class="text-muted">{{ $training->video->views_count }} ditonton &bull; Diunggah {{ $training->video->created_at->format('d M Y') }}</small>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center" style="gap: 6px;">
                                    <a href="{{ route('video.show', $training->video->id) }}" class="btn btn-sm btn-primary">
                                        <i class="fas fa-play mr-1"></i> Tonton Video
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#uploadTrainingVideoModal" title="Upload Video Rekaman Lain">
                                        <i class="fas fa-cloud-upload-alt mr-1"></i> Tambah / Ganti
                                    </button>
                                </div>
                            </div>
                        @else
                            <div class="p-3 rounded text-sm border mt-3 d-flex align-items-center justify-content-between" style="background: #fff8f8; border-color: #fecaca !important;">
                                <div class="d-flex align-items-center">
                                    <div class="rounded mr-3 bg-white text-danger border d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px; border-color: #fecaca !important;">
                                        <i class="fas fa-video" style="font-size: 1.1rem;"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1 font-weight-bold text-dark">Belum Ada Rekaman Video Materi</h6>
                                        <small class="text-muted">Pelatihan sudah selesai? Klik tombol di samping untuk langsung mengunggah video ke Video Materi.</small>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-danger font-weight-bold flex-shrink-0 ml-2" data-toggle="modal" data-target="#uploadTrainingVideoModal">
                                    <i class="fas fa-cloud-upload-alt mr-1"></i> Upload Video Sekarang
                                </button>
                            </div>
                        @endif
                    </div>

                    <!-- CONTROL ACTIONS -->
                    <div class="col-lg-4 pl-lg-4 border-left">
                        <span class="text-muted small font-weight-bold text-uppercase d-block mb-3">Aksi Pelatihan</span>
                        <div class="d-flex flex-column" style="gap: 10px;">
                            <button type="button" class="btn btn-danger btn-sm btn-block text-left py-2 font-weight-bold" data-toggle="modal" data-target="#uploadTrainingVideoModal">
                                <i class="fas fa-video mr-1"></i> Upload Video Materi Pelatihan
                            </button>
                            <form action="/training/{{ $training->id }}/broadcast-wa" method="POST" onsubmit="return confirm('Kirim notifikasi WhatsApp ke {{ $training->participants->count() }} peserta?')">
                                @csrf
                                <button type="submit" class="btn btn-success btn-sm btn-block text-left py-2 font-weight-bold">
                                    <i class="fab fa-whatsapp mr-1"></i> Broadcast WA ke Seluruh Peserta
                                </button>
                            </form>

                            <form action="/training/{{ $training->id }}/toggle-attendance" method="POST">
                                @csrf
                                @if($training->is_attendance_active)
                                    <button type="submit" class="btn btn-outline-danger btn-sm btn-block text-left py-2 font-weight-bold" title="Tutup sesi presensi peserta">
                                        <i class="fas fa-user-slash mr-1"></i> Tutup Akses Absensi
                                    </button>
                                @else
                                    <button type="submit" class="btn btn-outline-success btn-sm btn-block text-left py-2 font-weight-bold" title="Buka sesi presensi peserta">
                                        <i class="fas fa-user-check mr-1"></i> Buka Akses Absensi Peserta
                                    </button>
                                @endif
                            </form>

                            <form action="/training/{{ $training->id }}/toggle-quiz" method="POST">
                                @csrf
                                @if($training->is_quiz_active)
                                    <button type="submit" class="btn btn-outline-danger btn-sm btn-block text-left py-2 font-weight-bold" title="Tutup akses pengerjaan kuis">
                                        <i class="fas fa-lock mr-1"></i> Tutup Akses Kuis
                                    </button>
                                @else
                                    <button type="submit" class="btn btn-outline-primary btn-sm btn-block text-left py-2 font-weight-bold" title="Buka akses pengerjaan kuis peserta">
                                        <i class="fas fa-unlock-alt mr-1"></i> Buka Akses Kuis Peserta
                                    </button>
                                @endif
                            </form>

                            <form action="/training/{{ $training->id }}/toggle-proof" method="POST">
                                @csrf
                                @if($training->require_attendance_proof)
                                    <button type="submit" class="btn btn-outline-secondary btn-sm btn-block text-left py-2" title="Klik untuk menonaktifkan syarat upload screenshot">
                                        <i class="fas fa-camera text-info mr-1"></i> Syarat Screenshot: <strong>Wajib</strong>
                                    </button>
                                @else
                                    <button type="submit" class="btn btn-outline-secondary btn-sm btn-block text-left py-2" title="Klik untuk mewajibkan upload screenshot saat absen">
                                        <i class="fas fa-camera text-muted mr-1"></i> Syarat Screenshot: <strong>Opsional</strong>
                                    </button>
                                @endif
                            </form>

                            <form action="/training/{{ $training->id }}/toggle-mode" method="POST">
                                @csrf
                                @if($training->quiz_mode === 'game')
                                    <button type="submit" class="btn btn-outline-secondary btn-sm btn-block text-left py-2" title="Ganti ke Mode Formal">
                                        <i class="fas fa-file-alt text-primary mr-1"></i> Ganti ke Mode Formal
                                    </button>
                                @else
                                    <button type="submit" class="btn btn-outline-info btn-sm btn-block text-left py-2" title="Ganti ke Mode Game Quizizz">
                                        <i class="fas fa-gamepad text-success mr-1"></i> Ganti ke Mode Game (Quizizz)
                                    </button>
                                @endif
                            </form>

                            <form action="/training/{{ $training->id }}/status" method="POST" class="d-flex mt-1" style="gap: 6px;">
                                @csrf
                                <select name="status" class="form-control form-control-sm">
                                    <option value="scheduled" {{ $training->status === 'scheduled' ? 'selected' : '' }}>Ubah: Dijadwalkan</option>
                                    <option value="ongoing" {{ $training->status === 'ongoing' ? 'selected' : '' }}>Ubah: Berlangsung</option>
                                    <option value="completed" {{ $training->status === 'completed' ? 'selected' : '' }}>Ubah: Selesai</option>
                                    <option value="cancelled" {{ $training->status === 'cancelled' ? 'selected' : '' }}>Ubah: Dibatalkan</option>
                                </select>
                                <button type="submit" class="btn btn-default btn-sm px-3">Update</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SUMMARY STATS BAR -->
        <div class="row mb-4">
            <div class="col-md-2 col-4 mb-2">
                <div class="card p-3 text-center mb-0 bg-light">
                    <span class="text-muted small d-block">Total Peserta</span>
                    <strong class="h4 mb-0 text-dark" id="statTotal">{{ $stats['total'] }}</strong>
                </div>
            </div>
            <div class="col-md-2 col-4 mb-2">
                <div class="card p-3 text-center mb-0 bg-light">
                    <span class="text-muted small d-block">Hadir</span>
                    <strong class="h4 mb-0 text-success" id="statAttended">{{ $stats['attended'] }}</strong>
                </div>
            </div>
            <div class="col-md-2 col-4 mb-2">
                <div class="card p-3 text-center mb-0 bg-light">
                    <span class="text-muted small d-block">Tidak Hadir</span>
                    <strong class="h4 mb-0 text-danger" id="statAbsent">{{ $stats['absent'] }}</strong>
                </div>
            </div>
            <div class="col-md-2 col-4 mb-2">
                <div class="card p-3 text-center mb-0 bg-light">
                    <span class="text-muted small d-block">Belum Absen</span>
                    <strong class="h4 mb-0 text-warning" id="statPending">{{ $stats['pending'] }}</strong>
                </div>
            </div>
            <div class="col-md-2 col-4 mb-2">
                <div class="card p-3 text-center mb-0 bg-light">
                    <span class="text-muted small d-block">Selesai Kuis</span>
                    <strong class="h4 mb-0 text-primary" id="statQuiz">{{ $stats['quizSubmitted'] }}</strong>
                </div>
            </div>
            <div class="col-md-2 col-4 mb-2">
                <div class="card p-3 text-center mb-0 bg-light">
                    <span class="text-muted small d-block">Rata-rata Nilai</span>
                    <strong class="h4 mb-0 text-info" id="statAvg">{{ $stats['avgScore'] }}</strong>
                </div>
            </div>
        </div>

        <!-- TABEL REKAP PESERTA & NILAI -->
        <div class="card mb-4">
            <div class="card-header py-3 d-flex align-items-center justify-content-between">
                <h3 class="card-title font-weight-bold">
                    Rekap Absensi & Nilai Peserta
                </h3>
                <div class="card-tools">
                    <input type="text" id="filterTableInput" class="form-control form-control-sm" placeholder="Filter peserta..." style="width: 220px;">
                </div>
            </div>

            <div class="card-body p-0 table-responsive">
                <table class="table table-hover mb-0" id="participantsTable" style="font-size: 0.92rem;">
                    <thead class="bg-light text-muted">
                        <tr>
                            <th class="text-center" style="width: 50px; padding: 14px 16px;">No</th>
                            <th style="padding: 14px 16px;">Nama Peserta</th>
                            <th style="padding: 14px 16px;">Divisi</th>
                            <th style="padding: 14px 16px;">No WhatsApp</th>
                            <th class="text-center" style="padding: 14px 16px;">Notif WA</th>
                            <th class="text-center" style="padding: 14px 16px;">Absensi</th>
                            <th class="text-center" style="padding: 14px 16px;">Status Kuis</th>
                            <th class="text-center" style="padding: 14px 16px;">Nilai</th>
                            <th class="text-center" style="width: 140px; padding: 14px 16px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($training->participants as $index => $part)
                        @php
                            $user = $part->user;
                            $quiz = $part->quizResult;
                        @endphp
                        <tr>
                            <td class="text-center align-middle text-muted" style="padding: 16px;">{{ $index + 1 }}</td>
                            <td class="align-middle" style="padding: 16px;">
                                <strong class="d-block text-dark">{{ $user->full_name }}</strong>
                                <span class="text-muted small">{{ $user->id_karyawan ?? '-' }} &bull; {{ $user->email }}</span>
                            </td>
                            <td class="align-middle text-muted" style="padding: 16px;">
                                {{ $user->divisi->name ?? '-' }}
                            </td>
                            <td class="align-middle text-muted" style="padding: 16px;">
                                {{ $user->no_wa ?? '-' }}
                            </td>
                            <td class="text-center align-middle" style="padding: 16px;">
                                @if($part->wa_sent_at)
                                    <span class="badge badge-success px-2 py-1" title="{{ $part->wa_status }} - {{ $part->wa_sent_at }}">
                                        Terkirim
                                    </span>
                                @else
                                    <span class="badge badge-secondary px-2 py-1">Belum</span>
                                @endif
                            </td>
                            <td class="text-center align-middle" style="padding: 16px;">
                                @if($part->attendance_status === 'hadir')
                                    <span class="badge badge-success px-2 py-1">Hadir</span>
                                    <span class="d-block text-muted text-xs mt-1">
                                        {{ \Carbon\Carbon::parse($part->attended_at)->format('H:i') }} WIB
                                    </span>
                                    @if($part->attendance_proof)
                                        <button type="button" class="btn btn-xs btn-outline-info mt-1 d-inline-block font-weight-bold" data-toggle="modal" data-target="#proofModal_{{ $part->id }}" title="Lihat Screenshot Bukti Pelatihan">
                                            <i class="fas fa-camera mr-1"></i> Bukti Foto
                                        </button>
                                    @endif
                                @elseif($part->attendance_status === 'tidak_hadir')
                                    <span class="badge badge-danger px-2 py-1">Tidak Hadir</span>
                                    @if($part->attendance_notes)
                                        <span class="d-block text-muted text-xs mt-1">{{ $part->attendance_notes }}</span>
                                    @endif
                                @else
                                    <span class="badge badge-warning text-white px-2 py-1">Belum Absen</span>
                                @endif
                            </td>
                            <td class="text-center align-middle" style="padding: 16px;">
                                @if($quiz)
                                    <span class="badge badge-info px-2 py-1">
                                        Selesai ({{ $quiz->correct_answers }} PG Benar)
                                    </span>
                                    <span class="d-block text-muted text-xs mt-1">
                                        Nilai PG: <strong>{{ $quiz->mc_score ?? $quiz->score }}</strong>
                                    </span>
                                    @if($quiz->essay_status === 'graded')
                                        <span class="badge badge-success text-xs mt-1 d-inline-block">Essay: {{ $quiz->essay_score }}/100</span>
                                    @elseif($quiz->essay_status === 'pending')
                                        <span class="badge badge-warning text-white text-xs mt-1 d-inline-block">Essay: Menunggu Review</span>
                                    @endif
                                    <span class="d-block text-muted text-xs mt-1">
                                        {{ \Carbon\Carbon::parse($quiz->submitted_at)->format('d/m H:i') }}
                                    </span>
                                @else
                                    <span class="text-muted small">Belum Mengerjakan</span>
                                @endif
                            </td>
                            <td class="text-center align-middle" style="padding: 16px;">
                                @if($quiz)
                                    <strong class="{{ $quiz->score >= 75 ? 'text-success' : ($quiz->score >= 50 ? 'text-warning' : 'text-danger') }}" style="font-size: 1.2rem;">
                                        {{ $quiz->score }}
                                    </strong>
                                    @if($quiz->essay_status === 'pending')
                                        <span class="text-muted text-xs d-block font-italic">(Skor PG)</span>
                                    @elseif($quiz->essay_status === 'graded')
                                        <span class="text-success text-xs d-block font-weight-bold">(Nilai Akhir)</span>
                                    @endif

                                    @if($quiz->is_force_submitted)
                                        <span class="badge badge-danger text-xs mt-1 d-block" title="Peserta dikeluarkan / kuis dikunci karena melanggar toleransi keluar tab">
                                            <i class="fas fa-exclamation-triangle"></i> Curang (Auto-Submit)
                                        </span>
                                    @elseif(($quiz->tab_switch_count ?? 0) > 0)
                                        <span class="badge badge-warning text-dark text-xs mt-1 d-block" title="Terdeteksi keluar tab {{ $quiz->tab_switch_count }} kali">
                                            <i class="fas fa-exclamation-circle"></i> Pindah Tab: {{ $quiz->tab_switch_count }}x
                                        </span>
                                    @endif
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-center align-middle" style="padding: 16px;">
                                <div class="d-flex justify-content-center align-items-center" style="gap: 6px;">
                                    <!-- COPY LINK -->
                                    <button type="button" class="btn btn-xs btn-default copy-link-btn" 
                                            data-link="{{ url('/training/portal/' . $part->token) }}" title="Salin Tautan Akses">
                                        <i class="fas fa-copy"></i>
                                    </button>

                                    <!-- RESEND WA -->
                                    <form action="/training/{{ $training->id }}/send-wa/{{ $part->id }}" method="POST" class="d-inline m-0">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-default" title="Kirim WA Ulang">
                                            <i class="fab fa-whatsapp text-success"></i>
                                        </button>
                                    </form>

                                    <!-- RESET ATTENDANCE (Jadikan Belum Absen) -->
                                    @if($part->attendance_status !== 'pending')
                                        <form action="/training/{{ $training->id }}/reset-attendance/{{ $part->id }}" method="POST" class="d-inline m-0" onsubmit="return confirm('Ubah status presensi {{ $user->full_name }} menjadi BELUM ABSEN?')">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-default text-danger" title="Ubah Jadi Belum Absen">
                                                <i class="fas fa-user-times"></i>
                                            </button>
                                        </form>
                                    @endif

                                    <!-- EDIT ATTENDANCE MODAL BUTTON -->
                                    <button type="button" class="btn btn-xs btn-default text-info" data-toggle="modal" data-target="#editAttendanceModal_{{ $part->id }}" title="Atur Presensi Manual">
                                        <i class="fas fa-user-edit"></i>
                                    </button>

                                    <!-- RESET QUIZ -->
                                    @if($quiz)
                                        <form action="/training/{{ $training->id }}/reset-quiz/{{ $part->id }}" method="POST" class="d-inline m-0" onsubmit="return confirm('Reset kuis untuk {{ $user->full_name }}?')">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-default" title="Reset Kuis">
                                                <i class="fas fa-undo text-warning"></i>
                                            </button>
                                        </form>
                                    @endif

                                    <!-- DETAIL MODAL -->
                                    @if($quiz && $quiz->answers)
                                        <button type="button" class="btn btn-xs btn-default" data-toggle="modal" data-target="#answerModal_{{ $quiz->id }}" title="Lihat Lembar Jawaban & Beri Nilai Essay">
                                            <i class="fas fa-eye text-primary"></i>
                                        </button>
                                    @endif
                                </div>

                                <!-- MODAL LEMBAR JAWABAN -->
                                @if($quiz && $quiz->answers)
                                <div class="modal fade text-left" id="answerModal_{{ $quiz->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                                    <div class="modal-dialog modal-lg" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header py-3 bg-light">
                                                <h5 class="modal-title font-weight-bold">
                                                    <i class="fas fa-file-alt text-primary mr-2"></i> Lembar Jawaban: {{ $user->full_name }}
                                                </h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <div class="row mb-4 bg-light p-3 rounded border text-center">
                                                    <div class="col-3 border-right">
                                                        <span class="text-muted text-xs d-block">Nilai Akhir:</span>
                                                        <strong class="h4 text-primary mb-0 font-weight-bold">{{ $quiz->score }}</strong>
                                                    </div>
                                                    <div class="col-3 border-right">
                                                        <span class="text-muted text-xs d-block">Nilai PG ({{ $quiz->correct_answers }} Benar):</span>
                                                        <strong class="h5 text-dark mb-0">{{ $quiz->mc_score ?? $quiz->score }}</strong>
                                                    </div>
                                                    <div class="col-3 border-right">
                                                        <span class="text-muted text-xs d-block">Nilai Essay:</span>
                                                        <strong class="h5 {{ $quiz->essay_status === 'graded' ? 'text-success' : 'text-warning' }} mb-0">
                                                            {{ $quiz->essay_status === 'graded' ? $quiz->essay_score : 'Belum Dinilai' }}
                                                        </strong>
                                                    </div>
                                                    <div class="col-3">
                                                        <span class="text-muted text-xs d-block">Waktu Submit:</span>
                                                        <span class="small font-weight-bold text-muted">{{ \Carbon\Carbon::parse($quiz->submitted_at)->format('d/m/Y H:i') }}</span>
                                                    </div>
                                                </div>

                                                <!-- CATATAN ANTI-CURANG AUDIT -->
                                                @if(($quiz->tab_switch_count ?? 0) > 0 || $quiz->is_force_submitted)
                                                <div class="alert alert-{{ $quiz->is_force_submitted ? 'danger' : 'warning' }} mb-4 p-3 text-left shadow-sm">
                                                    <div class="d-flex align-items-center mb-1">
                                                        <i class="fas fa-shield-alt fa-lg mr-2"></i>
                                                        <strong class="font-weight-bold" style="font-size: 0.95rem;">
                                                            Catatan Integritas & Anti-Curang: {{ $quiz->is_force_submitted ? 'Kuis Dikunci Otomatis (Melebihi Toleransi Pindah Tab)' : 'Terdeteksi Keluar dari Halaman Kuis' }}
                                                        </strong>
                                                    </div>
                                                    <div class="small">
                                                        Peserta terdeteksi keluar dari halaman kuis / membuka aplikasi lain sebanyak <strong>{{ $quiz->tab_switch_count }} kali</strong>.
                                                        @if(!empty($quiz->violation_logs) && is_array($quiz->violation_logs))
                                                            <div class="mt-2 pt-2 border-top border-{{ $quiz->is_force_submitted ? 'danger' : 'warning' }}">
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
                                                @endif

                                                <!-- FORM PENILAIAN ESSAY OLEH PEMATERI -->
                                                @if($training->questions()->where('type', 'essay')->count() > 0)
                                                <div class="card border border-info mb-4 shadow-none" style="background-color: #f6fbff;">
                                                    <div class="card-header bg-info text-white py-2 font-weight-bold text-sm">
                                                        <i class="fas fa-pen-nib mr-1"></i> Penilaian Soal Essay oleh Pemateri
                                                    </div>
                                                    <div class="card-body p-3">
                                                        <form action="/training/{{ $training->id }}/grade-essay/{{ $quiz->id }}" method="POST">
                                                            @csrf
                                                            <div class="row align-items-end">
                                                                <div class="col-md-3 mb-2 mb-md-0">
                                                                    <label class="font-weight-bold text-dark text-xs mb-1">Nilai Essay (0-100):</label>
                                                                    <input type="number" name="essay_score" class="form-control form-control-sm font-weight-bold" min="0" max="100" step="1" value="{{ $quiz->essay_score !== null ? round($quiz->essay_score) : '' }}" placeholder="Contoh: 90" required>
                                                                </div>
                                                                <div class="col-md-6 mb-2 mb-md-0">
                                                                    <label class="font-weight-bold text-dark text-xs mb-1">Catatan Evaluasi / Ulasan (Opsional):</label>
                                                                    <input type="text" name="essay_feedback" class="form-control form-control-sm" value="{{ $quiz->essay_feedback ?? '' }}" placeholder="Contoh: Pemahaman konsep dan alur kerja sangat baik.">
                                                                </div>
                                                                <div class="col-md-3">
                                                                    <button type="submit" class="btn btn-info btn-sm btn-block font-weight-bold">
                                                                        <i class="fas fa-save mr-1"></i> Simpan Nilai
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            @if($quiz->essay_status === 'graded')
                                                                <div class="text-xs text-success font-weight-bold mt-2">
                                                                    <i class="fas fa-check-circle mr-1"></i> Nilai essay tersimpan {{ $quiz->essay_score }}/100. Nilai akhir total dihitung: (Nilai PG + Essay) secara proporsional.
                                                                </div>
                                                            @else
                                                                <div class="text-xs text-muted mt-2">
                                                                    <i class="fas fa-info-circle mr-1"></i> Nilai essay akan otomatis dikombinasikan dengan nilai PG secara proporsional.
                                                                </div>
                                                            @endif
                                                        </form>
                                                    </div>
                                                </div>
                                                @endif

                                                <h6 class="font-weight-bold mb-3 text-dark">Rincian Pertanyaan & Jawaban Peserta:</h6>
                                                @foreach($training->questions as $qIndex => $question)
                                                    @php
                                                        $ansData = $quiz->answers[$question->id] ?? null;
                                                        $userAns = $ansData['user_answer'] ?? null;
                                                        $isEssay = ($question->type === 'essay');
                                                        $isCorrect = $ansData['is_correct'] ?? false;
                                                    @endphp
                                                    <div class="p-3 mb-3 rounded border {{ $isEssay ? 'border-info' : ($isCorrect ? 'border-success' : 'border-danger') }}" style="background-color: {{ $isEssay ? '#f7faff' : ($isCorrect ? '#fafffa' : '#fff8f8') }};">
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <div>
                                                                <span class="badge {{ $isEssay ? 'badge-info' : 'badge-secondary' }} mr-1">
                                                                    #{{ $qIndex + 1 }} {{ $isEssay ? '(Essay)' : '(PG)' }}
                                                                </span>
                                                                <strong class="text-dark">{{ $question->question }}</strong>
                                                            </div>
                                                            <div>
                                                                @if($isEssay)
                                                                    <span class="badge badge-light border text-info px-2 py-1">Tersimpan</span>
                                                                @else
                                                                    <span class="badge {{ $isCorrect ? 'badge-success' : 'badge-danger' }} px-2 py-1">
                                                                        {{ $isCorrect ? 'Benar' : 'Salah' }}
                                                                    </span>
                                                                @endif
                                                            </div>
                                                        </div>

                                                        <div class="small mt-2 pt-2 border-top">
                                                            @if($isEssay)
                                                                <div class="mb-2">
                                                                    <span class="text-muted font-weight-bold d-block">Jawaban Essay Peserta:</span>
                                                                    <div class="p-2 bg-white rounded border mt-1 text-dark" style="white-space: pre-wrap;">{{ $userAns ?: '(Tidak ada jawaban)' }}</div>
                                                                </div>
                                                                @if($question->correct_answer)
                                                                    <div class="text-info small"><strong>Pedoman / Acuan Jawaban:</strong> {{ $question->correct_answer }}</div>
                                                                @endif
                                                            @else
                                                                <div class="mb-1">Jawaban Peserta: <strong>{{ strtoupper($userAns ?? '-') }}</strong> ({{ $question->{'option_' . $userAns} ?? '-' }})</div>
                                                                @if($question->correct_answer)
                                                                    <div class="text-success">Kunci Jawaban: <strong>{{ strtoupper($question->correct_answer) }}</strong> ({{ $question->{'option_' . $question->correct_answer} ?? '-' }})</div>
                                                                @endif
                                                            @endif

                                                            @if($question->explanation)
                                                                <div class="text-muted mt-2 pt-2 border-top font-italic">Catatan: {{ $question->explanation }}</div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                            <div class="modal-footer py-2">
                                                <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Tutup</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endif

                                <!-- MODAL BUKTI SCREENSHOT -->
                                @if($part->attendance_proof)
                                <div class="modal fade text-left" id="proofModal_{{ $part->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                                    <div class="modal-dialog modal-md modal-dialog-centered" role="document">
                                        <div class="modal-content shadow-lg border-0" style="border-radius: 12px;">
                                            <div class="modal-header py-3 bg-light">
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-circle bg-info text-white d-flex align-items-center justify-content-center mr-2" style="width: 32px; height: 32px;">
                                                        <i class="fas fa-camera"></i>
                                                    </div>
                                                    <div>
                                                        <h6 class="modal-title font-weight-bold text-dark mb-0">Bukti Kehadiran Peserta</h6>
                                                        <small class="text-muted">{{ $user->full_name }} &bull; {{ $user->divisi->name ?? '-' }}</small>
                                                    </div>
                                                </div>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body p-3 text-center">
                                                <div class="mb-2 text-left small text-muted">
                                                    <i class="fas fa-clock mr-1"></i> Waktu Absen: <strong>{{ \Carbon\Carbon::parse($part->attended_at)->format('d F Y, H:i') }} WIB</strong>
                                                </div>
                                                <div class="bg-light p-2 rounded border">
                                                    <a href="{{ asset('storage/' . $part->attendance_proof) }}" target="_blank" title="Buka gambar ukuran asli">
                                                        <img src="{{ asset('storage/' . $part->attendance_proof) }}" alt="Screenshot Bukti" class="img-fluid rounded border shadow-sm" style="max-height: 380px; object-fit: contain;">
                                                    </a>
                                                </div>
                                                <small class="text-muted mt-2 d-block">
                                                    <i class="fas fa-external-link-alt mr-1"></i> Klik foto di atas untuk membuka ukuran penuh di tab baru.
                                                </small>
                                            </div>
                                            <div class="modal-footer py-2 bg-light">
                                                <a href="{{ asset('storage/' . $part->attendance_proof) }}" target="_blank" download class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-download mr-1"></i> Unduh Foto
                                                </a>
                                                <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Tutup</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endif

                                <!-- MODAL ATUR PRESENSI MANUAL -->
                                <div class="modal fade text-left" id="editAttendanceModal_{{ $part->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                                    <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
                                        <div class="modal-content shadow border-0" style="border-radius: 12px;">
                                            <div class="modal-header py-3 bg-light">
                                                <h6 class="modal-title font-weight-bold text-dark mb-0">
                                                    <i class="fas fa-user-edit mr-1 text-info"></i> Atur Presensi Peserta
                                                </h6>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <form action="/training/{{ $training->id }}/update-attendance/{{ $part->id }}" method="POST">
                                                @csrf
                                                <div class="modal-body p-3">
                                                    <div class="mb-2">
                                                        <strong class="d-block text-dark small">{{ $user->full_name }}</strong>
                                                        <span class="text-muted text-xs">{{ $user->id_karyawan ?? '-' }} &bull; {{ $user->divisi->name ?? '-' }}</span>
                                                    </div>
                                                    <div class="form-group mb-3">
                                                        <label class="small font-weight-bold mb-1">Status Kehadiran:</label>
                                                        <select name="attendance_status" class="form-control form-control-sm">
                                                            <option value="pending" {{ $part->attendance_status === 'pending' ? 'selected' : '' }}>Belum Absen</option>
                                                            <option value="hadir" {{ $part->attendance_status === 'hadir' ? 'selected' : '' }}>Hadir</option>
                                                            <option value="tidak_hadir" {{ $part->attendance_status === 'tidak_hadir' ? 'selected' : '' }}>Tidak Hadir</option>
                                                        </select>
                                                        <small class="text-muted d-block mt-1">Pilih 'Belum Absen' untuk mengizinkan peserta absen kembali.</small>
                                                    </div>
                                                    <div class="form-group mb-0">
                                                        <label class="small font-weight-bold mb-1">Catatan / Alasan:</label>
                                                        <input type="text" name="attendance_notes" class="form-control form-control-sm" value="{{ $part->attendance_notes }}" placeholder="Keterangan izin/sakit/hadir...">
                                                    </div>
                                                </div>
                                                <div class="modal-footer py-2 bg-light">
                                                    <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-primary btn-sm px-3 font-weight-bold">Simpan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                Belum ada peserta yang terdaftar pada sesi ini.
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
        <div class="modal-content shadow border-0" style="border-radius: 14px;">
            <div class="modal-header bg-white border-bottom py-3">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-danger text-white mr-2" style="width: 38px; height: 38px;">
                        <i class="fas fa-video"></i>
                    </div>
                    <div>
                        <h5 class="modal-title font-weight-bold text-dark mb-0" id="uploadTrainingVideoModalLabel">Upload Video Materi Pelatihan</h5>
                        <small class="text-muted">Video akan otomatis masuk ke Galeri Video Materi dengan judul pelatihan ini.</small>
                    </div>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('video.store') }}" method="POST" enctype="multipart/form-data" id="uploadTrainingVideoForm">
                @csrf
                <input type="hidden" name="training_id" value="{{ $training->id }}">
                <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">

                <div class="modal-body p-4">
                    {{-- Judul Video Otomatis --}}
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-dark">
                            Judul Video Materi <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="title" class="form-control" value="{{ $training->title }}" required placeholder="Contoh: {{ $training->title }}">
                        <small class="text-muted">
                            <i class="fas fa-magic text-primary mr-1"></i> Terisi otomatis sesuai judul sesi pelatihan saat ini.
                        </small>
                    </div>

                    {{-- Pilihan Tipe Video: Upload File vs Tautan Link --}}
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-dark d-block">Sumber Video <span class="text-danger">*</span></label>
                        <div class="d-flex p-2 bg-light rounded border" style="gap: 20px;">
                            <div class="custom-control custom-radio">
                                <input type="radio" id="showTypeFile" name="video_type" value="file" class="custom-control-input" checked onchange="toggleVideoTypeInShow('file')">
                                <label class="custom-control-label font-weight-bold" for="showTypeFile" style="cursor: pointer;">
                                    <i class="fas fa-file-video text-danger mr-1"></i> Upload File Video
                                </label>
                            </div>
                            <div class="custom-control custom-radio">
                                <input type="radio" id="showTypeLink" name="video_type" value="link" class="custom-control-input" onchange="toggleVideoTypeInShow('link')">
                                <label class="custom-control-label font-weight-bold" for="showTypeLink" style="cursor: pointer;">
                                    <i class="fab fa-youtube text-danger mr-1"></i> Tautan / Link (YouTube / Drive / Zoom)
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Group 1: File Input --}}
                    <div class="form-group mb-3" id="showFileInputGroup">
                        <label class="font-weight-bold text-dark">Pilih File Video Rekaman <span class="text-danger">*</span></label>
                        <div class="custom-file">
                            <input type="file" name="video_file" id="showVideoFileInput" class="custom-file-input" accept="video/mp4,video/webm,video/ogg,video/quicktime,video/x-matroska" required onchange="updateShowFileName(this)">
                            <label class="custom-file-label" id="showVideoFileLabel" for="showVideoFileInput">Pilih file video (MP4, MKV, WEBM, MOV)...</label>
                        </div>
                        <small class="text-muted">Maksimal ukuran file video: 2 GB.</small>
                    </div>

                    {{-- Group 2: Link Input --}}
                    <div class="form-group mb-3" id="showLinkInputGroup" style="display: none;">
                        <label class="font-weight-bold text-dark">URL / Tautan Video <span class="text-danger">*</span></label>
                        <input type="url" name="video_link" id="showVideoLinkInput" class="form-control" placeholder="https://www.youtube.com/watch?v=... atau https://drive.google.com/file/d/...">
                        <small class="text-muted">Mendukung link YouTube, Zoom Cloud Recording, atau Google Drive.</small>
                    </div>

                    {{-- Deskripsi Otomatis --}}
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-dark">Deskripsi & Catatan Sesi (Opsional)</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Rangkuman materi atau topik yang dibahas...">Rekaman video materi sesi pelatihan {{ $training->title }} yang dilaksanakan pada {{ \Carbon\Carbon::parse($training->training_date)->format('d F Y') }}.</textarea>
                    </div>

                    {{-- Thumbnail Cover (Opsional) --}}
                    <div class="form-group mb-0">
                        <label class="font-weight-bold text-dark">Cover / Thumbnail Video (Opsional)</label>
                        <div class="custom-file">
                            <input type="file" name="thumbnail" id="showThumbInput" class="custom-file-input" accept="image/png,image/jpeg,image/webp" onchange="updateShowThumbLabel(this)">
                            <label class="custom-file-label" id="showThumbLabel" for="showThumbInput">Pilih gambar thumbnail (JPG, PNG, WEBP)...</label>
                        </div>
                        <small class="text-muted">Biarkan kosong jika ingin menggunakan cover video bawaan sistem.</small>
                    </div>
                </div>

                <div class="modal-footer bg-light py-3 border-top d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary px-3" data-dismiss="modal">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-danger font-weight-bold px-4 shadow-sm" id="btnSubmitVideo">
                        <i class="fas fa-cloud-upload-alt mr-1"></i> Simpan & Publikasikan ke Video Materi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

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
    const STATS_URL = '/training/{{ $training->id }}/live-stats';

    function updateStat(id, value) {
        const el = document.getElementById(id);
        if (el && el.textContent != value) {
            el.textContent = value;
            // Flash animasi kecil saat nilai berubah
            el.style.transition = 'color 0.3s';
            const orig = el.style.color;
            el.style.color = '#fd7e14';
            setTimeout(function() { el.style.color = orig; }, 600);
        }
    }

    function pollStats() {
        fetch(STATS_URL)
            .then(function(res) { return res.json(); })
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
</script>
@endsection
