@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <x-page-header title="Edit User" :subtitle="$user->nama_lengkap" eyebrow="Pengaturan / User">
                <x-slot:actions>
                    <a href="{{ route('user.index') }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Kembali</a>
                </x-slot:actions>
            </x-page-header>

            <x-flash />

            <form action="{{ route('user.update', $user) }}" method="POST">
                @csrf
                @method('PUT')
                @include('setting.user._form')
                <div class="d-flex justify-content-end gap-2 mb-4">
                    <a href="{{ route('user.index') }}" class="btn btn-default">Batal</a>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </section>
@endsection
