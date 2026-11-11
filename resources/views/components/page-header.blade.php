@props(['title', 'subtitle' => null, 'eyebrow' => null, 'breadcrumbs' => []])

{{--
    Page title row: optional breadcrumb, title, subtitle, and actions on the right.
    `breadcrumbs` is ['Label' => url|null]; a url makes that crumb a link back. `eyebrow` is the plain-text fallback.
    Usage:
    <x-page-header title="Data Area" subtitle="Badan usaha yang terdaftar" :breadcrumbs="['Master Data' => null, 'Area' => null]">
        <x-slot:actions><button class="btn btn-primary">Tambah</button></x-slot:actions>
    </x-page-header>
--}}
@php
    // Satu kalimat tampil tanpa titik di akhir; paragraf beberapa kalimat tetap utuh.
    if (is_string($subtitle) && ! str_contains(rtrim($subtitle, '.'), '. ')) {
        $subtitle = rtrim($subtitle, '.');
    }
@endphp
<div {{ $attributes->class(['page-header']) }}>
    <div class="page-header-text">
        @if ($breadcrumbs)
            <nav class="page-eyebrow" aria-label="Breadcrumb">
                @foreach ($breadcrumbs as $label => $url)
                    @if ($url)
                        <a href="{{ $url }}">{{ $label }}</a>
                    @else
                        <span>{{ $label }}</span>
                    @endif
                    @unless ($loop->last)
                        <span class="page-eyebrow-sep" aria-hidden="true">/</span>
                    @endunless
                @endforeach
            </nav>
        @elseif ($eyebrow)
            <div class="page-eyebrow">{{ $eyebrow }}</div>
        @endif
        <h1 class="page-title">{{ $title }}</h1>
        @if ($subtitle)
            <p class="page-subtitle">{{ $subtitle }}</p>
        @endif
        {{ $slot }}
    </div>
    @isset($actions)
        <div class="page-actions">{{ $actions }}</div>
    @endisset
</div>
