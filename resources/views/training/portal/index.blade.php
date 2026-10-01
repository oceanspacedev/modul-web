<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $training->title }} - Portal Pelatihan</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            color: #212529;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .portal-header {
            background-color: #ffffff;
            border-bottom: 1px solid #e9ecef;
            padding: 16px 0;
            margin-bottom: 24px;
        }
        .content-card {
            background: #ffffff;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .content-card-header {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f1f3f5;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    </style>
</head>
<body>

    <header class="portal-header">
        <div class="container" style="max-width: 760px;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong class="text-primary font-weight-bold" style="font-size: 1.1rem;">MODUL WEB</strong>
                    <span class="text-muted ml-2">| Portal Pelatihan</span>
                </div>
                <div class="text-muted small">
                    {{ $user->full_name }}
                </div>
            </div>
        </div>
    </header>

    <main class="container mb-5" style="max-width: 760px;">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                {{ session('success') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        @if(session('warning'))
            <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
                {{ session('warning') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        <!-- DETAIL PELATIHAN -->
        <div class="content-card">
            <div class="content-card-header">
                <span>Informasi Sesi</span>
                @if($training->status === 'ongoing')
                    <span class="badge badge-success px-2 py-1">Berlangsung</span>
                @elseif($training->status === 'completed')
                    <span class="badge badge-secondary px-2 py-1">Selesai</span>
                @else
                    <span class="badge badge-warning text-white px-2 py-1">Terjadwal</span>
                @endif
            </div>

            <h3 class="font-weight-bold mb-3" style="font-size: 1.4rem;">
                {{ $training->title }}
            </h3>

            <div class="row mb-3 text-muted" style="font-size: 0.95rem;">
                <div class="col-sm-6 mb-2">
                    <span class="text-secondary small d-block">Pemateri:</span>
                    <strong class="text-dark">{{ $training->trainer->full_name ?? '-' }}</strong>
                </div>
                <div class="col-sm-6 mb-2">
                    <span class="text-secondary small d-block">Jadwal:</span>
                    <strong class="text-dark">{{ \Carbon\Carbon::parse($training->training_date)->format('d F Y') }}</strong>
                    <span class="small d-block text-muted">{{ substr($training->start_time, 0, 5) }} - {{ substr($training->end_time, 0, 5) }} WIB</span>
                </div>
            </div>

            @if($training->description)
                <div class="p-3 bg-light rounded text-muted mb-4 small border">
                    <strong class="text-dark d-block mb-1">Deskripsi Materi:</strong>
                    {{ $training->description }}
                </div>
            @endif

            <div>
                <a href="{{ $training->zoom_link }}" target="_blank" class="btn btn-primary btn-block py-2 font-weight-bold">
                    Buka Tautan Zoom
                </a>
            </div>
        </div>

        <!-- KONFIRMASI KEHADIRAN -->
        <div class="content-card">
            <div class="content-card-header">
                <span>Presensi Kehadiran</span>
                @if($participant->attendance_status === 'hadir')
                    <span class="badge badge-success">Hadir</span>
                @elseif($participant->attendance_status === 'tidak_hadir')
                    <span class="badge badge-danger">Tidak Hadir</span>
                @else
                    <span class="badge badge-warning text-white">Belum Konfirmasi</span>
                @endif
            </div>

            @if($participant->attendance_status === 'hadir')
                <div class="alert alert-success mb-0">
                    Kehadiran Anda telah dicatat pada pukul {{ \Carbon\Carbon::parse($participant->attended_at)->format('H:i') }} WIB.
                </div>
            @elseif($participant->attendance_status === 'tidak_hadir')
                <div class="alert alert-danger mb-0">
                    Konfirmasi tidak hadir telah dicatat.
                    @if($participant->attendance_notes)
                        <div class="mt-1 small">Keterangan: {{ $participant->attendance_notes }}</div>
                    @endif
                </div>
            @else
                <p class="text-muted small mb-3">
                    Silakan lakukan konfirmasi kehadiran Anda untuk sesi pelatihan ini:
                </p>
                <div class="d-flex" style="gap: 12px;">
                    <form action="/training/portal/{{ $participant->token }}/attendance" method="POST" class="flex-grow-1">
                        @csrf
                        <input type="hidden" name="status" value="hadir">
                        <button type="submit" class="btn btn-success btn-block py-2 font-weight-bold">
                            Saya Hadir
                        </button>
                    </form>

                    <button type="button" class="btn btn-outline-secondary px-4 py-2" data-toggle="collapse" data-target="#notAttendBox">
                        Tidak Hadir
                    </button>
                </div>

                <div class="collapse mt-3" id="notAttendBox">
                    <form action="/training/portal/{{ $participant->token }}/attendance" method="POST" class="p-3 border rounded bg-light">
                        @csrf
                        <input type="hidden" name="status" value="tidak_hadir">
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold mb-1">Alasan Tidak Hadir:</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Tuliskan keterangan..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger btn-sm px-3">
                            Simpan Keterangan
                        </button>
                    </form>
                </div>
            @endif
        </div>

        <!-- KUIS EVALUASI -->
        <div class="content-card">
            <div class="content-card-header">
                <span>Kuis Evaluasi</span>
                <span class="text-muted small">{{ $training->questions->count() }} Pertanyaan</span>
            </div>

            @if($participant->quizResult)
                @if($training->questions->count() > $participant->quizResult->total_questions)
                    <div class="alert alert-warning mb-3 small">
                        Pemateri telah menambahkan soal baru pada pelatihan ini (Total: {{ $training->questions->count() }} soal). Anda dapat mengulang kuis untuk melengkapi jawaban.
                    </div>
                @endif

                <div class="p-4 bg-light rounded text-center border mb-3">
                    <span class="text-muted small text-uppercase font-weight-bold d-block mb-1">Nilai Akhir Anda</span>
                    <h2 class="display-4 font-weight-bold text-primary mb-2">{{ $participant->quizResult->score }}</h2>
                    <span class="text-muted small">
                        Jawaban Benar: {{ $participant->quizResult->correct_answers }} dari {{ $participant->quizResult->total_questions }} Soal
                    </span>
                </div>

                <div class="d-flex justify-content-center" style="gap: 10px;">
                    <a href="/training/portal/{{ $participant->token }}/result" class="btn btn-outline-primary btn-sm px-3 py-2 font-weight-bold">
                        Lihat Pembahasan
                    </a>
                    @if($training->is_quiz_active)
                        <a href="/training/portal/{{ $participant->token }}/retake" onclick="return confirm('Kerjakan kuis kembali?')" class="btn btn-warning text-white btn-sm px-3 py-2 font-weight-bold">
                            Kerjakan Ulang
                        </a>
                    @endif
                </div>
            @else
                @if($training->is_quiz_active)
                    <p class="text-muted small mb-3">
                        Kuis evaluasi telah dibuka oleh pemateri. Silakan klik tombol di bawah untuk mulai mengerjakan:
                    </p>
                    <a href="/training/portal/{{ $participant->token }}/quiz" class="btn btn-primary btn-block py-2 font-weight-bold">
                        Mulai Kerjakan Kuis
                    </a>
                @else
                    <div class="text-center py-4 text-muted">
                        Kuis evaluasi saat ini belum dibuka oleh pemateri.
                    </div>
                @endif
            @endif
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
