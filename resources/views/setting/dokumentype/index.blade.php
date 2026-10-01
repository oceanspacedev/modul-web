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
                                    <i class="fas fa-bookmark text-primary mr-1"></i> Data Document Type
                                </h3>
                                <button type="button" class="btn btn-sm btn-success" data-toggle="modal" data-target="#adddokumentype">
                                    <i class="fas fa-plus mr-1"></i> Tambah Document Type
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
                                        <th>Nama Document Type</th>
                                        <th style="width: 120px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($dokumentypes as $dokumentype)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td><strong>{{ $dokumentype->name }}</strong></td>
                                            <td>
                                                <div class="d-flex align-items-center" style="gap: 6px;">
                                                    <a href="dokumentype/{{ $dokumentype->id }}" class="badge bg-warning" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <form action="/dokumentype/delete" method="POST" class="d-inline m-0" onsubmit="return confirm('Hapus type dokumen {{ $dokumentype->name }}?')">
                                                        @csrf
                                                        <input type="hidden" name="id" value="{{ $dokumentype->id }}">
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
    <form action="/dokumentype" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="modal fade" id="adddokumentype" tabindex="-1" aria-labelledby="adddokumentypeLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="adddokumentypeLabel">Add Dokumen Type</h5>
                    </div>
                    <div class="modal-body">
                        <div class="col-12 mt-3">
                            <label for="name" class="form-label">Nama Dokumen Type</label>
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
