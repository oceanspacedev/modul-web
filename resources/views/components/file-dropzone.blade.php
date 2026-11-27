{{--
    Kotak unggah file besar: klik atau seret file ke kotak, lalu file yang dipilih tampil di bawahnya
    (nama, ukuran, status, tombol hapus). Saat form dikirim, barisnya berubah jadi "Mengunggah…".
    Perilakunya ada di public/script.js (.file-dropzone). Form pemakainya wajib enctype="multipart/form-data".
--}}
@props([
    'name' => 'file',
    'id' => null,
    'extensions' => ['pdf'],
    'maxMb' => 50,
    'required' => false,
    'title' => 'Klik untuk pilih atau seret file ke sini',
])

@php
    $inputId = $id ?? $name;
    $extensions = array_map('strtolower', (array) $extensions);
    $accept = collect($extensions)->map(fn ($ext) => '.'.$ext)
        ->when($extensions === ['pdf'], fn ($list) => $list->push('application/pdf'))
        ->implode(',');
    $typeLabel = collect($extensions)->map(fn ($ext) => strtoupper($ext))->join(', ', ' atau ');
    $hasError = $errors->has($name);
@endphp

<div {{ $attributes->class(['file-dropzone', 'is-invalid' => $hasError]) }}
    data-extensions="{{ implode(',', $extensions) }}" data-max-bytes="{{ $maxMb * 1024 * 1024 }}" data-max-label="{{ $maxMb }} MB">
    <input type="file" class="file-dropzone-input" id="{{ $inputId }}" name="{{ $name }}" accept="{{ $accept }}"
        @required($required) @if ($hasError) aria-invalid="true" @endif aria-describedby="{{ $inputId }}-meta">
    <label class="file-dropzone-area" for="{{ $inputId }}">
        <span class="file-dropzone-icon" aria-hidden="true"><i class="fas fa-cloud-upload-alt"></i></span>
        <span class="file-dropzone-title">{{ $title }}</span>
        <span class="file-dropzone-choose">Pilih file</span>
    </label>

    <div class="file-dropzone-meta" id="{{ $inputId }}-meta">
        <span>Hanya file {{ $typeLabel }}</span>
        <span>Maks. {{ $maxMb }} MB</span>
    </div>

    <div class="file-dropzone-item" hidden>
        <span class="file-dropzone-file-icon" aria-hidden="true">
            <i class="fas fa-file-alt"></i>
            <span class="file-dropzone-ext"></span>
        </span>
        <span class="file-dropzone-info">
            <span class="file-dropzone-name"></span>
            <span class="file-dropzone-status">
                <span class="file-dropzone-size"></span>
                <span class="file-dropzone-state" role="status" aria-live="polite"></span>
            </span>
            <span class="file-dropzone-progress" aria-hidden="true"><span></span></span>
        </span>
        <button type="button" class="file-dropzone-remove" aria-label="Hapus file" title="Hapus file">
            <i class="far fa-trash-alt"></i>
        </button>
    </div>

    @error($name)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
