@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <x-page-header title="Tambah User" subtitle="Daftarkan karyawan baru beserta jabatan, atasan, dan data loginnya." eyebrow="Pengaturan / User">
                <x-slot:actions>
                    <a href="{{ route('user.index') }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Kembali</a>
                </x-slot:actions>
            </x-page-header>

            <x-flash />

            <form action="{{ route('user.store') }}" method="POST">
                @csrf
                @include('setting.user._form')
                <div class="d-flex justify-content-end gap-2 mb-4">
                    <a href="{{ route('user.index') }}" class="btn btn-default">Batal</a>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </section>
@endsection
