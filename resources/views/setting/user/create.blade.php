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
                                <h3 class="card-title">CREATE &raquo; USER</h3>
                            </div>
                            <div class="card-body">
                                <form action="/user" method="POST">
                                    @csrf
                                    <div class="row">
                                        <div class="mb-3 col-lg-4">
                                            <label for="full_name" class="form-label">Nama Lengkap</label>
                                            <input type="text" class="form-control" id="full_name" name="full_name" autocomplete="off"
                                                required>
                                        </div>
                                        <div class="mb-3 col-lg-4">
                                            <label for="username" class="form-label">User Name</label>
                                            <input type="text" class="form-control" id="username" name="username" autocomplete="off"
                                                required>
                                        </div>
                                        <div class="mb-3 col-lg-4">
                                            <label for="password" class="form-label">Password</label>
                                            <input type="password" class="form-control" id="password" name="password" autocomplete="off"
                                                required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="row">
                                            <div class="col-lg-3">
                                                <label for="job_level_id" class="form-label col-lg-12">Job Level</label>
                                                <select class="custom-select col-lg-12" name="job_level_id" id="job_level_id"
                                                    required>
                                                    @foreach ($joblevels as $joblevel)
                                                        <option value="{{ $joblevel->id }}">{{ $joblevel->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-lg-3">
                                                <label for="divisi_id" class="form-label col-lg-12 ">Divisi</label>
                                                <select class="custom-select col-lg-12 adduserdivisi" name="divisi_id"
                                                    id="divisi_id" required>
                                                    <option value="">--Choose divisi--</option>
                                                    @foreach ($divisis as $divisi)
                                                        <option value="{{ $divisi->id }}" >
                                                            {{ $divisi->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-lg-3">
                                                <label for="sub_divisi_id" class="form-label col-lg-12">Sub Divisi</label>
                                                <select class="custom-select col-lg-12 addusersubdivisi" id="sub_divisi_id"
                                                    name="sub_divisi_id">
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-success mt-3">Simpan</button>
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
