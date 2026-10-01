@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-dark">
                            <div class="row d-inline-flex">
                                <h3 class="card-title">Questions &raquo; {{ $document->name }} &raquo; {{ $nonactive ? 'NONACTIVE' : 'ACTIVE' }}</h3>
                            </div>
                            <div class="card-tools">
                                <div class="input-group input-group-sm" style="width: 550px;">
                                    <a href="/question/{{ $document->id }}/deleteAll"><button class="badge bg-danger">DELETE ALL</button></a>
                                    <a href="/question/{{ $document->id }}/activeAll"><button class="badge bg-success">ACTIVE ALL</button></a>
                                    <form action="/question/{{ $document->id }}" class="d-inline-flex">
                                        <input type="text" name="search" class="form-control float-right"
                                            placeholder="Cari">
                                        <select class="custom-select col-4 mx-1" name="nonactive" id="nonactive"
                                            required>
                                            <option value="0">ACTIVE</option>
                                            <option value="1">NONACTIVE</option>
                                        </select>
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
                                    <th>Action</th>
                                    <th>Question</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($questions as $question)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>
                                            <a href="#" data-id="{{ $question->id }}" data-toggle="tooltip"
                                                title="View" class="badge bg-primary viewoption"><span><i
                                                        class="fas fa-eye"></i></span></a>
                                            @if ($question->deleted_at)
                                                <a href="/question/active/{{ $question->id }}" class="badge bg-success"
                                                    data-toggle="tooltip" title="Activate"
                                                    onclick="return confirm('Mengaktifkan kembali question {{ $question->question }}?')"><span><i
                                                            class="far fa-check-circle"></i></span></a>
                                            @else
                                                <a href="/question/delete/{{ $question->id }}" class="badge bg-danger"
                                                    data-toggle="tooltip" title="Deactivate"
                                                    onclick="return confirm('Apalah anda yakin menonaktifkan {{ $question->question }}?')"><span><i
                                                            class="far fa-times-circle"></i></span></a>
                                            @endif
                                        </td>
                                        <td>{{ $question->question }}</td>
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

    <!-- Modal -->
    <div class="modal fade" id="modalOption" tabindex="-1" aria-labelledby="modalOptionLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalOptionLabel"></h5>
                    <button type="button" class="btn" id="modalClose" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <div id="options">
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
