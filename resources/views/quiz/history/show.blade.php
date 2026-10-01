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
                                    <h3 class="card-title">HISTORY QUIZ &raquo; {{ $history->quiz->document->name }} &raquo;
                                        {{ $history->user->full_name }}</h3>
                                    {{-- <a href="/question/export">
                                        <button class="badge bg-primary mx-3 elevation-0">EXPORT
                                            ALL</button></a> --}}
                                </div>
                                {{-- <div class="card-tools">
                                    <div class="input-group input-group-sm" style="width: 150px;">
                                        <form action={{ '/quiz/history/' . $history->id }} class="d-inline-flex">
                                            <input type="text" name="search" class="form-control float-right"
                                                placeholder="Cari">
                                            <div class="input-group-append">
                                                <button type="submit" class="btn btn-default">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                        </form>
                                    </div>
                                </div>
                            </div> --}}
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
                                        <th>Value</th>
                                        <th>Question</th>
                                        <th>Answer</th>
                                        <th>Right Answer</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($questions as $question)
                                        <tr>
                                            <td>@if ($question->value)
                                                <a href="#"
                                                        class="badge bg-success" data-toggle="tooltip" title="activate"><span><i
                                                                class="far fa-check-circle"></i></span></a>
                                            @else
                                                <a href="#"
                                                        class="badge bg-danger" data-toggle="tooltip" title="activate"><span><i
                                                                class="far fa-times-circle"></i></span></a>
                                            @endif
                                            </td>
                                            <td>{{ $question->question->question }}</td>
                                            <td>{{ $question->option->content }}</td>
                                            <td>{{ $question->question->options->where('is_true', 1)->first()->content }}
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
@endsection
