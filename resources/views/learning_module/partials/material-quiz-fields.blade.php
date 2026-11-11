{{--
    Pengaturan pretest/posttest untuk form Video Materi / Dokumen.
    $card = true: tampil sebagai card tersendiri (form dua kolom).
    $quiz: LearningModule milik materi (null bila belum pernah diatur). Nilai disimpan oleh MaterialQuizService.
--}}
@php
    $quizPretest = (bool) old('quiz.pretest', $quiz?->has_pretest ?? false);
    $quizPosttest = (bool) old('quiz.posttest', $quiz?->has_posttest ?? false);
    $quizPretestMax = old('quiz.pretest_max_attempts', $quiz ? $quiz->pretest_max_attempts : 1);
    $quizPosttestMax = old('quiz.posttest_max_attempts', $quiz?->posttest_max_attempts);
    $quizPassing = old('quiz.passing_score', $quiz?->passing_score ?? 70);
    $quizPretestAnswers = (bool) old('quiz.pretest_shows_answers', $quiz?->pretest_shows_answers ?? true);
    $quizPosttestAnswers = (bool) old('quiz.posttest_shows_answers', $quiz?->posttest_shows_answers ?? true);
    $quizAsCard = $card ?? false;
@endphp
<div class="{{ $quizAsCard ? 'card' : 'border rounded p-3 mb-3 bg-light' }} material-quiz-fields">
    <div class="{{ $quizAsCard ? 'card-header' : 'd-flex justify-content-between align-items-center mb-1' }}">
        @if($quizAsCard)
            <h3 class="card-title">Pretest &amp; Posttest</h3>
        @else
            <h4 class="section-title">Pretest &amp; Posttest</h4>
        @endif
        @if($quiz && ($quiz->has_pretest || $quiz->has_posttest))
            <a href="{{ $quiz->url('questions.index') }}" class="small {{ $quizAsCard ? 'card-tools' : '' }}">Kelola soal ({{ $quiz->questions()->count() }})</a>
        @endif
    </div>
    @if($quizAsCard)<div class="card-body">@endif
    <p class="text-muted small mb-3">
        Kuis sebelum dan sesudah materi. Setelah disimpan, Anda diarahkan ke halaman soal; kuis baru aktif untuk peserta setelah ada minimal 1 soal.
    </p>

    <div class="row">
        <div class="col-md-6 mb-2">
            <input type="hidden" name="quiz[pretest]" value="0">
            <div class="custom-control custom-switch mb-2">
                <input type="checkbox" class="custom-control-input quiz-toggle" id="quiz_pretest" name="quiz[pretest]" value="1" data-target="#quizPretestMax" {{ $quizPretest ? 'checked' : '' }}>
                <label class="custom-control-label" for="quiz_pretest">Aktifkan Pretest</label>
            </div>
            <div id="quizPretestMax" class="{{ $quizPretest ? '' : 'd-none' }}">
                <label for="quiz_pretest_max">Batas percobaan</label>
                <input type="number" min="1" max="255" class="form-control" id="quiz_pretest_max" name="quiz[pretest_max_attempts]" value="{{ $quizPretestMax }}" placeholder="Kosong = tanpa batas">
                <input type="hidden" name="quiz[pretest_shows_answers]" value="0">
                <div class="custom-control custom-switch mt-2">
                    <input type="checkbox" class="custom-control-input" id="quiz_pretest_shows_answers" name="quiz[pretest_shows_answers]" value="1" {{ $quizPretestAnswers ? 'checked' : '' }}>
                    <label class="custom-control-label" for="quiz_pretest_shows_answers">Tampilkan jawaban benar/salah setelah selesai</label>
                </div>
                <small class="form-text text-muted">Mati = peserta hanya melihat nilai.</small>
            </div>
        </div>
        <div class="col-md-6 mb-2">
            <input type="hidden" name="quiz[posttest]" value="0">
            <div class="custom-control custom-switch mb-2">
                <input type="checkbox" class="custom-control-input quiz-toggle" id="quiz_posttest" name="quiz[posttest]" value="1" data-target="#quizPosttestMax" {{ $quizPosttest ? 'checked' : '' }}>
                <label class="custom-control-label" for="quiz_posttest">Aktifkan Posttest</label>
            </div>
            <div id="quizPosttestMax" class="{{ $quizPosttest ? '' : 'd-none' }}">
                <label for="quiz_posttest_max">Batas percobaan</label>
                <input type="number" min="1" max="255" class="form-control" id="quiz_posttest_max" name="quiz[posttest_max_attempts]" value="{{ $quizPosttestMax }}" placeholder="Kosong = tanpa batas">
                <input type="hidden" name="quiz[posttest_shows_answers]" value="0">
                <div class="custom-control custom-switch mt-2">
                    <input type="checkbox" class="custom-control-input" id="quiz_posttest_shows_answers" name="quiz[posttest_shows_answers]" value="1" {{ $quizPosttestAnswers ? 'checked' : '' }}>
                    <label class="custom-control-label" for="quiz_posttest_shows_answers">Tampilkan jawaban benar/salah setelah selesai</label>
                </div>
                <small class="form-text text-muted">Mati = peserta hanya melihat nilai.</small>
            </div>
        </div>
    </div>

    <div id="quizPassing" class="{{ $quizPretest || $quizPosttest ? '' : 'd-none' }}">
        <label for="quiz_passing_score">Nilai minimal lulus (KKM)</label>
        <input type="number" min="0" max="100" class="form-control mb-3" id="quiz_passing_score" name="quiz[passing_score]" value="{{ $quizPassing }}" style="max-width: 140px;">
        @include('learning_module.partials.quiz-mode-fields', [
            'modeName' => 'quiz[mode]',
            'secondsName' => 'quiz[question_seconds]',
            'mode' => old('quiz.mode', $quiz?->quiz_mode ?? \App\Models\LearningModule::MODE_FORMAL),
            'seconds' => old('quiz.question_seconds', $quiz?->question_seconds ?? 30),
            'idPrefix' => 'quiz',
        ])
    </div>
    @if($quizAsCard)</div>@endif
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var toggles = document.querySelectorAll('.material-quiz-fields .quiz-toggle');
    function refresh() {
        var anyOn = false;
        toggles.forEach(function (toggle) {
            document.querySelector(toggle.dataset.target).classList.toggle('d-none', !toggle.checked);
            anyOn = anyOn || toggle.checked;
        });
        document.getElementById('quizPassing').classList.toggle('d-none', !anyOn);
    }
    toggles.forEach(function (toggle) { toggle.addEventListener('change', refresh); });
});
</script>
