<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluasi Pelatihan - {{ $training->title }}</title>
    @include('layout.partials.portal-head')
</head>
<body>

    <header class="portal-header">
        <div class="container portal-container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h6 mb-0">{{ $training->title }}</h1>
                    <span class="text-muted small">Form Evaluasi Pelatihan</span>
                </div>
                <span class="badge badge-primary">{{ $questionCount }} Pertanyaan</span>
            </div>
        </div>
    </header>

    <main class="container portal-container mb-5">
        @if(session('warning'))
            <div class="alert alert-warning mb-4" role="alert">{{ session('warning') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger mb-4" role="alert">
                <strong><i class="fas fa-exclamation-circle mr-1"></i> Perhatian:</strong>
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Data yang di form kertas/Tally diketik peserta; di sini diambil dari akun peserta dan jadwal pelatihan. --}}
        @php
            $identity = array_filter([
                'Nama' => $user->nama_lengkap,
                'Jabatan' => $user->jabatan?->name,
                'Posisi' => $user->position?->name,
                'Divisi' => $user->divisi?->name,
                'Area' => $user->area?->name,
                'Tanggal Pelatihan' => $training->training_date->format('d F Y'),
                'Materi' => $training->title,
                'Pemateri' => $training->trainer?->nama_lengkap,
            ]);
        @endphp
        @if($training->evaluationClosesAt())
            <div class="alert alert-warning py-2 px-3 small mb-3">
                <i class="far fa-clock mr-1"></i> Form ditutup otomatis pukul <strong>{{ $training->evaluationClosesAt()->format('H:i') }}</strong> WIB. Kirim sebelum waktunya habis.
            </div>
        @endif

        <div class="content-card">
            <div class="content-card-header">
                <span>Data Anda</span>
                <span class="text-muted small font-weight-normal">Terisi otomatis</span>
            </div>
            <dl class="row mb-0 small">
                @foreach($identity as $label => $value)
                    <dt class="col-5 col-sm-4 text-muted font-weight-normal">{{ $label }}</dt>
                    <dd class="col-7 col-sm-8 mb-1 font-weight-bold">{{ $value }}</dd>
                @endforeach
            </dl>
        </div>

        <form action="/training/portal/{{ $participant->token }}/evaluation" method="POST" id="formEvaluation">
            @csrf

            @php $number = 0; @endphp
            @foreach($sections as $section)
                <div class="content-card">
                    @if(filled($section['title']))
                        <div class="content-card-header">
                            <span>{{ $section['title'] }}</span>
                            @if(collect($section['questions'])->contains(fn ($q) => $q->isScale()))
                                <span class="text-muted small font-weight-normal">1 = terendah, 5 = tertinggi</span>
                            @endif
                        </div>
                    @endif

                    @foreach($section['questions'] as $question)
                        @php
                            $number++;
                            $field = 'answers['.$question->id.']';
                            // Kiriman yang tidak wajar (mis. array) tidak ditampilkan kembali.
                            $old = old('answers.'.$question->id);
                            $old = is_scalar($old) ? (string) $old : '';
                        @endphp
                        <fieldset class="rating-question">
                            <legend>
                                {{ $number }}. {{ $question->question }}
                                @if($question->is_required)
                                    <span class="text-danger">*</span>
                                @else
                                    <span class="text-muted font-weight-normal">(opsional)</span>
                                @endif
                            </legend>

                            @if($question->isScale())
                                <div class="rating-scale">
                                    @foreach(\App\Support\TrainingEvaluationForm::scale() as $value)
                                        <label class="rating-option">
                                            <input type="radio" name="{{ $field }}" value="{{ $value }}" @required($question->is_required)
                                                @checked($old === (string) $value)>
                                            <span>{{ $value }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @if(filled($question->low_label) || filled($question->high_label))
                                    <div class="rating-scale-labels">
                                        <span>{{ $question->low_label }}</span>
                                        <span>{{ $question->high_label }}</span>
                                    </div>
                                @endif
                            @elseif($question->type === \App\Models\TrainingEvaluationQuestion::TYPE_CHOICE && $question->hasLongOptionList())
                                {{-- Daftar panjang (mis. nama toko) dipilih lewat dropdown supaya form tidak memanjang. --}}
                                <select name="{{ $field }}" class="form-control" aria-label="{{ $question->question }}" @required($question->is_required)>
                                    <option value="">Pilih jawaban...</option>
                                    @foreach($question->options as $option)
                                        <option value="{{ $option }}" @selected($old === (string) $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                            @elseif($question->type === \App\Models\TrainingEvaluationQuestion::TYPE_CHOICE)
                                @foreach($question->options ?? [] as $option)
                                    <label class="option-label">
                                        <input type="radio" name="{{ $field }}" value="{{ $option }}" @required($question->is_required)
                                            @checked($old === (string) $option)>
                                        <div>{{ $option }}</div>
                                    </label>
                                @endforeach
                            @else
                                <textarea name="{{ $field }}" class="form-control" rows="3" @required($question->is_required)
                                    maxlength="{{ \App\Support\TrainingEvaluationForm::TEXT_MAX }}"
                                    aria-label="{{ $question->question }}" placeholder="Tuliskan jawaban Anda...">{{ $old }}</textarea>
                            @endif
                        </fieldset>
                    @endforeach
                </div>
            @endforeach

            <div class="content-card text-center mb-5">
                <p class="text-muted small mb-3">
                    {{ $continuesToQuiz ? 'Setelah dikirim, Anda langsung masuk ke kuis.' : 'Evaluasi hanya bisa dikirim satu kali.' }}
                </p>
                <div class="d-flex justify-content-center gap-2">
                    <a href="/training/portal/{{ $participant->token }}" class="btn btn-default px-4">Kembali</a>
                    <button type="submit" class="btn btn-primary px-4" id="btnSubmitEvaluation">
                        {{ $continuesToQuiz ? 'Kirim & Lanjut ke Kuis' : 'Kirim Evaluasi' }}
                    </button>
                </div>
            </div>
        </form>
    </main>

    <script>
        // Cegah kirim ganda saat tombol ditekan dua kali.
        document.getElementById('formEvaluation').addEventListener('submit', function () {
            var button = document.getElementById('btnSubmitEvaluation');
            button.disabled = true;
            button.textContent = 'Mengirim...';
        });
    </script>
    @include('training.partials.portal-submit-guard')
</body>
</html>
