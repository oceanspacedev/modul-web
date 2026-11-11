@props(['steps' => [], 'title' => 'Langkah-langkah', 'subtitle' => null])

{{--
    Checklist langkah kerja: yang sudah selesai dicentang, langkah berikutnya disorot dengan tombolnya.
    Tiap langkah: ['label' => ..., 'hint' => ..., 'done' => bool, 'optional' => bool,
                  'action' => teks tombol, dan salah satu dari 'url' | 'modal' (id modal) | 'post' (url form POST)].
    Usage: <x-setup-steps title="Siapkan modul" :steps="$steps" />
--}}
@php
    $steps = array_values($steps);
    $required = array_filter($steps, fn ($s) => empty($s['optional']));
    $doneCount = count(array_filter($required, fn ($s) => ! empty($s['done'])));
    $current = null;
    foreach ($steps as $i => $s) {
        if (empty($s['done']) && empty($s['optional'])) {
            $current = $i;
            break;
        }
    }
@endphp

<div {{ $attributes->class(['card', 'setup-steps']) }}>
    <div class="card-header">
        <div class="min-w-0">
            <h3 class="card-title">{{ $title }}</h3>
            @if ($subtitle)
                <p class="section-subtitle">{{ $subtitle }}</p>
            @endif
        </div>
        <span class="setup-steps-count {{ $current === null ? 'is-complete' : '' }}">
            @if ($current === null)
                <i class="fas fa-check-circle"></i> Semua langkah selesai
            @else
                {{ $doneCount }} dari {{ count($required) }} selesai
            @endif
        </span>
    </div>
    <ol class="setup-steps-list">
        @foreach ($steps as $i => $step)
            @php
                $state = ! empty($step['done']) ? 'done' : ($i === $current ? 'current' : (! empty($step['optional']) ? 'optional' : 'todo'));
                $btnClass = $state === 'current' ? 'btn btn-primary btn-sm' : 'btn btn-default btn-sm';
            @endphp
            <li class="setup-step is-{{ $state }}" @if ($state === 'current') aria-current="step" @endif>
                <span class="setup-step-marker">
                    @if ($state === 'done')
                        <i class="fas fa-check"></i>
                    @else
                        {{ $i + 1 }}
                    @endif
                </span>
                <div class="setup-step-body">
                    <p class="setup-step-label">
                        {{ $step['label'] }}
                        @if (! empty($step['optional']) && empty($step['done']))
                            <span class="setup-step-tag">Opsional</span>
                        @endif
                    </p>
                    @if (! empty($step['hint']))
                        <p class="setup-step-hint">{{ $step['hint'] }}</p>
                    @endif
                    @if (! empty($step['action']))
                        <div class="setup-step-action">
                            @if (! empty($step['post']))
                                <form action="{{ $step['post'] }}" method="POST" class="m-0">
                                    @csrf
                                    <button type="submit" class="{{ $btnClass }}">{{ $step['action'] }}</button>
                                </form>
                            @elseif (! empty($step['modal']))
                                <button type="button" class="{{ $btnClass }}" data-toggle="modal" data-target="#{{ $step['modal'] }}">{{ $step['action'] }}</button>
                            @elseif (! empty($step['url']))
                                <a href="{{ $step['url'] }}" class="{{ $btnClass }}">{{ $step['action'] }}</a>
                            @endif
                        </div>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
</div>
