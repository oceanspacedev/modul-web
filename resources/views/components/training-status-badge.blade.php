@props(['status'])

<span {{ $attributes->class(['badge px-2 py-1', $status->badgeClass()]) }}>{{ $status->label() }}</span>
