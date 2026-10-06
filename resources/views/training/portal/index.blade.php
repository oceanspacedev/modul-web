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
        @keyframes livePulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%       { opacity: 0.4; transform: scale(1.3); }
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
        <div class="content-card" id="attendanceCard">
            <div class="content-card-header">
                <span>Presensi Kehadiran</span>
                <div class="d-flex align-items-center" style="gap: 8px;">
                    @if($participant->attendance_status === 'hadir')
                        <span class="badge badge-success px-2 py-1" id="attendanceBadge">Hadir</span>
                    @elseif($participant->attendance_status === 'tidak_hadir')
                        <span class="badge badge-danger px-2 py-1" id="attendanceBadge">Tidak Hadir</span>
                    @else
                        <span class="badge badge-warning text-white px-2 py-1" id="attendanceBadge">Belum Konfirmasi</span>
                    @endif
                    <span id="liveIndicator" class="d-flex align-items-center" style="gap: 4px;" title="Halaman ini memperbarui status secara otomatis">
                        <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#28a745;animation:livePulse 1.5s infinite;"></span>
                        <span class="text-muted" style="font-size:0.75rem;">Live</span>
                    </span>
                </div>
            </div>

            @if($participant->attendance_status === 'hadir')
                <div class="alert alert-success mb-0" id="attendanceBody">
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
                <div class="alert alert-danger mb-0" id="attendanceBody">
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
                @if(!$training->is_attendance_active)
                    <div class="text-center py-4" id="attendanceBody" data-state="locked">
                        <div class="mb-2 text-warning" style="font-size: 2.2rem;">
                            <i class="fas fa-user-clock"></i>
                        </div>
                        <h6 class="font-weight-bold text-dark mb-1">Presensi Kehadiran Belum Dibuka</h6>
                        <p class="text-muted small mb-0" style="max-width: 480px; margin: 0 auto;">
                            Sesi presensi kehadiran saat ini belum dibuka oleh Pemateri / Admin. Silakan tunggu instruksi dari pemateri saat sesi pelatihan berlangsung.
                        </p>
                    </div>
                @else
                    <div id="attendanceBody" data-state="open">
                    <p class="text-muted small mb-3">
                        Sesi presensi telah dibuka! Silakan lakukan konfirmasi kehadiran Anda untuk pelatihan ini:
                    </p>

                    @if($training->require_attendance_proof)
                        <div class="alert alert-info py-2 px-3 small mb-3" id="proofInfoBox">
                            <i class="fas fa-info-circle mr-1"></i> <strong>Wajib Screenshot:</strong> Anda diwajibkan mengunggah foto / tangkapan layar (screenshot) saat bergabung dalam sesi Zoom / pelatihan.
                        </div>
                    @else
                        <div class="d-none" id="proofInfoBox"></div>
                    @endif

                    <form action="/training/portal/{{ $participant->token }}/attendance" method="POST" enctype="multipart/form-data" id="attendanceForm">
                        @csrf
                        <input type="hidden" name="status" value="hadir">

                        <div id="proofFieldWrapper" class="{{ $training->require_attendance_proof ? '' : 'd-none' }}">
                            <div class="form-group mb-3">
                                <label class="small font-weight-bold text-dark mb-1" for="attendanceProofInput">
                                    Unggah Screenshot Bukti Zoom / Pelatihan <span class="text-danger">*</span>
                                </label>
                                <div class="custom-file">
                                    <input type="file" name="attendance_proof" id="attendanceProofInput" class="custom-file-input" accept="image/png, image/jpeg, image/jpg, image/webp" {{ $training->require_attendance_proof ? 'required' : '' }} onchange="previewAttendanceProof(this)">
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
                        </div>

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
                    </div>
                @endif
            @endif
        </div>

        <!-- KUIS EVALUASI -->
        <div class="content-card" id="quizCard">
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
                    <div id="quizRetakeBtn">
                    @if($training->is_quiz_active)
                        <a href="/training/portal/{{ $participant->token }}/retake" onclick="return confirm('Kerjakan kuis kembali?')" class="btn btn-warning text-white btn-sm px-3 py-2 font-weight-bold">
                            Kerjakan Ulang
                        </a>
                    @endif
                    </div>
                </div>
            @else
                <div id="quizBody">
                @if($training->is_quiz_active)
                    <p class="text-muted small mb-3">
                        Kuis evaluasi telah dibuka oleh pemateri. Silakan klik tombol di bawah untuk mulai mengerjakan:
                    </p>
                    <a href="/training/portal/{{ $participant->token }}/quiz" class="btn btn-primary btn-block py-2 font-weight-bold">
                        Mulai Kerjakan Kuis
                    </a>
                @else
                    <div class="text-center py-4 text-muted" id="quizLockedMsg">
                        Kuis evaluasi saat ini belum dibuka oleh pemateri.
                    </div>
                @endif
                </div>
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

        // ---------------------------------------------------------------
        // LIVE POLLING — cek status absen & kuis setiap 5 detik
        // ---------------------------------------------------------------
        (function () {
            const TOKEN      = '{{ $participant->token }}';
            const STATUS_URL = '/training/portal/' + TOKEN + '/status';
            const QUIZ_URL   = '/training/portal/' + TOKEN + '/quiz';
            const RETAKE_URL = '/training/portal/' + TOKEN + '/retake';

            // State saat ini dari server (render awal Blade)
            // Absen HANYA track is_attendance_active, tidak terpengaruh is_quiz_active
            let currentAttendanceActive = {{ $training->is_attendance_active ? 'true' : 'false' }};
            let currentQuizActive       = {{ $training->is_quiz_active ? 'true' : 'false' }};
            let currentRequireProof     = {{ $training->require_attendance_proof ? 'true' : 'false' }};
            const attendanceStatus      = '{{ $participant->attendance_status }}';
            const hasQuizResult         = {{ $participant->quizResult ? 'true' : 'false' }};

            // ---- HTML builders (pakai nilai requireProof dari API, bukan Blade) ----

            function buildAttendanceOpenHTML(requireProof) {
                let proofHtml = '';
                if (requireProof) {
                    proofHtml = `
                        <div class="alert alert-info py-2 px-3 small mb-3">
                            <i class="fas fa-info-circle mr-1"></i> <strong>Wajib Screenshot:</strong>
                            Anda diwajibkan mengunggah foto / tangkapan layar saat bergabung dalam sesi Zoom / pelatihan.
                        </div>
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-dark mb-1" for="attendanceProofInput">
                                Unggah Screenshot Bukti Zoom / Pelatihan <span class="text-danger">*</span>
                            </label>
                            <div class="custom-file">
                                <input type="file" name="attendance_proof" id="attendanceProofInput"
                                    class="custom-file-input"
                                    accept="image/png, image/jpeg, image/jpg, image/webp"
                                    required
                                    onchange="previewAttendanceProof(this)">
                                <label class="custom-file-label text-muted text-truncate"
                                    for="attendanceProofInput" id="attendanceProofLabel">
                                    Pilih screenshot bukti zoom...
                                </label>
                            </div>
                            <small class="form-text text-muted">Format: JPG, PNG, atau WEBP. Maksimal 5MB.</small>
                            <div id="proofPreviewWrapper" class="mt-2 text-center d-none">
                                <img id="proofPreviewImg" src="#" alt="Preview Screenshot"
                                    class="img-fluid rounded border shadow-sm"
                                    style="max-height: 180px; object-fit: contain;">
                                <div class="mt-1">
                                    <button type="button" class="btn btn-link btn-xs text-danger" onclick="clearProofInput()">
                                        <i class="fas fa-trash-alt mr-1"></i> Ganti Screenshot
                                    </button>
                                </div>
                            </div>
                        </div>`;
                }
                return `
                    <p class="text-muted small mb-3">
                        Sesi presensi telah dibuka! Silakan lakukan konfirmasi kehadiran Anda:
                    </p>
                    ${proofHtml}
                    <form action="/training/portal/${TOKEN}/attendance" method="POST"
                          enctype="multipart/form-data" id="attendanceForm">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <input type="hidden" name="status" value="hadir">
                        <div class="d-flex" style="gap: 12px;">
                            <button type="submit" class="btn btn-success flex-grow-1 py-2 font-weight-bold">
                                <i class="fas fa-check-circle mr-1"></i> Saya Hadir
                            </button>
                            <button type="button" class="btn btn-outline-secondary px-4 py-2"
                                    data-toggle="collapse" data-target="#notAttendBox">
                                Tidak Hadir
                            </button>
                        </div>
                    </form>
                    <div class="collapse mt-3" id="notAttendBox">
                        <form action="/training/portal/${TOKEN}/attendance" method="POST"
                              class="p-3 border rounded bg-light">
                            <input type="hidden" name="_token" value="{{ csrf_token() }}">
                            <input type="hidden" name="status" value="tidak_hadir">
                            <div class="form-group mb-3">
                                <label class="small font-weight-bold mb-1">Alasan Tidak Hadir:</label>
                                <textarea name="notes" class="form-control" rows="2"
                                          placeholder="Tuliskan keterangan..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-danger btn-sm px-3">
                                Simpan Keterangan
                            </button>
                        </form>
                    </div>`;
            }

            function buildAttendanceLockedHTML() {
                return `
                    <div class="text-center py-4">
                        <div class="mb-2 text-warning" style="font-size: 2.2rem;">
                            <i class="fas fa-user-clock"></i>
                        </div>
                        <h6 class="font-weight-bold text-dark mb-1">Presensi Kehadiran Belum Dibuka</h6>
                        <p class="text-muted small mb-0" style="max-width: 480px; margin: 0 auto;">
                            Sesi presensi kehadiran saat ini belum dibuka oleh Pemateri / Admin.
                            Halaman ini memperbarui otomatis — Anda tidak perlu me-refresh.
                        </p>
                    </div>`;
            }

            function buildQuizOpenHTML() {
                return `
                    <p class="text-muted small mb-3">
                        Kuis evaluasi telah dibuka oleh pemateri. Silakan klik tombol di bawah untuk mulai mengerjakan:
                    </p>
                    <a href="${QUIZ_URL}" class="btn btn-primary btn-block py-2 font-weight-bold">
                        Mulai Kerjakan Kuis
                    </a>`;
            }

            function buildQuizLockedHTML() {
                return `
                    <div class="text-center py-4 text-muted">
                        Kuis evaluasi saat ini belum dibuka oleh pemateri.
                    </div>`;
            }

            // Render ulang form absen (dipakai saat toggle require_proof berubah)
            function refreshAttendanceForm(requireProof) {
                const attendanceBody = document.getElementById('attendanceBody');

                // Kasus 1: form sudah di-inject via polling (innerHTML)
                // → rebuild seluruh form dengan nilai baru
                if (attendanceBody && attendanceBody.getAttribute('data-state') === 'open' &&
                    !document.getElementById('proofFieldWrapper')) {
                    attendanceBody.innerHTML = buildAttendanceOpenHTML(requireProof);
                    return;
                }

                // Kasus 2: form render dari Blade (ada id proofFieldWrapper)
                // → cukup toggle wrapper + required attribute tanpa rebuild
                const proofFieldWrapper = document.getElementById('proofFieldWrapper');
                const proofInfoBox      = document.getElementById('proofInfoBox');
                const proofInput        = document.getElementById('attendanceProofInput');

                if (proofFieldWrapper) {
                    if (requireProof) {
                        proofFieldWrapper.classList.remove('d-none');
                        if (proofInput) proofInput.setAttribute('required', 'required');
                        if (proofInfoBox) {
                            proofInfoBox.className = 'alert alert-info py-2 px-3 small mb-3';
                            proofInfoBox.innerHTML = '<i class="fas fa-info-circle mr-1"></i> <strong>Wajib Screenshot:</strong> Anda diwajibkan mengunggah foto / tangkapan layar saat bergabung dalam sesi Zoom / pelatihan.';
                        }
                    } else {
                        proofFieldWrapper.classList.add('d-none');
                        if (proofInput) {
                            proofInput.removeAttribute('required');
                            proofInput.value = '';
                        }
                        // Reset preview
                        const previewWrapper = document.getElementById('proofPreviewWrapper');
                        const previewImg     = document.getElementById('proofPreviewImg');
                        const proofLabel     = document.getElementById('attendanceProofLabel');
                        if (previewWrapper) previewWrapper.classList.add('d-none');
                        if (previewImg) previewImg.src = '#';
                        if (proofLabel) proofLabel.textContent = 'Pilih screenshot bukti zoom...';
                        if (proofInfoBox) {
                            proofInfoBox.className = 'd-none';
                            proofInfoBox.innerHTML = '';
                        }
                    }
                }
            }

            function poll() {
                fetch(STATUS_URL)
                    .then(function(res) { return res.json(); })
                    .then(function(data) {

                        // ---- ABSENSI (hanya jika belum absen) ----
                        if (attendanceStatus === 'pending') {
                            const attendanceBody  = document.getElementById('attendanceBody');
                            const attendanceBadge = document.getElementById('attendanceBadge');

                            // Absensi HANYA mengikuti is_attendance_active
                            // is_quiz_active TIDAK mempengaruhi tampilan absensi
                            const attendanceOpen = data.is_attendance_active;

                            if (attendanceOpen && !currentAttendanceActive) {
                                // Admin buka absen → tampilkan form
                                currentAttendanceActive = true;
                                currentRequireProof     = data.require_attendance_proof;
                                if (attendanceBody) {
                                    attendanceBody.innerHTML = buildAttendanceOpenHTML(data.require_attendance_proof);
                                    attendanceBody.setAttribute('data-state', 'open');
                                }
                                if (attendanceBadge) {
                                    attendanceBadge.className   = 'badge badge-warning text-white px-2 py-1';
                                    attendanceBadge.textContent = 'Belum Konfirmasi';
                                }
                            } else if (!attendanceOpen && currentAttendanceActive) {
                                // Admin tutup absen → kembali ke pesan terkunci
                                currentAttendanceActive = false;
                                if (attendanceBody) {
                                    attendanceBody.innerHTML = buildAttendanceLockedHTML();
                                    attendanceBody.setAttribute('data-state', 'locked');
                                }
                            } else if (attendanceOpen && data.require_attendance_proof !== currentRequireProof) {
                                // Admin toggle wajib screenshot saat absen sudah terbuka
                                // → render ulang form agar field upload muncul/hilang tanpa refresh
                                currentRequireProof = data.require_attendance_proof;
                                refreshAttendanceForm(data.require_attendance_proof);
                            }
                        }

                        // ---- KUIS (hanya jika belum ada hasil) ----
                        if (!hasQuizResult) {
                            const quizBody = document.getElementById('quizBody');
                            if (!quizBody) return;

                            if (data.is_quiz_active && !currentQuizActive) {
                                currentQuizActive = true;
                                quizBody.innerHTML = buildQuizOpenHTML();
                            } else if (!data.is_quiz_active && currentQuizActive) {
                                currentQuizActive = false;
                                quizBody.innerHTML = buildQuizLockedHTML();
                            }
                        }

                        // ---- Tombol retake (jika sudah ada hasil) ----
                        if (hasQuizResult) {
                            const retakeBtn = document.getElementById('quizRetakeBtn');
                            if (retakeBtn) {
                                if (data.is_quiz_active) {
                                    retakeBtn.innerHTML = `<a href="${RETAKE_URL}"
                                        onclick="return confirm('Kerjakan kuis kembali?')"
                                        class="btn btn-warning text-white btn-sm px-3 py-2 font-weight-bold">
                                        Kerjakan Ulang
                                    </a>`;
                                } else {
                                    retakeBtn.innerHTML = '';
                                }
                            }
                        }
                    })
                    .catch(function() { /* silent fail */ });
            }

            // Jalankan polling setiap 5 detik
            setInterval(poll, 5000);
        })();
    </script>
</body>
</html>
