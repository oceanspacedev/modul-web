@extends('layout.main_template')

@php
    $questions = $template['questions'];
    $required = collect($questions)->where('required', true)->pluck('label')->implode(', ');
    $action = $draft ? route('document-builder.recompose', $draft) : route('document-builder.store', $category);
@endphp

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header :title="($draft ? 'Susun Ulang · ' : '').$category->name" :subtitle="$draft ? 'Ubah jawaban, AI menyusun ulang isi dokumen' : 'Jawab singkat, AI yang menyusun dokumennya'"
            :breadcrumbs="$draft
                ? ['Dokumen Builder' => route('document-builder.index'), $draft->title => route('document-builder.show', $draft), 'Susun Ulang' => null]
                : ['Dokumen Builder' => route('document-builder.index'), 'Buat' => route('document-builder.create'), $category->code => null]" />
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />

        @if ($source?->documentRequest)
            @php($origin = $source->documentRequest)
            <div class="alert alert-light border d-flex align-items-start gap-2">
                <i class="fas fa-inbox text-info mt-1 flex-shrink-0"></i>
                <div class="small min-w-0 text-break">
                    <strong>Dari permintaan <span class="badge badge-info">{{ $origin->code }}</span></strong>
                    · {{ $origin->nama }} ({{ $origin->jabatan }}, {{ $origin->departemen }}){{ $source->prioritas ? ' · Prioritas '.$source->prioritas : '' }}
                    <div class="text-muted">{{ $draft ? 'Data permintaan ikut dikirim ke AI.' : 'Jawaban terisi dari permintaan. Cek dan lengkapi dulu, data permintaan juga ikut dikirim ke AI.' }}</div>
                </div>
            </div>
        @endif

        <form action="{{ $action }}" method="POST" id="builderForm">
            @csrf
            @if ($source && ! $draft)
                <input type="hidden" name="permintaan" value="{{ $source->id }}">
            @endif
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-body pb-2">
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="font-weight-bold">Progress</span>
                                <span><span data-progress-count>0</span> / {{ count($questions) }}</span>
                            </div>
                            <div class="progress progress-xs mb-0">
                                <div class="progress-bar bg-primary" data-progress-bar style="width: 0%"></div>
                            </div>
                        </div>
                    </div>

                    @foreach ($questions as $i => $question)
                        @php($name = 'answers['.$question['key'].']')
                        @php($value = old('answers.'.$question['key'], $answers[$question['key']] ?? ''))
                        <div class="card builder-step mb-2" data-step data-required="{{ $question['required'] ? 1 : 0 }}">
                            <button type="button" class="builder-step-head" data-step-toggle aria-expanded="true">
                                <span class="builder-step-num" data-step-num>{{ $i + 1 }}</span>
                                <span class="flex-fill min-w-0 text-left">
                                    <span class="d-block font-weight-bold">{{ $question['label'] }} @if ($question['required'])<span class="text-danger">*</span>@endif</span>
                                    <span class="d-block small text-muted">{{ $question['hint'] }}</span>
                                </span>
                                <i class="fas fa-chevron-down text-muted" data-step-chevron></i>
                            </button>
                            <div class="builder-step-body" data-step-body>
                                @if ($question['type'] === 'input')
                                    <input type="text" class="form-control @error('answers.'.$question['key']) is-invalid @enderror" name="{{ $name }}" id="q_{{ $question['key'] }}"
                                        value="{{ $value }}" placeholder="{{ $question['placeholder'] }}" maxlength="{{ $question['key'] === 'title' ? 250 : ($question['key'] === 'department' ? 150 : 2000) }}" data-step-input>
                                @else
                                    <textarea class="form-control @error('answers.'.$question['key']) is-invalid @enderror" name="{{ $name }}" id="q_{{ $question['key'] }}" rows="3"
                                        placeholder="{{ $question['placeholder'] }}" maxlength="2000" data-step-input>{{ $value }}</textarea>
                                @endif
                                @error('answers.'.$question['key'])<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                @unless ($loop->last)
                                    <div class="text-right mt-2"><button type="button" class="btn btn-sm btn-outline-primary" data-step-next>Lanjut</button></div>
                                @endunless
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="col-lg-4">
                    <div class="builder-sticky sticky-below-topbar">
                        <div class="card">
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="company" class="small font-weight-bold mb-1">Badan Usaha</label>
                                    <input type="text" class="form-control form-control-sm" id="company" name="company" maxlength="150" value="{{ old('company', $company) }}" placeholder="Nama perusahaan di kop dokumen">
                                </div>

                                @if ($aiReady)
                                    <button type="submit" name="mode" value="ai" class="btn btn-primary btn-block" data-submit-ai
                                        @if ($draft && $draft->hasContent()) onclick="return confirm('Isi dokumen yang sekarang akan diganti hasil AI. Lanjutkan?')" @endif>
                                        <i class="fas fa-magic"></i> {{ $draft ? 'Susun Ulang dengan AI' : 'Susun dengan AI' }}
                                    </button>
                                @else
                                    <div class="alert alert-light border small mb-2">AI belum aktif. Dokumen diisi manual.</div>
                                @endif
                                <button type="submit" name="mode" value="manual" class="btn btn-default btn-block">
                                    {{ $draft ? 'Simpan Jawaban Saja' : 'Isi Manual' }}
                                </button>
                                <p class="small text-muted mt-2 mb-0">Wajib: {{ $required }}</p>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header"><h3 class="card-title"><i class="far fa-lightbulb text-warning"></i> Tips</h3></div>
                            <div class="card-body small">
                                <ul class="pl-3 mb-0">
                                    @foreach ($template['tips'] as $tip)
                                        <li>{{ $tip }}</li>
                                    @endforeach
                                    <li>Makin detail jawaban, makin spesifik hasilnya</li>
                                </ul>
                                @can('manage-od-report')
                                    <a href="{{ route('document-category.builder-steps.edit', $category) }}" class="d-inline-block mt-2"><i class="fas fa-sliders-h"></i> Atur langkah</a>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>

