@php $scaleMax = \App\Support\TrainingEvaluationForm::SCALE_MAX; @endphp

<section class="training-panel" id="hasil-evaluasi" aria-labelledby="training-evaluation-title" data-training-panel @if($activeSection !== 'hasil-evaluasi') hidden @endif>
    <header class="training-panel-header">
        <div>
            <h2 class="training-section-title" id="training-evaluation-title">Hasil Evaluasi Peserta</h2>
            <p class="training-section-meta">
                {{ $evaluation['count'] }} dari {{ $stats['total'] }} mengisi
                @unless($training->has_evaluation)
                    · Form nonaktif
                @endunless
            </p>
        </div>
        <div class="training-panel-actions">
            <a href="{{ route('training.evaluation.index', $training) }}" class="btn btn-sm btn-default">Kelola Pertanyaan</a>
        </div>
    </header>

    <div class="training-panel-body">
        @if($evaluation['questionCount'] === 0)
            <p class="text-muted mb-0">Form evaluasi belum punya pertanyaan.</p>
        @elseif($evaluation['count'] === 0)
            <p class="text-muted mb-0">Belum ada peserta yang mengisi form evaluasi ({{ $evaluation['questionCount'] }} pertanyaan).</p>
        @else
            @if($evaluation['average'] !== null)
                <p class="mb-3"><strong class="score-value">{{ number_format($evaluation['average'], 2) }}</strong> <span class="text-muted">rata-rata dari skala {{ $scaleMax }}</span></p>
            @endif

            <ul class="list-unstyled mb-0">
                @foreach($evaluation['questions'] as $item)
                    @php $question = $item['question']; @endphp
                    <li class="py-3 border-top">
                        @if($question->isScale())
                            <div class="d-flex justify-content-between align-items-baseline gap-2 text-sm">
                                <span>{{ $question->question }}</span>
                                <strong class="text-nowrap">{{ $item['average'] === null ? '-' : number_format($item['average'], 2) }}</strong>
                            </div>
                            <div class="rating-bar mt-1" aria-hidden="true"><span style="width: {{ round(($item['average'] ?? 0) / $scaleMax * 100) }}%"></span></div>
                        @else
                            <details>
                                <summary class="text-sm">
                                    {{ $question->question }} <span class="text-muted">({{ $item['answered'] }} jawaban)</span>
                                </summary>
                                <div class="mt-2">
                                    @if($question->type === \App\Models\TrainingEvaluationQuestion::TYPE_CHOICE)
                                        @php
                                            $options = $question->hasLongOptionList() ? array_filter($item['options'], fn ($option) => $option['count'] > 0) : $item['options'];
                                            $unchosen = count($item['options']) - count($options);
                                        @endphp
                                        @foreach($options as $option)
                                            @php $share = $item['answered'] > 0 ? round($option['count'] / $item['answered'] * 100) : 0; @endphp
                                            <div class="d-flex justify-content-between align-items-baseline gap-2 text-sm text-muted">
                                                <span>{{ $option['label'] }}{{ $option['removed'] ? ' (opsi lama)' : '' }}</span>
                                                <span class="text-nowrap">{{ $option['count'] }} · {{ $share }}%</span>
                                            </div>
                                            <div class="rating-bar mb-2" aria-hidden="true"><span style="width: {{ $share }}%"></span></div>
                                        @endforeach
                                        @if($unchosen > 0)
                                            <p class="text-sm text-muted mb-0">{{ $unchosen }} pilihan lain belum dipilih.</p>
                                        @endif
                                    @else
                                        @if($item['answered'] > count($item['entries']))
                                            <p class="text-sm text-muted mb-2">{{ count($item['entries']) }} jawaban terbaru. Unduh rekap untuk melihat semua jawaban.</p>
                                        @endif
                                        @forelse($item['entries'] as $entry)
                                            <div class="text-sm border-left pl-2 mb-2">
                                                <strong>{{ $entry['name'] }}</strong>
                                                <span class="text-muted">· {{ $entry['at']->format('d M Y H:i') }}</span>
                                                <div class="text-break" style="white-space: pre-line;">{{ $entry['text'] }}</div>
                                            </div>
                                        @empty
                                            <p class="text-muted text-sm mb-0">Belum ada jawaban.</p>
                                        @endforelse
                                    @endif
                                </div>
                            </details>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
