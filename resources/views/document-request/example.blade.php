@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="@auth container-fluid @else container @endauth">
        <x-page-header :title="'Contoh Dokumen '.$category->code" :subtitle="$category->name" eyebrow="Permintaan Dokumen">
            <x-slot:actions>
                <a href="{{ $category->exampleUrl() }}" class="btn btn-default" download="{{ $category->exampleFileName() }}"><i class="fas fa-download"></i> Unduh</a>
                <a href="{{ route('document-request.create', ['kategori' => $category->code]) }}" class="btn btn-primary"><i class="fas fa-plus"></i> Ajukan</a>
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="@auth container-fluid @else container @endauth">
        @if (filled($cloud['url']))
            <div class="alert alert-light border">
                <strong>Dokumen yang sudah rilis</strong> ada di <a href="{{ $cloud['url'] }}" target="_blank" rel="noopener" class="text-break">{{ $cloud['url'] }}</a>
                @if ($cloud['user']) · user <code>{{ $cloud['user'] }}</code>@endif
                @if ($cloud['password']) · password <code>{{ $cloud['password'] }}</code>@endif
            </div>
        @endif

        @if ($categories->count() > 1)
            <ul class="nav nav-pills mb-3">
                @foreach ($categories as $item)
                    <li class="nav-item mw-100"><a class="nav-link mw-100 text-break {{ $item->is($category) ? 'active' : '' }}" href="{{ route('document-request.example', $item) }}">{{ $item->code }}</a></li>
                @endforeach
            </ul>
        @endif

        <div class="card">
            @switch($category->exampleViewer())
                @case('pdf')
                    <div class="card-body p-0">
                        <iframe src="{{ $category->exampleUrl() }}" title="Contoh dokumen {{ $category->code }}" style="width: 100%; height: 75vh; border: 0;"></iframe>
                    </div>
                    @break
                @case('image')
                    <div class="card-body text-center">
                        <img src="{{ $category->exampleUrl() }}" alt="Contoh dokumen {{ $category->code }}" class="img-fluid">
                    </div>
                    @break
                @default
                    <div class="card-body text-center py-5">
                        <i class="far fa-file-alt fa-3x text-muted mb-3"></i>
                        <p class="mb-3 text-break">{{ $category->exampleFileName() }}</p>
                        <a href="{{ $category->exampleUrl() }}" class="btn btn-primary" download="{{ $category->exampleFileName() }}"><i class="fas fa-download"></i> Unduh contoh</a>
                    </div>
            @endswitch
        </div>
    </div>
</section>
@endsection
