@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Kategori Dokumen" subtitle="Jenis dokumen dan tahapannya" eyebrow="Layanan">
            <x-slot:actions>
                <a href="{{ route('document-request.create') }}" class="btn btn-default" target="_blank"><i class="fas fa-external-link-alt"></i> Lihat form</a>
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addCategory"><i class="fas fa-plus"></i> Tambah Kategori</button>
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />

        <div class="card">
            <div class="card-header filter-toolbar">
                <form action="{{ route('document-category.index') }}" method="GET" class="w-100 m-0" role="search">
                    <x-search-field placeholder="Cari kode atau nama..." :clear-url="route('document-category.index')" style="max-width: 360px;" />
                </form>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Kategori</th>
                            <th>Tahapan</th>
                            <th>PIC bawaan</th>
                            <th class="text-right">Dipakai</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $category)
                            @php($used = ($documentCounts[$category->code] ?? 0) + $category->document_requests_count)
                            <tr>
                                <td>
                                    <a href="{{ route('document-category.edit', $category) }}"><strong>{{ $category->code }}</strong></a>
                                    @unless ($category->is_active)<span class="badge badge-secondary ml-1">Nonaktif</span>@endunless
                                    @if ($category->needs_observation)<span class="badge badge-light ml-1">Observasi</span>@endif
                                    <div class="small text-muted">{{ $category->name }}</div>
                                </td>
                                <td style="white-space: normal; min-width: 260px;" class="small">{{ implode(' → ', $category->stageNames()) }}</td>
                                <td>{{ $category->pic?->nama_lengkap ?? '-' }}</td>
                                <td class="text-right">{{ $documentCounts[$category->code] ?? 0 }} dok</td>
                                <td class="text-right text-nowrap">
                                    <a href="{{ route('document-category.edit', $category) }}" class="btn btn-default btn-xs">Ubah</a>
                                    <form action="{{ route('document-category.destroy', $category) }}" method="POST" class="d-inline m-0" onsubmit="return confirm(@js('Hapus kategori '.$category->code.'?'))">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-default btn-xs text-danger" @disabled($used > 0) title="{{ $used > 0 ? 'Sudah dipakai, nonaktifkan saja' : 'Hapus' }}">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    @if (filled(request('search')))
                                        <x-empty-state icon="fa-search" title="Tidak ada hasil" text="Tidak ada kategori yang cocok dengan pencarian ini." />
                                    @else
                                        <x-empty-state icon="fa-tags" title="Belum ada kategori" text="Tambahkan kategori pertama."><button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addCategory">Tambah Kategori</button></x-empty-state>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer text-muted small">Total {{ $categories->count() }} kategori</div>
        </div>
    </div>
</section>

<form action="{{ route('document-category.store') }}" method="POST">
    @csrf
    <div class="modal fade" id="addCategory" tabindex="-1" aria-labelledby="addCategoryLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCategoryLabel">Tambah Kategori</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="new-code">Kode</label>
                        <input type="text" class="form-control @error('code') is-invalid @enderror" id="new-code" name="code" maxlength="30" required placeholder="Contoh: BA" value="{{ old('code') }}">
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group mb-0">
                        <label for="new-name">Nama</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="new-name" name="name" maxlength="100" required placeholder="Contoh: Berita Acara" value="{{ old('name') }}">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <small class="form-text text-muted">Berikutnya: atur tahapannya.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@if ($errors->has('code') || $errors->has('name'))
    @section('footer')
    <script>
        $(function () { $('#addCategory').modal('show'); });
    </script>
    @endsection
@endif
