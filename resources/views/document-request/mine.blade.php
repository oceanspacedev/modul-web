@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Permintaan Saya" subtitle="Pengajuan dokumen ke tim OD" eyebrow="Permintaan Dokumen">
            <x-slot:actions>
                <a href="{{ route('document-request.example') }}" class="btn btn-default"><i class="far fa-file-alt"></i> Contoh Dokumen</a>
                <a href="{{ route('document-request.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Ajukan Dokumen</a>
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />

        <div class="card">
            <div class="card-body table-responsive p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Kategori</th>
                            <th>Dokumen</th>
                            <th>Status</th>
                            <th style="width: 160px;">Progres</th>
                            <th>Diajukan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($requests as $request)
                            <tr>
                                <td><a href="{{ route('document-request.track', ['kode' => $request->code]) }}">{{ $request->code }}</a></td>
                                <td>{{ $request->category?->code }}</td>
                                <td style="white-space: normal;">
                                    {{ $request->processes->first()?->title }}
                                    @if ($request->processes->count() > 1)<span class="text-muted">(+{{ $request->processes->count() - 1 }})</span>@endif
                                </td>
                                <td>{{ $request->stage() }}</td>
                                <td>
                                    <div class="progress" style="height: 6px;"><div class="progress-bar bg-success" style="width: {{ $request->progress() }}%"></div></div>
                                    <span class="small text-muted">{{ $request->progress() }}%</span>
                                </td>
                                <td>{{ $request->created_at->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <x-empty-state icon="fa-file-signature" title="Belum ada pengajuan" text="Ajukan pembuatan dokumen ke tim OD.">
                                        <a href="{{ route('document-request.create') }}" class="btn btn-primary btn-sm">Ajukan Dokumen</a>
                                    </x-empty-state>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($requests->hasPages())
                <div class="card-footer d-flex justify-content-end">{{ $requests->links('pagination::bootstrap-4') }}</div>
            @endif
        </div>
    </div>
</section>
@endsection
