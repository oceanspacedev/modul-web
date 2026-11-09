{{--
    Input gambar untuk form soal kuis: gambar saat ini (kalau ada) + centang "Hapus gambar" + pilih file baru.
    Form pemakainya wajib enctype="multipart/form-data".
--}}
@props([
    'name',
    'removeName',
    'current' => null,
    'label' => 'Tambah gambar',
])

@php($fieldId = 'qimg_'.\Illuminate\Support\Str::random(8))

<div {{ $attributes->merge(['class' => 'quiz-img-field']) }}>
    @if($current)
        <div class="d-flex align-items-start gap-2 mb-1">
            <a href="{{ $current }}" target="_blank" rel="noopener">
                <img src="{{ $current }}" alt="Gambar saat ini" class="quiz-img quiz-img-thumb">
            </a>
            <div class="custom-control custom-checkbox small">
                <input type="checkbox" class="custom-control-input" id="{{ $fieldId }}_remove" name="{{ $removeName }}" value="1">
                <label class="custom-control-label" for="{{ $fieldId }}_remove">Hapus gambar</label>
            </div>
        </div>
    @endif
    <label class="quiz-img-add mb-0" for="{{ $fieldId }}">
        <i class="fas fa-image mr-1"></i>{{ $current ? 'Ganti gambar' : $label }}
    </label>
    <input type="file" id="{{ $fieldId }}" name="{{ $name }}" accept="image/jpeg,image/png,image/webp" class="quiz-img-input">
    <span class="quiz-img-filename small text-muted"></span>
</div>
