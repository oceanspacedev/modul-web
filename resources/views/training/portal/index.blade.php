<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $training->title }} - Portal Pelatihan</title>
    @include('layout.partials.portal-head')
</head>
<body>

    <header class="portal-header">
        <div class="container portal-container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="portal-brand"><img src="{{ asset('images/logo.svg') }}" alt="" class="brand-logo"> Modul <span class="portal-brand-sub">Portal Pelatihan</span></span>
                </div>
                <div class="text-muted small">
                    {{ $user->nama_lengkap }}
                </div>
            </div>
        </div>
    </header>

    <main class="container portal-container mb-5">
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
        @php
            $step = 0;
            $pretestResult = $participant->pretestResult;
            $pretestQuestionCount = $training->questionsFor('pretest')->count();
            $evaluation = $participant->evaluation;
            $evaluationUrl = '/training/portal/'.$participant->token.'/evaluation';
            // Evaluasi diisi sebelum posttest: selama belum terisi, tombol kuis mengarah ke form evaluasi.
            $awaitsEvaluation = $training->awaitsEvaluationFrom($participant);
            $quizActive = $training->isPhaseActive('posttest');
            // Teks bukti presensi: tangkapan layar Zoom untuk pelatihan daring, foto di lokasi untuk tatap muka.
            $proofText = $training->isOnline() ? [
                'shown' => 'Bukti Screenshot Pelatihan / Zoom',
                'requiredTitle' => 'Wajib Screenshot',
                'required' => 'Anda diwajibkan mengunggah foto / tangkapan layar saat bergabung dalam sesi Zoom / pelatihan.',
                'upload' => 'Unggah Screenshot Bukti Zoom / Pelatihan',
                'pick' => 'Pilih screenshot bukti zoom...',
                'hint' => 'Pastikan tampilan Zoom / nama Anda tampak.',
                'replace' => 'Ganti Screenshot',
            ] : [
                'shown' => 'Foto Bukti Kehadiran',
                'requiredTitle' => 'Wajib Foto',
                'required' => 'Anda diwajibkan mengunggah foto bukti kehadiran di lokasi pelatihan.',
                'upload' => 'Unggah Foto Bukti Kehadiran',
                'pick' => 'Pilih foto bukti kehadiran...',
                'hint' => 'Pastikan Anda tampak di lokasi pelatihan.',
                'replace' => 'Ganti Foto',
            ];
            // Fase yang dijadwalkan pemateri: peserta diberi tahu jam bukanya.
            $pretestOpensAt = $training->phaseOpensAt('pretest');
            $quizOpensAt = $training->phaseOpensAt('posttest');
        @endphp

        @if($training->has_pretest)
        <!-- PRETEST -->
        <div class="content-card" id="pretestCard">
            <div class="content-card-header">
                <span><span class="badge badge-primary mr-1">Langkah {{ ++$step }}</span> Pretest</span>
                <span class="text-muted small">{{ $pretestQuestionCount }} Pertanyaan</span>
            </div>

            @if($training->quizAttemptsLeft($pretestResult, 'pretest') !== 0)
                @include('training.partials.portal-deadline', ['phase' => 'pretest'])
            @endif

            @if($pretestResult)
                <div class="p-4 bg-light rounded text-center border mb-3">
                    <span class="text-muted small d-block mb-1">Nilai pretest Anda</span>
                    <div class="score-display mb-2">{{ $pretestResult->score }}</div>
                    <span class="text-muted small">
                        Jawaban Benar: {{ $pretestResult->correct_answers }} dari {{ $pretestResult->total_questions }} Soal
                    </span>
                </div>

                <div class="d-flex justify-content-center gap-2">
                    <a href="/training/portal/{{ $participant->token }}/pretest/result" class="btn btn-default">
                        Lihat Hasil
                    </a>
                    @if($training->canRetakeQuiz($pretestResult, 'pretest'))
                        <a href="/training/portal/{{ $participant->token }}/pretest" class="btn btn-primary">
                            Kerjakan Ulang
                        </a>
                    @endif
                </div>
                @if($training->allowsQuizRetake('pretest'))
                    <p class="text-muted text-xs text-center mt-2 mb-0">
                        {{ ucfirst(\App\Support\QuizRules::remainingLabel($training->quizAttemptsLeft($pretestResult, 'pretest'))) }}; nilai tertinggi yang dipakai.
                    </p>
                @endif
            @elseif($training->isPhaseActive('pretest'))
                <p class="text-muted small mb-3">
                    Kerjakan pretest sebelum sesi dimulai. {{ \App\Support\QuizRules::summary($training->maxAttempts('pretest'), $training->showsAnswers('pretest')) }}.
                </p>
                <a href="/training/portal/{{ $participant->token }}/pretest" class="btn btn-primary btn-block">
                    Mulai Pretest
                </a>
            @else
                <div class="text-center py-4 text-muted" data-locked-text="pretest"
                    data-default="Pretest belum dibuka oleh pemateri." data-scheduled="Pretest dibuka otomatis " data-timed-out="Waktu pengerjaan pretest sudah habis.">
                    @if($pretestOpensAt)
                        Pretest dibuka otomatis {{ \App\Models\Training::timeLabel($pretestOpensAt) }} WIB.
                    @elseif($training->phaseTimedOut('pretest'))
                        Waktu pengerjaan pretest sudah habis.
                    @else
                        Pretest belum dibuka oleh pemateri.
                    @endif
                </div>
            @endif
        </div>
        @endif

        <div class="content-card">
            <div class="content-card-header">
                <span><span class="badge badge-primary mr-1">Langkah {{ ++$step }}</span> Ikuti Sesi</span>
                <x-training-status-badge :status="$training->status" />
            </div>

            <h1 class="page-title mb-2" style="font-size: 1.375rem;">
                {{ $training->title }}
            </h1>

            <div class="row mb-3">
                <div class="col-sm-6 mb-2">
                    <span class="text-muted small d-block">Pemateri</span>
                    <strong>{{ $training->trainer->nama_lengkap ?? '-' }}</strong>
                </div>
                <div class="col-sm-6 mb-2">
                    <span class="text-muted small d-block">Jadwal</span>
                    <strong>{{ \Carbon\Carbon::parse($training->training_date)->format('d F Y') }}</strong>
                    <span class="small d-block text-muted">{{ substr($training->start_time, 0, 5) }} - {{ substr($training->end_time, 0, 5) }} WIB</span>
                </div>
            </div>

            @if($training->description)
                <div class="p-3 bg-light rounded text-muted mb-4 small border">
                    <strong class="text-dark d-block mb-1">Deskripsi Materi:</strong>
                    {{ $training->description }}
                </div>
            @endif

            @if($training->status === App\Enums\TrainingStatus::Cancelled)
                <div class="alert alert-danger mb-0">
                    <i class="fas fa-ban mr-1"></i> Pelatihan ini dibatalkan.{{ $training->zoom_link ? ' Tautan Zoom tidak lagi tersedia.' : '' }}
                </div>
            @elseif($training->zoom_link)
                <div>
                    <a href="{{ $training->zoom_link }}" target="_blank" rel="noopener" class="btn btn-primary btn-block">
                        <i class="fas fa-video mr-1"></i> Buka Tautan Zoom
                    </a>
                </div>
            @else
                {{-- Pelatihan tatap muka: tidak ada tautan meeting. --}}
                <div class="alert alert-light border mb-0 small">
                    <i class="fas fa-map-marker-alt mr-1"></i> Pelatihan ini diadakan tatap muka, tanpa tautan meeting.
                </div>
            @endif

            @if($training->documents->isNotEmpty())
                <div class="mt-3 pt-3 border-top">
                    <strong class="d-block text-dark small mb-2">Dokumen Materi Pelatihan</strong>
                    <div class="list-group list-group-flush border rounded">
                        @foreach($training->documents as $pdoc)
                            @php
                                $pver = $pdoc->versions->first();
                                // Dokumen ber-pretest/posttest dibuka lewat halaman kuisnya (perlu login).
                                $pquiz = $pdoc->activeQuiz();
                                $purl = $pquiz ? $pquiz->url('show') : ($pver ? $pver->file_url : $pdoc->file_url);
                            @endphp
                            <a href="{{ $purl }}" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between p-2">
                                <div class="text-truncate mr-2">
                                    <span class="font-weight-bold text-dark small text-truncate d-block">{{ $pdoc->name }}</span>
                                    <span class="text-xs text-muted">{{ $pdoc->dokumentype->name ?? 'Dokumen' }} &bull; Versi {{ $pdoc->version ?? 1 }}</span>
                                </div>
                                <span class="badge badge-secondary">Buka</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <!-- KONFIRMASI KEHADIRAN (tidak tampil bila pelatihan tanpa presensi) -->
        @if($training->has_attendance)
        <div class="content-card" id="attendanceCard">
            <div class="content-card-header">
                <span><span class="badge badge-primary mr-1">Langkah {{ ++$step }}</span> Presensi Kehadiran</span>
                <div class="d-flex align-items-center gap-2">
                    @if($participant->attendance_status === 'hadir')
                        <span class="badge badge-success" id="attendanceBadge">Hadir</span>
                    @elseif($participant->attendance_status === 'tidak_hadir')
                        <span class="badge badge-danger" id="attendanceBadge">Tidak Hadir</span>
                    @else
                        <span class="badge badge-warning" id="attendanceBadge">Belum Konfirmasi</span>
                    @endif
                    <span id="liveIndicator" class="d-flex align-items-center gap-1" title="Halaman ini memperbarui status secara otomatis">
                        <span class="live-dot"></span>
                        <span class="text-muted text-xs">Live</span>
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
                                <i class="fas fa-camera mr-1 text-primary"></i> {{ $proofText['shown'] }}:
                            </span>
                            <div class="text-center">
                                <a href="{{ $participant->attendance_proof_url }}" target="_blank" title="Klik untuk perbesar">
                                    <img src="{{ $participant->attendance_proof_url }}" alt="Bukti Kehadiran" class="img-fluid rounded border shadow-sm" style="max-height: 220px; object-fit: contain;">
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
                        <span class="empty-state-icon"><i class="fas fa-user-clock"></i></span>
                        <h6 class="mb-1">Presensi Kehadiran Belum Dibuka</h6>
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
                            <i class="fas fa-info-circle mr-1"></i> <strong>{{ $proofText['requiredTitle'] }}:</strong> {{ $proofText['required'] }}
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
                                    {{ $proofText['upload'] }} <span class="text-danger">*</span>
                                </label>
                                <div class="custom-file">
                                    <input type="file" name="attendance_proof" id="attendanceProofInput" class="custom-file-input" accept="image/png, image/jpeg, image/jpg, image/webp" {{ $training->require_attendance_proof ? 'required' : '' }} onchange="previewAttendanceProof(this)">
                                    <label class="custom-file-label text-muted text-truncate" for="attendanceProofInput" id="attendanceProofLabel">
                                        {{ $proofText['pick'] }}
                                    </label>
                                </div>
                                <small class="form-text text-muted">Format: JPG, PNG, atau WEBP. Maksimal 5MB. {{ $proofText['hint'] }}</small>

                                <div id="proofPreviewWrapper" class="mt-2 text-center d-none">
                                    <img id="proofPreviewImg" src="#" alt="Preview Screenshot" class="img-fluid rounded border shadow-sm" style="max-height: 180px; object-fit: contain;">
                                    <div class="mt-1">
                                        <button type="button" class="btn btn-link btn-xs text-danger" onclick="clearProofInput()">
                                            <i class="fas fa-trash-alt mr-1"></i> {{ $proofText['replace'] }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="fas fa-check-circle mr-1"></i> Saya Hadir
                            </button>

                            <button type="button" class="btn btn-default px-4" data-toggle="collapse" data-target="#notAttendBox">
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
        @endif

        <!-- FORM EVALUASI PELATIHAN (sebelum posttest) -->
        @if($training->hasEvaluationForm())
        <div class="content-card" id="evaluationCard">
            <div class="content-card-header">
                <span><span class="badge badge-primary mr-1">Langkah {{ ++$step }}</span> Evaluasi Pelatihan</span>
                @if($evaluation)
                    <span class="badge badge-success">Terisi</span>
                @else
                    <span class="text-muted small">{{ $training->evaluationQuestions->count() }} Pertanyaan</span>
                @endif
            </div>

            @if($evaluation)
                <div class="alert alert-success mb-0">
                    <i class="fas fa-check-circle mr-1"></i>
                    <strong>Terima kasih, evaluasi Anda sudah tersimpan.</strong>
                    <span class="small d-block mt-1">Dikirim pada {{ $evaluation->submitted_at->format('d F Y, H:i') }} WIB.</span>
                </div>
            @elseif($training->isEvaluationOpen())
                <p class="text-muted small mb-3">
                    Beri penilaian singkat tentang pemateri dan pelatihan ini{{ $training->has_posttest ? ' sebelum mengerjakan kuis' : '' }}.
                    Nama, jabatan, dan data pelatihan sudah terisi otomatis.
                </p>
                @if($training->evaluationClosesAt())
                    <div class="alert alert-warning py-2 px-3 small mb-3">
                        <i class="far fa-clock mr-1"></i> Bisa diisi sampai pukul <strong>{{ $training->evaluationClosesAt()->format('H:i') }}</strong> WIB.
                    </div>
                @endif
                <a href="{{ $evaluationUrl }}" class="btn btn-primary btn-block">
                    Isi Form Evaluasi
                </a>
            @else
                <div class="text-center py-4 text-muted">
                    @if($training->has_posttest)
                        Form evaluasi dibuka bersamaan dengan kuis.
                    @elseif($training->evaluationOpensAt())
                        Form evaluasi dibuka otomatis {{ \App\Models\Training::timeLabel($training->evaluationOpensAt()) }} WIB.
                    @elseif($training->evaluationTimedOut())
                        Waktu pengisian form evaluasi sudah habis.
                    @elseif($training->hasEvaluationSchedule())
                        Form evaluasi belum dibuka oleh pemateri.
                    @else
                        Form evaluasi dibuka saat sesi berlangsung.
                    @endif
                </div>
            @endif
        </div>
        @endif

        <!-- KUIS EVALUASI (POSTTEST) -->
        @if($training->has_posttest)
        <div class="content-card" id="quizCard">
            <div class="content-card-header">
                <span><span class="badge badge-primary mr-1">Langkah {{ ++$step }}</span> {{ $training->has_pretest ? 'Posttest' : 'Kuis Evaluasi' }}</span>
                <span class="text-muted small">{{ $training->questionsFor('posttest')->count() }} Pertanyaan</span>
            </div>

            @if($training->quizAttemptsLeft($participant->quizResult) !== 0)
                @include('training.partials.portal-deadline', ['phase' => 'posttest'])
            @endif

            @if($participant->quizResult)
                <div class="p-4 bg-light rounded text-center border mb-3">
                    <span class="text-muted small d-block mb-1">Nilai akhir Anda</span>
                    <div class="score-display mb-2">{{ $participant->quizResult->score }}</div>
                    <span class="text-muted small">
                        Jawaban Benar: {{ $participant->quizResult->correct_answers }} dari {{ $participant->quizResult->total_questions }} Soal
                    </span>
                </div>

                <div class="d-flex justify-content-center gap-2">
                    <a href="/training/portal/{{ $participant->token }}/result" class="btn btn-default">
                        Lihat Pembahasan
                    </a>
                    @if($training->canRetakeQuiz($participant->quizResult))
                        <a href="{{ $awaitsEvaluation ? $evaluationUrl : '/training/portal/'.$participant->token.'/quiz' }}" class="btn btn-primary">
                            Kerjakan Ulang
                        </a>
                    @endif
                </div>
                @if($training->allowsQuizRetake())
                    <p class="text-muted text-xs text-center mt-2 mb-0">
                        {{ ucfirst(\App\Support\QuizRules::remainingLabel($training->quizAttemptsLeft($participant->quizResult))) }}; nilai tertinggi yang dipakai.
                    </p>
                @endif
            @else
                <div id="quizBody">
                @if($quizActive && $awaitsEvaluation)
                    <p class="text-muted small mb-3">
                        Kuis sudah dibuka. Isi form evaluasi terlebih dahulu, setelah itu kuis langsung terbuka.
                    </p>
                    <a href="{{ $evaluationUrl }}" class="btn btn-primary btn-block">
                        Isi Evaluasi, lalu Kerjakan Kuis
                    </a>
                @elseif($quizActive)
                    <p class="text-muted small mb-3">
                        Kuis evaluasi telah dibuka oleh pemateri. {{ \App\Support\QuizRules::summary($training->quiz_max_attempts, $training->show_quiz_answers) }}.
                    </p>
                    <a href="/training/portal/{{ $participant->token }}/quiz" class="btn btn-primary btn-block">
                        Mulai Kerjakan Kuis
                    </a>
                @else
                    <div class="text-center py-4 text-muted" id="quizLockedMsg" data-locked-text="posttest"
                        data-default="Kuis belum dibuka oleh pemateri." data-scheduled="Kuis dibuka otomatis " data-timed-out="Waktu pengerjaan kuis sudah habis.">
                        @if($quizOpensAt)
                            Kuis dibuka otomatis {{ \App\Models\Training::timeLabel($quizOpensAt) }} WIB.
                        @elseif($training->phaseTimedOut('posttest'))
                            Waktu pengerjaan kuis sudah habis.
                        @else
                            Kuis belum dibuka oleh pemateri.
                        @endif
                    </div>
                @endif
                </div>
            @endif
        </div>
        @endif
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
            if (label) label.textContent = '{{ $proofText['pick'] }}';
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

            // State saat ini dari server (render awal Blade)
            // Absen HANYA track is_attendance_active, tidak terpengaruh is_quiz_active
            let currentAttendanceActive = {{ $training->is_attendance_active ? 'true' : 'false' }};
            let currentQuizActive       = {{ $quizActive ? 'true' : 'false' }};
            // Pretest dirender server; saat pemateri membuka/menutupnya halaman dimuat ulang.
            const currentPretestActive  = {{ $training->isPhaseActive('pretest') ? 'true' : 'false' }};
            const watchPretest          = {{ $training->has_pretest && ! $pretestResult ? 'true' : 'false' }};
            // Form evaluasi juga dirender server: muat ulang saat pemateri menyalakan/mematikan atau membukanya.
            const currentHasEvaluation  = {{ $training->hasEvaluationForm() ? 'true' : 'false' }};
            const currentEvaluationOpen = {{ $training->isEvaluationOpen() ? 'true' : 'false' }};
            const watchEvaluation       = {{ $evaluation ? 'false' : 'true' }};
            let currentRequireProof     = {{ $training->require_attendance_proof ? 'true' : 'false' }};
            const attendanceStatus      = '{{ $participant->attendance_status }}';
            const hasQuizResult         = {{ $participant->quizResult ? 'true' : 'false' }};

            // ---- HTML builders (pakai nilai requireProof dari API, bukan Blade) ----

            function buildAttendanceOpenHTML(requireProof) {
                let proofHtml = '';
                if (requireProof) {
                    proofHtml = `
                        <div class="alert alert-info py-2 px-3 small mb-3">
                            <i class="fas fa-info-circle mr-1"></i> <strong>{{ $proofText['requiredTitle'] }}:</strong>
                            {{ $proofText['required'] }}
                        </div>
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-dark mb-1" for="attendanceProofInput">
                                {{ $proofText['upload'] }} <span class="text-danger">*</span>
                            </label>
                            <div class="custom-file">
                                <input type="file" name="attendance_proof" id="attendanceProofInput"
                                    class="custom-file-input"
                                    accept="image/png, image/jpeg, image/jpg, image/webp"
                                    required
                                    onchange="previewAttendanceProof(this)">
                                <label class="custom-file-label text-muted text-truncate"
                                    for="attendanceProofInput" id="attendanceProofLabel">
                                    {{ $proofText['pick'] }}
                                </label>
                            </div>
                            <small class="form-text text-muted">Format: JPG, PNG, atau WEBP. Maksimal 5MB.</small>
                            <div id="proofPreviewWrapper" class="mt-2 text-center d-none">
                                <img id="proofPreviewImg" src="#" alt="Preview Screenshot"
                                    class="img-fluid rounded border shadow-sm"
                                    style="max-height: 180px; object-fit: contain;">
                                <div class="mt-1">
                                    <button type="button" class="btn btn-link btn-xs text-danger" onclick="clearProofInput()">
                                        <i class="fas fa-trash-alt mr-1"></i> {{ $proofText['replace'] }}
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
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="fas fa-check-circle mr-1"></i> Saya Hadir
                            </button>
                            <button type="button" class="btn btn-default px-4"
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
                        <span class="empty-state-icon"><i class="fas fa-user-clock"></i></span>
                        <h6 class="mb-1">Presensi Kehadiran Belum Dibuka</h6>
                        <p class="text-muted small mb-0" style="max-width: 480px; margin: 0 auto;">
                            Sesi presensi kehadiran saat ini belum dibuka oleh Pemateri / Admin.
                            Halaman ini memperbarui otomatis — Anda tidak perlu me-refresh.
                        </p>
                    </div>`;
            }

            function buildQuizOpenHTML() {
                return `
                    <p class="text-muted small mb-3">
                        Kuis evaluasi telah dibuka oleh pemateri. {{ \App\Support\QuizRules::summary($training->quiz_max_attempts, $training->show_quiz_answers) }}.
                    </p>
                    <a href="${QUIZ_URL}" class="btn btn-primary btn-block">
                        Mulai Kerjakan Kuis
                    </a>`;
            }

            function buildQuizLockedHTML() {
                return `
                    <div class="text-center py-4 text-muted" data-locked-text="posttest"
                        data-default="Kuis belum dibuka oleh pemateri." data-scheduled="Kuis dibuka otomatis " data-timed-out="Waktu pengerjaan kuis sudah habis.">
                        Kuis belum dibuka oleh pemateri.
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
                            proofInfoBox.innerHTML = '<i class="fas fa-info-circle mr-1"></i> <strong>{{ $proofText['requiredTitle'] }}:</strong> {{ $proofText['required'] }}';
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
                        if (proofLabel) proofLabel.textContent = '{{ $proofText['pick'] }}';
                        if (proofInfoBox) {
                            proofInfoBox.className = 'd-none';
                            proofInfoBox.innerHTML = '';
                        }
                    }
                }
            }

            function attendanceInProgress() {
                const proof = document.getElementById('attendanceProofInput');
                const notes = document.querySelectorAll('#attendanceCard textarea');
                return (proof && proof.files && proof.files.length > 0)
                    || Array.prototype.some.call(notes, function (note) { return note.value.trim() !== ''; });
            }

            // ---- BATAS WAKTU pretest/posttest: hitung mundur dari sisa detik yang dikirim server ----
            const deadlineEnds = {};

            function setDeadline(phase, secondsLeft, timeLabel) {
                const box = document.querySelector('[data-deadline="' + phase + '"]');
                if (!box) return;

                if (secondsLeft === null || secondsLeft === undefined || secondsLeft === '') {
                    delete deadlineEnds[phase];
                    box.hidden = true;
                    return;
                }

                deadlineEnds[phase] = Date.now() + Number(secondsLeft) * 1000;
                if (timeLabel) box.querySelector('[data-deadline-time]').textContent = timeLabel;
                box.hidden = false;
            }

            function tickDeadlines() {
                Object.keys(deadlineEnds).forEach(function (phase) {
                    const box = document.querySelector('[data-deadline="' + phase + '"]');
                    const left = Math.max(0, Math.round((deadlineEnds[phase] - Date.now()) / 1000));
                    box.querySelector('[data-deadline-clock]').textContent =
                        String(Math.floor(left / 60)).padStart(2, '0') + ':' + String(left % 60).padStart(2, '0');

                    // Waktu habis: sembunyikan, lalu tanya server supaya tombol kuis ikut terkunci.
                    if (left === 0) {
                        delete deadlineEnds[phase];
                        box.hidden = true;
                        poll();
                    }
                });
            }

            // Pesan fase yang terkunci: jam buka terjadwal, "waktu sudah habis", atau "belum dibuka".
            function setLockedText(phase, opensLabel, timedOut) {
                const el = document.querySelector('[data-locked-text="' + phase + '"]');
                if (!el) return;
                el.textContent = opensLabel
                    ? el.dataset.scheduled + opensLabel + ' WIB.'
                    : (timedOut ? el.dataset.timedOut : el.dataset.default);
            }

            document.querySelectorAll('[data-deadline]').forEach(function (box) {
                setDeadline(box.dataset.deadline, box.dataset.secondsLeft);
            });
            setInterval(tickDeadlines, 1000);

            function poll() {
                fetch(STATUS_URL)
                    .then(function(res) { return res.json(); })
                    .then(function(data) {

                        setDeadline('pretest', data.pretest_seconds_left, data.pretest_closes_at);
                        setDeadline('posttest', data.quiz_seconds_left, data.quiz_closes_at);
                        setLockedText('pretest', data.pretest_opens_at, data.pretest_timed_out);
                        setLockedText('posttest', data.quiz_opens_at, data.quiz_timed_out);

                        if (watchPretest && data.is_pretest_active !== currentPretestActive) {
                            window.location.reload();
                            return;
                        }

                        // Ditunda selama peserta sedang mengisi presensi (foto terpilih / alasan diketik) supaya isiannya
                        // tidak hilang; pengecekan berikutnya mencoba lagi.
                        if (watchEvaluation && !attendanceInProgress()
                            && (data.has_evaluation !== currentHasEvaluation || data.is_evaluation_open !== currentEvaluationOpen)) {
                            window.location.reload();
                            return;
                        }

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
                                    attendanceBadge.className   = 'badge badge-warning';
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
                                setLockedText('posttest', data.quiz_opens_at, data.quiz_timed_out);
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
