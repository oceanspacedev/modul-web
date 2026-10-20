@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show {{ $class ?? 'mx-3 mt-3 mb-0' }}" role="alert">
        <ul class="mb-0 pl-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
