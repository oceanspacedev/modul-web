@extends('layout.main_template')

@php
    $isPretest = $phase === 'pretest';
    $maxAttempts = $module->maxAttempts($phase);
@endphp

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header :title="($isPretest ? 'Pretest' : 'Posttest').($maxAttempts === 1 ? '' : ' ke-'.$attemptNumber)" :subtitle="$module->title.' · '.$questions->count().' soal'" eyebrow="Modul Saya / Kuis">
            <x-slot:actions>
                <a href="{{ $backUrl }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Kembali</a>
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        @include('learning_module.partials.alerts')

        @if($isPretest)
            @include('learning_module.partials.manager-preview')
        @endif

        <div class="alert alert-light flash">
            <i class="fas fa-info-circle"></i>
            <div class="flex-fill">
                @if($isPretest)
                    Pretest mengukur pemahaman awal Anda. Jawab sebisanya, materi akan terbuka setelah pretest dikirim.
                @else
                    Nilai minimal lulus: <strong>{{ $module->passing_score }}</strong>. Setiap percobaan tetap tersimpan.
                @endif
                @if($maxAttempts === 1)
                    <strong>{{ $isPretest ? 'Pretest' : 'Posttest' }} hanya dapat dikerjakan satu kali.</strong>
                @elseif($maxAttempts !== null)
                    Ini percobaan ke-{{ $attemptNumber }} dari maksimal {{ $maxAttempts }}.
                @endif
            </div>
        </div>

        <form action="{{ $module->url('submit', ['phase' => $phase]) }}" method="POST" id="quizForm">
            @csrf
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">Soal</h3>
                    <div class="card-tools d-flex align-items-center gap-2 small text-muted">
                        <span><span id="answeredCount">0</span>/{{ $questions->count() }} soal terjawab</span>
                        <div class="progress" style="width: 120px;">
                            <div class="progress-bar" id="answeredBar" role="progressbar" style="width: 0%"></div>
                        </div>
                    </div>
                </div>

                @foreach($questions as $index => $question)
                    <div class="card-body {{ $loop->first ? '' : 'border-top' }}">
                        <p class="font-weight-bold mb-3" style="white-space: pre-line;">{{ $index + 1 }}. {{ $question->question }}</p>
                        @if($question->imageUrl())
                            <img src="{{ $question->imageUrl() }}" alt="Gambar soal {{ $index + 1 }}" class="quiz-img mb-3">
                        @endif
                        @foreach($question->options() as $letter => $text)
                            <div class="custom-control custom-radio mb-2">
                                <input type="radio" class="custom-control-input" id="q{{ $question->id }}_{{ $letter }}"
                                    name="answers[{{ $question->id }}]" value="{{ $letter }}"
                                    {{ old('answers.'.$question->id) === $letter ? 'checked' : '' }}>
                                <label class="custom-control-label font-weight-normal" for="q{{ $question->id }}_{{ $letter }}">
                                    {{ strtoupper($letter) }}. {{ $text }}
                                    @if($question->optionImageUrl($letter))
                                        <img src="{{ $question->optionImageUrl($letter) }}" alt="Gambar pilihan {{ strtoupper($letter) }}" class="quiz-img quiz-opt-img mt-1">
                                    @endif
                                </label>
                            </div>
                        @endforeach
                    </div>
                @endforeach

                <div class="card-footer d-flex justify-content-between align-items-center gap-2">
                    <span class="text-muted small">Soal yang tidak dijawab dihitung salah.</span>
                    <button type="submit" class="btn btn-primary" id="btnSubmit">Kirim Jawaban</button>
                </div>
            </div>
        </form>
    </div>
</section>
@endsection

@section('footer')
<script>
$(function () {
    var total = {{ $questions->count() }};
    var $form = $('#quizForm');

    function answered() {
        return $form.find('input[type=radio]:checked').length;
    }

    function updateCount() {
        var count = answered();
        $('#answeredCount').text(count);
        $('#answeredBar').css('width', (total ? Math.round(count / total * 100) : 0) + '%');
    }

    $form.on('change', 'input[type=radio]', updateCount);
    updateCount();

    $form.on('submit', function (e) {
        var missing = total - answered();
        var message = missing > 0
            ? missing + ' soal belum dijawab dan akan dihitung salah. Kirim jawaban sekarang?'
            : 'Kirim jawaban sekarang?';
        if (!confirm(message)) {
            e.preventDefault();
            return;
        }
        $('#btnSubmit').prop('disabled', true).text('Mengirim...');
    });
});
</script>
@endsection
