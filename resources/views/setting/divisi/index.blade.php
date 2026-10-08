@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <!-- Main content -->
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h3 class="card-title font-weight-bold text-dark m-0" style="font-size: 1.2rem;">
                                Data Divisi
                            </h3>
                            <div class="card-tools m-0">
                                <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#addDivisi">
                                    Tambah Divisi
                                </button>
                            </div>
                        </div>
                        @if ($message = Session::get('success'))
                            <div class="alert alert-success alert-dismissible fade show mx-3 mt-3 mb-0" role="alert">
                                <strong>{{ $message }}</strong>
                            </div>
                        @endif
                        @if ($message = Session::get('error'))
                            <div class="alert alert-danger alert-dismissible fade show mx-3 mt-3 mb-0" role="alert">
                                <strong>{{ $message }}</strong>
                            </div>
                        @endif
                        <!-- /.card-header -->
                        <div class="card-body table-responsive p-0" style="height: 520px;">
                            <table class="table table-head-fixed text-nowrap">
                                <thead>
                                    <tr>
                                        <th style="width: 70px;">No</th>
                                        <th>Nama Divisi</th>
                                        <th style="width: 120px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($divisis as $divisi)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td><strong>{{ $divisi->name }}</strong></td>
                                            <td>
                                                <div class="d-flex align-items-center" style="gap: 4px;">
                                                    <a href="/divisi/{{ $divisi->id }}" class="btn btn-default btn-xs">
                                                        Edit
                                                    </a>
                                                    <form action="/divisi/delete" method="POST" class="d-inline m-0" onsubmit="return confirm('Hapus divisi {{ $divisi->name }}?')">
                                                        @csrf
                                                        <input type="hidden" name="id" value="{{ $divisi->id }}">
                                                        <button type="submit" class="btn btn-default btn-xs text-danger">
                                                            Hapus
                                                        </button>
                                                    </form>
                                                </div>
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
