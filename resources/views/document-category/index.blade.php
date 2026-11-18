@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Kategori Dokumen" subtitle="Jenis dokumen dan tahapannya" eyebrow="Layanan">
            <x-slot:actions>
                <a href="{{ route('document-request.create') }}" class="btn btn-default" target="_blank"><i class="fas fa-external-link-alt"></i> Lihat form</a>
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
                                <td colspan="5"><x-empty-state icon="fa-tags" title="Belum ada kategori" text="Tambahkan kategori pertama di bawah." /></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <form action="{{ route('document-category.store') }}" method="POST" class="d-flex flex-wrap gap-2 m-0">
                    @csrf
                    <input type="text" name="code" maxlength="30" required placeholder="Kode, mis. BA" aria-label="Kode kategori baru"
                        class="form-control form-control-sm @error('code') is-invalid @enderror" style="max-width: 160px;" value="{{ old('code') }}">
                    <input type="text" name="name" maxlength="100" required placeholder="Nama, mis. Berita Acara" aria-label="Nama kategori baru"
                        class="form-control form-control-sm @error('name') is-invalid @enderror" style="max-width: 280px;" value="{{ old('name') }}">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Tambah</button>
                    @error('code')<div class="text-danger small w-100">{{ $message }}</div>@enderror
                    @error('name')<div class="text-danger small w-100">{{ $message }}</div>@enderror
                </form>
            </div>
        </div>
    </div>
</section>
@endsection
