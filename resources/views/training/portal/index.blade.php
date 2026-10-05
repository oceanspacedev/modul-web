<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $training->title }} - Portal Pelatihan</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
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

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <strong><i class="fas fa-exclamation-circle mr-1"></i> Perhatian:</strong>
                <ul class="mb-0 pl-3 mt-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
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
                    <i class="fas fa-video mr-1"></i> Buka Tautan Zoom
                </a>
            </div>
        </div>

        <!-- KONFIRMASI KEHADIRAN -->
        <div class="content-card">
            <div class="content-card-header">
                <span>Presensi Kehadiran</span>
                @if($participant->attendance_status === 'hadir')
                    <span class="badge badge-success px-2 py-1">Hadir</span>
                @elseif($participant->attendance_status === 'tidak_hadir')
                    <span class="badge badge-danger px-2 py-1">Tidak Hadir</span>
                @else
                    <span class="badge badge-warning text-white px-2 py-1">Belum Konfirmasi</span>
                @endif
            </div>

            @if($participant->attendance_status === 'hadir')
                <div class="alert alert-success mb-0">
                    <div class="d-flex align-items-center mb-1">
                        <i class="fas fa-check-circle fa-lg mr-2"></i>
                        <strong>Kehadiran Anda Telah Terverifikasi</strong>
                    </div>
                    <p class="small mb-0">
                        Dicatat pada {{ \Carbon\Carbon::parse($participant->attended_at)->format('d F Y, H:i') }} WIB.
                    </p>

                    @if($participant->attendance_proof)
                        <div class="mt-3 p-3 bg-white rounded border">
                            <span class="small font-weight-bold text-dark d-block mb-2">
                                <i class="fas fa-camera mr-1 text-primary"></i> Bukti Screenshot Pelatihan / Zoom:
                            </span>
                            <div class="text-center">
                                <a href="{{ asset('storage/' . $participant->attendance_proof) }}" target="_blank" title="Klik untuk perbesar">
                                    <img src="{{ asset('storage/' . $participant->attendance_proof) }}" alt="Bukti Kehadiran" class="img-fluid rounded border shadow-sm" style="max-height: 220px; object-fit: contain;">
                                </a>
                                <small class="text-muted d-block mt-1 font-italic">
                                    <i class="fas fa-external-link-alt mr-1"></i> Klik gambar untuk melihat ukuran penuh
                                </small>
                            </div>
                        </div>
                    @endif
                </div>
            @elseif($participant->attendance_status === 'tidak_hadir')
                <div class="alert alert-danger mb-0">
                    <div class="d-flex align-items-center mb-1">
                        <i class="fas fa-times-circle fa-lg mr-2"></i>
                        <strong>Konfirmasi Tidak Hadir Telah Dicatat</strong>
                    </div>
                    @if($participant->attendance_notes)
                        <div class="small mt-1">Keterangan: {{ $participant->attendance_notes }}</div>
                    @endif
                </div>
            @else
                {{-- BELUM ABSEN (STATUS: PENDING) --}}
                @if(!$training->is_attendance_active && !$training->is_quiz_active)
                    <div class="text-center py-4">
                        <div class="mb-2 text-warning" style="font-size: 2.2rem;">
                            <i class="fas fa-user-clock"></i>
                        </div>
                        <h6 class="font-weight-bold text-dark mb-1">Presensi Kehadiran Belum Dibuka</h6>
                        <p class="text-muted small mb-0" style="max-width: 480px; margin: 0 auto;">
                            Sesi presensi kehadiran saat ini belum dibuka oleh Pemateri / Admin. Silakan tunggu instruksi dari pemateri saat sesi pelatihan berlangsung.
                        </p>
                    </div>
                @else
                    <p class="text-muted small mb-3">
                        Sesi presensi telah dibuka! Silakan lakukan konfirmasi kehadiran Anda untuk pelatihan ini:
                    </p>

                    @if($training->require_attendance_proof)
                        <div class="alert alert-info py-2 px-3 small mb-3">
                            <i class="fas fa-info-circle mr-1"></i> <strong>Wajib Screenshot:</strong> Anda diwajibkan mengunggah foto / tangkapan layar (screenshot) saat bergabung dalam sesi Zoom / pelatihan.
                        </div>
                    @endif

                    <form action="/training/portal/{{ $participant->token }}/attendance" method="POST" enctype="multipart/form-data" id="attendanceForm">
                        @csrf
                        <input type="hidden" name="status" value="hadir">

                        @if($training->require_attendance_proof)
                            <div class="form-group mb-3">
                                <label class="small font-weight-bold text-dark mb-1" for="attendanceProofInput">
                                    Unggah Screenshot Bukti Zoom / Pelatihan <span class="text-danger">*</span>
                                </label>
                                <div class="custom-file">
                                    <input type="file" name="attendance_proof" id="attendanceProofInput" class="custom-file-input" accept="image/png, image/jpeg, image/jpg, image/webp" required onchange="previewAttendanceProof(this)">
                                    <label class="custom-file-label text-muted text-truncate" for="attendanceProofInput" id="attendanceProofLabel">
                                        Pilih screenshot bukti zoom...
                                    </label>
                                </div>
                                <small class="form-text text-muted">Format: JPG, PNG, atau WEBP. Maksimal 5MB. Pastikan tampilan Zoom / nama Anda tampak.</small>

                                <div id="proofPreviewWrapper" class="mt-2 text-center d-none">
                                    <img id="proofPreviewImg" src="#" alt="Preview Screenshot" class="img-fluid rounded border shadow-sm" style="max-height: 180px; object-fit: contain;">
                                    <div class="mt-1">
                                        <button type="button" class="btn btn-link btn-xs text-danger" onclick="clearProofInput()">
                                            <i class="fas fa-trash-alt mr-1"></i> Ganti Screenshot
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="d-flex" style="gap: 12px;">
                            <button type="submit" class="btn btn-success flex-grow-1 py-2 font-weight-bold">
                                <i class="fas fa-check-circle mr-1"></i> Saya Hadir
                            </button>

                            <button type="button" class="btn btn-outline-secondary px-4 py-2" data-toggle="collapse" data-target="#notAttendBox">
                                Tidak Hadir
                            </button>
                        </div>
                    </form>

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
    <script>
        function previewAttendanceProof(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const label = document.getElementById('attendanceProofLabel');
                const previewWrapper = document.getElementById('proofPreviewWrapper');
                const previewImg = document.getElementById('proofPreviewImg');

                if (label) {
                    label.textContent = file.name;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    if (previewImg && previewWrapper) {
                        previewImg.src = e.target.result;
                        previewWrapper.classList.remove('d-none');
                    }
                };
                reader.readAsDataURL(file);
            }
        }

        function clearProofInput() {
            const input = document.getElementById('attendanceProofInput');
            const label = document.getElementById('attendanceProofLabel');
            const previewWrapper = document.getElementById('proofPreviewWrapper');
            const previewImg = document.getElementById('proofPreviewImg');

            if (input) input.value = '';
            if (label) label.textContent = 'Pilih screenshot bukti zoom...';
            if (previewImg) previewImg.src = '#';
            if (previewWrapper) previewWrapper.classList.add('d-none');
        }
    </script>
</body>
</html>
