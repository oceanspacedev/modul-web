<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengerjaan Kuis - {{ $training->title }}</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            color: #212529;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .quiz-header {
            background-color: #ffffff;
            border-bottom: 1px solid #dee2e6;
            padding: 16px 0;
            margin-bottom: 24px;
            position: sticky;
            top: 0;
            z-index: 1020;
        }
        .question-card {
            background: #ffffff;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 24px;
            margin-bottom: 20px;
        }
        .option-label {
            display: flex;
            align-items: flex-start;
            padding: 12px 16px;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            margin-bottom: 10px;
            cursor: pointer;
            background-color: #ffffff;
        }
        .option-label:hover {
            background-color: #f8f9fa;
        }
        .option-label input[type="radio"] {
            margin-top: 4px;
            margin-right: 12px;
        }
    </style>
</head>
<body>

    <header class="quiz-header">
        <div class="container" style="max-width: 760px;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="font-weight-bold mb-0 text-dark">{{ $training->title }}</h5>
                    <span class="text-muted small">Kuis Evaluasi</span>
                </div>
                <div>
                    <span class="badge badge-secondary px-3 py-2 font-weight-bold">Total {{ $questions->count() }} Soal</span>
                </div>
            </div>
        </div>
    </header>

    <main class="container mb-5" style="max-width: 760px;">
        <div class="alert alert-light border mb-4 text-muted small">
            Pilih satu jawaban yang paling tepat untuk setiap pertanyaan berikut. Pastikan semua soal telah terjawab sebelum mengirim kuis.
        </div>

        <form action="/training/portal/{{ $participant->token }}/quiz" method="POST" id="formQuiz">
            @csrf

            @foreach($questions as $index => $q)
            <div class="question-card">
                <div class="mb-3">
                    <span class="badge badge-dark mr-2">Soal {{ $index + 1 }}</span>
                    <strong class="text-dark" style="font-size: 1.05rem;">{{ $q->question }}</strong>
                </div>

                <div class="mt-3">
                    <label class="option-label" for="q_{{ $q->id }}_a">
                        <input type="radio" name="answers[{{ $q->id }}]" value="a" id="q_{{ $q->id }}_a" required>
                        <div>
                            <strong>A.</strong> {{ $q->option_a }}
                        </div>
                    </label>

                    <label class="option-label" for="q_{{ $q->id }}_b">
                        <input type="radio" name="answers[{{ $q->id }}]" value="b" id="q_{{ $q->id }}_b">
                        <div>
                            <strong>B.</strong> {{ $q->option_b }}
                        </div>
                    </label>

                    <label class="option-label" for="q_{{ $q->id }}_c">
                        <input type="radio" name="answers[{{ $q->id }}]" value="c" id="q_{{ $q->id }}_c">
                        <div>
                            <strong>C.</strong> {{ $q->option_c }}
                        </div>
                    </label>

                    <label class="option-label" for="q_{{ $q->id }}_d">
                        <input type="radio" name="answers[{{ $q->id }}]" value="d" id="q_{{ $q->id }}_d">
                        <div>
                            <strong>D.</strong> {{ $q->option_d }}
                        </div>
                    </label>
                </div>
            </div>
            @endforeach

            <div class="card p-4 text-center border bg-white mb-5">
                <p class="text-muted small mb-3">Pastikan Anda telah memeriksa kembali seluruh jawaban.</p>
                <div class="d-flex justify-content-center" style="gap: 12px;">
                    <a href="/training/portal/{{ $participant->token }}" class="btn btn-default px-4">Batal</a>
                    <button type="submit" class="btn btn-primary px-5 font-weight-bold" onclick="return confirm('Kirimkan seluruh jawaban kuis ini?')">
                        Kirim Jawaban
                    </button>
                </div>
            </div>
        </form>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
