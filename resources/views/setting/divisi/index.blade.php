@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <x-page-header title="Divisi" subtitle="Divisi per area, kepala divisi, dan jumlah karyawannya." eyebrow="Pengaturan / Master Data">
                <x-slot:actions>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addDivisi">
                        <i class="fas fa-plus"></i> Tambah Divisi
                    </button>
                </x-slot:actions>
            </x-page-header>

            <x-flash />

            <div class="card">
                <div class="card-body table-responsive p-0" style="max-height: 560px;">
                    <table class="table table-head-fixed text-nowrap">
                        <thead>
                            <tr>
                                <th style="width: 70px;">No</th>
                                <th>Nama Divisi</th>
                                <th>Area</th>
                                <th>Kepala Divisi</th>
                                <th>Jumlah Karyawan</th>
                                <th style="width: 140px;" class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($divisis as $divisi)
                                <tr>
                                    <td class="text-muted">{{ $loop->iteration }}</td>
                                    <td><strong>{{ $divisi->name }}</strong></td>
                                    <td>{{ $divisi->area->name ?? '-' }}</td>
                                    <td>{{ $divisi->manager->nama_lengkap ?? '-' }}</td>
                                    <td>{{ $divisi->users_count }}</td>
                                    <td class="text-right">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <a href="{{ route('divisi.edit', $divisi) }}" class="btn btn-default btn-xs">Edit</a>
                                            <form action="{{ route('divisi.destroy', $divisi) }}" method="POST" class="d-inline m-0" onsubmit="return confirm(@js('Hapus divisi '.$divisi->name.'?'))">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-default btn-xs text-danger">Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <x-empty-state icon="fa-sitemap" title="Belum ada divisi" text="Tambahkan divisi pertama untuk mengelompokkan karyawan." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <!-- Modal -->
    <form action="{{ route('divisi.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="modal fade" id="addDivisi" tabindex="-1" aria-labelledby="addDivisiLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addDivisiLabel">Tambah Divisi</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="name">Nama Divisi</label>
                            <input class="form-control" type="text" id="name" name="name">
                        </div>
                        <div class="form-group">
                            <label for="area_id">Area</label>
                            <select class="custom-select" id="area_id" name="area_id">
                                <option value="">-- Tanpa Area --</option>
                                @foreach ($areas as $area)
                                    <option value="{{ $area->id }}">{{ $area->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group mb-0">
                            <label for="manager_id">Kepala Divisi</label>
                            <select class="custom-select select2" id="manager_id" name="manager_id" style="width: 100%;">
                                <option value="">-- Belum Ada --</option>
                                @foreach ($managers as $manager)
                                    <option value="{{ $manager->id }}">{{ $manager->nama_lengkap }}</option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">Disarankan sebagai approval line default karyawan divisi ini.</small>
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
