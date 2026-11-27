{{--
    Kotak unggah file besar: klik atau seret file ke kotak, lalu file yang dipilih tampil di bawahnya
    (ikon sesuai tipe, nama, ukuran, status, tombol hapus). Saat form dikirim, barisnya jadi "Mengunggah…".
    Perilakunya ada di public/file-dropzone.js. Form pemakainya wajib enctype="multipart/form-data".

    Atribut lain (required, onchange, data-*, disabled, ...) diteruskan ke <input type="file">;
    class dan style dipasang di pembungkusnya.
--}}
@props([
    'name' => 'file',
    'id' => null,
    'extensions' => ['pdf'],
    'accept' => null,
    'maxMb' => 50,
    'maxFiles' => null,
    'multiple' => false,
    'compact' => false,
    'title' => 'Klik untuk pilih atau seret file ke sini',
    'types' => null,
    'errorKey' => null,
])

@php
    $inputId = $id ?? trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $name), '-');
    $extensions = array_map('strtolower', (array) $extensions);
    $accept ??= collect($extensions)->map(fn ($ext) => '.'.$ext)
        ->when($extensions === ['pdf'], fn ($list) => $list->push('application/pdf'))
        ->implode(',');
    $types ??= collect($extensions)->map(fn ($ext) => strtoupper($ext))->join(', ', ' atau ');
    // :max-mb="false" = tanpa batas ukuran di browser (Blade membuang nilai null, jadi pakai false).
    $maxMb = $maxMb ?: null;
    $maxLabel = $maxMb === null ? null
        : ($maxMb >= 1024 && $maxMb % 1024 === 0 ? ($maxMb / 1024).' GB' : $maxMb.' MB');

    // Kunci error Laravel dari nama input: proses[3][lampiran][] -> proses.3.lampiran (+ proses.3.lampiran.*).
    $errorKey ??= trim(preg_replace('/\]?\[/', '.', str_replace('[]', '', $name)), '.]');
    $errorMessage = $errorKey === false ? null : ($errors->first($errorKey) ?: $errors->first($errorKey.'.*'));
@endphp

<div {{ $attributes->only(['class', 'style'])->class(['file-dropzone', 'is-compact' => $compact, 'is-invalid' => $errorMessage]) }}
    data-extensions="{{ implode(',', $extensions) }}"
    @if ($maxMb !== null) data-max-bytes="{{ $maxMb * 1024 * 1024 }}" data-max-label="{{ $maxLabel }}" @endif
    @if ($maxFiles) data-max-files="{{ $maxFiles }}" @endif>
    <input type="file" {{ $attributes->except(['class', 'style'])->merge([
        'class' => 'file-dropzone-input',
        'id' => $inputId,
        'name' => $name,
        'accept' => $accept,
        'aria-describedby' => $inputId.'-meta',
    ]) }} @if ($multiple) multiple @endif @if ($errorMessage) aria-invalid="true" @endif>
    <label class="file-dropzone-area" for="{{ $inputId }}">
        <span class="file-dropzone-icon" aria-hidden="true"><i class="fas fa-cloud-upload-alt"></i></span>
        <span class="file-dropzone-title">{{ $title }}</span>
        <span class="file-dropzone-choose">{{ $multiple ? 'Pilih satu atau beberapa file' : 'Pilih file' }}</span>
    </label>

    <div class="file-dropzone-meta" id="{{ $inputId }}-meta">
        <span>Hanya file {{ $types }}</span>
        @if ($maxLabel || $maxFiles)
            <span>{{ collect([$maxFiles ? 'Maks. '.$maxFiles.' file' : null, $maxLabel ? 'Maks. '.$maxLabel.($multiple ? ' per file' : '') : null])->filter()->implode(' · ') }}</span>
        @endif
    </div>

    <div class="file-dropzone-list"></div>
    <template class="file-dropzone-template">
        <div class="file-dropzone-item">
            <span class="file-dropzone-file-icon" aria-hidden="true">
                <i class="fas fa-file-alt"></i>
                <span class="file-dropzone-ext"></span>
            </span>
            <span class="file-dropzone-info">
                <span class="file-dropzone-name"></span>
                <span class="file-dropzone-status">
                    <span class="file-dropzone-size"></span>
                    <span class="file-dropzone-state"></span>
                </span>
                <span class="file-dropzone-progress" aria-hidden="true"><span></span></span>
            </span>
            <button type="button" class="file-dropzone-remove" aria-label="Hapus file" title="Hapus file">
                <i class="far fa-trash-alt"></i>
            </button>
        </div>
    </template>
    <span class="sr-only" role="status" aria-live="polite" data-dropzone-announce></span>

    @if ($errorMessage)
        <div class="invalid-feedback d-block">{{ $errorMessage }}</div>
    @endif
</div>
