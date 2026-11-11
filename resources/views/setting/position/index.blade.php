@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <x-page-header title="Posisi" subtitle="Posisi kerja karyawan (berbeda dari Jabatan dan Hak Akses) beserta jumlah pemegangnya." eyebrow="Pengaturan / Master Data">
                <x-slot:actions>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addPosition">
                        <i class="fas fa-plus"></i> Tambah Posisi
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
                                <th>Nama Posisi</th>
                                <th style="width: 160px;">Jumlah Karyawan</th>
                                <th style="width: 140px;" class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($positions as $position)
                                <tr>
                                    <td class="text-muted">{{ $loop->iteration }}</td>
                                    <td><strong>{{ $position->name }}</strong></td>
                                    <td>{{ $position->users_count }}</td>
                                    <td class="text-right">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <a href="{{ route('position.edit', $position) }}" class="btn btn-default btn-xs">Edit</a>
                                            <form action="{{ route('position.destroy', $position) }}" method="POST" class="d-inline m-0" onsubmit="return confirm(@js('Hapus posisi '.$position->name.'?'))">
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
                                        <x-empty-state icon="fa-id-badge" title="Belum ada posisi" text="Tambahkan posisi pertama untuk dipakai pada data karyawan."><button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addPosition">Tambah Posisi</button></x-empty-state>
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
    <form action="{{ route('position.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="modal fade" id="addPosition" tabindex="-1" aria-labelledby="addPositionLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addPositionLabel">Tambah Posisi</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group mb-0">
                            <label for="name">Nama Posisi</label>
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
