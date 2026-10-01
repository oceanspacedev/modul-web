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
                                <h3 class="card-title">EDIT &raquo; {{ $subdivisi->name }}</h3>
                            </div>
                            <div class="card-body">
                                <form action="/subdivisi/{{ $subdivisi->id }}" method="POST">
                                    @csrf
                                    <div class="mb-3 col-lg-3">
                                        <label for="divisi_id" class="form-label col-lg-12 ">Divisi</label>
                                        <select class="custom-select col-lg-12 adduserdivisi" name="divisi_id"
                                            id="divisi_id" required>
                                            <option value="">--Choose divisi--</option>
                                            @foreach ($divisis as $divisi)
                                                <option value="{{ $divisi->id }}" {{ $subdivisi->divisi->id == $divisi->id ? 'selected' : '' }}>
                                                    {{ $divisi->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-3 col-lg-3">
                                        <label for="name" class="form-label">Nama Sub Divisi</label>
                                        <input type="text" class="form-control" id="name" name="name"
                                            value="{{ $subdivisi->name }}" required>
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
