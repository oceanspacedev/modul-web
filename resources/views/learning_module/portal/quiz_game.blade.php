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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        :root {
            --timer-purple: #864cbf;
            --color-a: #e21b3c; --color-b: #1368ce; --color-c: #d89e00; --color-d: #26890c; --color-e: #8a2be2;
        }
        * { box-sizing: border-box; }
        body { background: #f2f2f2; color: #1e293b; font-family: 'Outfit', sans-serif; min-height: 100vh; display: flex; flex-direction: column; margin: 0; overflow-x: hidden; }
        .btn-default { background: #f8f9fa; border: 1px solid #ced4da; color: #1e293b; }

        .kahoot-navbar { background: #fff; border-bottom: 2px solid #e2e8f0; padding: 12px 24px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 8px rgba(0,0,0,.04); gap: 12px; }
        .q-step-counter { font-size: 1.35rem; font-weight: 800; color: #0f172a; letter-spacing: -.5px; }
        .nav-pill { padding: 5px 14px; border-radius: 20px; font-size: .88rem; font-weight: 800; display: inline-flex; align-items: center; white-space: nowrap; }
        .type-pill { background: #f1f5f9; border: 1px solid #cbd5e1; color: #475569; }
        .streak-pill { background: #fef3c7; border: 1px solid #fde68a; color: #b45309; }
        .score-pill { background: #ede9fe; border: 1px solid #ddd6fe; color: #6d28d9; }
        .btn-round { background: #f1f5f9; border: 1px solid #cbd5e1; color: #475569; width: 36px; height: 36px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; text-decoration: none !important; }
        .btn-round:hover { background: #e2e8f0; color: #0f172a; }

        .kahoot-stage { flex: 1; display: flex; flex-direction: column; width: 100%; min-height: calc(100vh - 65px); }
        #gameplayScreen { flex: 1; display: none; flex-direction: column; justify-content: space-between; min-height: calc(100vh - 65px); width: 100%; }
        .kahoot-question-stage { flex: 1; display: flex; align-items: center; justify-content: center; padding: 24px 32px 12px; }
        .kahoot-question-card { background: #fff; box-shadow: 0 10px 30px rgba(0,0,0,.05); border-radius: 20px; border: 2px solid #e2e8f0; padding: 38px 48px; width: 100%; min-height: 190px; display: flex; align-items: center; justify-content: space-between; gap: 28px; position: relative; }
        .kahoot-timer-col, .kahoot-spacer-col { width: 90px; flex-shrink: 0; display: flex; justify-content: center; }
        .kahoot-timer-circle { width: 82px; height: 82px; border-radius: 50%; background: var(--timer-purple); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 2.4rem; font-weight: 800; box-shadow: 0 6px 16px rgba(134,76,191,.35); letter-spacing: -1px; }
        .kahoot-timer-circle.timer-urgent { background: #e21b3c; box-shadow: 0 0 25px rgba(226,27,60,.6); animation: urgentPulse .5s infinite alternate; }
        @keyframes urgentPulse { from { transform: scale(1); } to { transform: scale(1.08); } }
        .kahoot-question-text { font-size: 2.1rem; font-weight: 800; color: #0f172a; line-height: 1.35; margin: 0; flex: 1; text-align: center; white-space: pre-line; }
        .kahoot-question-main { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 16px; min-width: 0; }
        .kahoot-question-image { max-width: 100%; max-height: 240px; object-fit: contain; border-radius: 12px; border: 1px solid #e2e8f0; }
        .kahoot-opt-image { display: block; max-width: 100%; max-height: 120px; margin-top: 8px; object-fit: contain; border-radius: 8px; background: #fff; }
        .kahoot-answer-arena { margin-top: auto; padding: 20px 28px 28px; }
        .answer-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .kahoot-btn { border: none; outline: none !important; border-radius: 10px; padding: 24px 32px; min-height: 120px; color: #fff; display: flex; align-items: center; cursor: pointer; box-shadow: 0 6px 14px rgba(0,0,0,.12); transition: transform .15s, filter .15s, opacity .2s; text-align: left; width: 100%; }
        .kahoot-btn:hover:not(:disabled) { filter: brightness(1.08); transform: translateY(-2px); }
        .kahoot-shape { font-size: 2.3rem; line-height: 1; margin-right: 22px; width: 38px; flex-shrink: 0; text-align: center; }
        .kahoot-text { flex: 1; line-height: 1.35; font-weight: 800; font-size: 1.45rem; }
        .btn-opt-a { background: var(--color-a); } .btn-opt-b { background: var(--color-b); } .btn-opt-c { background: var(--color-c); }
        .btn-opt-d { background: var(--color-d); } .btn-opt-e { background: var(--color-e); }
        .btn-chosen { outline: 4px solid #0f172a !important; outline-offset: 3px; }
        .btn-correct { background: #10b981 !important; transform: scale(1.02); box-shadow: 0 0 25px rgba(16,185,129,.7) !important; }
        .btn-wrong { background: #ef4444 !important; animation: shakeWrong .4s ease; }
        @keyframes shakeWrong { 0%,100% { transform: translateX(0); } 25% { transform: translateX(-8px); } 75% { transform: translateX(8px); } }
        .btn-dimmed { opacity: .25; }
        .feedback-banner { position: absolute; bottom: -20px; left: 50%; transform: translateX(-50%); padding: 10px 28px; border-radius: 30px; font-size: 1.15rem; font-weight: 800; box-shadow: 0 10px 25px rgba(0,0,0,.18); display: none; z-index: 20; white-space: nowrap; color: #fff; }
        .feedback-correct { background: #10b981; } .feedback-wrong { background: #ef4444; } .feedback-wait { background: #64748b; }

        .finish-card { background: #fff; border: 2px solid #e2e8f0; border-radius: 24px; padding: 36px 28px; width: calc(100% - 32px); max-width: 720px; text-align: center; box-shadow: 0 20px 50px rgba(0,0,0,.08); position: relative; overflow: hidden; margin: 30px auto; }
        .stat-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 14px 10px; height: 100%; }
        .stat-box small { display: block; font-weight: 700; text-transform: uppercase; color: #64748b; font-size: .72rem; margin-bottom: 4px; }
        .confetti-container { position: absolute; inset: 0; pointer-events: none; overflow: hidden; }
        .confetti-piece { position: absolute; width: 10px; height: 12px; top: -20px; border-radius: 2px; animation: confettiFall 2.8s linear forwards; }
        @keyframes confettiFall { from { transform: translateY(0) rotate(0); opacity: 1; } to { transform: translateY(700px) rotate(720deg); opacity: 0; } }

        @media (max-width: 768px) {
            .kahoot-navbar { padding: 10px 14px; }
            .type-pill { display: none; }
            .kahoot-question-stage { padding: 14px 16px 8px; }
            .kahoot-question-card { flex-direction: column; padding: 20px 18px; gap: 14px; min-height: auto; }
            .kahoot-question-text { font-size: 1.35rem; }
            .kahoot-timer-col, .kahoot-spacer-col { width: auto; }
            .kahoot-answer-arena { padding: 12px 16px 20px; }
            .answer-grid { grid-template-columns: 1fr; gap: 12px; }
            .kahoot-btn { min-height: 72px; padding: 14px 18px; }
            .kahoot-shape { font-size: 1.7rem; margin-right: 14px; width: 28px; }
            .kahoot-text { font-size: 1.1rem; }
        }
    </style>
</head>
<body>
    <header class="kahoot-navbar">
        <div class="d-flex align-items-center" style="gap: 12px;">
            <a href="{{ $backUrl }}" class="btn-round" id="btnBack" title="Kembali"><i class="fas fa-arrow-left"></i></a>
            <span class="q-step-counter" id="qStepCounter">{{ $phaseLabel }}</span>
        </div>
        <span class="nav-pill type-pill"><i class="fas fa-gamepad mr-1"></i> QUIZIZZ MODE</span>
        <div class="d-flex align-items-center" style="gap: 10px;">
            <span class="nav-pill streak-pill" title="Jawaban benar beruntun"><i class="fas fa-fire mr-1"></i> <span id="streakCount">0</span></span>
            <span class="nav-pill score-pill" title="Poin"><span id="scoreBadge">0</span>&nbsp;poin</span>
            <button type="button" class="btn-round" id="btnSoundToggle" title="Suara aktif"><i class="fas fa-volume-up" id="soundIcon"></i></button>
        </div>
    </header>

    <main class="kahoot-stage">
        {{-- LOBBY --}}
        <div class="finish-card" id="lobbyScreen">
            @foreach(['error' => 'danger', 'info' => 'info', 'success' => 'success'] as $key => $type)
                @if(session($key))
                    <div class="alert alert-{{ $type }} text-left small">{{ session($key) }}</div>
                @endif
            @endforeach
            @if($isPretest)
                <div class="text-left">@include('learning_module.partials.manager-preview')</div>
            @endif

            <div class="d-inline-flex align-items-center justify-content-center mb-3" style="width: 58px; height: 58px; background: #e0e7ff; color: #4338ca; border-radius: 16px;">
                <i class="fas fa-gamepad fa-2x"></i>
            </div>
            <div class="text-uppercase font-weight-bold small text-primary mb-1">{{ $phaseLabel }}</div>
            <h2 class="font-weight-bold mb-2" style="font-size: 1.55rem;">{{ $module->title }}</h2>
            <p class="text-muted mb-4">
                @if($isPretest)
                    Pretest mengukur pemahaman awal Anda. Jawab sebisanya, materi terbuka setelah pretest selesai.
                @else
                    Jawab setiap soal sebelum waktu habis. Setiap percobaan tetap tersimpan.
                @endif
                Setiap jawaban langsung dikunci dan hanya ditandai <b>Benar</b> atau <b>Salah</b>.
            </p>

            <div class="row justify-content-center mb-4 text-center">
                <div class="col-6 col-md-3 mb-2"><div class="stat-box"><small>Jumlah Soal</small><strong class="h5">{{ $questions->count() }}</strong></div></div>
                <div class="col-6 col-md-3 mb-2"><div class="stat-box"><small>Waktu / Soal</small><strong class="h5">{{ $module->question_seconds }} detik</strong></div></div>
                <div class="col-6 col-md-3 mb-2"><div class="stat-box"><small>Nilai Lulus</small><strong class="h5">{{ $module->passing_score }}</strong></div></div>
                <div class="col-6 col-md-3 mb-2"><div class="stat-box"><small>Sisa Percobaan</small><strong class="h5">{{ $remainingAttempts === null ? 'Tanpa batas' : $remainingAttempts }}</strong></div></div>
            </div>

            <button type="button" class="btn btn-primary px-5 py-3 font-weight-bold shadow-sm" id="btnStartGame" style="background: #4338ca; border: none; border-radius: 12px; font-size: 1.1rem;">
                {{ $answered->isEmpty() ? 'Mulai '.($isPretest ? 'Pretest' : 'Posttest') : 'Lanjutkan' }}
            </button>
            @if($answered->isNotEmpty())
                <p class="text-muted small mt-3 mb-0">{{ $answered->count() }} dari {{ $questions->count() }} soal sudah Anda jawab dan tidak bisa diubah.</p>
            @endif
        </div>

        {{-- GAMEPLAY --}}
        <div id="gameplayScreen">
            <div class="kahoot-question-stage">
                <div class="kahoot-question-card">
                    <div class="kahoot-timer-col">
                        <div class="kahoot-timer-circle" id="kahootTimerCircle"><span id="kahootTimerNumber">{{ $module->question_seconds }}</span></div>
                    </div>
                    <div class="kahoot-question-main">
                        <h1 class="kahoot-question-text" id="questionPrompt"></h1>
                        <img id="questionImage" class="kahoot-question-image" alt="Gambar soal" hidden>
                    </div>
                    <div class="kahoot-spacer-col"></div>
                    <div class="feedback-banner" id="feedbackBanner"></div>
                </div>
            </div>
            <div class="kahoot-answer-arena">
                <div class="answer-grid" id="optionsGrid"></div>
            </div>
        </div>

        {{-- FINISH --}}
        <div class="finish-card" id="finishScreen" style="display: none;">
            <div id="confettiContainer" class="confetti-container"></div>
            <div class="d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px; background: #ecfdf5; color: #10b981; border-radius: 50%;">
                <i class="fas fa-flag-checkered fa-2x"></i>
            </div>
            <h2 class="font-weight-bold mb-1">{{ $isPretest ? 'Pretest' : 'Posttest' }} Selesai!</h2>
            <p class="text-muted small mb-4" id="finishSubtitle"></p>

            <div class="row justify-content-center mb-4 text-center">
                <div class="col-6 col-md-3 mb-2"><div class="stat-box"><small>Jawaban Benar</small><strong class="h5 text-success" id="finalCorrect">0 / 0</strong></div></div>
                <div class="col-6 col-md-3 mb-2"><div class="stat-box"><small>Nilai</small><strong class="h5 text-primary" id="finalScore">0</strong></div></div>
                <div class="col-6 col-md-3 mb-2"><div class="stat-box"><small>Poin</small><strong class="h5" style="color: #6d28d9;" id="finalPoints">0</strong></div></div>
                <div class="col-6 col-md-3 mb-2"><div class="stat-box"><small>Streak Terbaik</small><strong class="h5" style="color: #b45309;" id="finalStreak">0</strong></div></div>
            </div>

            <form action="{{ $module->url('submit', ['phase' => $phase]) }}" method="POST" id="submitForm">
                @csrf
                <button type="submit" class="btn btn-primary px-5 py-2 font-weight-bold" id="btnFinish" style="background: #4338ca; border: none; border-radius: 12px;">
                    Simpan &amp; Lihat Hasil
                </button>
            </form>
            <p class="text-muted small mt-2 mb-0" id="autoSubmitNote"></p>
        </div>
    </main>

    <script>
    (function () {
        const QUESTIONS = @json($gameQuestions);
        const ANSWERED = @json((object) $answered->all());
        const QUESTION_TIME = {{ (int) $module->question_seconds }};
        const PASSING_SCORE = {{ (int) $module->passing_score }};
        const ANSWER_URL = @json($module->url('answer', ['phase' => $phase]));
        const CSRF = document.querySelector('meta[name=csrf-token]').content;
        const SHAPES = { a: '▲', b: '◆', c: '●', d: '■', e: '★' };

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
            banner.className = 'feedback-banner feedback-' + kind;
            banner.innerHTML = html;
            banner.style.display = 'inline-block';
        }

        function loadQuestion() {
            if (index >= QUESTIONS.length || index === -1) { endGame(); return; }
            const q = QUESTIONS[index];
            banner.style.display = 'none';
            $('qStepCounter').textContent = `${index + 1} / ${QUESTIONS.length}`;
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
                btn.className = `kahoot-btn btn-opt-${key}`;
                btn.dataset.key = key;
                const shape = document.createElement('span'); shape.className = 'kahoot-shape'; shape.textContent = SHAPES[key] || key.toUpperCase();
                const label = document.createElement('span'); label.className = 'kahoot-text'; label.textContent = text;
                const optionImage = (q.option_images || {})[key];
                if (optionImage) {
                    const img = document.createElement('img');
                    img.src = optionImage; img.alt = 'Gambar pilihan ' + key.toUpperCase(); img.className = 'kahoot-opt-image';
                    label.appendChild(img);
                }
                btn.append(shape, label);
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
            $('kahootTimerNumber').textContent = sec;
            $('kahootTimerCircle').classList.toggle('timer-urgent', sec <= 5);
        }

        // Jawaban (atau waktu habis = null) dikirim ke server; server membalas benar/salah tanpa kunci jawaban.
        function choose(key, btn) {
            if (!canAnswer) return;
            canAnswer = false;
            clearInterval(timer);
            const remaining = timeLeft;
            const q = QUESTIONS[index];

            grid.querySelectorAll('.kahoot-btn').forEach(b => {
                b.disabled = true;
                b.classList.add(b === btn ? 'btn-chosen' : 'btn-dimmed');
            });
            if (key === null) showBanner('wrong', '<i class="fas fa-hourglass-end mr-1"></i> WAKTU HABIS!');
            else playClick();

            send(q.id, key, 0).then(data => {
                if (data.correct) {
                    streak++; correctCount++;
                    bestStreak = Math.max(bestStreak, streak);
                    score += 1000 + Math.round(remaining * 25) + streak * 100;
                    if (btn) btn.classList.add('btn-correct');
                    playCorrect();
                    showBanner('correct', '<i class="fas fa-check-circle mr-1"></i> BENAR!');
                } else {
                    streak = 0;
                    if (btn) btn.classList.add('btn-wrong');
                    playWrong();
                    showBanner('wrong', key === null
                        ? '<i class="fas fa-hourglass-end mr-1"></i> WAKTU HABIS!'
                        : '<i class="fas fa-times-circle mr-1"></i> SALAH!');
                }
                updateBadges();
                setTimeout(() => { index++; loadQuestion(); }, 1600);
            }).catch(message => {
                showBanner('wrong', message);
                const retry = document.createElement('button');
                retry.type = 'button';
                retry.className = 'btn btn-light btn-sm ml-2 font-weight-bold';
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
                    showBanner('wait', '<i class="fas fa-sync fa-spin mr-1"></i> Menyimpan jawaban...');
                    return new Promise(r => setTimeout(r, 1500)).then(() => send(questionId, answer, tries + 1));
                }
                throw '<i class="fas fa-wifi mr-1"></i> Koneksi terputus.';
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
            const finalScore = total ? Math.round(correctCount / total * 10000) / 100 : 0;
            $('finalCorrect').textContent = `${correctCount} / ${total}`;
            $('finalScore').textContent = finalScore.toLocaleString('id-ID');
            $('finalPoints').textContent = score.toLocaleString('id-ID');
            $('finalStreak').textContent = bestStreak;
            $('finishSubtitle').textContent = finalScore >= PASSING_SCORE
                ? `Nilai Anda memenuhi nilai minimal lulus (${PASSING_SCORE}).`
                : `Nilai minimal lulus: ${PASSING_SCORE}.`;
            playFanfare();
            launchConfetti();

            // Percobaan baru tersimpan setelah dikirim; kirim otomatis bila peserta tidak menekan tombol.
            let wait = 8;
            const note = $('autoSubmitNote');
            const tick = setInterval(() => {
                note.textContent = `Hasil disimpan otomatis dalam ${wait} detik.`;
                if (wait-- <= 0) { clearInterval(tick); $('submitForm').requestSubmit(); }
            }, 1000);
            $('submitForm').addEventListener('submit', () => {
                clearInterval(tick);
                $('btnFinish').disabled = true;
                $('btnFinish').textContent = 'Menyimpan...';
                note.textContent = '';
            });
        }

        function launchConfetti() {
            const box = $('confettiContainer');
            const colors = ['#f59e0b', '#ec4899', '#8b5cf6', '#10b981', '#3b82f6', '#ffd700', '#ef4444'];
            for (let i = 0; i < 45; i++) {
                const piece = document.createElement('div');
                piece.className = 'confetti-piece';
                piece.style.left = Math.random() * 100 + '%';
                piece.style.background = colors[Math.floor(Math.random() * colors.length)];
                piece.style.animationDelay = Math.random() * 1.5 + 's';
                piece.style.animationDuration = 2 + Math.random() * 2 + 's';
                box.appendChild(piece);
            }
        }
    })();
    </script>
</body>
</html>
