@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <x-page-header title="Tipe Dokumen" subtitle="Kategori yang dipakai untuk mengelompokkan dokumen." eyebrow="Pengaturan / Master Data">
                <x-slot:actions>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#adddokumentype">
                        <i class="fas fa-plus"></i> Tambah Tipe Dokumen
                    </button>
                </x-slot:actions>
            </x-page-header>

            <x-flash />

            <div class="card">
                {{-- Pencarian dan pembagian halaman dijalankan server; mengetik langsung mencari (lihat layout.footer). --}}
                <div class="card-header filter-toolbar">
                    <form action="{{ route('dokumentype.index') }}" method="GET" class="w-100 m-0" role="search">
                        <x-search-field placeholder="Cari tipe dokumen..." :clear-url="route('dokumentype.index')" style="max-width: 360px;" />
                    </form>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-head-fixed text-nowrap">
                        <thead>
                            <tr>
                                <th style="width: 70px;">No</th>
                                <th>Nama Tipe Dokumen</th>
                                <th style="width: 140px;" class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($dokumentypes as $dokumentype)
                                <tr>
                                    <td class="text-muted">{{ $dokumentypes->firstItem() + $loop->index }}</td>
                                    <td><strong>{{ $dokumentype->name }}</strong></td>
                                    <td class="text-right">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <a href="{{ route('dokumentype.edit', $dokumentype) }}" class="btn btn-default btn-xs">Edit</a>
                                            <form action="{{ route('dokumentype.destroy', $dokumentype) }}" method="POST" class="d-inline m-0" onsubmit="return confirm(@js('Hapus type dokumen '.$dokumentype->name.'?'))">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-default btn-xs text-danger">Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">
                                        @if (filled(request('search')))
                                            <x-empty-state icon="fa-search" title="Tidak ada hasil" text="Tidak ada tipe dokumen yang cocok dengan pencarian ini." />
                                        @else
                                            <x-empty-state icon="fa-folder" title="Belum ada tipe dokumen" text="Tambahkan tipe dokumen pertama untuk mengelompokkan dokumen."><button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#adddokumentype">Tambah Tipe Dokumen</button></x-empty-state>
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="text-muted small">Total {{ $dokumentypes->total() }} tipe dokumen</span>
                    {{ $dokumentypes->links('pagination::bootstrap-4') }}
                </div>
            </div>
        </div>
    </section>

    <!-- Modal -->
    <form action="{{ route('dokumentype.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="modal fade" id="adddokumentype" tabindex="-1" aria-labelledby="adddokumentypeLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="adddokumentypeLabel">Tambah Tipe Dokumen</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group mb-0">
                            <label for="name">Nama Tipe Dokumen</label>
                            <input class="form-control" type="text" id="name" name="name">
                        </div>
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
