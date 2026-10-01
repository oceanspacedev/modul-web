<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Kuis - {{ $training->title }}</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            color: #212529;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .content-card {
            background: #ffffff;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 24px;
            margin-bottom: 24px;
        }
    </style>
</head>
<body>

    <main class="container py-5" style="max-width: 760px;">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                {{ session('success') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        <!-- SCORE SUMMARY -->
        <div class="content-card text-center p-4">
            <span class="text-muted small text-uppercase font-weight-bold d-block mb-1">Hasil Evaluasi Kuis</span>
            <h4 class="font-weight-bold mb-4">{{ $training->title }}</h4>

            <div class="d-inline-block px-5 py-3 border rounded bg-light mb-4">
                <span class="text-xs text-muted d-block font-weight-bold mb-1">NILAI AKHIR</span>
                <span class="display-3 font-weight-bold text-primary">{{ $quizResult->score }}</span>
            </div>

            <div class="row max-w-sm mx-auto p-3 bg-light rounded border text-sm mb-4">
                <div class="col-4 border-right">
                    <span class="text-muted text-xs d-block">Total Soal</span>
                    <strong>{{ $quizResult->total_questions }}</strong>
                </div>
                <div class="col-4 border-right">
                    <span class="text-muted text-xs d-block">Jawaban Benar</span>
                    <strong class="text-success">{{ $quizResult->correct_answers }}</strong>
                </div>
                <div class="col-4">
                    <span class="text-muted text-xs d-block">Jawaban Salah</span>
                    <strong class="text-danger">{{ $quizResult->total_questions - $quizResult->correct_answers }}</strong>
                </div>
            </div>

            <div class="d-flex justify-content-center flex-wrap" style="gap: 12px;">
                <a href="/training/portal/{{ $participant->token }}" class="btn btn-default px-4">
                    Kembali ke Halaman Pelatihan
                </a>
                @if($training->is_quiz_active)
                    <a href="/training/portal/{{ $participant->token }}/retake" onclick="return confirm('Apakah Anda ingin mengulang kuis ini?')" class="btn btn-warning text-white px-4 font-weight-bold">
                        Kerjakan Ulang Kuis
                    </a>
                @endif
            </div>
        </div>

        <!-- REVIEW PERTANYAAN -->
        @if($quizResult->answers)
        <div class="content-card p-4">
            <h5 class="font-weight-bold mb-3 pb-2 border-bottom">Rincian Jawaban & Kunci</h5>

            @foreach($questions as $index => $q)
            @php
                $ansData = $quizResult->answers[$q->id] ?? null;
                $userAns = $ansData['user_answer'] ?? null;
                $isCorrect = $ansData['is_correct'] ?? false;
            @endphp
            <div class="p-3 mb-3 rounded border {{ $isCorrect ? 'border-success' : 'border-danger' }}" style="background-color: {{ $isCorrect ? '#fafffa' : '#fff8f8' }};">
                <div class="d-flex justify-content-between mb-2">
                    <div>
                        <span class="badge {{ $isCorrect ? 'badge-success' : 'badge-danger' }} mr-1">Soal {{ $index + 1 }}</span>
                        <strong class="text-dark">{{ $q->question }}</strong>
                    </div>
                    <span class="text-xs font-weight-bold {{ $isCorrect ? 'text-success' : 'text-danger' }}">
                        {{ $isCorrect ? 'Benar' : 'Salah' }}
                    </span>
                </div>

                <div class="small mt-2 pt-2 border-top">
                    <div class="mb-1">
                        Jawaban Anda: 
                        <strong class="{{ $isCorrect ? 'text-success' : 'text-danger' }}">
                            {{ strtoupper($userAns ?? '-') }}. {{ $q->{'option_' . $userAns} ?? '-' }}
                        </strong>
                    </div>
                    @if(!$isCorrect)
                    <div class="mb-1 text-success">
                        Kunci Jawaban: <strong>{{ strtoupper($q->correct_answer) }}. {{ $q->{'option_' . $q->correct_answer} }}</strong>
                    </div>
                    @endif

                    @if($q->explanation)
                    <div class="text-muted text-xs mt-2 pt-2 border-top font-italic">
                        Catatan: {{ $q->explanation }}
                    </div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
