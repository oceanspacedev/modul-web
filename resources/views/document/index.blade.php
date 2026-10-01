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
                                    <h3 class="card-title">Documents</h3>
                                    <a href="/document/export">
                                        <button class="badge bg-primary mx-3 elevation-0">EXPORT
                                            ALL</button>
                                    </a>
                                    {{-- <a href="/document/template"><button class="badge bg-warning mx-3 elevation-0">TEMPLATE
                                            IMPORT</button>
                                    </a>
                                    <a href="#"><button class="badge bg-success mx-3 elevation-0" data-toggle="modal"
                                            data-target="#impordocument">IMPORT</button> --}}
                                    <a href="/document/create"><button class="badge bg-success mx-3 elevation-0">+
                                            ADD</button>
                                    </a>
                                </div>
                                <div class="card-tools d-flex">
                                    <div class="input-group input-group-sm mr-3" style="max-width: 440px;">
                                        <form action="/document" class="d-inline-flex">
                                            <select class="custom-select col-lg-12 mx-2" name="divisi_id" id="divisi_id"
                                                required style="max-width: 180px">
                                                <option value="">Choose Divisi</option>
                                                @foreach ($divisis as $divisi)
                                                    <option value="{{ $divisi->id }}">{{ $divisi->name }}</option>
                                                @endforeach
                                            </select>
                                            <select class="custom-select col-lg-12 mx-2" name="doctype_id" id="doctype_id"
                                                required style="max-width: 200px">
                                                <option value="">Choose Document Type</option>
                                                @foreach ($doctypes as $doctype)
                                                    <option value="{{ $doctype->id }}">{{ $doctype->name }}</option>
                                                @endforeach
                                            </select>
                                            <div class="input-group-append" style="max-width: 20px">
                                                <button type="submit" class="btn btn-default">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                    <div class="input-group input-group-sm" style="width: 220px;">
                                        <form action="/document" class="d-inline-flex">
                                            <input type="text" name="search" class="form-control"
                                                placeholder="Cari">
                                            <div class="input-group-append">
                                                <button type="submit" class="btn btn-default">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                        </form>
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
                                        <th>Document Name</th>
                                        <th>Divisi</th>
                                        <th>Sub Divisi</th>
                                        <th>Job Level</th>
                                        <th>Document Type</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($documents as $document)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $document->name }}</td>
                                            <td>{{ $document->divisi->name }}</td>
                                            <td>{{ $document->subdivisi->name ?? '-' }}</td>
                                            <td>{{ $document->joblevel->name }}</td>
                                            <td>{{ $document->dokumentype->name }}</td>
                                            <td>
                                                @if ($document->deleted_at)
                                                    NONAKTIF
                                                @else
                                                    AKTIF
                                                @endif
                                            </td>
                                            <td>
                                                <a href={{ '/storage/dokumen/'.$document->path }} target='_blank' data-toggle="tooltip" title="view" class="badge bg-primary"><span><i
                                                            class="fas fa-eye"></i></span></a>
                                                <a href="/document/{{ $document->id }}" data-toggle="tooltip" title="edit" class="badge bg-warning"><span><i
                                                            class="fas fa-edit"></i></span></a>
                                                @if ($document->deleted_at)
                                                    <a href="/document/active/{{ $document->id }}"
                                                        class="badge bg-success" data-toggle="tooltip" title="activate"
                                                        onclick="return confirm('Mengaktifkan kembali dokumen {{ $document->name }}?')"><span><i
                                                                class="far fa-check-circle"></i></span></a>
                                                @else
                                                    <a href="/document/delete/{{ $document->id }}"
                                                        class="badge bg-danger" data-toggle="tooltip" title="nonactive"
                                                        onclick="return confirm('Apalah anda yakin menghapus dokumen {{ $document->name }}?')"><span><i
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
                    {{ $documents->links() }}
                </div> --}}
            </div>
        </section>
    </section>

    <!-- Modal -->
    <form action="/document/import" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="modal fade" id="impordocument" tabindex="-1" aria-labelledby="impordocumentLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="impordocumentLabel">Import document</h5>
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
