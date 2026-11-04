{{-- Field soal pilihan ganda; $question opsional (form edit). --}}
<div class="form-group mb-3">
    <label class="font-weight-600 mb-1">Pertanyaan <span class="text-danger">*</span></label>
    <textarea name="question" class="form-control" rows="3" required>{{ $question->question ?? '' }}</textarea>
</div>
<div class="row">
    @foreach(['a', 'b', 'c', 'd', 'e'] as $letter)
        <div class="col-md-6 mb-2">
            <label class="font-weight-600 mb-1">
                Pilihan {{ strtoupper($letter) }}
                @if(in_array($letter, ['a', 'b']))
                    <span class="text-danger">*</span>
                @else
                    <span class="text-muted font-weight-normal">(opsional)</span>
                @endif
            </label>
            <input type="text" name="option_{{ $letter }}" class="form-control" value="{{ $question?->{'option_'.$letter} ?? '' }}" {{ in_array($letter, ['a', 'b']) ? 'required' : '' }}>
        </div>
    @endforeach
    <div class="col-md-6 mb-2">
        <label class="font-weight-600 mb-1">Kunci Jawaban <span class="text-danger">*</span></label>
        <select name="correct_answer" class="form-control" required>
            @foreach(['a', 'b', 'c', 'd', 'e'] as $letter)
                <option value="{{ $letter }}" {{ ($question->correct_answer ?? 'a') === $letter ? 'selected' : '' }}>Pilihan {{ strtoupper($letter) }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group mb-0 mt-2">
    <label class="font-weight-600 mb-1">Pembahasan (opsional)</label>
    <textarea name="explanation" class="form-control" rows="2">{{ $question->explanation ?? '' }}</textarea>
</div>
