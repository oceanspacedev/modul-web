@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                                    <h3 class="card-title mr-3">
                                        <i class="fas fa-users-cog text-primary mr-1"></i> Data User
                                    </h3>
                                    <a href="/user/create" class="btn btn-sm btn-success">
                                        <i class="fas fa-plus mr-1"></i> Tambah User
                                    </a>
                                    <a href="/user/export" class="btn btn-sm btn-primary">
                                        <i class="fas fa-file-export mr-1"></i> Export All
                                    </a>
                                    <a href="/user/template" class="btn btn-sm btn-warning">
                                        <i class="fas fa-download mr-1"></i> Template
                                    </a>
                                    <button type="button" class="btn btn-sm btn-info text-white" data-toggle="modal" data-target="#imporUser">
                                        <i class="fas fa-file-import mr-1"></i> Import
                                    </button>
                                </div>
                                <div class="card-tools ml-auto">
                                    <form action="/user" method="GET" class="d-inline-flex">
                                        <div class="input-group input-group-sm" style="width: 220px;">
                                            <input type="text" name="search" value="{{ request('search') }}" class="form-control"
                                                placeholder="Cari user / ID...">
                                            <div class="input-group-append">
                                                <button type="submit" class="btn btn-default">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
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
                                        <th style="width: 60px;">No</th>
                                        <th>ID Karyawan</th>
                                        <th>Nama Lengkap</th>
                                        <th>User Name</th>
                                        <th>Email</th>
                                        <th>No WA</th>
                                        <th>Divisi</th>
                                        <th>Sub Divisi</th>
                                        <th>Job Level</th>
                                        <th>Status</th>
                                        <th>Last Seen</th>
                                        <th style="width: 100px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($users as $user)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td><span class="badge badge-light border">{{ $user->id_karyawan ?? '-' }}</span></td>
                                            <td><strong>{{ $user->full_name }}</strong></td>
                                            <td><span class="text-muted">{{ $user->username }}</span></td>
                                            <td>{{ $user->email ?? '-' }}</td>
                                            <td>{{ $user->no_wa ?? '-' }}</td>
                                            <td>{{ $user->divisi->name }}</td>
                                            <td>{{ $user->subdivisi->name ?? '-' }}</td>
                                            <td><span class="badge badge-light border">{{ $user->joblevel->name }}</span></td>
                                            <td>
                                                @if ($user->deleted_at)
                                                    <span class="badge badge-secondary">NONAKTIF</span>
                                                @else
                                                    <span class="badge badge-success">AKTIF</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if(count($user->lastSeen))
                                                    <span class="text-sm font-weight-500">{{ $user->lastSeen[0]->last_used_at->format('d M Y H:i') }}</span>
                                                @else
                                                    <span class="text-muted text-sm">Inactive</span>
                                                @endif
                                            </td>
                                            <td>
                                                <a href="/user/{{ $user->id }}" class="badge bg-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                @if ($user->deleted_at)
                                                    <a href="/user/active/{{ $user->id }}" class="badge bg-success" title="Aktifkan"
                                                        onclick="return confirm('Mengaktifkan kembali user {{ $user->full_name }}?')">
                                                        <i class="far fa-check-circle"></i>
                                                    </a>
                                                @else
                                                    <a href="/user/delete/{{ $user->id }}" class="badge bg-danger" title="Nonaktifkan"
                                                        onclick="return confirm('Apakah anda yakin menonaktifkan user {{ $user->full_name }}?')">
                                                        <i class="far fa-times-circle"></i>
                                                    </a>
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
