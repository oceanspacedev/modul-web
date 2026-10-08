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
                                Data Quiz
                            </h3>
                            <div class="card-tools m-0">
                                <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#addQuiz">
                                    <i class="fas fa-plus mr-1"></i> Tambah Quiz
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
                                    @forelse ($quizs as $quiz)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>
                                                @if (now() > Carbon\Carbon::parse($quiz->start / 1000))
                                                    <span class="text-muted">-</span>
                                                @else
                                                    <a href="/quiz/{{ $quiz->id }}" class="btn btn-default btn-xs mr-1" title="Edit Quiz">
                                                        Edit
                                                    </a>
                                                    <a href="/quiz/delete/{{ $quiz->id }}"
                                                       class="btn btn-outline-danger btn-xs"
                                                       title="Hapus Quiz"
                                                       onclick="return confirm('Apakah Anda yakin menghapus quiz {{ $quiz->document->name ?? '' }}?')">
                                                        Hapus
                                                    </a>
                                                @endif
                                            </td>
                                            <td>
                                                @if(now() > Carbon\Carbon::parse($quiz->start / 1000) && now() < Carbon\Carbon::parse($quiz->end / 1000))
                                                    <span class="badge badge-success px-2 py-1">ON GOING</span>
                                                @elseif(now() < Carbon\Carbon::parse($quiz->start / 1000))
                                                    <span class="badge badge-primary px-2 py-1">SCHEDULED</span>
                                                @else
                                                    <span class="badge badge-secondary px-2 py-1">EXPIRED</span>
                                                @endif
                                            </td>
                                            <td>{{ $quiz->document->name ?? '-' }}</td>
                                            <td>{{ $quiz->document->joblevel->name ?? '-' }}</td>
                                            <td>{{ date('d M Y H:i', $quiz->start / 1000) }}</td>
                                            <td>{{ date('d M Y H:i', $quiz->end / 1000) }}</td>
                                            <td>
                                                <button type="button" class="btn btn-default btn-xs mr-1" title="Unduh Hasil" onclick="$('#dataid').val('{{ $quiz->id }}'); $('#formExportResult').attr('action', '/quiz/result/export/{{ $quiz->id }}'); $('#resultQuiz').modal('show');">
                                                    Result
                                                </button>
                                                <a href="/quiz/history/exportall/{{ $quiz->id }}" target="_blank" class="btn btn-default btn-xs" title="Unduh Rincian">
                                                    Detailed
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-3">
                                                Belum ada data Quiz. Silakan klik tombol <strong>Tambah Quiz</strong> di atas untuk membuat Quiz baru.
                                            </td>
                                        </tr>
                                    @endforelse
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
    <form id="formExportResult" action="/quiz/result/export/{{ isset($quiz) ? $quiz->id : 0 }}" method="POST" enctype="multipart/form-data">
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
