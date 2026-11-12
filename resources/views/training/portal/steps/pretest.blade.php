{{--
    Kartu langkah Pretest di portal peserta. Urutan dan nomor langkahnya diatur pemateri (Training::portalSteps()); variabel
    lain berasal dari training/portal/index.blade.php.
--}}
@if($training->has_pretest)
<!-- PRETEST -->
<div class="content-card" id="pretestCard">
    <div class="content-card-header">
        <span><span class="badge badge-primary mr-1">Langkah {{ $stepNumber['pretest'] }}</span> Pretest</span>
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
