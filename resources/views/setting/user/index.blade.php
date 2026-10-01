@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-dark">
                                <div class="row d-inline-flex">
                                    <h3 class="card-title">User</h3>
                                    {{-- <a href="/user/export">
                                        <button class="badge bg-primary mx-3 elevation-0">EXPORT
                                            ALL</button>
                                    </a> --}}
                                    <a href="/user/template"><button class="badge bg-warning mx-3 elevation-0">TEMPLATE
                                            IMPORT</button>
                                    </a>
                                    <a href="#"><button class="badge bg-success mx-3 elevation-0" data-toggle="modal"
                                            data-target="#imporUser">IMPORT</button>
                                        <a href="/user/create"><button class="badge bg-success mx-3 elevation-0">+
                                                ADD</button>
                                        </a>
                                </div>
                                <div class="card-tools">
                                    <div class="input-group input-group-sm" style="width: 150px;">
                                        <form action="/user" class="d-inline-flex">
                                            <input type="text" name="search" class="form-control float-right"
                                                placeholder="Cari">
                                            <div class="input-group-append">
                                                <button type="submit" class="btn btn-default">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                        </form>
                                    </div>
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
                                        <th>Nama Lengkap</th>
                                        <th>User Name</th>
                                        <th>Divisi</th>
                                        <th>Sub Divisi</th>
                                        <th>Job Level</th>
                                        <th>Status</th>
                                        <th>Last Seen</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($users as $user)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $user->full_name }}</td>
                                            <td>{{ $user->username }}</td>
                                            <td>{{ $user->divisi->name }}</td>
                                            <td>{{ $user->subdivisi->name ?? '-' }}</td>
                                            <td>{{ $user->joblevel->name }}</td>
                                            <td>
                                                @if ($user->deleted_at)
                                                    NONAKTIF
                                                @else
                                                    AKTIF
                                                @endif
                                            </td>
                                            <td>{{ count($user->lastSeen) ?  $user->lastSeen[0]->last_used_at->format('d M Y H:i') : 'Inactive' }}</td>
                                            {{-- <td>{{ count($user->lastSeen) }}</td> --}}
                                            <td>
                                                <a href="/user/{{ $user->id }}" class="badge bg-warning"><span><i
                                                            class="fas fa-edit"></i></span></a>
                                                @if ($user->deleted_at)
                                                    <a href="/user/active/{{ $user->id }}" class="badge bg-success"
                                                        onclick="return confirm('Mengaktifkan kembali user {{ $user->full_name }}?')"><span><i
                                                                class="far fa-check-circle"></i></span></a>
                                                @else
                                                    <a href="/user/delete/{{ $user->id }}" class="badge bg-danger"
                                                        onclick="return confirm('Apalah anda yakin menonaktifkan user {{ $user->full_name }}?')"><span><i
                                                                class="far fa-times-circle"></i></span></a>
                                                @endif
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
                {{-- <div class="d-flex justify-content-center">
                    {{ $users->links() }}
                </div> --}}
            </div>
        </section>
    </section>

    <!-- Modal -->
    <form action="/user/import" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="modal fade" id="imporUser" tabindex="-1" aria-labelledby="imporUserLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="imporUserLabel">Import User</h5>
                    </div>
                    <div class="modal-body">
                        <div class="col-12 mt-3">
                            <label for="formFile" class="form-label">Pilih File</label>
                            <input class="form-control" type="file" id="formFile" name="file">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Import</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
    <!-- /.content -->
@endsection
