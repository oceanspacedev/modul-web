@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <section class="content">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="font-weight-bold text-dark m-0" style="font-size: 1.3rem;">Edit User &raquo; {{ $user->nama_lengkap }}</h3>
                    <a href="{{ route('user.index') }}" class="btn btn-sm btn-default">Kembali</a>
                </div>
                <form action="{{ route('user.update', $user) }}" method="POST">
                    @csrf
                    @method('PUT')
                    @include('setting.user._form')
                    <button type="submit" class="btn btn-success font-weight-bold px-4 mb-4">Simpan Perubahan</button>
                </form>
            </div>
        </section>
    </section>
@endsection
