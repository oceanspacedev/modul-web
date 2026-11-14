<section class="training-panel" id="kontrol-sesi" aria-labelledby="training-session-title" data-training-panel @if($activeSection !== 'kontrol-sesi') hidden @endif>
    <header class="training-panel-header">
        <h2 class="training-section-title" id="training-session-title">Kontrol Sesi</h2>
    </header>

    <form action="{{ route('training.status', $training) }}" method="POST" id="formStatusPelatihan" class="d-flex align-items-center flex-wrap gap-2 px-3 pb-3">
        @csrf
        <label for="training-session-status" class="text-sm mb-0">Status pelatihan</label>
        <select name="status" id="training-session-status" data-native-select class="form-control form-control-sm w-auto">
            @foreach(App\Enums\TrainingStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected($training->status === $status)>{{ $status->label() }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-default btn-sm">Simpan status</button>
    </form>

    <div class="list-group list-group-flush border-top">
        @php $pretestQuestionCount = $training->questionsFor('pretest')->count(); @endphp
        @foreach($training->portalStepOrder() as $controlStep)
            @includeIf('training.controls.'.$controlStep)
        @endforeach
    </div>

    <details class="border-top">
        <summary class="px-3 py-3 text-sm font-weight-bold">Urutan peserta dan aturan kuis</summary>
        <div class="list-group list-group-flush">
            <div class="list-group-item d-flex align-items-start justify-content-between gap-2">
                <div class="min-w-0">
                    <div class="text-sm font-weight-bold">Urutan Langkah Peserta</div>
                    <div class="text-muted text-xs">
                        {{ implode(' → ', array_map(fn (string $step) => \App\Models\Training::PORTAL_STEPS[$step], $training->portalSteps())) }}
                    </div>
                </div>
                <button type="button" class="btn btn-default btn-sm text-nowrap" aria-label="Ubah urutan langkah peserta" data-toggle="modal" data-target="#portalStepsModal">Ubah</button>
            </div>

            <div class="list-group-item d-flex align-items-start justify-content-between gap-2">
                <div class="min-w-0">
                    <div class="text-sm font-weight-bold">Aturan Kuis</div>
                    @forelse(array_filter(\App\Models\Training::PHASES, fn ($phase) => $training->hasPhase($phase)) as $phase)
                        <div class="text-muted text-xs">
                            {{ \App\Models\Training::phaseLabel($phase) }}: {{ mb_strtolower(\App\Support\QuizRules::attemptsLabel($training->maxAttempts($phase))) }}
                            &middot; hasil {{ mb_strtolower(\App\Support\QuizRules::answersLabel($training->showsAnswers($phase))) }}
                            &middot; {{ $training->durationMinutes($phase) ? 'terbuka '.$training->durationMinutes($phase).' menit' : 'tanpa batas waktu' }}
                            &middot; mode {{ $training->quizMode($phase) === 'game' ? 'game' : 'formal' }}
                        </div>
                    @empty
                        <div class="text-muted text-xs">Tanpa kuis</div>
                    @endforelse
                    @if($training->has_posttest && $training->allowsQuizRetake() && $training->show_quiz_answers)
                        <div class="text-warning text-xs">Kunci jawaban terlihat, peserta bisa menyontek saat mengulang</div>
                    @endif
                </div>
                <button type="button" class="btn btn-default btn-sm text-nowrap" aria-label="Ubah aturan kuis" data-toggle="modal" data-target="#quizRulesModal">Ubah</button>
            </div>
        </div>
    </details>
</section>
