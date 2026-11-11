{{--
    Panel pretest/posttest di halaman tonton video. Parameter: $quiz (LearningModule aktif), $progress
    (LearningModuleProgress peserta), $canManage (pengelola video: tautan soal & rekap).
    Tombol posttest muncul setelah event "material-completed" dari partial video-player.
--}}
@php
    $attempts = $progress->pretests->merge($progress->posttests)->sortByDesc('submitted_at');
    $limitLabel = fn (?int $remaining) => \App\Support\QuizRules::remainingLabel($remaining);
    $ruleLabel = fn (string $phase) => \App\Support\QuizRules::summary($quiz->maxAttempts($phase), $quiz->showsAnswers($phase));
@endphp

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h3 class="card-title">
            <i class="fas fa-clipboard-check"></i>
            {{ collect([$quiz->has_pretest ? 'Pretest' : null, $quiz->has_posttest ? 'Posttest' : null])->filter()->join(' & ') }}
            <span class="text-muted small font-weight-normal ml-1">KKM {{ $quiz->passing_score }}</span>
        </h3>
        @if($canManage)
            <div class="d-flex gap-1">
                <a href="{{ $quiz->url('questions.index') }}" class="btn btn-default btn-xs">Kelola Soal</a>
                <a href="{{ $quiz->url('results') }}" class="btn btn-default btn-xs">Rekap Nilai</a>
            </div>
        @endif
    </div>
    <div class="card-body">
        @if(session('error'))
            <div class="alert alert-danger flash" role="alert">
                <i class="fas fa-exclamation-circle"></i>
                <div class="flex-fill">{{ session('error') }}</div>
            </div>
        @endif

        @if($quiz->has_pretest)
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <strong>Pretest</strong>
                    @if($progress->pretest)
                        <span class="text-muted small ml-1">nilai awal {{ $progress->pretest->score }} &middot; {{ $limitLabel($progress->remainingAttempts('pretest')) }}</span>
                    @else
                        <span class="text-muted small ml-1">belum dikerjakan &middot; {{ mb_strtolower($ruleLabel('pretest')) }}</span>
                    @endif
                </div>
                @if($progress->canTakePretest())
                    <a href="{{ $quiz->url('quiz', ['phase' => 'pretest']) }}" class="btn {{ $progress->hasDonePretest() ? 'btn-default' : 'btn-primary' }} btn-sm">
                        {{ $progress->hasDonePretest() ? 'Ulangi Pretest' : 'Kerjakan Pretest' }}
                    </a>
                @endif
            </div>
        @endif

        @if($quiz->has_posttest)
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <strong>Posttest</strong>
                    @if($progress->bestPosttest())
                        <span class="text-muted small ml-1">
                            nilai terbaik {{ $progress->bestPosttest()->score }}
                            &middot; {{ $progress->hasPassed() ? 'Lulus' : 'belum mencapai KKM' }}
                        </span>
                    @endif
                    <span class="text-muted small ml-1 {{ $progress->isPosttestUnlocked() ? 'd-none' : '' }}" id="posttestLocked">
                        <i class="fas fa-lock mr-1"></i> terbuka setelah video selesai ditonton
                    </span>
                    <span class="text-muted small ml-1 {{ $progress->isPosttestUnlocked() ? '' : 'd-none' }}" id="posttestRemaining">
                        &middot; {{ $progress->posttests->isEmpty() ? mb_strtolower($ruleLabel('posttest')) : $limitLabel($progress->remainingAttempts('posttest')) }}
                    </span>
                </div>
                @if($progress->remainingAttempts('posttest') !== 0)
                    <a href="{{ $quiz->url('quiz', ['phase' => 'posttest']) }}" class="btn btn-primary btn-sm {{ $progress->isPosttestUnlocked() ? '' : 'd-none' }}" id="btnPosttest">
                        {{ $progress->posttests->isEmpty() ? 'Kerjakan Posttest' : 'Ulangi Posttest' }}
                    </a>
                @endif
            </div>
        @endif

        @if($attempts->isNotEmpty())
            <div class="table-responsive mt-3 border-top pt-2">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr><th>Percobaan</th><th>Waktu</th><th class="text-center">Benar</th><th class="text-center">Nilai</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach($attempts as $attempt)
                            <tr>
                                <td>{{ $attempt->isPretest() ? 'Pretest' : 'Posttest' }} ke-{{ $attempt->attempt_number }}</td>
                                <td>{{ $attempt->submitted_at->format('d M Y H:i') }}</td>
                                <td class="text-center">{{ $attempt->correct_answers }}/{{ $attempt->total_questions }}</td>
                                <td class="text-center font-weight-bold {{ $attempt->is_passed ? 'text-success' : '' }}">{{ $attempt->score }}</td>
                                <td class="text-right"><a href="{{ $quiz->url('attempt', ['attempt' => $attempt]) }}" class="btn btn-default btn-xs">Lihat</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<script>
document.addEventListener('material-completed', function (event) {
    if (!event.detail.posttest_open) return;
    ['btnPosttest', 'posttestRemaining'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.classList.remove('d-none');
    });
    var locked = document.getElementById('posttestLocked');
    if (locked) locked.classList.add('d-none');
});
</script>
