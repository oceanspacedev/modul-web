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
                <span class="badge badge-primary">{{ collect($sections)->flatten(1)->count() }} Penilaian</span>
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
            @foreach($sections as $sectionTitle => $questions)
                <div class="content-card">
                    <div class="content-card-header">
                        <span>{{ $sectionTitle }}</span>
                        <span class="text-muted small font-weight-normal">1 = terendah, 5 = tertinggi</span>
                    </div>

                    @foreach($questions as $key => $question)
                        @php $number++; @endphp
                        <fieldset class="rating-question">
                            <legend>{{ $number }}. {{ $question['text'] }} <span class="text-danger">*</span></legend>
                            <div class="rating-scale">
                                @foreach(\App\Support\TrainingEvaluationForm::scale() as $value)
                                    <label class="rating-option" title="{{ $loop->first ? $question['low'] : ($loop->last ? $question['high'] : $value) }}">
                                        <input type="radio" name="ratings[{{ $key }}]" value="{{ $value }}" required
                                            @checked((string) old('ratings.'.$key) === (string) $value)>
                                        <span>{{ $value }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <div class="rating-scale-labels">
                                <span>{{ $question['low'] }}</span>
                                <span>{{ $question['high'] }}</span>
                            </div>
                        </fieldset>
                    @endforeach
                </div>
            @endforeach

            <div class="content-card">
                <label for="evaluationComment" class="font-weight-bold d-block mb-2">
                    Ada tanggapan atau masukan untuk keseluruhan sesi ini? <span class="text-muted font-weight-normal">(opsional)</span>
                </label>
                <textarea name="comment" id="evaluationComment" class="form-control" rows="4"
                    maxlength="{{ \App\Support\TrainingEvaluationForm::COMMENT_MAX }}"
                    placeholder="Tuliskan masukan Anda...">{{ old('comment') }}</textarea>
            </div>

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
</body>
</html>
