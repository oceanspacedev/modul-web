@props(['title', 'subtitle' => null, 'eyebrow' => null])

{{--
    Page title row: optional eyebrow (breadcrumb-ish), title, subtitle, and actions on the right.
    Usage:
    <x-page-header title="Data Area" subtitle="Badan usaha yang terdaftar">
        <x-slot:actions><button class="btn btn-primary">Tambah</button></x-slot:actions>
    </x-page-header>
--}}
<div {{ $attributes->class(['page-header']) }}>
    <div class="page-header-text">
        @if ($eyebrow)
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
