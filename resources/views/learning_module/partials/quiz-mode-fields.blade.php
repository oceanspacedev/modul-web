{{--
    Pilihan mode pretest/posttest: formal (semua soal satu halaman) atau Quizizz (satu soal per layar, timer per soal).
    Parameter: $modeName, $secondsName (nama field; null = tanpa field detik per soal), $mode, $seconds (nilai saat ini),
    $idPrefix (unik per halaman).
--}}
@php
    $isGame = $mode === \App\Models\LearningModule::MODE_GAME;
@endphp
<div class="quiz-mode-fields" data-prefix="{{ $idPrefix }}">
    <label class="d-block">Mode Kuis</label>
    <div class="row">
        <div class="col-md-6 mb-2 mb-md-0">
            <div class="border rounded p-3 h-100 bg-white">
                <div class="custom-control custom-radio">
                    <input type="radio" id="{{ $idPrefix }}_mode_formal" name="{{ $modeName }}" value="formal" class="custom-control-input quiz-mode-radio" {{ $isGame ? '' : 'checked' }}>
                    <label class="custom-control-label d-block" for="{{ $idPrefix }}_mode_formal">
                        <strong class="d-block">Formal</strong>
                        <span class="d-block text-muted small mt-1">Semua soal dalam satu halaman.</span>
                    </label>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="border rounded p-3 h-100 bg-white">
                <div class="custom-control custom-radio">
                    <input type="radio" id="{{ $idPrefix }}_mode_game" name="{{ $modeName }}" value="game" class="custom-control-input quiz-mode-radio" {{ $isGame ? 'checked' : '' }}>
                    <label class="custom-control-label d-block" for="{{ $idPrefix }}_mode_game">
                        <strong class="d-block">Game (Quizizz)</strong>
                        <span class="d-block text-muted small mt-1">1 soal per layar dengan timer.</span>
                    </label>
                </div>
            </div>
        </div>
    </div>
    @if($secondsName)
    <div class="quiz-seconds mt-3 {{ $isGame ? '' : 'd-none' }}">
        <label for="{{ $idPrefix }}_question_seconds">Detik per soal</label>
        <input type="number" min="{{ \App\Models\LearningModule::MIN_QUESTION_SECONDS }}" max="{{ \App\Models\LearningModule::MAX_QUESTION_SECONDS }}"
            class="form-control" id="{{ $idPrefix }}_question_seconds" name="{{ $secondsName }}" value="{{ $seconds }}" style="max-width: 140px;">
        <small class="form-text text-muted">Waktu habis = soal dihitung salah.</small>
    </div>
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var box = document.querySelector('.quiz-mode-fields[data-prefix="{{ $idPrefix }}"]');
    if (!box) return;
    function refresh() {
        var seconds = box.querySelector('.quiz-seconds');
        if (!seconds) return;
        seconds.classList.toggle('d-none', !box.querySelector('.quiz-mode-radio[value=game]').checked);
    }
    box.querySelectorAll('.quiz-mode-radio').forEach(function (radio) { radio.addEventListener('change', refresh); });
});
</script>
