@props(['name' => 'search', 'placeholder' => 'Cari...', 'clearUrl' => null])

@php($value = request($name))

<div {{ $attributes->class(['search-field']) }}>
    <i class="fas fa-search search-field-icon" aria-hidden="true"></i>
    <input type="search" name="{{ $name }}" value="{{ $value }}" placeholder="{{ $placeholder }}"
        aria-label="{{ $placeholder }}" autocomplete="off">
    @if (filled($value) && $clearUrl)
        <a href="{{ $clearUrl }}" class="search-field-clear" title="Hapus pencarian" aria-label="Hapus pencarian">
            <i class="fas fa-times"></i>
        </a>
    @endif
</div>
