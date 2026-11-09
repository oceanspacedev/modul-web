@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show flash {{ $class ?? 'mx-3 mt-3 mb-0' }}" role="alert">
        <i class="fas fa-exclamation-circle"></i>
        <ul class="mb-0 flex-fill">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
