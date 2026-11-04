@extends('layout.main_template')

@php
    $isPretest = $phase === 'pretest';
    $maxAttempts = $module->maxAttempts($phase);
@endphp

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <div class="mb-3 d-flex align-items-center">
            <a href="{{ $module->participantUrl() }}" class="btn btn-default btn-sm mr-3">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="m-0 text-dark font-weight-bold" style="font-size: 1.5rem;">
                    {{ $isPretest ? 'Pretest' : 'Posttest' }}{{ $maxAttempts === 1 ? '' : ' ke-'.$attemptNumber }}
                </h1>
                <span class="text-muted small">{{ $module->title }} &middot; {{ $questions->count() }} soal</span>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid" style="max-width: 900px;">
        @include('learning_module.partials.alerts')

        @if($isPretest)
            @include('learning_module.partials.manager-preview')
        @endif

        <div class="alert {{ $isPretest ? 'alert-info' : 'alert-light border' }}">
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

        <form action="{{ route('learning.submit', [$module, $phase]) }}" method="POST" id="quizForm">
            @csrf
            @foreach($questions as $index => $question)
                <div class="card mb-3">
                    <div class="card-body">
                        <p class="font-weight-600 text-dark mb-3" style="white-space: pre-line;">{{ $index + 1 }}. {{ $question->question }}</p>
                        @foreach($question->options() as $letter => $text)
                            <div class="custom-control custom-radio mb-2">
                                <input type="radio" class="custom-control-input" id="q{{ $question->id }}_{{ $letter }}"
                                    name="answers[{{ $question->id }}]" value="{{ $letter }}"
                                    {{ old('answers.'.$question->id) === $letter ? 'checked' : '' }}>
                                <label class="custom-control-label font-weight-normal" for="q{{ $question->id }}_{{ $letter }}">
                                    {{ strtoupper($letter) }}. {{ $text }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="d-flex justify-content-between align-items-center mb-5">
                <span class="text-muted small"><span id="answeredCount">0</span>/{{ $questions->count() }} soal terjawab</span>
                <button type="submit" class="btn btn-primary" id="btnSubmit">Kirim Jawaban</button>
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

    $form.on('change', 'input[type=radio]', function () {
        $('#answeredCount').text(answered());
    });
    $('#answeredCount').text(answered());

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
