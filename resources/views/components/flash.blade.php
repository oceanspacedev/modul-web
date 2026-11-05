@props(['withErrors' => true])

{{-- Session success/error/warning messages plus validation errors, in one consistent stack. --}}
@php
    $messages = collect([
        'success' => ['alert-success', 'fa-check-circle'],
        'error' => ['alert-danger', 'fa-exclamation-circle'],
        'warning' => ['alert-warning', 'fa-exclamation-triangle'],
        'info' => ['alert-info', 'fa-info-circle'],
    ])->filter(fn ($style, $key) => session()->has($key));
    $showErrors = $withErrors && session('errors')?->any();
@endphp

@if ($messages->isNotEmpty() || $showErrors)
    <div {{ $attributes->class(['flash-stack']) }}>
        @foreach ($messages as $key => [$class, $icon])
            <div class="alert {{ $class }} alert-dismissible fade show flash" role="alert">
                <i class="fas {{ $icon }}"></i>
                <div class="flex-fill">{{ session($key) }}</div>
                <button type="button" class="close" data-dismiss="alert" aria-label="Tutup">&times;</button>
            </div>
        @endforeach

        @if ($showErrors)
            <div class="alert alert-danger alert-dismissible fade show flash" role="alert">
                <i class="fas fa-exclamation-circle"></i>
                <ul class="mb-0 flex-fill">
                    @foreach (session('errors')->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="close" data-dismiss="alert" aria-label="Tutup">&times;</button>
            </div>
        @endif
    </div>
@endif
