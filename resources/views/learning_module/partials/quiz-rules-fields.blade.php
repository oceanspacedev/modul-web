{{--
    Aturan kuis yang sama untuk form Video Materi, Dokumen, Modul Belajar, dan Pelatihan: tiap fase
    (pretest/posttest) bisa dinyalakan sendiri, dengan batas percobaan dan hasil yang dilihat peserta. Field dikirim
    sebagai quiz[...].
    $quiz: LearningModule yang sedang diatur (null bila belum ada).
    $trainingRules: Training yang sedang diatur, sebagai ganti $quiz. Pelatihan tidak punya KKM, acak soal, dan
        detik per soal, jadi field itu tidak tampil; sebagai gantinya tiap fase punya batas waktu sejak dibuka pemateri.
    $card = true: tampil sebagai card tersendiri.
    $defaultOn = true: kedua fase nyala untuk data baru (Modul Belajar).
--}}
@php
    use App\Support\QuizRules;

    $quizTraining = $trainingRules ?? null;
    $quiz = $quizTraining ? null : ($quiz ?? null);
    $quizIsNew = ! ($quizTraining ?? $quiz)?->exists;
    $quizHasOld = session()->hasOldInput('quiz');
    $quizValue = fn (string $key, $fresh, $stored) => $quizHasOld ? old('quiz.'.$key) : ($quizIsNew ? $fresh : $stored);
    $quizDefaultOn = $defaultOn ?? false;
    $quizAsCard = $card ?? false;

    // Pelatihan: kuis utamanya posttest, nyala dan sekali kerja secara bawaan.
    $quizPhases = [
        'pretest' => [
            'label' => 'Pretest',
            'hint' => $quizTraining ? 'Sebelum sesi dimulai' : 'Sebelum materi dibuka',
            'on' => (bool) $quizValue('pretest', $quizDefaultOn, $quizTraining?->has_pretest ?? $quiz?->has_pretest),
            'max' => $quizValue('pretest_max_attempts', 1, $quizTraining ? $quizTraining->pretest_max_attempts : $quiz?->pretest_max_attempts),
            // Soalnya sama dengan posttest, jadi pretest baru hanya menampilkan nilai.
            'answers' => (bool) $quizValue('pretest_shows_answers', false, $quizTraining?->pretest_shows_answers ?? $quiz?->pretest_shows_answers),
            'duration' => $quizValue('pretest_duration_minutes', null, $quizTraining?->pretest_duration_minutes),
            'mode' => $quizValue('pretest_mode', 'formal', $quizTraining?->quizMode('pretest')) ?? 'formal',
        ],
        'posttest' => [
            'label' => 'Posttest',
            'hint' => 'Setelah materi selesai',
            'on' => (bool) $quizValue('posttest', $quizTraining ? true : $quizDefaultOn, $quizTraining?->has_posttest ?? $quiz?->has_posttest),
            'max' => $quizValue('posttest_max_attempts', $quizTraining ? 1 : null, $quizTraining ? $quizTraining->quiz_max_attempts : $quiz?->posttest_max_attempts),
            'answers' => (bool) $quizValue('posttest_shows_answers', true, $quizTraining?->show_quiz_answers ?? $quiz?->posttest_shows_answers),
            'duration' => $quizValue('posttest_duration_minutes', null, $quizTraining?->quiz_duration_minutes),
            'mode' => $quizValue('posttest_mode', 'formal', $quizTraining?->quizMode('posttest')) ?? 'formal',
        ],
    ];
    $quizAnyOn = $quizPhases['pretest']['on'] || $quizPhases['posttest']['on'];
    $quizShuffle = (bool) $quizValue('shuffle_questions', true, $quiz?->shuffle_questions);
