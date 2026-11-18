@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Contoh Dokumen IK" subtitle="Gambaran hasil Instruksi Kerja yang dibuat tim OD" eyebrow="Permintaan Dokumen">
            <x-slot:actions>
                <a href="{{ asset('assets/permintaan-dokumen/contoh-ik.pdf') }}" class="btn btn-default" download><i class="fas fa-download"></i> Unduh</a>
                <a href="{{ route('document-request.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Ajukan</a>
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        @if (filled($cloud['url']))
            <div class="alert alert-light border">
                <strong>Dokumen yang sudah rilis</strong> ada di <a href="{{ $cloud['url'] }}" target="_blank" rel="noopener">{{ $cloud['url'] }}</a>
                @if ($cloud['user']) · user <code>{{ $cloud['user'] }}</code>@endif
                @if ($cloud['password']) · password <code>{{ $cloud['password'] }}</code>@endif
            </div>
        @endif

        <div class="card">
            <div class="card-body p-0">
                <iframe src="{{ asset('assets/permintaan-dokumen/contoh-ik.pdf') }}" title="Contoh dokumen IK" style="width: 100%; height: 75vh; border: 0;"></iframe>
            </div>
        </div>
    </div>
</section>
@endsection
