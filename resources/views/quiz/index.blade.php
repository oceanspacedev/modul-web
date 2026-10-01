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
                                <h3 class="card-title">Quiz</h3>
                                <a href="#"><button class="badge bg-success mx-3 elevation-0" data-toggle="modal"
                                        data-target="#addQuiz">ADD</button>
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
                                        <th>Action</th>
                                        <th>Status</th>
                                        <th>Modul</th>
                                        <th>Job Level</th>
                                        <th>Start</th>
                                        <th>End</th>
                                        <th>Result</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($quizs as $quiz)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>
                                                @if (now() > Carbon\Carbon::parse($quiz->start / 1000))
                                                    -
                                                @else
                                                    <a href="/quiz/{{ $quiz->id }}" data-toggle="tooltip" title="edit" class="badge bg-warning"><span><i
                                                            class="fas fa-edit"></i></span></a>
                                                            <a href="/quiz/delete/{{ $quiz->id }}"
                                                        class="badge bg-danger" data-toggle="tooltip" title="nonactive"
                                                        onclick="return confirm('Apalah anda yakin menghapus quiz {{ $quiz->document->name }}?')"><span><i
                                                                class="far fa-times-circle"></i></span></a>
                                                @endif
                                            </td>
                                            <td
                                                class={{ now() > Carbon\Carbon::parse($quiz->start / 1000) && now() < Carbon\Carbon::parse($quiz->end / 1000)
                                                    ? 'text-success'
                                                    : (now() < Carbon\Carbon::parse($quiz->start / 1000)
                                                        ? 'text-primary'
                                                        : 'text-danger') }}>
                                                {{ now() > Carbon\Carbon::parse($quiz->start / 1000) && now() < Carbon\Carbon::parse($quiz->end / 1000)
                                                    ? 'ON GOING'
                                                    : (now() < Carbon\Carbon::parse($quiz->start / 1000)
                                                        ? 'SCHEDULED'
                                                        : 'EXPIRED') }}
                                            </td>
                                            <td>{{ $quiz->document->name }}</td>
                                            <td>{{ $quiz->document->joblevel->name }}</td>
                                            <td>{{ date('d M Y H:i', $quiz->start / 1000) }}</td>
                                            <td>{{ date('d M Y H:i', $quiz->end / 1000) }}</td>
                                            <td><a 
                                                    data-toggle="modal" title="download" class="badge bg-primary"><span><i
                                                            class="fas fa-download" data-id="{{ $quiz->id }}" onclick="$('#dataid').val($(this).data('id')); $('#resultQuiz').modal('show');"></i> Result </span></a>
                                                <a href={{ '/quiz/history/exportall/' . $quiz->id }} target='#'
                                                    data-toggle="tooltip" title="download" class="badge bg-success"><span><i
                                                            class="fas fa-download"></i> Detailed </span></a>
                                                {{-- <button data-id="{{ $quiz->id }}"  onclick="$('#dataid').val($(this).data('id')); $('#resultQuiz').modal('show');" >click me</button> --}}
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

    <!-- Modal Result-->
    <form action="quiz/result/export/{{ $quiz->id }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="modal fade" id="resultQuiz" tabindex="-1" aria-labelledby="resultQuiz" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addQuizLabel">Download Result</h5>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3 col-lg-12">
                            <label for="dataid" class="form-label">ID Quiz</label>
                            <input type="text" name="dataid" id="dataid" class="form-control" readonly/>
                        </div>
                        <div class="mb-3 col-lg-12">
                            <label for="kkm" class="form-label">Nilai Min</label>
                            <input type="text" class="form-control" id="kkm" name="kkm" required>
                        </div>
                        <div class="mb-3 col-lg-12">
                            <label for="denda" class="form-label">Denda</label>
                            <input type="text" class="form-control" id="denda" name="denda" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Download</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Modal -->
    <form action="/quiz" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="modal fade" id="addQuiz" tabindex="-1" aria-labelledby="addQuizLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addQuizLabel">Add quiz</h5>
                    </div>
                    <div class="modal-body">
                        <div class="col-lg-12 mt-3">
                            <label for="document_id" class="form-label col-lg-12">Modul</label>
                            <select class="custom-select col-lg-12" name="document_id" id="document_id" required>
                                @foreach ($documents as $document)
                                    <option value="{{ $document->id }}">{{ $document->name.' - '.$document->joblevel->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3 col-lg-12">
                            <label for="date" class="form-label">Start</label>
                            <input type="datetime-local" class="form-control" id="start" name="start" required>
                        </div>
                        <div class="mb-3 col-lg-12">
                            <label for="date" class="form-label">End</label>
                            <input type="datetime-local" class="form-control" id="end" name="end" required>
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
