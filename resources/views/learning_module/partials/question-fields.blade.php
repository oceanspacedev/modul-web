{{-- Field soal pilihan ganda; $question opsional (form edit). Form pemakainya wajib enctype="multipart/form-data". --}}
<div class="form-group mb-3">
    <label class="mb-1">Pertanyaan <span class="text-danger">*</span></label>
    <textarea name="question" class="form-control" rows="3" required>{{ $question->question ?? '' }}</textarea>
    <x-quiz-image-field class="mt-2" name="question_image" remove-name="remove_question_image" :current="$question?->imageUrl()" label="Tambah gambar soal" />
</div>
<div class="row">
    @foreach(\App\Models\LearningModuleQuestion::OPTION_LETTERS as $letter)
        <div class="col-md-6 mb-2">
            <label class="mb-1">
                Pilihan {{ strtoupper($letter) }}
                @if(in_array($letter, ['a', 'b']))
                    <span class="text-danger">*</span>
                @else
                    <span class="text-muted font-weight-normal">(opsional)</span>
                @endif
            </label>
            <input type="text" name="option_{{ $letter }}" class="form-control" value="{{ $question?->{'option_'.$letter} ?? '' }}">
            <x-quiz-image-field class="mt-1"
                name="option_images[{{ $letter }}]"
                remove-name="remove_option_images[{{ $letter }}]"
                :current="$question?->optionImageUrl($letter)"
                label="Gambar {{ strtoupper($letter) }}" />
        </div>
    @endforeach
    <div class="col-md-6 mb-2">
        <label class="mb-1">Kunci Jawaban <span class="text-danger">*</span></label>
        <select name="correct_answer" class="form-control" required>
            @foreach(\App\Models\LearningModuleQuestion::OPTION_LETTERS as $letter)
                <option value="{{ $letter }}" {{ ($question->correct_answer ?? 'a') === $letter ? 'selected' : '' }}>Pilihan {{ strtoupper($letter) }}</option>
            @endforeach
        </select>
    </div>
</div>
<small class="form-text text-muted">Pilihan boleh berupa teks, gambar, atau keduanya.</small>
<div class="form-group mb-0 mt-2">
    <label class="mb-1">Pembahasan (opsional)</label>
    <textarea name="explanation" class="form-control" rows="2">{{ $question->explanation ?? '' }}</textarea>
</div>