<style>
    .builder-step { overflow: hidden; }
    .builder-step-head { display: flex; align-items: center; gap: .75rem; width: 100%; padding: .75rem 1rem; background: none; border: 0; color: var(--gray-950); }
    .builder-step-head:focus-visible { outline: none; box-shadow: inset 0 0 0 2px var(--primary-600); }
    .builder-step-num { flex: 0 0 28px; height: 28px; border-radius: 50%; border: 2px solid var(--gray-300); display: inline-flex; align-items: center; justify-content: center; font-size: .8rem; font-weight: 600; color: var(--gray-500); }
    .builder-step.is-done .builder-step-num { background: var(--success-solid); border-color: var(--success-solid); color: var(--on-solid); }
    .builder-step.is-open { border-color: var(--primary-600) !important; box-shadow: var(--focus-ring) !important; }
    .builder-step-body { padding: 0 1rem 1rem 3.4rem; }
    .builder-step.is-collapsed .builder-step-body { display: none; }
    .builder-step.is-collapsed [data-step-chevron] { transform: rotate(-90deg); }
    @media (max-width: 575.98px) { .builder-step-body { padding: 0 1rem 1rem; } }
    @media (min-width: 992px) { .builder-sticky { position: sticky; top: calc(var(--topbar-height, 57px) + 1rem); } }
</style>
@endsection

@section('footer')
<script>
    (function () {
        const steps = [...document.querySelectorAll('[data-step]')];
        const aiButton = document.querySelector('[data-submit-ai]');

        const input = step => step.querySelector('[data-step-input]');
        const filled = step => input(step).value.trim() !== '';
        function warn(step) {
            const el = input(step);
            el.setCustomValidity('Wajib diisi');
            el.reportValidity();
            el.addEventListener('input', () => el.setCustomValidity(''), { once: true });
        }

        function open(step) {
            steps.forEach(s => {
                const isTarget = s === step;
                s.classList.toggle('is-collapsed', !isTarget);
                s.classList.toggle('is-open', isTarget);
                s.querySelector('[data-step-toggle]').setAttribute('aria-expanded', isTarget ? 'true' : 'false');
            });
            if (step) input(step).focus({ preventScroll: true });
        }

        function refresh() {
            let done = 0;
            steps.forEach((step, i) => {
                const ok = filled(step);
                step.classList.toggle('is-done', ok);
                step.querySelector('[data-step-num]').innerHTML = ok ? '<i class="fas fa-check"></i>' : String(i + 1);
                if (ok) done++;
            });
            document.querySelector('[data-progress-count]').textContent = done;
            document.querySelector('[data-progress-bar]').style.width = (done / steps.length * 100) + '%';
            if (aiButton) aiButton.disabled = steps.some(s => s.dataset.required === '1' && !filled(s));
        }

        steps.forEach((step, i) => {
            step.querySelector('[data-step-toggle]').addEventListener('click', () => {
                step.classList.contains('is-collapsed') ? open(step) : step.classList.add('is-collapsed');
            });
            input(step).addEventListener('input', refresh);
            const next = step.querySelector('[data-step-next]');
            if (next) next.addEventListener('click', () => {
                if (step.dataset.required === '1' && !filled(step)) {
                    warn(step);
                    return;
                }
                open(steps[i + 1]);
                steps[i + 1].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
            // Enter di kolom satu baris = Lanjut, bukan kirim form.
            if (input(step).tagName === 'INPUT') input(step).addEventListener('keydown', e => {
                if (e.key === 'Enter') { e.preventDefault(); if (next) next.click(); }
            });
        });

        // Buka langkah yang error, atau langkah wajib pertama yang belum terisi, atau langkah pertama.
        open(steps.find(s => s.querySelector('.is-invalid')) || steps.find(s => !filled(s)) || steps[0]);
        refresh();

        document.getElementById('builderForm').addEventListener('submit', e => {
            // AI butuh semua jawaban wajib; isi manual cukup judul (langkah pertama).
            const needed = e.submitter && e.submitter.value === 'ai' ? steps.filter(s => s.dataset.required === '1') : [steps[0]];
            const missing = needed.find(s => !filled(s));
            if (missing) { e.preventDefault(); open(missing); warn(missing); return; }
            const button = e.submitter;
            if (button) setTimeout(() => { button.disabled = true; }, 0);
        });
    })();
</script>
@endsection
