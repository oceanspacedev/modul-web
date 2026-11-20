@extends('layout.main_template')

@php($isLeader = auth()->user()->can('manage-od-report'))

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Dokumen Builder" :subtitle="$isLeader ? 'Draf dokumen yang disusun tim OD' : 'Draf dokumen yang Anda susun'" eyebrow="Dokumen">
            <x-slot:actions>
                <a href="{{ route('document-builder.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Buat Dokumen</a>
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />

        <div class="card">
            <div class="card-header filter-toolbar">
                <form action="{{ route('document-builder.index') }}" method="GET" class="d-flex flex-wrap align-items-center gap-2 ml-auto m-0">
                    <select name="kategori" class="custom-select custom-select-sm" style="width: 190px;" onchange="this.form.submit()" aria-label="Kategori">
                        <option value="">Semua kategori</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((int) request('kategori') === $category->id)>{{ $category->label() }}</option>
                        @endforeach
                    </select>
                    <x-search-field placeholder="Cari judul..." style="width: 220px; max-width: 100%;"
                        :clear-url="route('document-builder.index', request()->except(['search', 'page']))" />
                </form>
            </div>

            <div class="card-body table-responsive p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Dokumen</th>
                            <th>Kategori</th>
                            @if ($isLeader)<th>Penyusun</th>@endif
                            <th>Diperbarui</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($drafts as $draft)
                            <tr>
                                <td style="white-space: normal; min-width: 240px;">
                                    <a href="{{ route('document-builder.show', $draft) }}" class="font-weight-bold">{{ $draft->title }}</a>
                                    @if ($draft->department || $draft->document_number)
                                        <div class="small text-muted">{{ collect([$draft->document_number, $draft->department])->filter()->implode(' · ') }}</div>
                                    @endif
                                </td>
                                <td><span class="badge badge-light border">{{ $draft->category->code }}</span></td>
                                @if ($isLeader)<td>{{ $draft->user?->nama_lengkap ?? '-' }}</td>@endif
                                <td class="text-nowrap">{{ $draft->updated_at->format('d/m/Y H:i') }}</td>
                                <td class="text-right text-nowrap">
                                    <a href="{{ route('document-builder.edit', $draft) }}" class="btn btn-default btn-sm" title="Edit"><i class="fas fa-pen"></i></a>
                                    <a href="{{ route('document-builder.word', $draft) }}" class="btn btn-default btn-sm" title="Unduh Word"><i class="far fa-file-word"></i></a>
                                    <form action="{{ route('document-builder.destroy', $draft) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus dokumen ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-default btn-sm text-danger" title="Hapus"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isLeader ? 5 : 4 }}">
                                    <x-empty-state icon="fa-magic" title="Belum ada dokumen" text="Pilih kategori, isi form singkat, AI yang menyusun draf.">
                                        <a href="{{ route('document-builder.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Buat Dokumen</a>
                                    </x-empty-state>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($drafts->hasPages())
                <div class="card-footer d-flex justify-content-end">
                    {{ $drafts->links('pagination::bootstrap-4') }}
                </div>
            @endif
        </div>
    </div>
</section>
@endsection
