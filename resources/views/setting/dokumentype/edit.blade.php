@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <x-page-header title="Edit Tipe Dokumen" :subtitle="$dokumentype->name" eyebrow="Pengaturan / Master Data">
                <x-slot:actions>
                    <a href="{{ route('dokumentype.index') }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Kembali</a>
                </x-slot:actions>
            </x-page-header>

            <x-flash />

            <div class="row">
                <div class="col-lg-6">
                    <div class="card">
                        <form action="{{ route('dokumentype.update', $dokumentype) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="card-body">
                                <div class="form-group mb-0">
                                    <label for="name">Nama Tipe Dokumen</label>
                                    <input type="text" class="form-control" id="name" name="name"
                                        value="{{ $dokumentype->name }}" required>
                                </div>
                            </div>
                            <div class="card-footer d-flex justify-content-end gap-2">
                                <a href="{{ route('dokumentype.index') }}" class="btn btn-default">Batal</a>
                                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
