@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <x-page-header title="Area" subtitle="Badan usaha yang terdaftar beserta jumlah karyawannya." eyebrow="Pengaturan / Master Data">
                <x-slot:actions>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addArea">
                        <i class="fas fa-plus"></i> Tambah Area
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
                                <th>Nama Area</th>
                                <th style="width: 160px;">Jumlah Karyawan</th>
                                <th style="width: 140px;" class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($areas as $area)
                                <tr>
                                    <td class="text-muted">{{ $loop->iteration }}</td>
                                    <td><strong>{{ $area->name }}</strong></td>
                                    <td>{{ $area->users_count }}</td>
                                    <td class="text-right">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <a href="{{ route('area.edit', $area) }}" class="btn btn-default btn-xs">Edit</a>
                                            <form action="{{ route('area.destroy', $area) }}" method="POST" class="d-inline m-0" onsubmit="return confirm(@js('Hapus area '.$area->name.'?'))">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-default btn-xs text-danger">Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">
                                        <x-empty-state icon="fa-building" title="Belum ada area" text="Tambahkan badan usaha pertama untuk mulai mengelompokkan karyawan." />
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
    <form action="{{ route('area.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="modal fade" id="addArea" tabindex="-1" aria-labelledby="addAreaLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addAreaLabel">Tambah Area</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group mb-0">
                            <label for="name">Nama Area</label>
                            <input class="form-control" type="text" id="name" name="name" placeholder="Contoh: PT Maju Bersama">
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
