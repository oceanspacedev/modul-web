@props(['steps' => [], 'title' => 'Langkah'])

{{--
    Stepper ringkas: satu baris label pendek (selesai dicentang), lalu satu bar "Berikutnya" untuk langkah aktif.
    Tiap langkah: ['label' => kata pendek, 'meta' => keterangan singkat (tooltip), 'hint' => kalimat untuk bar berikutnya,
                  'done' => bool, 'optional' => bool, 'action' => teks tombol, dan salah satu dari
                  'url' | 'modal' (id modal, dengan 'modalData' opsional: atribut data-* untuk tombol pemicunya) |
                  'post' (url form POST)].
    Usage: <x-setup-steps title="Langkah menyiapkan modul" :steps="$steps" />
--}}
@php
    $steps = array_values($steps);
    $current = null;
    foreach ($steps as $i => $s) {
        if (empty($s['done']) && empty($s['optional'])) {
            $current = $i;
            break;
        }
    }
    $next = $current === null ? null : $steps[$current];
@endphp

<div {{ $attributes->class(['card', 'setup-steps']) }} role="group" aria-label="{{ $title }}">
    <ol class="setup-steps-list">
        @foreach ($steps as $i => $step)
            @php
                $state = ! empty($step['done']) ? 'done' : ($i === $current ? 'current' : (! empty($step['optional']) ? 'optional' : 'todo'));
            @endphp
            <li class="setup-step is-{{ $state }}" @if ($state === 'current') aria-current="step" @endif
                @if (! empty($step['meta'])) title="{{ $step['meta'] }}" @endif>
                <span class="setup-step-marker">
                    @if ($state === 'done')
                        <i class="fas fa-check" aria-label="Selesai"></i>
                    @else
                        {{ $i + 1 }}
                    @endif
                </span>
                <span class="setup-step-label">{{ $step['label'] }}@if ($state === 'optional')<span class="text-muted font-weight-normal"> (opsional)</span>@endif</span>
            </li>
        @endforeach
    </ol>

    @if ($next)
        <div class="setup-steps-next">
            <p class="setup-steps-next-text">
                <span class="setup-steps-next-label">Berikutnya:</span> {{ $next['hint'] ?? $next['label'] }}
            </p>
            @if (! empty($next['action']))
                @if (! empty($next['post']))
                    <form action="{{ $next['post'] }}" method="POST" class="m-0">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm">{{ $next['action'] }}</button>
                    </form>
                @elseif (! empty($next['modal']))
                    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#{{ $next['modal'] }}"
                        @foreach ($next['modalData'] ?? [] as $name => $value) data-{{ $name }}="{{ $value }}" @endforeach>{{ $next['action'] }}</button>
                @elseif (! empty($next['url']))
                    <a href="{{ $next['url'] }}" class="btn btn-primary btn-sm">{{ $next['action'] }}</a>
                @endif
            @endif
        </div>
    @endif
</div>
