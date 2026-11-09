{{-- Jawaban satu percobaan; kunci jawaban hanya ditampilkan bila $showKey (admin).
     Gambar diambil dari snapshot; kalau filenya sudah dihapus (soal dihapus/diganti) gambarnya disembunyikan. --}}
@foreach($attempt->answers as $index => $answer)
    <div class="border rounded p-3 mb-2">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <span class="font-weight-bold" style="white-space: pre-line;">{{ $index + 1 }}. {{ $answer['question'] }}</span>
            @if($answer['is_correct'])
                <span class="badge badge-success ml-2">Benar</span>
            @else
                <span class="badge badge-danger ml-2">Salah</span>
            @endif
        </div>
        @if(! empty($answer['question_image']))
            <img src="{{ \App\Support\QuizImage::url($answer['question_image']) }}" alt="Gambar soal" class="quiz-img mb-2" onerror="this.remove()">
        @endif
        <div class="small">
            @foreach($answer['options'] as $letter => $text)
                @php
                    $isChosen = $answer['user_answer'] === $letter;
                    $isKey = $showKey && $answer['correct_answer'] === $letter;
                    $tint = $isKey ? 'bg-soft-success' : ($isChosen ? ($answer['is_correct'] ? 'bg-soft-success' : 'bg-soft-danger') : '');
                @endphp
                <div class="rounded px-2 py-1 {{ $tint }} {{ $isChosen ? 'font-weight-bold' : ($isKey ? '' : 'text-muted') }}">
                    {{ strtoupper($letter) }}. {{ $text }}
                    @if(! empty($answer['option_images'][$letter]))
                        <img src="{{ \App\Support\QuizImage::url($answer['option_images'][$letter]) }}" alt="Gambar pilihan {{ strtoupper($letter) }}" class="quiz-img quiz-opt-img my-1" onerror="this.remove()">
                    @endif
                    @if($isChosen) <span class="badge badge-secondary ml-1">Jawaban peserta</span> @endif
                    @if($isKey) <span class="badge badge-success ml-1">Kunci</span> @endif
                </div>
            @endforeach
            @if($answer['user_answer'] === null)
                <div class="text-danger font-italic mt-1">Tidak dijawab</div>
            @endif
        </div>
    </div>
@endforeach
