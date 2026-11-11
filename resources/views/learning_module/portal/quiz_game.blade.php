@php
    $isPretest = $phase === \App\Models\LearningModuleAttempt::PHASE_PRETEST;
    $maxAttempts = $module->maxAttempts($phase);
    $phaseLabel = ($isPretest ? 'Pretest' : 'Posttest').($maxAttempts === 1 ? '' : ' ke-'.$attemptNumber);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $phaseLabel }}: {{ $module->title }}</title>
    @include('layout.partials.portal-head')
</head>
<body class="game-body">
    <header class="game-bar">
        <div class="game-bar-side">
            <a href="{{ $backUrl }}" class="game-icon-btn" id="btnBack" title="Kembali" aria-label="Kembali"><i class="fas fa-arrow-left"></i></a>
            <span class="game-step" id="qStepCounter">{{ $phaseLabel }}</span>
        </div>
        <span class="game-mode">Mode game</span>
        <div class="game-bar-side">
            {{-- Poin & streak membocorkan benar/salah, jadi disembunyikan bila rincian jawaban tidak ditampilkan. --}}
            <span class="game-meta" title="Jawaban benar beruntun" @unless($showsAnswers) hidden @endunless><i class="fas fa-fire"></i> <span id="streakCount">0</span></span>
            <span class="game-meta" title="Poin" @unless($showsAnswers) hidden @endunless><span id="scoreBadge">0</span> poin</span>
            <button type="button" class="game-icon-btn" id="btnSoundToggle" title="Suara aktif" aria-label="Suara"><i class="fas fa-volume-up" id="soundIcon"></i></button>
        </div>
    </header>

    <main class="game-stage">
        {{-- LOBBY --}}
        <div class="game-card is-wide" id="lobbyScreen">
            @foreach(['error' => 'danger', 'info' => 'info', 'success' => 'success'] as $key => $type)
                @if(session($key))
                    <div class="alert alert-{{ $type }} small">{{ session($key) }}</div>
                @endif
            @endforeach
            @if($isPretest)
                @include('learning_module.partials.manager-preview')
            @endif

            <div class="game-eyebrow">{{ $phaseLabel }}</div>
            <h2 class="game-title">{{ $module->title }}</h2>
            <p class="game-lead">
                @if($isPretest)
                    Pretest mengukur pemahaman awal Anda. Jawab sebisanya, materi terbuka setelah pretest selesai.
                @else
                    Jawab setiap soal sebelum waktu habis. Setiap percobaan tetap tersimpan.
                @endif
                @if($showsAnswers)
                    Setiap jawaban langsung dikunci dan hanya ditandai <b>Benar</b> atau <b>Salah</b>.
                @else
                    Setiap jawaban langsung dikunci. Nilai Anda tampil setelah semua soal selesai.
                @endif
            </p>

            <div class="game-stats" style="--game-stats-cols: 4;">
                <div class="game-stat"><span class="game-stat-label">Jumlah Soal</span><span class="game-stat-value">{{ $questions->count() }}</span></div>
                <div class="game-stat"><span class="game-stat-label">Waktu / Soal</span><span class="game-stat-value">{{ $module->question_seconds }} detik</span></div>
                <div class="game-stat"><span class="game-stat-label">Nilai Lulus</span><span class="game-stat-value">{{ $module->passing_score }}</span></div>
                <div class="game-stat"><span class="game-stat-label">Sisa Percobaan</span><span class="game-stat-value">{{ $remainingAttempts === null ? 'Tanpa batas' : $remainingAttempts }}</span></div>
            </div>

            <button type="button" class="btn btn-primary btn-lg" id="btnStartGame">
                {{ $answered->isEmpty() ? 'Mulai '.($isPretest ? 'Pretest' : 'Posttest') : 'Lanjutkan' }}
            </button>
            @if($answered->isNotEmpty())
                <p class="game-note">{{ $answered->count() }} dari {{ $questions->count() }} soal sudah Anda jawab dan tidak bisa diubah.</p>
            @endif
        </div>

        {{-- GAMEPLAY --}}
        <div class="game-play" id="gameplayScreen">
            <div class="game-question" id="questionCard">
                <div class="game-timer-track"><div class="game-timer-fill" id="timerFill"></div></div>
                <div class="game-question-body">
                    <div class="game-question-main">
                        <h1 class="game-question-text" id="questionPrompt"></h1>
                        <img id="questionImage" class="game-question-image" alt="Gambar soal" hidden>
                    </div>
                    <div class="game-timer" id="timerBox">
                        <span class="game-timer-number" id="timerNumber">{{ $module->question_seconds }}</span>
                        <span class="game-timer-unit">detik</span>
                    </div>
                </div>
            </div>

            <div class="game-feedback-slot" aria-live="polite">
                <div class="game-feedback" id="feedbackBanner"></div>
            </div>

            <div class="game-options" id="optionsGrid"></div>
        </div>

        {{-- FINISH --}}
        <div class="game-card is-wide" id="finishScreen" style="display: none;">
            <h2 class="game-title">{{ $isPretest ? 'Pretest' : 'Posttest' }} selesai</h2>
            <p class="game-lead" id="finishSubtitle"></p>

            <div class="game-stats" style="--game-stats-cols: 4;" @unless($showsAnswers) hidden @endunless>
                <div class="game-stat"><span class="game-stat-label">Jawaban Benar</span><span class="game-stat-value" id="finalCorrect">0 / 0</span></div>
                <div class="game-stat"><span class="game-stat-label">Nilai</span><span class="game-stat-value" id="finalScore">0</span></div>
                <div class="game-stat"><span class="game-stat-label">Poin</span><span class="game-stat-value" id="finalPoints">0</span></div>
                <div class="game-stat"><span class="game-stat-label">Streak Terbaik</span><span class="game-stat-value" id="finalStreak">0</span></div>
            </div>

            <form action="{{ $module->url('submit', ['phase' => $phase]) }}" method="POST" id="submitForm">
                @csrf
                <button type="submit" class="btn btn-primary btn-lg" id="btnFinish">Simpan &amp; Lihat Hasil</button>
            </form>
            <p class="game-note" id="autoSubmitNote"></p>
        </div>
    </main>

    <script>
    (function () {
        const QUESTIONS = @json($gameQuestions);
        const ANSWERED = @json((object) $answered->all());
        // false: server tidak mengirim benar/salah; tiap jawaban hanya "tersimpan" dan nilai dilihat di halaman hasil.
        const SHOWS_ANSWERS = @json($showsAnswers);
        const QUESTION_TIME = {{ (int) $module->question_seconds }};
        const PASSING_SCORE = {{ (int) $module->passing_score }};
        const ANSWER_URL = @json($module->url('answer', ['phase' => $phase]));
        const CSRF = document.querySelector('meta[name=csrf-token]').content;

        // ---------- Suara (Web Audio, tanpa file) ----------
        let soundEnabled = true, audioCtx = null;
        function tone(freqs, type, gainValue, length, gap) {
            if (!soundEnabled) return;
            try {
                audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
                if (audioCtx.state === 'suspended') audioCtx.resume();
                freqs.forEach((freq, i) => setTimeout(() => {
                    const osc = audioCtx.createOscillator(), gain = audioCtx.createGain();
                    osc.type = type;
                    osc.frequency.setValueAtTime(freq, audioCtx.currentTime);
                    gain.gain.setValueAtTime(gainValue, audioCtx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + length);
                    osc.connect(gain); gain.connect(audioCtx.destination);
                    osc.start(); osc.stop(audioCtx.currentTime + length);
                }, i * gap));
            } catch (e) {}
        }
        const playClick = () => tone([440], 'sine', 0.15, 0.08, 0);
        const playCorrect = () => tone([523.25, 659.25, 783.99, 1046.5], 'triangle', 0.25, 0.35, 70);
        const playWrong = () => tone([220, 165], 'sawtooth', 0.15, 0.3, 120);
        const playFanfare = () => tone([440, 554.37, 659.25, 880], 'triangle', 0.3, 0.5, 120);
        document.getElementById('btnSoundToggle').addEventListener('click', function () {
            soundEnabled = !soundEnabled;
            document.getElementById('soundIcon').className = soundEnabled ? 'fas fa-volume-up' : 'fas fa-volume-mute';
            this.title = soundEnabled ? 'Suara aktif' : 'Suara mati';
        });

        // ---------- Status permainan (melanjutkan jawaban yang sudah terkunci) ----------
        let score = 0, streak = 0, bestStreak = 0, correctCount = 0;
        QUESTIONS.forEach(q => {
            if (!(q.id in ANSWERED)) return;
            if (!SHOWS_ANSWERS) return;
            if (ANSWERED[q.id]) { correctCount++; streak++; score += 1000; } else { streak = 0; }
            bestStreak = Math.max(bestStreak, streak);
        });
        let index = QUESTIONS.findIndex(q => !(q.id in ANSWERED));
        let timeLeft = QUESTION_TIME, timer = null, canAnswer = false, playing = false;

        const $ = id => document.getElementById(id);
        const grid = $('optionsGrid'), banner = $('feedbackBanner');

        function updateBadges() {
            $('scoreBadge').textContent = score.toLocaleString('id-ID');
            $('streakCount').textContent = streak;
        }
        updateBadges();

        $('btnStartGame').addEventListener('click', () => {
            playClick();
            $('lobbyScreen').style.display = 'none';
            playing = true;
            if (index === -1) { endGame(); return; }
            $('gameplayScreen').style.display = 'flex';
            loadQuestion();
        });

        // Meninggalkan permainan: soal yang sedang tampil belum terkunci, jadi bisa dijawab lagi nanti dari awal timernya.
        $('btnBack').addEventListener('click', e => {
            if (playing && index !== -1 && !confirm('Keluar dari permainan? Soal yang sudah dijawab tetap terkunci.')) e.preventDefault();
        });

        function showBanner(kind, html) {
            banner.className = 'game-feedback is-' + kind;
            banner.innerHTML = html;
            banner.style.display = 'inline-flex';
        }

        function loadQuestion() {
            if (index >= QUESTIONS.length || index === -1) { endGame(); return; }
            const q = QUESTIONS[index];
            banner.style.display = 'none';
            $('qStepCounter').textContent = `Soal ${index + 1} dari ${QUESTIONS.length}`;
            $('questionPrompt').textContent = q.question;
            if (q.image) {
                $('questionImage').src = q.image;
                $('questionImage').hidden = false;
            } else {
                $('questionImage').removeAttribute('src');
                $('questionImage').hidden = true;
            }

            grid.innerHTML = '';
            Object.entries(q.options).forEach(([key, text]) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'game-option';
                btn.dataset.key = key;
                const letter = document.createElement('span'); letter.className = 'game-option-letter'; letter.textContent = key.toUpperCase();
                const label = document.createElement('span'); label.className = 'game-option-text'; label.textContent = text;
                const optionImage = (q.option_images || {})[key];
                if (optionImage) {
                    const img = document.createElement('img');
                    img.src = optionImage; img.alt = 'Gambar pilihan ' + key.toUpperCase(); img.className = 'game-option-image';
                    label.appendChild(img);
                }
                btn.append(letter, label);
                btn.addEventListener('click', () => choose(key, btn));
                grid.appendChild(btn);
            });

            clearInterval(timer);
            timeLeft = QUESTION_TIME;
            renderTimer();
            canAnswer = true;
            timer = setInterval(() => {
                timeLeft = Math.max(0, timeLeft - 0.1);
                renderTimer();
                if (timeLeft <= 0) { clearInterval(timer); choose(null, null); }
            }, 100);
        }

        function renderTimer() {
            const sec = Math.ceil(timeLeft);
            $('timerNumber').textContent = sec;
            $('timerBox').classList.toggle('is-urgent', sec <= 5);
            $('questionCard').classList.toggle('is-urgent', sec <= 5);
            $('timerFill').style.transform = `scaleX(${Math.max(0, timeLeft / QUESTION_TIME)})`;
        }

        // Jawaban (atau waktu habis = null) dikirim ke server; server membalas benar/salah tanpa kunci jawaban.
        function choose(key, btn) {
            if (!canAnswer) return;
            canAnswer = false;
            clearInterval(timer);
            const remaining = timeLeft;
            const q = QUESTIONS[index];

            grid.querySelectorAll('.game-option').forEach(b => {
                b.disabled = true;
                b.classList.add(b === btn ? 'is-chosen' : 'is-dimmed');
            });
            if (key === null) showBanner('wrong', '<i class="fas fa-hourglass-end"></i> Waktu habis');
            else playClick();

            send(q.id, key, 0).then(data => {
                if (!SHOWS_ANSWERS) {
                    if (key !== null) showBanner('wait', '<i class="fas fa-check"></i> Jawaban tersimpan');
                } else if (data.correct) {
                    streak++; correctCount++;
                    bestStreak = Math.max(bestStreak, streak);
                    score += 1000 + Math.round(remaining * 25) + streak * 100;
                    if (btn) { btn.classList.remove('is-chosen'); btn.classList.add('is-correct'); }
                    playCorrect();
                    showBanner('correct', '<i class="fas fa-check-circle"></i> Benar');
                } else {
                    streak = 0;
                    if (btn) { btn.classList.remove('is-chosen'); btn.classList.add('is-wrong'); }
                    playWrong();
                    showBanner('wrong', key === null
                        ? '<i class="fas fa-hourglass-end"></i> Waktu habis'
                        : '<i class="fas fa-times-circle"></i> Salah');
                }
                updateBadges();
                setTimeout(() => { index++; loadQuestion(); }, 1600);
            }).catch(message => {
                showBanner('wrong', message);
                const retry = document.createElement('button');
                retry.type = 'button';
                retry.className = 'btn btn-default btn-sm';
                retry.textContent = 'Coba lagi';
                retry.addEventListener('click', () => { canAnswer = true; choose(key, btn); });
                banner.appendChild(retry);
            });
        }

        function send(questionId, answer, tries) {
            return fetch(ANSWER_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ question_id: questionId, answer: answer }),
            }).then(response => {
                if (response.ok) return response.json();
                return response.json().catch(() => ({})).then(body => {
                    // Ditolak server (mis. batas percobaan habis / sesi berakhir): kembali ke halaman materi.
                    if (response.status === 403 || response.status === 419) {
                        alert(body.message || 'Sesi Anda berakhir. Silakan muat ulang halaman.');
                        window.location.href = @json($backUrl);
                        return new Promise(() => {});
                    }
                    throw new Error('server');
                });
            }).catch(error => {
                if (tries < 2) {
                    showBanner('wait', '<i class="fas fa-sync fa-spin"></i> Menyimpan jawaban');
                    return new Promise(r => setTimeout(r, 1500)).then(() => send(questionId, answer, tries + 1));
                }
                throw '<i class="fas fa-wifi"></i> Koneksi terputus';
            });
        }

        function endGame() {
            clearInterval(timer);
            playing = false;
            $('gameplayScreen').style.display = 'none';
            $('finishScreen').style.display = 'block';
            $('qStepCounter').textContent = 'Selesai';
            window.scrollTo({ top: 0, behavior: 'smooth' });

            const total = QUESTIONS.length;
            if (!SHOWS_ANSWERS) {
                $('finishSubtitle').textContent = 'Semua jawaban sudah terkunci. Simpan untuk melihat nilai Anda.';
                $('btnFinish').textContent = 'Simpan & Lihat Nilai';
            }
            const finalScore = total ? Math.round(correctCount / total * 10000) / 100 : 0;
            $('finalCorrect').textContent = `${correctCount} / ${total}`;
            $('finalScore').textContent = finalScore.toLocaleString('id-ID');
            $('finalPoints').textContent = score.toLocaleString('id-ID');
            $('finalStreak').textContent = bestStreak;
            if (SHOWS_ANSWERS) {
                $('finishSubtitle').textContent = finalScore >= PASSING_SCORE
                    ? `Nilai Anda memenuhi nilai minimal lulus (${PASSING_SCORE})`
                    : `Nilai minimal lulus: ${PASSING_SCORE}`;
            }
            playFanfare();

            // Percobaan baru tersimpan setelah dikirim; kirim otomatis bila peserta tidak menekan tombol.
            let wait = 8;
            const note = $('autoSubmitNote');
            const tick = setInterval(() => {
                note.textContent = `Hasil disimpan otomatis dalam ${wait} detik`;
                if (wait-- <= 0) { clearInterval(tick); $('submitForm').requestSubmit(); }
            }, 1000);
            $('submitForm').addEventListener('submit', () => {
                clearInterval(tick);
                $('btnFinish').disabled = true;
                $('btnFinish').textContent = 'Menyimpan';
                note.textContent = '';
            });
        }
    })();
    </script>
</body>
</html>
