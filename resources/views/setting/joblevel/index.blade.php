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
                                    <i class="fas fa-briefcase text-secondary mr-1"></i> Data Job Level
                                </h3>
                                <button type="button" class="btn btn-sm btn-success" data-toggle="modal" data-target="#addjoblevel">
                                    <i class="fas fa-plus mr-1"></i> Tambah Job Level
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
                                        <th>Nama Job Level</th>
                                        <th style="width: 120px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($joblevels as $joblevel)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td><strong>{{ $joblevel->name }}</strong></td>
                                            <td>
                                                <div class="d-flex align-items-center" style="gap: 6px;">
                                                    <a href="joblevel/{{ $joblevel->id }}" class="badge bg-warning" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <form action="/joblevel/delete" method="POST" class="d-inline m-0" onsubmit="return confirm('Hapus job level {{ $joblevel->name }}?')">
                                                        @csrf
                                                        <input type="hidden" name="id" value="{{ $joblevel->id }}">
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
    <form action="/joblevel" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="modal fade" id="addjoblevel" tabindex="-1" aria-labelledby="addjoblevelLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addjoblevelLabel">Add Job Level</h5>
                    </div>
                    <div class="modal-body">
                        <div class="col-12 mt-3">
                            <label for="name" class="form-label">Nama Job Level</label>
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
