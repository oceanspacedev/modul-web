@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card border shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h3 class="card-title font-weight-bold text-dark m-0" style="font-size: 1.15rem;">Edit Job Level &raquo; {{ $joblevel->name }}</h3>
                            </div>
                            <div class="card-body">
                                <form action="/joblevel/{{ $joblevel->id }}" method="POST">
                                    @csrf
                                    <div class="mb-3 col-lg-4">
                                        <label for="name" class="form-label font-weight-bold">Nama Job Level</label>
                                        <input type="text" class="form-control" id="name" name="name"
                                            value="{{ $joblevel->name }}" required>
                                    </div>
                                    <a href="/joblevel" class="btn btn-secondary mt-3 mr-1">Kembali</a>
                                    <button type="submit" class="btn btn-primary mt-3">Simpan Perubahan</button>
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
