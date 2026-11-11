@props(['icon' => 'fa-inbox', 'title' => 'Belum ada data', 'text' => null])

@php
    if (is_string($text) && ! str_contains(rtrim($text, '.'), '. ')) {
        $text = rtrim($text, '.');
    }
@endphp
<div {{ $attributes->class(['empty-state']) }}>
    <span class="empty-state-icon"><i class="fas {{ $icon }}"></i></span>
    <p class="empty-state-title">{{ $title }}</p>
    @if ($text)
        <p class="empty-state-text">{{ $text }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="empty-state-actions">{{ $slot }}</div>
    @endif
</div>
