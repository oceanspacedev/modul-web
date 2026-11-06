@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <x-page-header title="Edit Divisi" :subtitle="$divisi->name" eyebrow="Pengaturan / Master Data">
                <x-slot:actions>
                    <a href="{{ route('divisi.index') }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Kembali</a>
                </x-slot:actions>
            </x-page-header>

            <x-flash />

            <div class="row">
                <div class="col-lg-6">
                    <div class="card">
                        <form action="{{ route('divisi.update', $divisi) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="name">Nama Divisi</label>
                                    <input type="text" class="form-control" id="name" name="name"
                                        value="{{ old('name', $divisi->name) }}" required>
                                </div>
                                <div class="form-group">
                                    <label for="area_id">Area</label>
                                    <select class="custom-select select2" id="area_id" name="area_id" style="width: 100%;">
                                        <option value="">-- Tanpa Area --</option>
                                        @foreach ($areas as $area)
                                            <option value="{{ $area->id }}" @selected((string) old('area_id', $divisi->area_id) === (string) $area->id)>{{ $area->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group mb-0">
                                    <label for="manager_id">Kepala Divisi</label>
                                    <select class="custom-select select2" id="manager_id" name="manager_id" style="width: 100%;">
                                        <option value="">-- Belum Ada --</option>
                                        @foreach ($managers as $manager)
                                            <option value="{{ $manager->id }}" @selected((string) old('manager_id', $divisi->manager_id) === (string) $manager->id)>{{ $manager->nama_lengkap }}</option>
                                        @endforeach
                                    </select>
                                    <small class="form-text text-muted">Disarankan sebagai approval line default karyawan divisi ini.</small>
                                </div>
                            </div>
                            <div class="card-footer d-flex justify-content-end gap-2">
                                <a href="{{ route('divisi.index') }}" class="btn btn-default">Batal</a>
                                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
