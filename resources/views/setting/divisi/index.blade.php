@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <!-- Main content -->
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-dark">
                            <div class="row d-inline-flex">
                                <h3 class="card-title">Divisi</h3>
                                <a href="#"><button class="badge bg-success mx-3 elevation-0" data-toggle="modal"
                                        data-target="#addDivisi">ADD</button>
                                </a>
                            </div>
                            <div class="card-tools">
                                <div class="input-group input-group-sm" style="width: 150px;">
                                </div>
                            </div>
                        </div>
                        @if ($message = Session::get('success'))
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <strong>{{ $message }}</strong>
                            </div>
                        @endif
                        @if ($message = Session::get('error'))
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <strong>{{ $message }}</strong>
                            </div>
                        @endif
                        <!-- /.card-header -->
                        <div class="card-body table-responsive p-0" style="height: 500px;">
                            <table class="table table-head-fixed text-nowrap">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Nama</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($divisis as $divisi)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $divisi->name }}</td>
                                            <td class="d-flex">
                                                <a href="/divisi/{{ $divisi->id }}"><i class="btn fas fa-edit"
                                                        style="color: rgb(239, 239, 54)"></i></a>
                                                <form action="/divisi/delete" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="id" value="{{ $divisi->id }}">
                                                    <button type="submit" class="btn"
                                                        style="color: rgb(204, 26, 26);"><i
                                                            class="fas fa-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <!-- /.card-body -->
                    </div>
                    <!-- /.card -->
                </div>
            </div>
        </div>
    </section>

    <!-- Modal -->
    <form action="/divisi" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="modal fade" id="addDivisi" tabindex="-1" aria-labelledby="addDivisiLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addDivisiLabel">Add Divisi</h5>
                    </div>
                    <div class="modal-body">
                        <div class="col-12 mt-3">
                            <label for="name" class="form-label">Nama Divisi</label>
                            <input class="form-control" type="text" id="name" name="name">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
    <!-- /.content -->
@endsection
