@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card card-dark">
                            <!-- /.card-header -->
                            <div class="card-header">
                                <h3 class="card-title">EDIT QUIZ &raquo; {{ $quiz->document->name}}</h3>
                            </div>
                            <div class="card-body">
                                <form action="/quiz/{{ $quiz->id }}" method="POST">
                                    @csrf
                                    <div class="row">
                                        <div class="mb-3 col-lg-3">
                                            <label for="document_name" class="form-label">Nama Modul</label>
                                        <input type="text" class="form-control" id="document_name" name="document_name"
                                            value="{{ $quiz->document->name}}" disabled>
                                        </div>
                                        <div class="mb-3 col-lg-3">
                                            <label for="date" class="form-label">Start</label>
                                            <input type="datetime-local" min="{{ date('Y-m-d H:i') }}" class="form-control" id="start" name="start"
                                            value="{{ date('Y-m-d H:i', $quiz->start / 1000) }}"
                                                required>
                                        </div>
                                         <div class="mb-3 col-lg-3">
                                            <label for="date" class="form-label">End</label>
                                            <input type="datetime-local" min="{{ date('Y-m-d H:i') }}" class="form-control" id="end" name="end"
                                            value="{{ date('Y-m-d H:i', $quiz->end / 1000) }}"
                                                required>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-success mt-3">Update</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <!-- /.card -->
                </div>
            </div>
        </section>
    </section>
    <!-- /.content -->
@endsection
