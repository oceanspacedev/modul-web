<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Kuis: {{ $training->title }}</title>
    @include('layout.partials.portal-head')
</head>
<body class="game-body prevent-select">

    <header class="game-bar">
        <div class="game-bar-side">
            <span class="game-step" id="qStepCounter">Soal 1 dari {{ $questions->count() }}</span>
        </div>

        <span class="game-mode">Mode game</span>

        <div class="game-bar-side">
            <span class="badge badge-warning" id="deadlineBadge" title="Kuis ditutup otomatis saat waktu habis" hidden>
                <i class="far fa-clock"></i> <span id="deadlineClock"></span>
            </span>
            <span class="game-meta" id="streakBadge" title="Jawaban benar beruntun" @unless($showsAnswers) style="display: none;" @endunless>
                <i class="fas fa-fire"></i> <span id="streakCount">0</span>
            </span>
            <span id="scoreBadge" style="display: none;">0</span>
            <button type="button" class="game-icon-btn" id="btnSoundToggle" title="Suara Game Aktif" aria-label="Suara game">
                <i class="fas fa-volume-up" id="soundIcon"></i>
            </button>
        </div>
    </header>

    <main class="game-stage">

        {{-- Layar 1: lembar pembuka --}}
        <div class="game-card" id="lobbyScreen">
            <div class="game-eyebrow">{{ $phase === 'pretest' ? 'Pretest Pelatihan' : 'Kuis Evaluasi Pelatihan' }}</div>
            <h2 class="game-title">{{ $training->title }}</h2>
            <p class="game-lead">
                Kuis untuk <strong>{{ $participant->user->nama_lengkap }}</strong>. Jawab setiap soal sebelum waktunya habis.
            </p>

            <div class="game-stats">
                <div class="game-stat">
                    <span class="game-stat-label">Jumlah Soal</span>
                    <span class="game-stat-value">{{ $questions->count() }}</span>
                </div>
                <div class="game-stat">
                    <span class="game-stat-label">Waktu Per Soal</span>
                    <span class="game-stat-value">30 detik</span>
                </div>
                <div class="game-stat">
                    <span class="game-stat-label">Sistem Penilaian</span>
                    <span class="game-stat-value">Nilai Akhir</span>
                </div>
            </div>

            @if($closesAt)
                <p class="game-lead">
                    {{ $phase === 'pretest' ? 'Pretest' : 'Kuis' }} ditutup otomatis pukul <strong>{{ $closesAt->format('H:i') }} WIB</strong>. Saat waktu habis, jawaban yang sudah masuk otomatis dikumpulkan.
                </p>
            @endif

            <button type="button" class="btn btn-primary btn-lg" id="btnStartGame">Mulai Game Kuis</button>
        </div>

        {{-- Layar 2: satu soal per layar --}}
        <div class="game-play" id="gameplayScreen">
            <div class="game-question" id="questionCard">
                <div class="game-timer-track"><div class="game-timer-fill" id="timerFill"></div></div>
                <div class="game-question-body">
                    <div class="game-question-main">
                        <h1 class="game-question-text" id="questionPrompt">Memuat soal</h1>
                        <img id="questionImage" class="game-question-image" alt="Gambar soal" hidden>
                    </div>
                    <div class="game-timer" id="timerBox">
                        <span class="game-timer-number" id="timerNumber">30</span>
                        <span class="game-timer-unit">detik</span>
                    </div>
                </div>
            </div>

            <div class="game-feedback-slot" aria-live="polite">
                <div class="game-feedback" id="feedbackBanner"></div>
            </div>

            <div class="game-options" id="optionsGrid"></div>

            <div id="essayContainer" style="display: none;" class="game-essay">
                <label for="essayInput">Jawaban Anda</label>
                <textarea id="essayInput" class="form-control" rows="5" placeholder="Tulis jawaban"></textarea>
                <button type="button" class="btn btn-primary btn-lg btn-block mt-3" id="btnSubmitEssay">
                    Simpan Jawaban &amp; Lanjut <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>

        {{-- Layar 3: hasil --}}
        <div class="game-card" id="finishScreen" style="display: none;">
            <h2 class="game-title" id="finishTitle">Kuis selesai</h2>
            <p class="game-lead" id="finishSubtitle">Seluruh jawaban Anda sudah tersimpan ke sistem evaluasi</p>

            {{-- DOM podium tetap ada untuk pengujian; peringkat tidak ditampilkan ke peserta. --}}
            <div style="display: none !important;">
                <div class="podium-banner-alert" id="podiumBannerAlert">
                    <span id="podiumBannerText"></span>
                </div>
                <div class="podium-stage-wrapper">
                    <div class="podium-stage">
                        <div class="podium-col col-silver" id="podiumCol2">
                            <div class="podium-name" id="nameRank2"></div>
                            <div class="podium-pts" id="scoreRank2"></div>
                        </div>
                        <div class="podium-col col-gold" id="podiumCol1">
                            <div class="podium-crown"></div>
                            <div class="podium-name" id="nameRank1"></div>
                            <div class="podium-pts" id="scoreRank1"></div>
                        </div>
                        <div class="podium-col col-bronze" id="podiumCol3">
                            <div class="podium-name" id="nameRank3"></div>
                            <div class="podium-pts" id="scoreRank3"></div>
                        </div>
                    </div>
                </div>
                <div id="moreLeaderboardContainer">
                    <div id="moreLeaderboardList"></div>
                </div>
            </div>

            <div class="game-stats" style="--game-stats-cols: {{ $showsAnswers ? 2 : 1 }};">
                @if($showsAnswers)
                <div class="game-stat">
                    <span class="game-stat-label">Jawaban Benar</span>
                    <span class="game-stat-value is-figure" id="finalAccuracy">0 / 0</span>
                </div>
                @endif
                <div class="game-stat">
                    <span class="game-stat-label">Nilai Akhir</span>
                    <span class="game-stat-value is-figure"><span id="finalAcademicScore">{{ $showsAnswers ? '0' : '...' }}</span> <small>/ 100</small></span>
                </div>
            </div>
            @if($showsAnswers)
                <span id="finalGameScore" style="display: none;">0</span>
            @endif

            <a href="/training/portal/{{ $participant->token }}" class="btn btn-primary btn-lg">
                <i class="fas fa-arrow-left"></i> Kembali ke Halaman Pelatihan
            </a>
        </div>

    </main>

    <!-- Hidden form for saving results to server -->
    <form action="{{ $phaseUrls['quiz'] }}" method="POST" id="hiddenSubmitForm" style="display: none;">
        @csrf
        <div id="hiddenAnswersContainer"></div>
    </form>

    <!-- Data Injection -->
    <script>
        @php
            $showAnswers = (bool) $showsAnswers;
            // Kunci & pembahasan tidak dikirim ke browser bila jawaban disembunyikan.
            $gameQuestions = $questions->map(fn ($question) => [
                ...($showAnswers
                    ? $question->toArray()
                    : \Illuminate\Support\Arr::except($question->toArray(), ['correct_answer', 'explanation'])),
                'question_image_url' => $question->imageUrl(),
                'option_image_urls' => (object) $question->optionImageUrls(),
            ])->values();
        @endphp
        const QUIZ_QUESTIONS = @json($gameQuestions);
        const SHOW_ANSWERS = @json($showAnswers);
        const PARTICIPANT_TOKEN = "{{ $participant->token }}";
        const CURRENT_USER_ID = {{ (int)$participant->user_id }};
        const CURRENT_USER_NAME = "{{ addslashes($participant->user->nama_lengkap) }}";
        let INITIAL_LEADERBOARD = {!! json_encode(collect($leaderboard)->map(function($l) {
            return [
                'user_id' => $l->user_id,
                'name' => $l->user ? $l->user->nama_lengkap : 'Peserta',
                'score' => (float)$l->score,
            ];
        })->values()) !!};
    </script>

    @include('training.partials.quiz-deadline-script')

    <!-- Audio FX & Game Controller -->
    <script>
    (function() {
        let soundEnabled = true;
        let audioCtx = null;

        function getAudioContext() {
            if (!audioCtx) {
                audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            }
            if (audioCtx.state === 'suspended') {
                audioCtx.resume();
            }
            return audioCtx;
        }

        // Web Audio Synthesizer: Click blip
        function playClickSound() {
            if (!soundEnabled) return;
            try {
                const ctx = getAudioContext();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(440, ctx.currentTime);
                gain.gain.setValueAtTime(0.15, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.08);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.08);
            } catch(e) {}
        }

        // Web Audio Synthesizer: Joyful Correct Chime (C5, E5, G5, C6)
        function playCorrectSound() {
            if (!soundEnabled) return;
            try {
                const ctx = getAudioContext();
                const chord = [523.25, 659.25, 783.99, 1046.50];
                chord.forEach((freq, idx) => {
                    setTimeout(() => {
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        osc.type = 'triangle';
                        osc.frequency.setValueAtTime(freq, ctx.currentTime);
                        gain.gain.setValueAtTime(0.25, ctx.currentTime);
                        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.35);
                        osc.connect(gain);
                        gain.connect(ctx.destination);
                        osc.start();
                        osc.stop(ctx.currentTime + 0.35);
                    }, idx * 70);
                });
            } catch(e) {}
        }

        // Web Audio Synthesizer: Wrong Boing / Buzz
        function playWrongSound() {
            if (!soundEnabled) return;
            try {
                const ctx = getAudioContext();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(220, ctx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(110, ctx.currentTime + 0.35);
                gain.gain.setValueAtTime(0.2, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.35);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.35);
            } catch(e) {}
        }

        // Web Audio Synthesizer: Finish Fanfare
        function playFanfareSound() {
            if (!soundEnabled) return;
            try {
                const ctx = getAudioContext();
                const notes = [440, 554.37, 659.25, 880];
                notes.forEach((freq, idx) => {
                    setTimeout(() => {
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        osc.type = 'triangle';
                        osc.frequency.setValueAtTime(freq, ctx.currentTime);
                        gain.gain.setValueAtTime(0.3, ctx.currentTime);
                        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.5);
                        osc.connect(gain);
                        gain.connect(ctx.destination);
                        osc.start();
                        osc.stop(ctx.currentTime + 0.5);
                    }, idx * 120);
                });
            } catch(e) {}
        }

        // Sound toggle button
        const soundBtn = document.getElementById('btnSoundToggle');
        const soundIcon = document.getElementById('soundIcon');
        soundBtn.addEventListener('click', () => {
            soundEnabled = !soundEnabled;
            soundIcon.className = soundEnabled ? 'fas fa-volume-up' : 'fas fa-volume-mute';
            soundBtn.title = soundEnabled ? 'Suara Game Aktif' : 'Suara Game Dimatikan';
        });

        // ================= GAME ENGINE =================
        let currentQuestionIndex = 0;
        let score = 0;
        let streak = 0;
        let correctAnswersCount = 0;
        let answersData = {};
        const QUESTION_TIME = 30; // 30s per question
        let timeLeft = QUESTION_TIME;
        let timerInterval = null;
        let canAnswer = false;
        let gameStarted = false;
        let gameEnded = false;

        const lobbyScreen = document.getElementById('lobbyScreen');
        const gameplayScreen = document.getElementById('gameplayScreen');
        const finishScreen = document.getElementById('finishScreen');
        const qStepCounter = document.getElementById('qStepCounter');
        const questionPrompt = document.getElementById('questionPrompt');
        const optionsGrid = document.getElementById('optionsGrid');
        const essayContainer = document.getElementById('essayContainer');
        const essayInput = document.getElementById('essayInput');
        const btnSubmitEssay = document.getElementById('btnSubmitEssay');
        const feedbackBanner = document.getElementById('feedbackBanner');
        const scoreBadge = document.getElementById('scoreBadge');
        const streakCount = document.getElementById('streakCount');
        const timerNumber = document.getElementById('timerNumber');
        const timerBox = document.getElementById('timerBox');
        const timerFill = document.getElementById('timerFill');
        const questionCard = document.getElementById('questionCard');

        document.getElementById('btnStartGame').addEventListener('click', () => {
            playClickSound();
            gameStarted = true;
            lobbyScreen.style.display = 'none';
            gameplayScreen.style.display = 'flex';
            loadQuestion(0);
        });

        // Batas waktu habis: yang sedang bermain langsung dikumpulkan dengan jawaban yang sudah masuk; yang belum
        // mulai dikembalikan ke halaman pelatihan tanpa memakai jatah percobaan.
        window.startQuizDeadline(function() {
            if (gameEnded) return;
            if (!gameStarted) {
                window.location.href = @json($phaseUrls['portal']);
                return;
            }
            // Essay yang sedang diketik ikut dikumpulkan.
            const current = QUIZ_QUESTIONS[currentQuestionIndex];
            if (canAnswer && current && current.type === 'essay') {
                answersData[current.id] = essayInput.value.trim();
            }
            endGame();
        });

        function loadQuestion(index) {
            // Jeda antar-soal yang masih berjalan saat waktu habis tidak boleh memunculkan soal berikutnya.
            if (gameEnded) return;
            currentQuestionIndex = index;
            canAnswer = true;
            feedbackBanner.style.display = 'none';

            if (index >= QUIZ_QUESTIONS.length) {
                endGame();
                return;
            }

            const q = QUIZ_QUESTIONS[index];
            if (qStepCounter) qStepCounter.textContent = `Soal ${index + 1} dari ${QUIZ_QUESTIONS.length}`;
            questionPrompt.textContent = q.question;
            const questionImage = document.getElementById('questionImage');
            if (q.question_image_url) {
                questionImage.src = q.question_image_url;
                questionImage.hidden = false;
            } else {
                questionImage.removeAttribute('src');
                questionImage.hidden = true;
            }

            // Reset timer
            clearInterval(timerInterval);
            timeLeft = QUESTION_TIME;
            updateTimerDisplay();

            timerInterval = setInterval(() => {
                timeLeft -= 0.1;
                if (timeLeft <= 0) {
                    timeLeft = 0;
                    clearInterval(timerInterval);
                    updateTimerDisplay();
                    handleTimeOut();
                } else {
                    updateTimerDisplay();
                }
            }, 100);

            if (q.type === 'essay') {
                optionsGrid.style.display = 'none';
                essayContainer.style.display = 'block';
                essayInput.value = '';
                essayInput.focus();
            } else {
                essayContainer.style.display = 'none';
                optionsGrid.style.display = 'grid';
                optionsGrid.innerHTML = '';

                const optKeys = ['a', 'b', 'c', 'd', 'e', 'f'];
                optKeys.forEach(key => {
                    const optText = q['option_' + key] || '';
                    const optImage = (q.option_image_urls || {})[key] || null;
                    if (optText || optImage) {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'game-option';
                        btn.setAttribute('data-key', key);
                        btn.innerHTML = `
                            <span class="game-option-letter">${key.toUpperCase()}</span>
                            <span class="game-option-text"></span>
                        `;
                        const textEl = btn.querySelector('.game-option-text');
                        textEl.textContent = optText;
                        if (optImage) {
                            const img = document.createElement('img');
                            img.src = optImage;
                            img.alt = 'Gambar pilihan ' + key.toUpperCase();
                            img.className = 'game-option-image';
                            textEl.appendChild(img);
                        }
                        btn.addEventListener('click', () => selectAnswer(key, btn));
                        optionsGrid.appendChild(btn);
                    }
                });
            }
        }

        function updateTimerDisplay() {
            const secLeft = Math.ceil(timeLeft);
            const urgent = secLeft <= 5;
            if (timerNumber) timerNumber.textContent = secLeft;
            if (timerBox) timerBox.classList.toggle('is-urgent', urgent);
            if (questionCard) questionCard.classList.toggle('is-urgent', urgent);
            if (timerFill) timerFill.style.transform = `scaleX(${Math.max(0, timeLeft / QUESTION_TIME)})`;
        }

        // Handle Player Choosing Multiple Choice Option
        function selectAnswer(selectedKey, clickedBtn) {
            if (!canAnswer) return;
            canAnswer = false;
            clearInterval(timerInterval);

            const q = QUIZ_QUESTIONS[currentQuestionIndex];
            answersData[q.id] = selectedKey;

            if (!SHOW_ANSWERS) {
                optionsGrid.querySelectorAll('.game-option').forEach(b => {
                    b.disabled = true;
                    b.classList.add(b === clickedBtn ? 'is-chosen' : 'is-dimmed');
                });

                playClickSound();
                feedbackBanner.className = 'game-feedback';
                feedbackBanner.innerHTML = `<i class="fas fa-check"></i> Jawaban tersimpan`;
                feedbackBanner.style.display = 'inline-flex';

                setTimeout(() => {
                    loadQuestion(currentQuestionIndex + 1);
                }, 1200);
                return;
            }

            const correctKey = (q.correct_answer || '').toLowerCase().trim();
            const isCorrect = (selectedKey === correctKey);

            // Audio & Visual Effects
            const allButtons = optionsGrid.querySelectorAll('.game-option');
            allButtons.forEach(b => {
                b.disabled = true;
                const bKey = b.getAttribute('data-key');
                if (bKey === correctKey) {
                    b.classList.add('is-correct');
                } else if (b === clickedBtn && !isCorrect) {
                    b.classList.add('is-wrong');
                } else {
                    b.classList.add('is-dimmed');
                }
            });

            if (isCorrect) {
                playCorrectSound();
                streak++;
                correctAnswersCount++;
                const timeBonus = Math.round(timeLeft * 25);
                const questionPoints = 1000 + timeBonus + (streak * 100);
                score += questionPoints;

                feedbackBanner.className = 'game-feedback is-correct';
                feedbackBanner.innerHTML = `<i class="fas fa-check-circle"></i> Benar`;
                feedbackBanner.style.display = 'inline-flex';
            } else {
                playWrongSound();
                streak = 0;
                feedbackBanner.className = 'game-feedback is-wrong';
                feedbackBanner.innerHTML = `<i class="fas fa-times-circle"></i> Salah, kunci: ${correctKey.toUpperCase()}`;
                feedbackBanner.style.display = 'inline-flex';
            }

            scoreBadge.textContent = score;
            streakCount.textContent = streak;

            setTimeout(() => {
                loadQuestion(currentQuestionIndex + 1);
            }, 1800);
        }

        // Handle Timeout on MC
        function handleTimeOut() {
            if (!canAnswer) return;
            canAnswer = false;

            const q = QUIZ_QUESTIONS[currentQuestionIndex];
            answersData[q.id] = ''; // unanswered
            streak = 0;
            streakCount.textContent = streak;

            playWrongSound();
            feedbackBanner.className = 'game-feedback is-wrong';
            feedbackBanner.innerHTML = `<i class="fas fa-hourglass-end"></i> Waktu habis`;
            feedbackBanner.style.display = 'inline-flex';

            const correctKey = (q.correct_answer || '').toLowerCase().trim();
            const allButtons = optionsGrid.querySelectorAll('.game-option');
            allButtons.forEach(b => {
                b.disabled = true;
                if (SHOW_ANSWERS && b.getAttribute('data-key') === correctKey) {
                    b.classList.add('is-correct');
                } else {
                    b.classList.add('is-dimmed');
                }
            });

            setTimeout(() => {
                loadQuestion(currentQuestionIndex + 1);
            }, 1800);
        }

        // Handle Essay Submission
        btnSubmitEssay.addEventListener('click', () => {
            if (!canAnswer) return;
            canAnswer = false;
            clearInterval(timerInterval);

            const q = QUIZ_QUESTIONS[currentQuestionIndex];
            answersData[q.id] = essayInput.value.trim();

            playCorrectSound();
            feedbackBanner.className = 'game-feedback';
            feedbackBanner.innerHTML = `<i class="fas fa-check"></i> Jawaban tersimpan`;
            feedbackBanner.style.display = 'inline-flex';

            setTimeout(() => {
                loadQuestion(currentQuestionIndex + 1);
            }, 1200);
        });

        // Podium & peringkat (DOM tersembunyi)
        function renderPodium(list) {
            const col1 = document.getElementById('podiumCol1');
            const col2 = document.getElementById('podiumCol2');
            const col3 = document.getElementById('podiumCol3');
            const bannerText = document.getElementById('podiumBannerText');
            const moreContainer = document.getElementById('moreLeaderboardContainer');
            const moreList = document.getElementById('moreLeaderboardList');

            if (!col1 || !col2 || !col3) return;

            col1.classList.remove('is-current-user');
            col2.classList.remove('is-current-user');
            col3.classList.remove('is-current-user');

            if (!list || list.length === 0) return;

            // Scenario 1: Solo Participant (Main Sendiri / Peserta Pertama)
            if (list.length === 1) {
                const solo = list[0];
                document.getElementById('nameRank1').textContent = solo.name + (solo.user_id === CURRENT_USER_ID ? ' (Anda)' : '');
                document.getElementById('scoreRank1').textContent = `Nilai: ${solo.score}`;
                col1.classList.add('is-current-user');

                document.getElementById('nameRank2').textContent = 'Menunggu...';
                document.getElementById('scoreRank2').textContent = '-';

                document.getElementById('nameRank3').textContent = 'Menunggu...';
                document.getElementById('scoreRank3').textContent = '-';

                bannerText.innerHTML = `Anda menyelesaikan kuis di posisi <strong>Juara 1</strong>`;
                if (moreContainer) moreContainer.style.display = 'none';
                return;
            }

            // Scenario 2: Two Participants
            if (list.length === 2) {
                const p1 = list[0];
                const p2 = list[1];

                document.getElementById('nameRank1').textContent = p1.name + (p1.user_id === CURRENT_USER_ID ? ' (Anda)' : '');
                document.getElementById('scoreRank1').textContent = `Nilai: ${p1.score}`;
                if (p1.user_id === CURRENT_USER_ID) col1.classList.add('is-current-user');

                document.getElementById('nameRank2').textContent = p2.name + (p2.user_id === CURRENT_USER_ID ? ' (Anda)' : '');
                document.getElementById('scoreRank2').textContent = `Nilai: ${p2.score}`;
                if (p2.user_id === CURRENT_USER_ID) col2.classList.add('is-current-user');

                document.getElementById('nameRank3').textContent = 'Menunggu...';
                document.getElementById('scoreRank3').textContent = '-';

                const myRank = list.findIndex(p => p.user_id === CURRENT_USER_ID) + 1;
                bannerText.innerHTML = myRank === 1 
                    ? `Anda menduduki posisi <strong>Juara 1</strong>`
                    : `Anda menduduki posisi <strong>Juara 2</strong>`;

                if (moreContainer) moreContainer.style.display = 'none';
                return;
            }

            // Scenario 3: Three or More Participants (Full Podium)
            const p1 = list[0];
            const p2 = list[1];
            const p3 = list[2];

            document.getElementById('nameRank1').textContent = p1.name + (p1.user_id === CURRENT_USER_ID ? ' (Anda)' : '');
            document.getElementById('scoreRank1').textContent = `Nilai: ${p1.score}`;
            if (p1.user_id === CURRENT_USER_ID) col1.classList.add('is-current-user');

            document.getElementById('nameRank2').textContent = p2.name + (p2.user_id === CURRENT_USER_ID ? ' (Anda)' : '');
            document.getElementById('scoreRank2').textContent = `Nilai: ${p2.score}`;
            if (p2.user_id === CURRENT_USER_ID) col2.classList.add('is-current-user');

            document.getElementById('nameRank3').textContent = p3.name + (p3.user_id === CURRENT_USER_ID ? ' (Anda)' : '');
            document.getElementById('scoreRank3').textContent = `Nilai: ${p3.score}`;
            if (p3.user_id === CURRENT_USER_ID) col3.classList.add('is-current-user');

            const myRank = list.findIndex(p => p.user_id === CURRENT_USER_ID) + 1;
            if (myRank === 1) {
                bannerText.innerHTML = `Anda menduduki posisi <strong>Juara 1</strong>`;
            } else if (myRank === 2) {
                bannerText.innerHTML = `Anda menduduki posisi <strong>Juara 2</strong>`;
            } else if (myRank === 3) {
                bannerText.innerHTML = `Anda menduduki posisi <strong>Juara 3</strong>`;
            } else if (myRank > 3) {
                bannerText.innerHTML = `Anda menempati <strong>peringkat ${myRank}</strong> dari ${list.length} peserta`;
            }

            // Render remaining ranks (4 and above)
            if (list.length > 3 && moreContainer && moreList) {
                moreContainer.style.display = 'block';
                moreList.innerHTML = '';
                for (let i = 3; i < list.length; i++) {
                    const row = list[i];
                    const isMe = row.user_id === CURRENT_USER_ID;
                    const item = document.createElement('div');
                    item.className = `leaderboard-row ${isMe ? 'is-current' : ''}`;
                    item.innerHTML = `
                        <div class="d-flex align-items-center">
                            <strong class="mr-3 text-muted">#${i + 1}</strong>
                            <span>${row.name} ${isMe ? '<span class="badge badge-primary ml-2">Anda</span>' : ''}</span>
                        </div>
                        <strong class="text-primary font-weight-bold">Nilai: ${row.score}</strong>
                    `;
                    moreList.appendChild(item);
                }
            } else if (moreContainer) {
                moreContainer.style.display = 'none';
            }
        }

        // Finish Game & Auto Submit
        function endGame() {
            if (gameEnded) return;
            gameEnded = true;
            canAnswer = false;
            clearInterval(timerInterval);
            if (gameplayScreen) gameplayScreen.style.display = 'none';
            if (finishScreen) {
                finishScreen.style.display = 'block';
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }

            try {
                playFanfareSound();
            } catch(e) {}

            // Saat jawaban disembunyikan, nilai & podium menunggu respons server.
            if (SHOW_ANSWERS) try {
                const finalGameScoreElem = document.getElementById('finalGameScore');
                if (finalGameScoreElem) finalGameScoreElem.textContent = score;

                const finalAccElem = document.getElementById('finalAccuracy');
                if (finalAccElem) finalAccElem.textContent = `${correctAnswersCount} / ${QUIZ_QUESTIONS.length}`;

                // Calculate academic score (percentage of multiple choice)
                const mcCount = QUIZ_QUESTIONS.filter(q => q.type !== 'essay').length;
                const academicScore = mcCount > 0 ? Math.round((correctAnswersCount / mcCount) * 100) : 100;
                const finalAcadElem = document.getElementById('finalAcademicScore');
                if (finalAcadElem) finalAcadElem.textContent = `${academicScore}`;

                // Prepare local leaderboard merging current result
                const currentUserEntry = {
                    user_id: CURRENT_USER_ID,
                    name: CURRENT_USER_NAME,
                    score: academicScore,
                    isCurrent: true
                };

                let currentList = (INITIAL_LEADERBOARD || []).filter(item => item.user_id !== CURRENT_USER_ID);
                currentList.push(currentUserEntry);
                currentList.sort((a, b) => b.score - a.score);

                renderPodium(currentList);
            } catch(e) {
                console.error('Error rendering finish stats/podium:', e);
            }

            // Build hidden inputs and submit in background via fetch
            try {
                const hiddenContainer = document.getElementById('hiddenAnswersContainer');
                if (hiddenContainer) {
                    hiddenContainer.innerHTML = '';
                    for (const qId in answersData) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = `answers[${qId}]`;
                        input.value = answersData[qId];
                        hiddenContainer.appendChild(input);
                    }
                }

                // Background submission
                const submitForm = document.getElementById('hiddenSubmitForm');
                if (submitForm) {
                    const formData = new FormData(submitForm);
                    fetch(@json($phaseUrls['quiz']), {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }).then(response => response.json())
                    .then(data => {
                        console.log('Quiz results recorded', data);
                        if (!SHOW_ANSWERS && data && data.score !== undefined) {
                            const finalAcadElem = document.getElementById('finalAcademicScore');
                            if (finalAcadElem) finalAcadElem.textContent = `${Math.round(data.score)}`;
                        }
                        if (data && data.leaderboard && data.leaderboard.length > 0) {
                            renderPodium(data.leaderboard);
                        }
                    }).catch(err => {
                        console.error('Submission error:', err);
                    });
                }
            } catch(e) {
                console.error('Error submitting quiz answers:', e);
            }
        }
    })();
    </script>
</body>
</html>
