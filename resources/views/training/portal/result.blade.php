<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Kuis - {{ $training->title }}</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            color: #212529;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .content-card {
            background: #ffffff;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 24px;
            margin-bottom: 24px;
        }
    </style>
</head>
<body>

    <main class="container py-5" style="max-width: 760px;">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                {{ session('success') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        @if(session('warning'))
            <div class="alert alert-warning alert-dismissible fade show mb-4 font-weight-bold" role="alert">
                <i class="fas fa-exclamation-triangle mr-1"></i> {{ session('warning') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        @if($quizResult->is_force_submitted)
            <div class="alert alert-danger border-danger mb-4 shadow-sm" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-shield-alt fa-2x mr-3 text-danger"></i>
                    <div>
                        <strong class="d-block font-weight-bold" style="font-size: 1.05rem;">Kuis Dikumpulkan Otomatis (Terdeteksi Berpindah Tab)</strong>
                        <span class="text-sm">Anda telah melanggar toleransi keluar tab sebanyak <strong>{{ $quizResult->tab_switch_count }} kali</strong>. Kuis Anda dikunci dan dikumpulkan otomatis oleh sistem.</span>
                    </div>
                </div>
            </div>
        @elseif($quizResult->tab_switch_count > 0)
            <div class="alert alert-warning border-warning mb-4" role="alert">
                <i class="fas fa-info-circle mr-1"></i>
                Catatan Integritas: Terdeteksi <strong>{{ $quizResult->tab_switch_count }} kali</strong> perpindahan tab selama pengerjaan kuis. Catatan ini tersimpan di laporan pemateri.
            </div>
        @endif

        <!-- SCORE SUMMARY -->
        <div class="content-card text-center p-4">
            <span class="text-muted small text-uppercase font-weight-bold d-block mb-1">Hasil Evaluasi Kuis</span>
            <h4 class="font-weight-bold mb-4">{{ $training->title }}</h4>

            @php
                $hasEssay = ($questions->where('type', 'essay')->count() > 0);
                $mcCount = $questions->where('type', '!=', 'essay')->count();
            @endphp

            <div class="d-inline-block px-5 py-3 border rounded bg-light mb-4">
                <span class="text-xs text-muted d-block font-weight-bold mb-1">
                    {{ $hasEssay && $quizResult->essay_status !== 'graded' ? 'NILAI SEMENTARA (PILIHAN GANDA)' : 'NILAI AKHIR' }}
                </span>
                <span class="display-3 font-weight-bold text-primary">{{ $quizResult->score }}</span>
                @if($hasEssay && $quizResult->essay_status !== 'graded')
                    <span class="badge badge-warning text-white text-xs d-block mt-2 px-3 py-1">
                        <i class="fas fa-hourglass-half mr-1"></i> Soal Essay sedang menunggu penilaian pemateri
                    </span>
                @elseif($hasEssay && $quizResult->essay_status === 'graded')
                    <span class="badge badge-success text-xs d-block mt-2 px-3 py-1">
                        <i class="fas fa-check-circle mr-1"></i> Nilai akhir gabungan Pilihan Ganda & Essay
                    </span>
                @endif
            </div>

            <div class="row max-w-sm mx-auto p-3 bg-light rounded border text-sm mb-4">
                <div class="col-4 border-right">
                    <span class="text-muted text-xs d-block">Soal Pilihan Ganda</span>
                    <strong class="text-dark">{{ $quizResult->correct_answers }} / {{ $mcCount }} Benar</strong>
                    <span class="text-xs text-muted d-block font-weight-bold">Nilai: {{ $quizResult->mc_score ?? $quizResult->score }}</span>
                </div>
                <div class="col-4 border-right">
                    <span class="text-muted text-xs d-block">Status Soal Essay</span>
                    @if($hasEssay)
                        @if($quizResult->essay_status === 'graded')
                            <strong class="text-success">{{ $quizResult->essay_score }} / 100</strong>
                            <span class="text-xs text-success d-block">Sudah Dinilai</span>
                        @else
                            <strong class="text-warning">Tersimpan</strong>
                            <span class="text-xs text-muted d-block">Menunggu Review</span>
                        @endif
                    @else
                        <strong class="text-muted">-</strong>
                        <span class="text-xs text-muted d-block">Tidak Ada Essay</span>
                    @endif
                </div>
                <div class="col-4">
                    <span class="text-muted text-xs d-block">Total Seluruh Soal</span>
                    <strong class="text-primary">{{ $quizResult->total_questions }} Soal</strong>
                    <span class="text-xs text-muted d-block">{{ $mcCount }} PG + {{ $questions->count() - $mcCount }} Essay</span>
                </div>
            </div>

            @if($quizResult->essay_feedback)
                <div class="alert alert-info text-left text-sm mb-4 mx-auto" style="max-width: 600px;">
                    <strong><i class="fas fa-comment-dots mr-1"></i> Catatan Evaluasi dari Pemateri:</strong>
                    <p class="mb-0 mt-1">{{ $quizResult->essay_feedback }}</p>
                </div>
            @endif

            <div class="d-flex justify-content-center flex-wrap" style="gap: 12px;">
                <a href="/training/portal/{{ $participant->token }}" class="btn btn-default px-4">
                    Kembali ke Halaman Pelatihan
                </a>
                @if($training->is_quiz_active)
                    <a href="/training/portal/{{ $participant->token }}/retake" onclick="return confirm('Apakah Anda ingin mengulang kuis ini?')" class="btn btn-warning text-white px-4 font-weight-bold">
                        Kerjakan Ulang Kuis
                    </a>
                @endif
            </div>
        </div>

        <!-- REVIEW PERTANYAAN -->
        @if($quizResult->answers)
        <div class="content-card p-4">
            <h5 class="font-weight-bold mb-3 pb-2 border-bottom">Rincian Jawaban & Kunci</h5>

            @foreach($questions as $index => $q)
            @php
                $ansData = $quizResult->answers[$q->id] ?? null;
                $userAns = $ansData['user_answer'] ?? null;
                $isEssay = ($q->type === 'essay');
                $isCorrect = $ansData['is_correct'] ?? false;
            @endphp
            <div class="p-3 mb-3 rounded border {{ $isEssay ? 'border-info' : ($isCorrect ? 'border-success' : 'border-danger') }}" style="background-color: {{ $isEssay ? '#f7faff' : ($isCorrect ? '#fafffa' : '#fff8f8') }};">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="badge {{ $isEssay ? 'badge-info' : ($isCorrect ? 'badge-success' : 'badge-danger') }} mr-1">
                            Soal {{ $index + 1 }} {{ $isEssay ? '(Essay)' : '' }}
                        </span>
                        <strong class="text-dark">{{ $q->question }}</strong>
                    </div>
                    <div>
                        @if($isEssay)
                            <span class="badge badge-light border text-info px-2 py-1">Tersimpan</span>
                        @else
                            <span class="text-xs font-weight-bold {{ $isCorrect ? 'text-success' : 'text-danger' }}">
                                {{ $isCorrect ? 'Benar' : 'Salah' }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="small mt-2 pt-2 border-top">
                    @if($isEssay)
                        <div class="mb-2">
                            <span class="text-muted d-block font-weight-bold">Jawaban Anda:</span>
                            <div class="p-2 bg-white rounded border mt-1 text-dark" style="white-space: pre-wrap;">{{ $userAns ?: '(Tidak ada jawaban)' }}</div>
                        </div>
                        @if($q->correct_answer)
                            <div class="mb-1 text-info">
                                <strong>Pedoman / Acuan Jawaban:</strong> {{ $q->correct_answer }}
                            </div>
                        @endif
                    @else
                        <div class="mb-1">
                            Jawaban Anda: 
                            <strong class="{{ $isCorrect ? 'text-success' : 'text-danger' }}">
                                {{ strtoupper($userAns ?? '-') }}. {{ $q->{'option_' . $userAns} ?? '-' }}
                            </strong>
                        </div>
                        @if(!$isCorrect && $q->correct_answer)
                        <div class="mb-1 text-success">
                            Kunci Jawaban: <strong>{{ strtoupper($q->correct_answer) }}. {{ $q->{'option_' . $q->correct_answer} ?? '-' }}</strong>
                        </div>
                        @endif
                    @endif

                    @if($q->explanation)
                    <div class="text-muted text-xs mt-2 pt-2 border-top font-italic">
                        Catatan: {{ $q->explanation }}
                    </div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
