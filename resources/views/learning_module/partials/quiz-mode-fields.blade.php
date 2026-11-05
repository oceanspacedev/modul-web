{{--
    Pilihan mode pretest/posttest: formal (semua soal satu halaman) atau Quizizz (satu soal per layar, timer per soal).
    Parameter: $modeName, $secondsName (nama field), $mode, $seconds (nilai saat ini), $idPrefix (unik per halaman).
--}}
@php
    $isGame = $mode === \App\Models\LearningModule::MODE_GAME;
@endphp
<div class="quiz-mode-fields" data-prefix="{{ $idPrefix }}">
    <label class="small text-muted mb-1 d-block">Mode kuis</label>
    <div class="row">
        <div class="col-md-6 mb-2">
            <div class="custom-control custom-radio p-2 pl-4 border rounded h-100 bg-white">
                <input type="radio" id="{{ $idPrefix }}_mode_formal" name="{{ $modeName }}" value="formal" class="custom-control-input quiz-mode-radio" {{ $isGame ? '' : 'checked' }}>
                <label class="custom-control-label font-weight-bold d-block" for="{{ $idPrefix }}_mode_formal" style="cursor: pointer;">
                    <i class="fas fa-file-alt text-muted mr-1"></i> Mode Formal (Ujian)
                    <span class="d-block text-muted small font-weight-normal mt-1">Semua soal dalam satu halaman.</span>
                </label>
            </div>
        </div>
        <div class="col-md-6 mb-2">
            <div class="custom-control custom-radio p-2 pl-4 border rounded h-100 bg-white">
                <input type="radio" id="{{ $idPrefix }}_mode_game" name="{{ $modeName }}" value="game" class="custom-control-input quiz-mode-radio" {{ $isGame ? 'checked' : '' }}>
                <label class="custom-control-label font-weight-bold d-block" for="{{ $idPrefix }}_mode_game" style="cursor: pointer;">
                    <i class="fas fa-gamepad text-muted mr-1"></i> Mode Quizizz (Game)
                    <span class="d-block text-muted small font-weight-normal mt-1">1 soal per layar dengan timer.</span>
                </label>
            </div>
        </div>
    </div>
    <div class="quiz-seconds {{ $isGame ? '' : 'd-none' }}">
        <label class="small text-muted mb-1" for="{{ $idPrefix }}_question_seconds">Detik per soal</label>
        <input type="number" min="{{ \App\Models\LearningModule::MIN_QUESTION_SECONDS }}" max="{{ \App\Models\LearningModule::MAX_QUESTION_SECONDS }}"
            class="form-control form-control-sm" id="{{ $idPrefix }}_question_seconds" name="{{ $secondsName }}" value="{{ $seconds }}" style="max-width: 140px;">
        <small class="text-muted">Waktu habis = soal dihitung salah.</small>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var box = document.querySelector('.quiz-mode-fields[data-prefix="{{ $idPrefix }}"]');
    if (!box) return;
    function refresh() {
        var game = box.querySelector('.quiz-mode-radio[value=game]').checked;
        box.querySelector('.quiz-seconds').classList.toggle('d-none', !game);
    }
    box.querySelectorAll('.quiz-mode-radio').forEach(function (radio) { radio.addEventListener('change', refresh); });
});
</script>
