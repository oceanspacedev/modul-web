<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Kuis: {{ $training->title }}</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

    <style>
        :root {
            --bg-page: #f2f2f2;
            --bg-card: #ffffff;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --timer-purple: #864cbf;
            --color-a: #e21b3c; /* Red ▲ */
            --color-b: #1368ce; /* Blue ◆ */
            --color-c: #d89e00; /* Yellow ● */
            --color-d: #26890c; /* Green ■ */
            --color-e: #8a2be2; /* Purple ★ */
            --color-f: #ff7f50; /* Orange ⬟ */
        }

        * {
            box-sizing: border-box;
            user-select: none;
        }

        body {
            background-color: var(--bg-page);
            color: var(--text-dark);
            font-family: 'Outfit', 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            margin: 0;
            padding: 0;
        }

        /* Top Kahoot Navbar */
        .kahoot-navbar {
            background: #ffffff;
            border-bottom: 2px solid #e2e8f0;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .q-step-counter {
            font-size: 1.35rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.5px;
        }

        .kahoot-type-pill {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #475569;
            padding: 5px 16px;
            border-radius: 20px;
            font-size: 0.88rem;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            letter-spacing: 0.2px;
        }

        .streak-pill {
            background: #fef3c7;
            border: 1px solid #fde68a;
            color: #b45309;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
        }

        .score-pill {
            background: #ede9fe;
            border: 1px solid #ddd6fe;
            color: #6d28d9;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.88rem;
            font-weight: 800;
        }

        .btn-sound {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #475569;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-sound:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        /* Stage Layout - Full Viewport */
        .kahoot-stage {
            flex: 1;
            display: flex;
            flex-direction: column;
            width: 100%;
            min-height: calc(100vh - 65px);
            padding: 0;
            position: relative;
        }

        #gameplayScreen {
            flex: 1;
            display: none;
            flex-direction: column;
            justify-content: space-between;
            min-height: calc(100vh - 65px);
            width: 100%;
        }

        #gameplayScreen[style*="display: block"],
        #gameplayScreen[style*="display: flex"] {
            display: flex !important;
            flex-direction: column !important;
            justify-content: space-between !important;
            min-height: calc(100vh - 65px) !important;
        }

        /* 1. Centered Full Question Stage & Card */
        .kahoot-question-stage {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 24px 32px 12px 32px;
        }

        .kahoot-question-card {
            background: #ffffff;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            border-radius: 20px;
            border: 2px solid #e2e8f0;
            padding: 38px 48px;
            width: 100%;
            min-height: 190px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 28px;
            position: relative;
            transition: all 0.3s ease;
        }

        .kahoot-timer-col {
            width: 90px;
            flex-shrink: 0;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .kahoot-timer-circle {
            width: 82px;
            height: 82px;
            border-radius: 50%;
            background-color: var(--timer-purple);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.4rem;
            font-weight: 800;
            box-shadow: 0 6px 16px rgba(134, 76, 191, 0.35);
            transition: all 0.2s ease;
            user-select: none;
            letter-spacing: -1px;
        }

        .kahoot-timer-circle.timer-urgent {
            background-color: #e21b3c;
            box-shadow: 0 0 25px rgba(226, 27, 60, 0.6);
            animation: urgentPulse 0.5s infinite alternate;
        }
        @keyframes urgentPulse {
            0% { transform: scale(1); }
            100% { transform: scale(1.08); }
        }

        .kahoot-question-text {
            font-size: 2.5rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.35;
            margin: 0;
            flex: 1;
            text-align: center;
        }

        .kahoot-spacer-col {
            width: 90px;
            flex-shrink: 0;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* 2. Bottom Quadrants Answer Buttons - PINNED FIRMLY AT VERY BOTTOM */
        .kahoot-answer-arena {
            margin-top: auto !important; /* Paling bawah! */
            width: 100%;
            padding: 20px 28px 28px 28px;
            display: flex;
            flex-direction: column;
        }

        .answer-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            width: 100%;
        }

        @media (max-width: 768px) {
            body {
                height: auto;
                overflow-y: auto;
            }
            .kahoot-stage {
                height: auto;
                overflow: visible;
            }
            .kahoot-question-stage {
                padding: 14px 16px 8px 16px;
            }
            .kahoot-question-card {
                flex-direction: column;
                padding: 20px 18px;
                gap: 14px;
                min-height: auto;
            }
            .kahoot-question-text {
                font-size: 1.45rem;
            }
            .kahoot-timer-col, .kahoot-spacer-col {
                width: auto;
            }
            .kahoot-answer-arena {
                padding: 12px 16px 20px 16px;
            }
            .answer-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            .kahoot-btn {
                min-height: 80px;
                padding: 16px 20px;
            }
            .kahoot-shape {
                font-size: 1.8rem;
                margin-right: 16px;
                width: 28px;
            }
            .kahoot-text {
                font-size: 1.2rem;
            }
        }

        .kahoot-btn {
            border: none;
            outline: none;
            border-radius: 10px;
            padding: 24px 32px;
            height: 100%;
            min-height: 130px;
            color: #ffffff;
            font-size: 1.55rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            cursor: pointer;
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.12);
            transition: transform 0.15s ease, filter 0.15s ease, box-shadow 0.15s ease;
            text-align: left;
            width: 100%;
        }

        .kahoot-btn:hover {
            filter: brightness(1.08);
            transform: translateY(-2px);
            box-shadow: 0 10px 22px rgba(0, 0, 0, 0.18);
        }

        .kahoot-btn:active {
            transform: translateY(2px);
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.1);
        }

        .kahoot-shape {
            font-size: 2.5rem;
            line-height: 1;
            margin-right: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            flex-shrink: 0;
            color: #ffffff;
        }

        .kahoot-text {
            flex: 1;
            line-height: 1.35;
            font-weight: 800;
            color: #ffffff;
            font-size: 1.6rem;
            letter-spacing: -0.2px;
        }

        /* Color classes matching exact Kahoot quadrants */
        .btn-opt-a { background-color: var(--color-a); } /* Red ▲ */
        .btn-opt-b { background-color: var(--color-b); } /* Blue ◆ */
        .btn-opt-c { background-color: var(--color-c); } /* Yellow ● */
        .btn-opt-d { background-color: var(--color-d); } /* Green ■ */
        .btn-opt-e { background-color: var(--color-e); } /* Purple ★ */
        .btn-opt-f { background-color: var(--color-f); } /* Coral ⬟ */

        /* Correct / Wrong visual states */
        .btn-correct {
            background-color: #10b981 !important;
            transform: scale(1.02);
            box-shadow: 0 0 25px rgba(16, 185, 129, 0.7) !important;
            animation: correctPop 0.5s ease;
        }
        @keyframes correctPop {
            0% { transform: scale(1); }
            50% { transform: scale(1.04); }
            100% { transform: scale(1.02); }
        }

        .btn-wrong {
            background-color: #ef4444 !important;
            opacity: 0.55;
            animation: shakeWrong 0.4s ease;
        }
        @keyframes shakeWrong {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-8px); }
            75% { transform: translateX(8px); }
        }

        .btn-dimmed {
            opacity: 0.25 !important;
            pointer-events: none;
            transform: scale(0.98);
        }

        /* Feedback Banner */
        .feedback-banner {
            position: absolute;
            bottom: -20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 10px 28px;
            border-radius: 30px;
            font-size: 1.15rem;
            font-weight: 800;
            box-shadow: 0 10px 25px rgba(0,0,0,0.18);
            display: none;
            text-align: center;
            z-index: 20;
            white-space: nowrap;
        }
        .feedback-correct {
            background: #10b981;
            color: #ffffff;
        }
        .feedback-wrong {
            background: #ef4444;
            color: #ffffff;
        }

        /* Essay Box */
        .kahoot-essay-box {
            background: #ffffff;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
        }

        /* Cards in Lobby & Finish */
        .finish-card {
            background: #ffffff;
            border: 2px solid #e2e8f0;
            border-radius: 24px;
            padding: 36px 28px;
            width: 100%;
            max-width: 720px;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.08);
            animation: fadeInScale 0.4s ease-out;
            position: relative;
            overflow: hidden;
            margin: 30px auto;
            color: #1e293b;
        }

        @keyframes fadeInScale {
            0% { opacity: 0; transform: scale(0.96); }
            100% { opacity: 1; transform: scale(1); }
        }

        /* Confetti Container */
        .confetti-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
            z-index: 2;
        }
        .confetti-piece {
            position: absolute;
            width: 10px;
            height: 12px;
            top: -20px;
            border-radius: 2px;
            opacity: 0.9;
            animation: confettiFall 2.8s linear forwards;
        }
        @keyframes confettiFall {
            0% { transform: translateY(0) rotate(0deg); opacity: 1; }
            100% { transform: translateY(700px) rotate(720deg); opacity: 0; }
        }

        /* 3D Podium Stage */
        .podium-stage-wrapper {
            background: linear-gradient(180deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 22px;
            padding: 36px 16px 16px 16px;
            margin: 20px 0;
            border: 2px solid #cbd5e1;
            position: relative;
            z-index: 5;
        }

        .podium-stage {
            display: flex;
            align-items: flex-end;
            justify-content: center;
            gap: 14px;
            margin-top: 15px;
        }

        .podium-col {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            max-width: 160px;
            position: relative;
        }

        .podium-crown {
            font-size: 2.2rem;
            position: absolute;
            top: -42px;
            z-index: 6;
            animation: crownBob 2s ease-in-out infinite;
            filter: drop-shadow(0 2px 8px rgba(245, 158, 11, 0.6));
        }
        @keyframes crownBob {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-7px) rotate(-3deg); }
        }

        .podium-player-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 12px;
            width: 100%;
        }

        .podium-avatar {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 8px;
            background: #ffffff;
            border: 3px solid #cbd5e1;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.1);
            position: relative;
            transition: all 0.3s;
        }

        .col-gold .podium-avatar {
            width: 66px;
            height: 66px;
            font-size: 1.8rem;
            border-color: #ffd700;
            background: radial-gradient(circle, #fffbeb, #fef08a);
            box-shadow: 0 0 25px rgba(245, 158, 11, 0.5);
        }

        .col-silver .podium-avatar {
            border-color: #94a3b8;
            background: radial-gradient(circle, #ffffff, #f1f5f9);
            box-shadow: 0 0 15px rgba(148, 163, 184, 0.35);
        }

        .col-bronze .podium-avatar {
            border-color: #cd7f32;
            background: radial-gradient(circle, #fff7ed, #fed7aa);
            box-shadow: 0 0 15px rgba(205, 127, 50, 0.35);
        }

        .podium-name {
            font-size: 0.88rem;
            font-weight: 700;
            color: #1e293b;
            max-width: 100%;
            text-align: center;
            line-height: 1.2;
            margin-bottom: 3px;
        }

        .podium-pts {
            font-size: 0.78rem;
            font-weight: 800;
            color: #d97706;
            background: #ffffff;
            padding: 2px 8px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
        }

        .podium-block {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            border-top-left-radius: 14px;
            border-top-right-radius: 14px;
            font-weight: 900;
            position: relative;
            box-shadow: inset 0 3px 5px rgba(255, 255, 255, 0.5), 0 8px 16px rgba(0, 0, 0, 0.12);
        }

        .block-gold {
            height: 140px;
            background: linear-gradient(180deg, #ffd700 0%, #f59e0b 60%, #d97706 100%);
            color: #ffffff;
            border-top: 3px solid #fef08a;
        }

        .block-silver {
            height: 105px;
            background: linear-gradient(180deg, #e2e8f0 0%, #94a3b8 60%, #64748b 100%);
            color: #ffffff;
            border-top: 3px solid #ffffff;
        }

        .block-bronze {
            height: 78px;
            background: linear-gradient(180deg, #fb923c 0%, #ea580c 60%, #9a3412 100%);
            color: #ffffff;
            border-top: 3px solid #fed7aa;
        }

        .block-rank-number {
            font-size: 2.4rem;
            line-height: 1;
            font-weight: 900;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.25);
        }

        .podium-col.is-current-user .podium-avatar {
            outline: 3px solid #3b82f6;
            outline-offset: 3px;
            box-shadow: 0 0 25px rgba(59, 130, 246, 0.8) !important;
        }

        .podium-banner-alert {
            background: #fef3c7;
            border: 1px solid #fde68a;
            padding: 8px 18px;
            border-radius: 30px;
            font-size: 0.92rem;
            font-weight: 700;
            color: #92400e;
            display: inline-flex;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .stat-card-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 14px 10px;
        }

        .leaderboard-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            margin-bottom: 8px;
            font-size: 0.92rem;
        }

        .leaderboard-row.is-current {
            background: #ede9fe;
            border: 1px solid #c4b5fd;
            font-weight: 700;
        }
    </style>
</head>
<body>

    <!-- TOP NAVBAR -->
    <header class="kahoot-navbar">
        <div class="d-flex align-items-center">
            <span class="q-step-counter" id="qStepCounter">1 of {{ $questions->count() }}</span>
        </div>

        <div class="d-flex align-items-center">
            <span class="kahoot-type-pill">
                <i class="fas fa-poll mr-1"></i> QUIZIZZ MODE
            </span>
        </div>

        <div class="d-flex align-items-center" style="gap: 10px;">
            <div class="streak-pill" id="streakBadge">
                <i class="fas fa-fire mr-1 text-warning"></i> <span id="streakCount">0</span>
            </div>
            <span id="scoreBadge" style="display: none;">0</span>
            <button type="button" class="btn-sound" id="btnSoundToggle" title="Suara Game Aktif">
                <i class="fas fa-volume-up" id="soundIcon"></i>
            </button>
        </div>
    </header>

    <!-- MAIN GAME STAGE -->
    <main class="kahoot-stage">

        <!-- SCREEN 1: LOBBY -->
        <div class="finish-card text-center" id="lobbyScreen">
            <div class="mb-3">
                <span style="font-size: 3.8rem;">🎮</span>
            </div>
            <h2 class="font-weight-800 mb-2 text-dark">{{ $training->title }}</h2>
            <p class="text-muted mb-4">
                Halo <strong>{{ $participant->user->full_name }}</strong>! Siapkan diri Anda untuk menjawab kuis interaktif ini.
            </p>

            <div class="row justify-content-center mb-4">
                <div class="col-4 border-right">
                    <span class="text-muted text-xs d-block">Jumlah Soal</span>
                    <strong class="h5 font-weight-bold text-dark">{{ $questions->count() }}</strong>
                </div>
                <div class="col-4 border-right">
                    <span class="text-muted text-xs d-block">Waktu Per Soal</span>
                    <strong class="h5 font-weight-bold text-warning">30 Detik</strong>
                </div>
                <div class="col-4">
                    <span class="text-muted text-xs d-block">Sistem Penilaian</span>
                    <strong class="h5 font-weight-bold text-success">Nilai Akhir</strong>
                </div>
            </div>

            <button type="button" class="btn btn-primary btn-lg px-5 py-3 font-weight-bold shadow" id="btnStartGame" style="background: var(--timer-purple); border: none; border-radius: 30px; font-size: 1.25rem;">
                Mulai Game Kuis 🚀
            </button>
        </div>

        <!-- SCREEN 2: GAMEPLAY (Full Screen Pure Kahoot) -->
        <div id="gameplayScreen" style="display: none; width: 100%; height: 100%;">
            <!-- Centered & Full Question Arena -->
            <div class="kahoot-question-stage">
                <div class="kahoot-question-card">
                    <div class="kahoot-timer-col">
                        <div class="kahoot-timer-circle" id="kahootTimerCircle">
                            <span id="kahootTimerNumber">30</span>
                        </div>
                    </div>

                    <h1 class="kahoot-question-text" id="questionPrompt">Memuat soal...</h1>

                    <div class="kahoot-spacer-col"></div>

                    <div class="feedback-banner" id="feedbackBanner"></div>
                </div>
            </div>

            <!-- Bottom Full-Width Answer Grid (The 4 Color Blocks) -->
            <div class="kahoot-answer-arena">
                <div class="answer-grid" id="optionsGrid"></div>

                <!-- Essay Option Container -->
                <div id="essayContainer" style="display: none;" class="kahoot-essay-box">
                    <label class="text-dark font-weight-bold mb-2 d-block" style="font-size: 1.2rem;">Tuliskan uraian / jawaban Anda:</label>
                    <textarea id="essayInput" class="form-control" rows="5" placeholder="Ketikkan jawaban Anda di sini..." style="border: 2px solid #cbd5e1; border-radius: 10px; font-size: 1.15rem; padding: 14px;"></textarea>
                    <button type="button" class="btn btn-primary btn-block mt-3 py-3 font-weight-bold shadow" id="btnSubmitEssay" style="background: #1368ce; border: none; border-radius: 8px; font-size: 1.2rem;">
                        Simpan Jawaban Essay & Lanjut <i class="fas fa-arrow-right ml-1"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- SCREEN 3: FINISH PODIUM & LEADERBOARD -->
        <div class="finish-card text-center" id="finishScreen" style="display: none;">
            <!-- Falling Confetti Container -->
            <div id="confettiContainer" class="confetti-container"></div>

            <div class="mb-3">
                <h2 class="font-weight-bold mb-1" id="finishTitle">🎉 Permainan Kuis Selesai!</h2>
                <p class="text-muted small mb-0" id="finishSubtitle">Jawaban Anda berhasil disimpan ke sistem evaluasi.</p>
            </div>

            <!-- PODIUM BANNER (SOLO / RANK INFO) -->
            <div class="mb-2">
                <div class="podium-banner-alert" id="podiumBannerAlert">
                    <i class="fas fa-crown text-warning mr-2"></i>
                    <span id="podiumBannerText">Memuat podium juara...</span>
                </div>
            </div>

            <!-- 3D PODIUM STAGE (SILVER, GOLD, BRONZE) -->
            <div class="podium-stage-wrapper">
                <div class="podium-stage">
                    <!-- PILLAR 2: SILVER (Left) -->
                    <div class="podium-col col-silver" id="podiumCol2">
                        <div class="podium-player-box">
                            <div class="podium-avatar" id="avatarRank2">🥈</div>
                            <div class="podium-name text-truncate" id="nameRank2">Menunggu...</div>
                            <div class="podium-pts" id="scoreRank2">-</div>
                        </div>
                        <div class="podium-block block-silver">
                            <span class="block-rank-number">2</span>
                        </div>
                    </div>

                    <!-- PILLAR 1: GOLD (Center - Tallest) -->
                    <div class="podium-col col-gold" id="podiumCol1">
                        <div class="podium-crown">👑</div>
                        <div class="podium-player-box">
                            <div class="podium-avatar" id="avatarRank1">🥇</div>
                            <div class="podium-name text-truncate font-weight-bold" id="nameRank1">Peserta</div>
                            <div class="podium-pts" id="scoreRank1">Nilai: -</div>
                        </div>
                        <div class="podium-block block-gold">
                            <span class="block-rank-number">1</span>
                        </div>
                    </div>

                    <!-- PILLAR 3: BRONZE (Right) -->
                    <div class="podium-col col-bronze" id="podiumCol3">
                        <div class="podium-player-box">
                            <div class="podium-avatar" id="avatarRank3">🥉</div>
                            <div class="podium-name text-truncate" id="nameRank3">Menunggu...</div>
                            <div class="podium-pts" id="scoreRank3">-</div>
                        </div>
                        <div class="podium-block block-bronze">
                            <span class="block-rank-number">3</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STAT SUMMARY MINI CARDS (JAWABAN BENAR & NILAI AKHIR) -->
            <div class="row justify-content-center mb-4">
                <div class="col-6">
                    <div class="stat-card-box">
                        <span class="text-muted text-xs d-block mb-1 font-weight-bold text-uppercase">Jawaban Benar</span>
                        <h4 class="font-weight-bold text-success mb-0" id="finalAccuracy">0 / 0</h4>
                    </div>
                </div>
                <div class="col-6">
                    <div class="stat-card-box">
                        <span class="text-muted text-xs d-block mb-1 font-weight-bold text-uppercase">Nilai Akhir</span>
                        <h4 class="font-weight-bold text-primary mb-0" id="finalAcademicScore">0 / 100</h4>
                    </div>
                </div>
            </div>
            <span id="finalGameScore" style="display: none;">0</span>

            <!-- REMAINING LEADERBOARD (Rank 4+) -->
            <div class="text-left mb-4" id="moreLeaderboardContainer" style="display: none;">
                <span class="text-xs text-muted font-weight-bold text-uppercase d-block mb-2">
                    <i class="fas fa-list-ol mr-1"></i> Peserta Lainnya
                </span>
                <div id="moreLeaderboardList"></div>
            </div>

            <div class="d-flex justify-content-center mt-2">
                <a href="/training/portal/{{ $participant->token }}" class="btn btn-primary px-5 py-2 font-weight-bold shadow-sm" style="background: var(--timer-purple); border: none; border-radius: 25px; font-size: 1.05rem;">
                    <i class="fas fa-home mr-2"></i> Kembali ke Portal Utama
                </a>
            </div>
        </div>

    </main>

    <!-- Hidden form for saving results to server -->
    <form action="/training/portal/{{ $participant->token }}/quiz" method="POST" id="hiddenSubmitForm" style="display: none;">
        @csrf
        <div id="hiddenAnswersContainer"></div>
    </form>

    <!-- Data Injection -->
    <script>
        const QUIZ_QUESTIONS = @json($questions);
        const PARTICIPANT_TOKEN = "{{ $participant->token }}";
        const CURRENT_USER_ID = {{ (int)$participant->user_id }};
        const CURRENT_USER_NAME = "{{ addslashes($participant->user->full_name) }}";
        let INITIAL_LEADERBOARD = {!! json_encode(collect($leaderboard)->map(function($l) {
            return [
                'user_id' => $l->user_id,
                'name' => $l->user ? $l->user->full_name : 'Peserta',
                'score' => (float)$l->score,
            ];
        })->values()) !!};
    </script>

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
        const kahootTimerNumber = document.getElementById('kahootTimerNumber');
        const kahootTimerCircle = document.getElementById('kahootTimerCircle');

        // Shapes for buttons (Kahoot geometric shapes)
        const shapes = {
            'a': '▲',
            'b': '◆',
            'c': '●',
            'd': '■',
            'e': '★',
            'f': '⬟'
        };

        document.getElementById('btnStartGame').addEventListener('click', () => {
            playClickSound();
            lobbyScreen.style.display = 'none';
            gameplayScreen.style.display = 'flex';
            loadQuestion(0);
        });

        function loadQuestion(index) {
            currentQuestionIndex = index;
            canAnswer = true;
            feedbackBanner.style.display = 'none';

            if (index >= QUIZ_QUESTIONS.length) {
                endGame();
                return;
            }

            const q = QUIZ_QUESTIONS[index];
            if (qStepCounter) qStepCounter.textContent = `${index + 1} of ${QUIZ_QUESTIONS.length}`;
            questionPrompt.textContent = q.question;

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
                    const optText = q['option_' + key];
                    if (optText) {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = `kahoot-btn btn-opt-${key}`;
                        btn.setAttribute('data-key', key);
                        btn.innerHTML = `
                            <span class="kahoot-shape">${shapes[key] || key.toUpperCase()}</span>
                            <span class="kahoot-text">${optText}</span>
                        `;
                        btn.addEventListener('click', () => selectAnswer(key, btn));
                        optionsGrid.appendChild(btn);
                    }
                });
            }
        }

        function updateTimerDisplay() {
            const secLeft = Math.ceil(timeLeft);
            if (kahootTimerNumber) kahootTimerNumber.textContent = secLeft;
            if (kahootTimerCircle) {
                if (secLeft <= 5) {
                    kahootTimerCircle.classList.add('timer-urgent');
                } else {
                    kahootTimerCircle.classList.remove('timer-urgent');
                }
            }
        }

        // Handle Player Choosing Multiple Choice Option
        function selectAnswer(selectedKey, clickedBtn) {
            if (!canAnswer) return;
            canAnswer = false;
            clearInterval(timerInterval);

            const q = QUIZ_QUESTIONS[currentQuestionIndex];
            const correctKey = (q.correct_answer || '').toLowerCase().trim();
            const isCorrect = (selectedKey === correctKey);

            answersData[q.id] = selectedKey;

            // Audio & Visual Effects
            const allButtons = optionsGrid.querySelectorAll('.kahoot-btn');
            allButtons.forEach(b => {
                b.disabled = true;
                const bKey = b.getAttribute('data-key');
                if (bKey === correctKey) {
                    b.classList.add('btn-correct');
                } else if (b === clickedBtn && !isCorrect) {
                    b.classList.add('btn-wrong');
                } else {
                    b.classList.add('btn-dimmed');
                }
            });

            if (isCorrect) {
                playCorrectSound();
                streak++;
                correctAnswersCount++;
                const timeBonus = Math.round(timeLeft * 25);
                const questionPoints = 1000 + timeBonus + (streak * 100);
                score += questionPoints;

                feedbackBanner.className = 'feedback-banner feedback-correct';
                feedbackBanner.innerHTML = `<i class="fas fa-check-circle mr-1"></i> BENAR!`;
                feedbackBanner.style.display = 'inline-block';
            } else {
                playWrongSound();
                streak = 0;
                feedbackBanner.className = 'feedback-banner feedback-wrong';
                feedbackBanner.innerHTML = `<i class="fas fa-times-circle mr-1"></i> SALAH! Kunci: ${correctKey.toUpperCase()}`;
                feedbackBanner.style.display = 'inline-block';
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
            feedbackBanner.className = 'feedback-banner feedback-wrong';
            feedbackBanner.innerHTML = `<i class="fas fa-hourglass-end mr-1"></i> WAKTU HABIS!`;
            feedbackBanner.style.display = 'inline-block';

            const correctKey = (q.correct_answer || '').toLowerCase().trim();
            const allButtons = optionsGrid.querySelectorAll('.kahoot-btn');
            allButtons.forEach(b => {
                b.disabled = true;
                if (b.getAttribute('data-key') === correctKey) {
                    b.classList.add('btn-correct');
                } else {
                    b.classList.add('btn-dimmed');
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
            feedbackBanner.className = 'feedback-banner feedback-correct';
            feedbackBanner.innerHTML = `<i class="fas fa-save mr-1"></i> JAWABAN TERSIMPAN!`;
            feedbackBanner.style.display = 'block';

            setTimeout(() => {
                loadQuestion(currentQuestionIndex + 1);
            }, 1200);
        });

        // Launch Celebration Confetti
        function launchConfetti() {
            const container = document.getElementById('confettiContainer');
            if (!container) return;
            container.innerHTML = '';
            const colors = ['#f59e0b', '#ec4899', '#8b5cf6', '#10b981', '#3b82f6', '#ffd700', '#ef4444'];
            for (let i = 0; i < 45; i++) {
                const conf = document.createElement('div');
                conf.className = 'confetti-piece';
                conf.style.left = (Math.random() * 100) + '%';
                conf.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                conf.style.animationDelay = (Math.random() * 1.5) + 's';
                conf.style.animationDuration = (2 + Math.random() * 2) + 's';
                conf.style.transform = `scale(${0.6 + Math.random() * 0.8})`;
                container.appendChild(conf);
            }
        }

        // Render 3D Podium & Dynamic Rankings
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

                bannerText.innerHTML = `🏆 <strong>JUARA 1!</strong> Anda berhasil menyelesaikan kuis dan memimpin peringkat puncak!`;
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
                    ? `👑 <strong>LUAR BIASA!</strong> Anda menduduki posisi <strong>Juara 1</strong> di podium kuis!`
                    : `🥈 <strong>HEBAT!</strong> Anda menduduki posisi <strong>Juara 2</strong> di podium kuis!`;

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
                bannerText.innerHTML = `👑 <strong>JUARA 1!</strong> Anda berhasil menduduki puncak podium tertinggi!`;
            } else if (myRank === 2) {
                bannerText.innerHTML = `🥈 <strong>PODIUM PERAK!</strong> Anda meraih posisi <strong>Juara 2</strong>!`;
            } else if (myRank === 3) {
                bannerText.innerHTML = `🥉 <strong>PODIUM PERUNGGU!</strong> Anda meraih posisi <strong>Juara 3</strong>!`;
            } else if (myRank > 3) {
                bannerText.innerHTML = `🎖️ Anda menempati <strong>Peringkat #${myRank}</strong> dari ${list.length} peserta.`;
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
            clearInterval(timerInterval);
            if (gameplayScreen) gameplayScreen.style.display = 'none';
            if (finishScreen) {
                finishScreen.style.display = 'block';
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }

            try {
                playFanfareSound();
            } catch(e) {}

            try {
                const finalGameScoreElem = document.getElementById('finalGameScore');
                if (finalGameScoreElem) finalGameScoreElem.textContent = score;

                const finalAccElem = document.getElementById('finalAccuracy');
                if (finalAccElem) finalAccElem.textContent = `${correctAnswersCount} / ${QUIZ_QUESTIONS.length}`;

                // Calculate academic score (percentage of multiple choice)
                const mcCount = QUIZ_QUESTIONS.filter(q => q.type !== 'essay').length;
                const academicScore = mcCount > 0 ? Math.round((correctAnswersCount / mcCount) * 100) : 100;
                const finalAcadElem = document.getElementById('finalAcademicScore');
                if (finalAcadElem) finalAcadElem.textContent = `${academicScore} / 100`;

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

                // Render 3D Podium & Confetti Immediately
                renderPodium(currentList);
                launchConfetti();
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
                    fetch(`/training/portal/${PARTICIPANT_TOKEN}/quiz`, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }).then(response => response.json())
                    .then(data => {
                        console.log('Quiz results successfully recorded!', data);
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
