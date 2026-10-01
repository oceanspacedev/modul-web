@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-white py-3 d-block">
                                <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap: 12px;">
                                    <h3 class="card-title font-weight-bold text-dark m-0" style="font-size: 1.2rem;">
                                        Questions
                                    </h3>
                                    <div class="card-tools m-0">
                                        <form action="/question" method="GET" class="d-inline-flex m-0">
                                            <div class="input-group input-group-sm" style="width: 220px;">
                                                <input type="text" name="search" value="{{ request('search') }}" class="form-control"
                                                    placeholder="Cari pertanyaan...">
                                                <div class="input-group-append">
                                                    <button type="submit" class="btn btn-default">
                                                        <i class="fas fa-search"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center flex-wrap mt-3" style="gap: 8px;">
                                    <a href="/question/template" class="btn btn-sm btn-primary">
                                        <i class="fas fa-download mr-1"></i> Template Import
                                    </a>
                                    <button type="button" class="btn btn-sm btn-success" data-toggle="modal" data-target="#importQuestion">
                                        <i class="fas fa-file-import mr-1"></i> New Import
                                    </button>
                                    <button type="button" class="btn btn-sm btn-warning" data-toggle="modal" data-target="#extraimportQuestion">
                                        <i class="fas fa-sync-alt mr-1"></i> Update Question
                                    </button>
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
                                        <th>Action</th>
                                        <th>Status Modul</th>
                                        <th>Modul</th>
                                        <th>Job Level</th>
                                        <th>Total Question</th>
                                        <th>Active Question</th>
                                        <th>NonActive Question</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($documents as $document)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td><a href="/question/{{ $document->id }}">View</a></td>
                                            <td class={{ $document->deleted_at ? "text-danger": "text-success" }} >{{ $document->deleted_at ? 'Nonactive' : 'Active' }}</td>
                                            <td>{{ $document->name }}</td>
                                            <td>{{ $document->joblevel->name }}</td>
                                            <td class="text-primary">{{ $questions->where('document_id', $document->id)->count() }}</td>
                                            <td class="text-success">{{ $questions->where('document_id', $document->id)->whereNull('deleted_at')->count() }}
                                            </td>
                                            <td class="text-danger">{{ $questions->where('document_id', $document->id)->whereNotNull('deleted_at')->count() }}
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
        </section>
    </section>
    <!-- Modal -->
    <form action="/question/import" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="modal fade" id="importQuestion" tabindex="-1" aria-labelledby="importQuestionLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="importQuestionLabel">Import Question</h5>
                    </div>
                    <div class="modal-body">
                        <div class="col-lg-12 mt-3">
                            <label for="document_id" class="form-label col-lg-12">Modul</label>
                            <select class="custom-select col-lg-12" name="document_id" id="document_id" required>
                                @foreach ($alldocs as $document)
                                    <option value="{{ $document->id }}">{{ $document->name }} - {{ $document->joblevel->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 mt-3">
                            <label for="formFile" class="form-label">Pilih File</label>
                            <input class="form-control" type="file" id="formFile" name="file">
                        </div>
                            <input class="form-control" type="hidden" name="type" value="newImport">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Import</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Modal -->
    <form action="/question/import" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="modal fade" id="extraimportQuestion" tabindex="-1" aria-labelledby="extraimportQuestionLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="extraimportQuestionLabel">Extra Import Question</h5>
                    </div>
                    <div class="modal-body">
                        <div class="col-lg-12 mt-3">
                            <label for="document_id" class="form-label col-lg-12">Modul</label>
                            <select class="custom-select col-lg-12" name="document_id" id="document_id" required>
                                @foreach ($documents as $document)
                                    <option value="{{ $document->id }}">{{ $document->name }} - {{ $document->joblevel->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 mt-3">
                            <label for="formFile" class="form-label">Pilih File</label>
                            <input class="form-control" type="file" id="formFile" name="file">
                        </div>
                            <input class="form-control" type="hidden" name="type" value="updateImport">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Extra Import</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
    <!-- /.content -->
@endsection
