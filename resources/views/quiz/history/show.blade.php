@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-white py-3">
                                <h3 class="card-title font-weight-bold text-dark m-0" style="font-size: 1.15rem;">
                                    History Quiz &raquo; {{ $history->quiz->document->name }} &raquo; {{ $history->user->full_name }}
                                </h3>
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
                                        <th>Status</th>
                                        <th>Question</th>
                                        <th>Answer</th>
                                        <th>Right Answer</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($questions as $question)
                                        <tr>
                                            <td>
                                                @if ($question->value)
                                                    <span class="badge badge-success px-2 py-1"><i class="fas fa-check mr-1"></i> Benar</span>
                                                @else
                                                    <span class="badge badge-danger px-2 py-1"><i class="fas fa-times mr-1"></i> Salah</span>
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
