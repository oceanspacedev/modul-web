<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengerjaan Kuis - {{ $training->title }}</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <!-- FontAwesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            color: #212529;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .quiz-header {
            background-color: #ffffff;
            border-bottom: 1px solid #dee2e6;
            padding: 16px 0;
            margin-bottom: 24px;
            position: sticky;
            top: 0;
            z-index: 1020;
        }
        .question-card {
            background: #ffffff;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 24px;
            margin-bottom: 20px;
        }
        .option-label {
            display: flex;
            align-items: flex-start;
            padding: 12px 16px;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            margin-bottom: 10px;
            cursor: pointer;
            background-color: #ffffff;
            transition: all 0.15s ease-in-out;
        }
        .option-label:hover {
            background-color: #f8f9fa;
            border-color: #ced4da;
        }
        .option-label input[type="radio"] {
            margin-top: 4px;
            margin-right: 12px;
        }
        .prevent-select {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
    </style>
</head>
<body class="prevent-select">

    <header class="quiz-header">
        <div class="container" style="max-width: 760px;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="font-weight-bold mb-0 text-dark">{{ $training->title }}</h5>
                    <span class="text-muted small">Kuis Evaluasi Pelatihan</span>
                </div>
                <div class="d-flex align-items-center" style="gap: 8px;">
                    <span class="badge badge-light border text-muted px-2 py-1 small" title="Maksimal 3 kali keluar halaman">
                        <i class="fas fa-shield-alt text-danger mr-1"></i> Toleransi Keluar: 3x
                    </span>
                    <span class="badge badge-secondary px-3 py-1 font-weight-bold">
                        {{ $questions->count() }} Soal
                    </span>
                </div>
            </div>
        </div>
    </header>

    <main class="container mb-5" style="max-width: 760px;">
        <div class="alert alert-light border mb-4 text-muted small">
            <i class="fas fa-exclamation-circle text-danger mr-1"></i>
            <strong>Perhatian:</strong> Dilarang berpindah tab atau membuka aplikasi lain selama kuis berlangsung (toleransi maksimal 3 kali sebelum kuis otomatis dikumpulkan).
        </div>

        <form action="/training/portal/{{ $participant->token }}/quiz" method="POST" id="formQuiz">
            @csrf

            <!-- Anti-Cheat Hidden Data -->
            <input type="hidden" name="tab_switch_count" id="tabSwitchCount" value="0">
            <input type="hidden" name="is_force_submitted" id="isForceSubmitted" value="0">
            <input type="hidden" name="violation_logs" id="violationLogs" value="[]">

            @foreach($questions as $index => $q)
            <div class="question-card">
                <div class="mb-3 d-flex align-items-baseline">
                    <span class="badge badge-dark mr-2">Soal {{ $index + 1 }}</span>
                    @if($q->type === 'essay')
                        <span class="badge badge-info mr-2">Essay / Uraian</span>
                    @endif
                    <strong class="text-dark" style="font-size: 1.05rem;">{{ $q->question }}</strong>
                </div>

                @if($q->type === 'essay')
                    <div class="mt-3">
                        <label class="text-muted small font-weight-bold d-block mb-1">Jawaban Anda:</label>
                        <textarea name="answers[{{ $q->id }}]" class="form-control" rows="4" placeholder="Ketikkan penjelasan atau jawaban Anda secara lengkap..." required style="border-radius: 6px;"></textarea>
                    </div>
                @else
                    <div class="mt-3">
                        @php
                            $availableOptions = [];
                            foreach(['a', 'b', 'c', 'd', 'e', 'f'] as $opt) {
                                if (!empty($q->{'option_' . $opt})) {
                                    $availableOptions[$opt] = $q->{'option_' . $opt};
                                }
                            }
                        @endphp

                        @foreach($availableOptions as $optKey => $optVal)
                            <label class="option-label" for="q_{{ $q->id }}_{{ $optKey }}">
                                <input type="radio" name="answers[{{ $q->id }}]" value="{{ $optKey }}" id="q_{{ $q->id }}_{{ $optKey }}" {{ $loop->first ? 'required' : '' }}>
                                <div>
                                    <strong>{{ strtoupper($optKey) }}.</strong> {{ $optVal }}
                                </div>
                            </label>
                        @endforeach
                    </div>
                @endif
            </div>
            @endforeach

            <div class="card p-4 text-center border bg-white mb-5">
                <p class="text-muted small mb-3">Pastikan Anda telah memeriksa kembali seluruh jawaban.</p>
                <div class="d-flex justify-content-center" style="gap: 12px;">
                    <a href="/training/portal/{{ $participant->token }}" class="btn btn-default px-4">Batal</a>
                    <button type="submit" id="btnSubmitQuiz" class="btn btn-primary px-5 font-weight-bold" onclick="return confirm('Kirimkan seluruh jawaban kuis ini?')">
                        Kirim Jawaban
                    </button>
                </div>
            </div>
        </form>
    </main>

    <!-- MODAL PERINGATAN (SIMPLE & TEGAS) -->
    <div class="modal fade" id="antiCheatModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
            <div class="modal-content border-danger shadow">
                <div class="modal-header bg-danger text-white py-2 px-3">
                    <h6 class="modal-title font-weight-bold mb-0" id="cheatModalHeading">
                        <i class="fas fa-exclamation-triangle mr-1"></i> Peringatan Pindah Layar
                    </h6>
                </div>
                <div class="modal-body text-center p-3" id="antiCheatBody">
                    <p class="text-dark font-weight-bold mb-2">
                        Anda terdeteksi meninggalkan halaman kuis!
                    </p>
                    <p class="text-muted small mb-0">
                        Pelanggaran ke-<strong class="text-danger" id="violationCountDisplay">1</strong> dari 3 kesempatan.
                        <br>
                        Sisa kesempatan: <strong class="text-dark" id="cheatRemainingDisplay">2</strong> kali lagi.
                    </p>
                </div>
                <div class="modal-footer p-2 justify-content-center bg-light">
                    <button type="button" class="btn btn-danger btn-sm font-weight-bold px-3" id="btnDismissAlarm">
                        <i class="fas fa-check mr-1"></i> Matikan Alarm & Lanjutkan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    (function() {
        let tabSwitchCount = 0;
        const MAX_VIOLATIONS = 3;
        const violationLogs = [];
        let isSubmitting = false;
        let lastViolationTime = 0;

        // Web Audio API Synthesizer - Harsh Startling Alarm Buzzer
        let audioCtx = null;
        let alarmInterval = null;

        function playStartlingAlarm() {
            try {
                if (!audioCtx) {
                    audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                }
                if (audioCtx.state === 'suspended') {
                    audioCtx.resume();
                }

                stopAlarmSound();

                // Generate harsh, loud discordant shock buzzer
                function shockBuzzer() {
                    if (!audioCtx) return;
                    try {
                        const now = audioCtx.currentTime;
                        // 3 discordant frequencies creating harsh acoustic dissonance
                        const frequencies = [680, 720, 1400];

                        frequencies.forEach(freq => {
                            const osc = audioCtx.createOscillator();
                            const gain = audioCtx.createGain();

                            osc.type = 'sawtooth';
                            osc.frequency.setValueAtTime(freq, now);

                            // Very loud, sharp instant attack (startling shock)
                            gain.gain.setValueAtTime(0.65, now);
                            gain.gain.exponentialRampToValueAtTime(0.01, now + 0.28);

                            osc.connect(gain);
                            gain.connect(audioCtx.destination);

                            osc.start(now);
                            osc.stop(now + 0.28);
                        });
                    } catch(err) {
                        console.log('Audio error:', err);
                    }
                }

                // Rapid triple-burst shock on trigger: BZZZT! BZZZT! BZZZT!
                shockBuzzer();
                setTimeout(shockBuzzer, 160);
                setTimeout(shockBuzzer, 320);

                // Repeat the alarm pattern every 850ms until dismissed
                alarmInterval = setInterval(() => {
                    shockBuzzer();
                    setTimeout(shockBuzzer, 160);
                }, 850);
            } catch (e) {
                console.error('AudioContext error:', e);
            }
        }

        function stopAlarmSound() {
            if (alarmInterval) {
                clearInterval(alarmInterval);
                alarmInterval = null;
            }
        }

        // Trigger violation handler
        function recordViolation(reason) {
            if (isSubmitting) return;

            const now = Date.now();
            if (now - lastViolationTime < 1500) return; // Debounce 1.5s
            lastViolationTime = now;

            tabSwitchCount++;
            violationLogs.push({
                type: reason,
                count: tabSwitchCount,
                time: new Date().toISOString()
            });

            document.getElementById('tabSwitchCount').value = tabSwitchCount;
            document.getElementById('violationLogs').value = JSON.stringify(violationLogs);

            // Play Startling Loud Alarm
            playStartlingAlarm();

            document.getElementById('violationCountDisplay').textContent = tabSwitchCount;
            const remaining = Math.max(0, MAX_VIOLATIONS - tabSwitchCount);
            document.getElementById('cheatRemainingDisplay').textContent = remaining;

            if (tabSwitchCount >= MAX_VIOLATIONS) {
                // Max violations reached -> AUTO SUBMIT!
                document.getElementById('isForceSubmitted').value = "1";
                document.getElementById('cheatModalHeading').innerHTML = '<i class="fas fa-ban mr-1"></i> Kuis Dikunci Otomatis';
                document.getElementById('antiCheatBody').innerHTML = `
                    <p class="text-danger font-weight-bold mb-1">
                        Batas 3 kali pelanggaran telah tercapai.
                    </p>
                    <p class="text-muted small mb-2">
                        Jawaban kuis Anda otomatis dikumpulkan ke server sekarang.
                    </p>
                    <div class="spinner-border text-danger spinner-border-sm mt-1" role="status"></div>
                `;
                document.getElementById('btnDismissAlarm').style.display = 'none';

                $('#antiCheatModal').modal('show');

                setTimeout(() => {
                    stopAlarmSound();
                    isSubmitting = true;
                    $('#formQuiz').find('[required]').removeAttr('required');
                    document.getElementById('formQuiz').submit();
                }, 2000);
            } else {
                $('#antiCheatModal').modal('show');
            }
        }

        // Event listener: Tab switch (visibility change)
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                recordViolation('Pindah Tab Browser');
            }
        });

        // Event listener: Window blur (opening another app)
        window.addEventListener('blur', function() {
            recordViolation('Buka Aplikasi Lain / Layar Tidak Aktif');
        });

        // Button to dismiss alarm and acknowledge
        document.getElementById('btnDismissAlarm').addEventListener('click', function() {
            stopAlarmSound();
            $('#antiCheatModal').modal('hide');
        });

        // Normal submit button
        document.getElementById('formQuiz').addEventListener('submit', function() {
            isSubmitting = true;
            stopAlarmSound();
        });

        // Prevent Copy, Cut, Context Menu
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            return false;
        });
        document.addEventListener('copy', function(e) {
            e.preventDefault();
            return false;
        });
        document.addEventListener('cut', function(e) {
            e.preventDefault();
            return false;
        });
    })();
    </script>
</body>
</html>
