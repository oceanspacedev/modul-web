<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Kuis - {{ $training->title }}</title>
    @include('layout.partials.portal-head')
</head>
<body>

    <main class="container portal-container py-5">
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
                <i class="fas fa-exclamation-triangle mr-1"></i> {{ session('warning') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        @if($quizResult->is_force_submitted)
            <div class="alert alert-danger mb-4" role="alert">
                <div class="d-flex align-items-start">
                    <i class="fas fa-shield-alt mr-2 mt-1"></i>
                    <div>
                        <strong class="d-block">Kuis Dikumpulkan Otomatis (Terdeteksi Berpindah Tab)</strong>
                        <span class="text-sm">Anda telah melanggar toleransi keluar tab sebanyak <strong>{{ $quizResult->tab_switch_count }} kali</strong>. Kuis Anda dikunci dan dikumpulkan otomatis oleh sistem.</span>
                    </div>
                </div>
            </div>
        @elseif($quizResult->tab_switch_count > 0)
            <div class="alert alert-warning mb-4" role="alert">
                <i class="fas fa-info-circle mr-1"></i>
                Catatan Integritas: Terdeteksi <strong>{{ $quizResult->tab_switch_count }} kali</strong> perpindahan tab selama pengerjaan kuis. Catatan ini tersimpan di laporan pemateri.
            </div>
        @endif

        <!-- SCORE SUMMARY -->
        <div class="content-card text-center p-4">
            <span class="eyebrow d-block mb-1">Hasil Evaluasi Kuis</span>
            <h1 class="h4 mb-4">{{ $training->title }}</h1>

            @php
                $hasEssay = ($questions->where('type', 'essay')->count() > 0);
                $mcCount = $questions->where('type', '!=', 'essay')->count();
            @endphp

            <div class="d-inline-block px-5 py-3 border rounded-lg bg-light mb-4">
                <span class="text-xs text-muted d-block mb-1">
                    {{ $hasEssay && $quizResult->essay_status !== 'graded' ? 'Nilai sementara (pilihan ganda)' : 'Nilai akhir' }}
                </span>
                <span class="score-display d-block">{{ $quizResult->score }}</span>
                @if($hasEssay && $quizResult->essay_status !== 'graded')
                    <span class="badge badge-warning mt-2">
                        <i class="fas fa-hourglass-half mr-1"></i> Soal Essay sedang menunggu penilaian pemateri
                    </span>
                @elseif($hasEssay && $quizResult->essay_status === 'graded')
                    <span class="badge badge-success mt-2">
                        <i class="fas fa-check-circle mr-1"></i> Nilai akhir gabungan Pilihan Ganda & Essay
                    </span>
                @endif
            </div>

            <div class="row mx-auto py-3 border rounded-lg text-sm mb-4">
                <div class="col-4 border-right">
                    <span class="text-muted text-xs d-block">Soal Pilihan Ganda</span>
                    <strong>{{ $quizResult->correct_answers }} / {{ $mcCount }} Benar</strong>
                    <span class="text-xs text-muted d-block">Nilai: {{ $quizResult->mc_score ?? $quizResult->score }}</span>
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
                    <strong>{{ $quizResult->total_questions }} Soal</strong>
                    <span class="text-xs text-muted d-block">{{ $mcCount }} PG + {{ $questions->count() - $mcCount }} Essay</span>
                </div>
            </div>

            @if($quizResult->essay_feedback)
                <div class="alert alert-light text-left mb-4 mx-auto" style="max-width: 600px;">
                    <strong><i class="fas fa-comment-dots mr-1"></i> Catatan Evaluasi dari Pemateri:</strong>
                    <p class="mb-0 mt-1">{{ $quizResult->essay_feedback }}</p>
                </div>
            @endif

            <div class="d-flex justify-content-center flex-wrap gap-2">
                <a href="/training/portal/{{ $participant->token }}" class="btn btn-default px-4">
                    Kembali ke Halaman Pelatihan
                </a>
            </div>
        </div>

        <!-- REVIEW PERTANYAAN -->
        @if($quizResult->answers)
        <div class="content-card p-4">
            <div class="content-card-header">Rincian Jawaban & Kunci</div>

            @foreach($questions as $index => $q)
            @php
                $ansData = $quizResult->answers[$q->id] ?? null;
                $userAns = $ansData['user_answer'] ?? null;
                $isEssay = ($q->type === 'essay');
                $isCorrect = $ansData['is_correct'] ?? false;
            @endphp
            <div class="p-3 mb-3 rounded-lg border {{ $isEssay ? '' : ($isCorrect ? 'bg-soft-success' : 'bg-soft-danger') }}" style="color: inherit !important;">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="badge {{ $isEssay ? 'badge-primary' : ($isCorrect ? 'badge-success' : 'badge-danger') }} mr-1">
                            Soal {{ $index + 1 }} {{ $isEssay ? '(Essay)' : '' }}
                        </span>
                        <strong class="d-block mt-2">{{ $q->question }}</strong>
                        @if($q->imageUrl())
                            <img src="{{ $q->imageUrl() }}" alt="Gambar soal" class="quiz-img mt-2">
                        @endif
                    </div>
                    <div>
                        @if($isEssay)
                            <span class="badge badge-secondary">Tersimpan</span>
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
                            <span class="text-muted d-block">Jawaban Anda:</span>
                            <div class="p-2 bg-white rounded border mt-1 text-dark" style="white-space: pre-wrap;">{{ $userAns ?: '(Tidak ada jawaban)' }}</div>
                        </div>
                        @if($q->correct_answer)
                            <div class="mb-1 text-muted">
                                <strong>Pedoman / Acuan Jawaban:</strong> {{ $q->correct_answer }}
                            </div>
                        @endif
                    @else
                        <div class="mb-1">
                            Jawaban Anda: 
                            <strong class="{{ $isCorrect ? 'text-success' : 'text-danger' }}">
                                {{ strtoupper($userAns ?? '-') }}. {{ $q->{'option_' . $userAns} ?? '-' }}
                            </strong>
                            @if($userAns && $q->optionImageUrl($userAns))
                                <img src="{{ $q->optionImageUrl($userAns) }}" alt="Gambar jawaban Anda" class="quiz-img quiz-opt-img mt-1">
                            @endif
                        </div>
                        @if(!$isCorrect && $q->correct_answer)
                        <div class="mb-1 text-success">
                            Kunci Jawaban: <strong>{{ strtoupper($q->correct_answer) }}. {{ $q->{'option_' . $q->correct_answer} ?? '-' }}</strong>
                            @if($q->optionImageUrl($q->correct_answer))
                                <img src="{{ $q->optionImageUrl($q->correct_answer) }}" alt="Gambar kunci jawaban" class="quiz-img quiz-opt-img mt-1">
                            @endif
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
