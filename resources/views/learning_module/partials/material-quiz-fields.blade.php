{{--
    Pengaturan pretest/posttest untuk form Video Materi / Dokumen.
    $quiz: LearningModule milik materi (null bila belum pernah diatur). Nilai disimpan oleh MaterialQuizService.
--}}
@php
    $quizPretest = (bool) old('quiz.pretest', $quiz?->has_pretest ?? false);
    $quizPosttest = (bool) old('quiz.posttest', $quiz?->has_posttest ?? false);
    $quizPretestMax = old('quiz.pretest_max_attempts', $quiz ? $quiz->pretest_max_attempts : 1);
    $quizPosttestMax = old('quiz.posttest_max_attempts', $quiz?->posttest_max_attempts);
    $quizPassing = old('quiz.passing_score', $quiz?->passing_score ?? 70);
@endphp
<div class="border rounded p-3 mb-3 material-quiz-fields" style="background: #f8fafc;">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <label class="font-weight-bold mb-0">Pretest &amp; Posttest</label>
        @if($quiz && ($quiz->has_pretest || $quiz->has_posttest))
            <a href="{{ $quiz->url('questions.index') }}" class="small">Kelola soal ({{ $quiz->questions()->count() }})</a>
        @endif
    </div>
    <p class="text-muted small mb-3">
        Pretest dikerjakan peserta sebelum membuka materi ini, posttest setelah materi selesai ditonton/dibuka.
        Kuis baru aktif setelah soal ditambahkan.
    </p>

    <div class="row">
        <div class="col-md-6 mb-2">
            <input type="hidden" name="quiz[pretest]" value="0">
            <div class="custom-control custom-switch mb-2">
                <input type="checkbox" class="custom-control-input quiz-toggle" id="quiz_pretest" name="quiz[pretest]" value="1" data-target="#quizPretestMax" {{ $quizPretest ? 'checked' : '' }}>
                <label class="custom-control-label" for="quiz_pretest">Aktifkan Pretest</label>
            </div>
            <div id="quizPretestMax" class="{{ $quizPretest ? '' : 'd-none' }}">
                <label class="small text-muted mb-1" for="quiz_pretest_max">Batas percobaan pretest</label>
                <input type="number" min="1" max="255" class="form-control form-control-sm" id="quiz_pretest_max" name="quiz[pretest_max_attempts]" value="{{ $quizPretestMax }}" placeholder="Kosong = tanpa batas">
            </div>
        </div>
        <div class="col-md-6 mb-2">
            <input type="hidden" name="quiz[posttest]" value="0">
            <div class="custom-control custom-switch mb-2">
                <input type="checkbox" class="custom-control-input quiz-toggle" id="quiz_posttest" name="quiz[posttest]" value="1" data-target="#quizPosttestMax" {{ $quizPosttest ? 'checked' : '' }}>
                <label class="custom-control-label" for="quiz_posttest">Aktifkan Posttest</label>
            </div>
            <div id="quizPosttestMax" class="{{ $quizPosttest ? '' : 'd-none' }}">
                <label class="small text-muted mb-1" for="quiz_posttest_max">Batas percobaan posttest</label>
                <input type="number" min="1" max="255" class="form-control form-control-sm" id="quiz_posttest_max" name="quiz[posttest_max_attempts]" value="{{ $quizPosttestMax }}" placeholder="Kosong = tanpa batas">
            </div>
        </div>
    </div>

    <div id="quizPassing" class="{{ $quizPretest || $quizPosttest ? '' : 'd-none' }}">
        <label class="small text-muted mb-1" for="quiz_passing_score">Nilai minimal lulus (KKM)</label>
        <input type="number" min="0" max="100" class="form-control form-control-sm" id="quiz_passing_score" name="quiz[passing_score]" value="{{ $quizPassing }}" style="max-width: 140px;">
    </div>
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
