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
                                    <h3 class="card-title">Dashboard</h3>
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
                            <div class="card-body table-responsive p-0" style="height: 750px;">
                                <br>
                                <div class="row">
                                    <div class="col-md-3 col-sm-6">
                                        <div class="info-box">
                                            <span class="info-box-icon bg-gradient-lightblue"><img src="{{ asset('assets') }}/user.png" width='25'
                                                    height='25' class='mr-1'></span>
                                            <a href="/user">
                                                <div class="info-box-content">
                                                    <small class="info-box-text text-dark">Total User</small>
                                                    <span
                                                        class="info-box-number text-dark">{{ $data['totalUser'] }}</span>
                                                </div>
                                            </a>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                        <div class="info-box">
                                            <span class="info-box-icon bg-gradient-lightblue"><img src="{{ asset('assets') }}/document.png" width='25'
                                                    height='25' class='mr-1'></span>
                                            <a href="#">
                                                <div class="info-box-content">
                                                    <small class="info-box-text text-dark">Total Document</small>
                                                    <span
                                                        class="info-box-number text-dark">{{ $data['totalDocument'] }}</span>
                                                </div>
                                            </a>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                        <div class="info-box">
                                            <span class="info-box-icon bg-gradient-lightblue"><img src="{{ asset('assets') }}/quiz.png" width='25'
                                                    height='25' class='mr-1'></span>
                                            <a href="#">
                                                <div class="info-box-content">
                                                    <small class="info-box-text text-dark">Total Quiz</small>
                                                    <span
                                                        class="info-box-number text-dark">{{ $data['totalQuiz'] }}</span>
                                                </div>
                                            </a>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                        <div class="info-box">
                                            <span class="info-box-icon bg-gradient-lightblue"><img src="{{ asset('assets') }}/question.png" width='25'
                                                    height='25' class='mr-1'></span>
                                            <a href="#">
                                                <div class="info-box-content">
                                                    <small class="info-box-text text-dark">Total History</small>
                                                    <span
                                                        class="info-box-number text-dark">{{ $data['totalHistory'] }}</span>
                                                </div>
                                            </a>
                                        </div>
                                    </div>
                                    <div id="container"></div>

                                </div>
                                <!-- WILL ADD ABSENT TABLE HERE -->
                            </div>
                            <!-- /.card-body -->
                        </div>
                        <!-- /.card -->
                    </div>
                </div>
            </div>
        </section>
    </section>
@endsection