@endphp
<div class="{{ $quizAsCard ? 'card' : 'border rounded p-3 mb-3 bg-light' }} quiz-rules-fields">
    <div class="{{ $quizAsCard ? 'card-header' : 'd-flex justify-content-between align-items-center mb-1' }}">
        @if($quizAsCard)
            <h3 class="card-title">Aturan Kuis</h3>
        @else
            <h4 class="section-title">Aturan Kuis</h4>
        @endif
        @if(! $quizTraining && $quiz?->isOwned() && ($quiz->has_pretest || $quiz->has_posttest))
            <a href="{{ $quiz->url('questions.index') }}" class="small {{ $quizAsCard ? 'card-tools' : '' }}">Kelola soal ({{ $quiz->questions()->count() }})</a>
        @endif
    </div>
    @if($quizAsCard)<div class="card-body">@endif
    <p class="text-muted small mb-3">
        Nyalakan kuis yang dibutuhkan.
        @if($quizTraining)
            Pretest dan posttest memakai soal yang sama, kecuali soal yang Anda tandai khusus pretest atau khusus posttest di halaman soal.
        @else
            Pretest dan posttest memakai soal yang sama.
        @endif
    </p>

    @foreach($quizPhases as $phase => $rule)
        <div class="quiz-rule {{ $rule['on'] ? 'is-on' : '' }}">
            <div class="quiz-rule-head">
                <input type="hidden" name="quiz[{{ $phase }}]" value="0">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input quiz-toggle" id="quiz_{{ $phase }}" name="quiz[{{ $phase }}]" value="1" @checked($rule['on'])>
                    <label class="custom-control-label" for="quiz_{{ $phase }}">{{ $rule['label'] }}</label>
                </div>
                <span class="quiz-rule-hint">{{ $rule['hint'] }}</span>
            </div>
            <div class="quiz-rule-body">
                <div class="row align-items-end">
                    <div class="col-sm-6 form-group mb-sm-0">
                        <label for="quiz_{{ $phase }}_max">Boleh dikerjakan</label>
                        <select class="form-control" id="quiz_{{ $phase }}_max" name="quiz[{{ $phase }}_max_attempts]">
                            @foreach(QuizRules::attemptOptions($rule['max'] === null ? null : (int) $rule['max']) as $value => $label)
                                <option value="{{ $value }}" @selected((string) $rule['max'] === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 form-group mb-0">
                        <label for="quiz_{{ $phase }}_answers">Setelah selesai, peserta melihat</label>
                        <select class="form-control" id="quiz_{{ $phase }}_answers" name="quiz[{{ $phase }}_shows_answers]">
                            @foreach(QuizRules::answerOptions() as $value => $label)
                                <option value="{{ $value }}" @selected((string) (int) $rule['answers'] === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @if($quizTraining)
                    <div class="form-group mb-0 mt-3">
                        <label for="quiz_{{ $phase }}_mode">Mode kuis</label>
                        <select class="form-control" id="quiz_{{ $phase }}_mode" name="quiz[{{ $phase }}_mode]">
                            <option value="formal" @selected($rule['mode'] !== 'game')>Formal: semua soal dalam satu halaman</option>
                            <option value="game" @selected($rule['mode'] === 'game')>Game: satu soal per layar dengan timer</option>
                        </select>
                    </div>
                    <div class="form-group mb-0 mt-3">
                        <label for="quiz_{{ $phase }}_duration">Batas waktu bawaan sejak dibuka</label>
                        <select class="form-control" id="quiz_{{ $phase }}_duration" name="quiz[{{ $phase }}_duration_minutes]">
                            @foreach(QuizRules::durationOptions(is_numeric($rule['duration']) ? (int) $rule['duration'] : null) as $value => $label)
                                <option value="{{ $value }}" @selected((string) $rule['duration'] === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted mb-0">
                            Isian awal saat Anda menekan Buka {{ $rule['label'] }}: dibuka 09.40 dengan 10 menit berarti tutup otomatis 09.50. Saat membuka, Anda tetap bisa mengisi jam buka dan jam tutup pasti.
                        </small>
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    <p class="text-muted small mb-0 {{ $quizAnyOn ? 'd-none' : '' }}" id="quizNone">Tanpa kuis: peserta hanya {{ $quizTraining ? 'mengikuti sesi dan presensi' : 'mempelajari materi' }}.</p>

    <div id="quizCommon" class="mt-3 {{ $quizAnyOn ? '' : 'd-none' }}">
        @unless($quizTraining)
        <div class="row">
            <div class="col-sm-6 form-group">
                <label for="quiz_passing_score">Nilai minimal lulus (KKM)</label>
                <input type="number" min="0" max="100" class="form-control" id="quiz_passing_score" name="quiz[passing_score]"
                    value="{{ $quizValue('passing_score', 70, $quiz?->passing_score) }}">
            </div>
            <div class="col-sm-6 form-group d-flex align-items-end">
                <input type="hidden" name="quiz[shuffle_questions]" value="0">
                <div class="custom-control custom-switch mb-2">
                    <input type="checkbox" class="custom-control-input" id="quiz_shuffle_questions" name="quiz[shuffle_questions]" value="1" @checked($quizShuffle)>
                    <label class="custom-control-label" for="quiz_shuffle_questions">Acak urutan soal</label>
                </div>
            </div>
        </div>
        @endunless
        {{-- Pelatihan memilih mode per fase di atas, jadi pilihan mode bersama ini hanya untuk modul/video/dokumen. --}}
        @unless($quizTraining)
        @include('learning_module.partials.quiz-mode-fields', [
            'modeName' => 'quiz[mode]',
            'secondsName' => $quizTraining ? null : 'quiz[question_seconds]',
            'mode' => $quizValue('mode', \App\Models\LearningModule::MODE_FORMAL, $quizTraining?->quiz_mode ?? $quiz?->quiz_mode) ?? \App\Models\LearningModule::MODE_FORMAL,
            'seconds' => $quizValue('question_seconds', 30, $quiz?->question_seconds) ?? 30,
            'idPrefix' => 'quiz',
        ])
        @endunless
    </div>
    @if($quizAsCard)</div>@endif
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var toggles = document.querySelectorAll('.quiz-rules-fields .quiz-toggle');
    function refresh() {
        var anyOn = false;
        toggles.forEach(function (toggle) {
            toggle.closest('.quiz-rule').classList.toggle('is-on', toggle.checked);
            anyOn = anyOn || toggle.checked;
        });
        document.getElementById('quizCommon').classList.toggle('d-none', !anyOn);
        document.getElementById('quizNone').classList.toggle('d-none', anyOn);
    }
    toggles.forEach(function (toggle) { toggle.addEventListener('change', refresh); });
});
</script>
