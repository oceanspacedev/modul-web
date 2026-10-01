@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card card-dark">
                            <!-- /.card-header -->
                            <div class="card-header">
                                <h3 class="card-title">EDIT &raquo; {{ $user->full_name }}</h3>
                            </div>
                            <div class="card-body">
                                <form action="/user/{{ $user->id }}" method="POST">
                                    @csrf
                                    <div class="row">
                                        <div class="mb-3 col-lg-4">
                                            <label for="id_karyawan" class="form-label">ID Karyawan</label>
                                            <input type="text" class="form-control" id="id_karyawan" name="id_karyawan"
                                                value="{{ $user->id_karyawan }}" placeholder="Contoh: KRY-001">
                                        </div>
                                        <div class="mb-3 col-lg-4">
                                            <label for="full_name" class="form-label">Nama Lengkap</label>
                                            <input type="text" class="form-control" id="full_name"
                                                value="{{ $user->full_name }}" name="full_name" required>
                                        </div>
                                        <div class="mb-3 col-lg-4">
                                            <label for="username" class="form-label">User Name</label>
                                            <input type="text" class="form-control" id="username" name="username"
                                                value="{{ $user->username }}" required>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="mb-3 col-lg-4">
                                            <label for="email" class="form-label">Email</label>
                                            <input type="email" class="form-control" id="email" name="email"
                                                value="{{ $user->email }}" placeholder="contoh@email.com">
                                        </div>
                                        <div class="mb-3 col-lg-4">
                                            <label for="no_wa" class="form-label">No WhatsApp</label>
                                            <input type="text" class="form-control" id="no_wa" name="no_wa"
                                                value="{{ $user->no_wa }}" placeholder="08xxxxxxxxxx">
                                        </div>
                                        <div class="mb-3 col-lg-4">
                                            <label for="password" class="form-label">Password <small class="text-muted">(Kosongkan jika tidak diubah)</small></label>
                                            <input type="text" class="form-control" id="password" name="password" placeholder="Biarkan kosong jika tidak diubah">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="row">
                                            <div class="col-lg-4">
                                                <label for="job_level_id" class="form-label col-lg-12">job_level</label>
                                                <select class="custom-select col-lg-12" name="job_level_id" id="job_level_id"
                                                    required>
                                                    @foreach ($joblevels as $joblevel)
                                                        <option value="{{ $joblevel->id }}"
                                                            @if ($user->job_level_id === $joblevel->id) selected @endif>
                                                            {{ $joblevel->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-lg-4">
                                                <label for="divisi_id" class="form-label col-lg-12">Divisi</label>
                                                <select class="custom-select col-lg-12 edituserdivisi" id="divisi_id" name="divisi_id"
                                                    required>
                                                    @foreach ($divisis as $divisi)
                                                        <option value="{{ $divisi->id }}"
                                                            {{ $divisi->id === $user->divisi_id ? 'selected' : '' }}>
                                                            {{ $divisi->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-lg-4">
                                                <label for="sub_divisi_id" class="form-label col-lg-12">sub_divisi</label>
                                                <select class="custom-select col-lg-12 editusersubdivisi" id="sub_divisi_id" name="sub_divisi_id"
                                                    >
                                                    @foreach ($subdivisis as $subdivisi)
                                                        <option value="{{ $subdivisi->id }}"
                                                            {{ $subdivisi->id === $user->sub_divisi_id ? 'selected' : '' }}>
                                                            {{ $subdivisi->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-success mt-3">Update</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <!-- /.card -->
                </div>
            </div>
        </section>
    </section>
    <!-- /.content -->
@endsection
