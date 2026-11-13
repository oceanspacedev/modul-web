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

        @if (request('unggahan') === 'terlalu-besar')
            <div class="alert alert-danger mb-4" role="alert">
                <strong><i class="fas fa-exclamation-circle mr-1"></i> Foto belum terkirim:</strong>
                ukuran fotonya terlalu besar. Pilih foto lain atau ambil screenshot, lalu kirim ulang.
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
            // Nomor "Langkah N" tiap kartu mengikuti urutan pilihan pemateri.
            $stepNumber = array_map(fn (int $position) => $position + 1, array_flip($training->portalSteps()));
            $pretestResult = $participant->pretestResult;
            $pretestQuestionCount = $training->questionsFor('pretest')->count();
            $evaluation = $participant->evaluation;
            $evaluationUrl = '/training/portal/'.$participant->token.'/evaluation';
            // Evaluasi yang diurutkan sebelum posttest: selama belum terisi, tombol kuis mengarah ke form evaluasi.
            $awaitsEvaluation = $training->requiresEvaluationBeforePosttest($participant);
            $evaluationFollowsPosttest = $training->evaluationFollowsPosttest();
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
            $attendanceOpensAt = $training->phaseOpensAt('attendance');
            // Pesan presensi yang terkunci; dipakai render awal dan pembaruan berkala.
            $attendanceLockedText = [
                'title' => 'Presensi Kehadiran Belum Dibuka',
                'closedTitle' => 'Presensi Kehadiran Sudah Ditutup',
                'default' => 'Sesi presensi kehadiran saat ini belum dibuka oleh Pemateri / Admin. Silakan tunggu instruksi dari pemateri saat sesi pelatihan berlangsung.',
                'scheduled' => 'Presensi dibuka otomatis ',
                'timedOut' => 'Waktu pengisian presensi sudah habis.',
            ];
        @endphp

        {{-- Kartu langkah peserta, dalam urutan yang diatur pemateri untuk pelatihan ini. --}}
        @foreach($training->portalSteps() as $portalStep)
            @include('training.portal.steps.'.$portalStep)
        @endforeach
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

        // Foto dari kamera ponsel bisa belasan MB. Sebelum dikirim, foto diperkecil di perangkat peserta (sisi terpanjang
        // 1600px, JPEG) supaya unggahannya cepat di jaringan seluler dan tidak ditolak karena ukuran. Selama diproses
        // tombol kirim dimatikan. Perangkat yang tidak mendukung mengirim foto aslinya.
        const PROOF_MAX_BYTES = 5 * 1024 * 1024;
        const PROOF_MAX_SIDE = 1600;

        function shrinkAttendanceProof(input) {
            const file = input.files && input.files[0];
            if (!file) return;

            const buttons = input.form ? input.form.querySelectorAll('button[type="submit"]') : [];
            const finish = function () {
                buttons.forEach(function (button) { button.disabled = false; });
                const chosen = input.files && input.files[0];
                if (chosen && chosen.size > PROOF_MAX_BYTES) {
                    alert('Ukuran foto melebihi 5MB. Pilih foto lain atau ambil screenshot.');
                    clearProofInput();
                }
            };

            if (file.size <= 300 * 1024 || !/^image\//.test(file.type) || typeof DataTransfer === 'undefined') {
                finish();
                return;
            }

            buttons.forEach(function (button) { button.disabled = true; });
            const url = URL.createObjectURL(file);
            const image = new Image();
            image.onload = function () {
                try {
                    const scale = Math.min(1, PROOF_MAX_SIDE / Math.max(image.naturalWidth, image.naturalHeight));
                    const canvas = document.createElement('canvas');
                    canvas.width = Math.round(image.naturalWidth * scale);
                    canvas.height = Math.round(image.naturalHeight * scale);
                    canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);
                    canvas.toBlob(function (blob) {
                        URL.revokeObjectURL(url);
                        // Hanya dipakai bila masih foto yang sama dan hasilnya memang lebih kecil.
                        if (blob && blob.size < file.size && input.files[0] === file) {
                            try {
                                const transfer = new DataTransfer();
                                transfer.items.add(new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' }));
                                input.files = transfer.files;
                            } catch (e) { /* kirim foto aslinya */ }
                        }
                        finish();
                    }, 'image/jpeg', 0.8);
                } catch (e) {
                    URL.revokeObjectURL(url);
                    finish();
                }
            };
            image.onerror = function () {
                URL.revokeObjectURL(url);
                finish();
            };
            image.src = url;
        }

        // Berlaku juga untuk form presensi yang dibuat ulang oleh polling.
        document.addEventListener('change', function (event) {
            if (event.target && event.target.id === 'attendanceProofInput') shrinkAttendanceProof(event.target);
        });

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
            // Absen hanya mengikuti presensi (dibuka pemateri, jam bukanya sudah tiba, jam tutupnya belum lewat); tidak
            // terpengaruh is_quiz_active.
            let currentAttendanceActive = {{ $training->isAttendanceOpen() ? 'true' : 'false' }};
            let currentQuizActive       = {{ $quizActive ? 'true' : 'false' }};
            // Pretest dirender server; saat pemateri membuka/menutupnya halaman dimuat ulang.
            const currentPretestActive  = {{ $training->isPhaseActive('pretest') ? 'true' : 'false' }};
            const watchPretest          = {{ $training->has_pretest && ! $pretestResult ? 'true' : 'false' }};
            // Form evaluasi juga dirender server: muat ulang saat pemateri menyalakan/mematikan atau membukanya.
            const currentHasEvaluation  = {{ $training->hasEvaluationForm() ? 'true' : 'false' }};
            const currentEvaluationOpen = {{ $training->isEvaluationOpen() ? 'true' : 'false' }};
            const watchEvaluation       = {{ $evaluation ? 'false' : 'true' }};
            // Evaluasi yang diurutkan sebelum kuis dan belum diisi: tombol kuis mengarah ke form evaluasi, dan itu hanya
            // dirender server. Form evaluasi kini bisa sudah terbuka sebelum kuis, jadi dibukanya kuis harus memuat ulang.
            const evaluationBeforeQuiz  = {{ $evaluationFollowsPosttest && ! $evaluation && $training->hasEvaluationForm() ? 'true' : 'false' }};
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

            // Teks presensi yang terkunci: belum dibuka, terjadwal, atau waktunya habis (diisi setAttendanceLocked()).
            const ATTENDANCE_LOCKED = @json($attendanceLockedText);

            function buildAttendanceLockedHTML() {
                return `
                    <div class="text-center py-4">
                        <span class="empty-state-icon"><i class="fas fa-user-clock"></i></span>
                        <h6 class="mb-1" data-attendance-locked-title>${ATTENDANCE_LOCKED.title}</h6>
                        <p class="text-muted small mb-0" style="max-width: 480px; margin: 0 auto;" data-locked-text="attendance"
                            data-default="${ATTENDANCE_LOCKED.default}" data-scheduled="${ATTENDANCE_LOCKED.scheduled}" data-timed-out="${ATTENDANCE_LOCKED.timedOut}">
                            ${ATTENDANCE_LOCKED.default}
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

            // Presensi yang terkunci: judul dan pesannya mengikuti jadwal buka atau waktu yang sudah habis.
            function setAttendanceLocked(opensLabel, timedOut) {
                setLockedText('attendance', opensLabel, timedOut);
                const title = document.querySelector('[data-attendance-locked-title]');
                if (title) title.textContent = timedOut && !opensLabel ? ATTENDANCE_LOCKED.closedTitle : ATTENDANCE_LOCKED.title;
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
                        setDeadline('attendance', data.attendance_seconds_left, data.attendance_closes_at);
                        setDeadline('evaluation', data.evaluation_seconds_left, data.evaluation_closes_at);
                        setAttendanceLocked(data.attendance_opens_at, data.attendance_timed_out);
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

                            // Absensi HANYA mengikuti presensi (terbuka, sudah jam bukanya, belum jam tutupnya);
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
                                // Presensi ditutup atau waktunya habis → kembali ke pesan terkunci. Bila waktunya baru
                                // habis saat peserta sedang mengisi (foto terpilih / alasan diketik), formnya dibiarkan:
                                // kiriman masih diterima sebentar setelah jam tutup.
                                if (!(data.attendance_timed_out && attendanceInProgress())) {
                                    currentAttendanceActive = false;
                                    if (attendanceBody) {
                                        attendanceBody.innerHTML = buildAttendanceLockedHTML();
                                        attendanceBody.setAttribute('data-state', 'locked');
                                    }
                                    setAttendanceLocked(data.attendance_opens_at, data.attendance_timed_out);
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

                            // Ditunda selama presensi sedang diisi; pengecekan berikutnya mencoba lagi.
                            if (evaluationBeforeQuiz && data.is_quiz_active !== currentQuizActive) {
                                if (!attendanceInProgress()) window.location.reload();
                                return;
                            }

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
    @include('training.partials.portal-submit-guard')
</body>
</html>
