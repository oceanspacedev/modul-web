{{-- Pilihan A–F untuk form tambah/edit soal pilihan ganda: tiap pilihan boleh teks, gambar, atau keduanya. --}}
<div class="form-row">
    @foreach(\App\Models\TrainingQuestion::OPTION_LETTERS as $letter)
        <div class="form-group col-md-6">
            <label>
                Pilihan {{ strtoupper($letter) }}
                @if(in_array($letter, ['a', 'b']))
                    <span class="text-danger">*</span>
                @else
                    <span class="text-muted">(opsional)</span>
                @endif
            </label>
            <input type="text" name="option_{{ $letter }}" class="form-control" value="{{ $question?->{'option_'.$letter} }}" placeholder="Teks opsi {{ strtoupper($letter) }}">
            <x-quiz-image-field class="mt-1"
                name="option_images[{{ $letter }}]"
                remove-name="remove_option_images[{{ $letter }}]"
                :current="$question?->optionImageUrl($letter)"
                label="Gambar {{ strtoupper($letter) }}" />
        </div>
    @endforeach
</div>
<small class="form-text text-muted mb-3">Pilihan boleh berupa teks, gambar, atau keduanya.</small>
