@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <!-- Main content -->
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex align-items-center" style="gap: 8px;">
                                <h3 class="card-title mr-3">
                                    <i class="fas fa-building text-info mr-1"></i> Data Sub Divisi
                                </h3>
                                <button type="button" class="btn btn-sm btn-success" data-toggle="modal" data-target="#addsubdivisi">
                                    <i class="fas fa-plus mr-1"></i> Tambah Sub Divisi
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
                                        <th>Divisi Utama</th>
                                        <th>Nama Sub Divisi</th>
                                        <th style="width: 120px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($subdivisis as $subdivisi)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td><span class="badge badge-light border">{{ $subdivisi->divisi->name }}</span></td>
                                            <td><strong>{{ $subdivisi->name }}</strong></td>
                                            <td>
                                                <div class="d-flex align-items-center" style="gap: 6px;">
                                                    <a href="subdivisi/{{ $subdivisi->id }}" class="badge bg-warning" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <form action="/subdivisi/delete" method="POST" class="d-inline m-0" onsubmit="return confirm('Hapus subdivisi {{ $subdivisi->name }}?')">
                                                        @csrf
                                                        <input type="hidden" name="id" value="{{ $subdivisi->id }}">
                                                        <button type="submit" class="badge bg-danger border-0" title="Hapus">
                                                            <i class="fas fa-trash"></i>
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
    <form action="/subdivisi" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="modal fade" id="addsubdivisi" tabindex="-1" aria-labelledby="addsubdivisiLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addsubdivisiLabel">Add Sub Divisi</h5>
                    </div>
                    <div class="modal-body">
                        <div class="col-6 mt-3">
                            <label for="divisi_id" class="form-label col-lg-12">Main Divisi</label>
                            <select class="custom-select col-lg-12" name="divisi_id" id="divisi_id" required>
                                <option value=''>--Choose Main Divisi--</option>
                                @foreach ($divisis as $divisi)
                                    <option value="{{ $divisi->id }}">
                                        {{ $divisi->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 mt-3">
                            <label for="name" class="form-label">Nama Sub Divisi</label>
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
