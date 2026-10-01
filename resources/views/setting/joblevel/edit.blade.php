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
                                <h3 class="card-title">EDIT &raquo; {{ $joblevel->name }}</h3>
                            </div>
                            <div class="card-body">
                                <form action="/joblevel/{{ $joblevel->id }}" method="POST">
                                    @csrf
                                    <div class="mb-3 col-lg-3">
                                        <label for="name" class="form-label">Nama Job Level</label>
                                        <input type="text" class="form-control" id="name" name="name"
                                            value="{{ $joblevel->name }}" required>
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
