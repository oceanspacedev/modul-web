{{--
    Kartu langkah Posttest (kuis utama) di portal peserta. Urutan dan nomor langkahnya diatur pemateri (Training::portalSteps()); variabel
    lain berasal dari training/portal/index.blade.php.
--}}
<!-- KUIS EVALUASI (POSTTEST) -->
@if($training->has_posttest)
<div class="content-card" id="quizCard">
    <div class="content-card-header">
        <span><span class="badge badge-primary mr-1">Langkah {{ $stepNumber['posttest'] }}</span> {{ $training->has_pretest ? 'Posttest' : 'Kuis Evaluasi' }}</span>
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
