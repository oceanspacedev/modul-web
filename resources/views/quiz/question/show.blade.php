@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-white py-3 d-block">
                            <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap: 12px;">
                                <h3 class="card-title font-weight-bold text-dark m-0" style="font-size: 1.15rem;">
                                    Questions &raquo; {{ $document->name }} &raquo; <span class="badge {{ $nonactive ? 'badge-secondary' : 'badge-success' }}">{{ $nonactive ? 'NONACTIVE' : 'ACTIVE' }}</span>
                                </h3>
                                <div class="card-tools m-0">
                                    <form action="/question/{{ $document->id }}" method="GET" class="d-inline-flex m-0 align-items-center" style="gap: 6px;">
                                        <select class="custom-select custom-select-sm" name="nonactive" id="nonactive" style="width: 130px;" onchange="this.form.submit()">
                                            <option value="0" {{ request('nonactive') == '0' ? 'selected' : '' }}>ACTIVE</option>
                                            <option value="1" {{ request('nonactive') == '1' ? 'selected' : '' }}>NONACTIVE</option>
                                        </select>
                                        <div class="input-group input-group-sm" style="width: 200px;">
                                            <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Cari...">
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
                                <a href="/question/{{ $document->id }}/deleteAll" class="btn btn-sm btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus semua pertanyaan?')">
                                    <i class="fas fa-trash-alt mr-1"></i> Delete All
                                </a>
                                <a href="/question/{{ $document->id }}/activeAll" class="btn btn-sm btn-success">
                                    <i class="fas fa-check-circle mr-1"></i> Active All
                                </a>
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
                                                <button type="button" data-id="{{ $question->id }}" class="btn btn-default btn-xs mr-1 viewoption" title="Lihat Pilihan">
                                                    Lihat
                                                </button>
                                                @if ($question->deleted_at)
                                                    <a href="/question/active/{{ $question->id }}" class="btn btn-outline-success btn-xs"
                                                        onclick="return confirm('Mengaktifkan kembali question {{ $question->question }}?')">
                                                        Aktifkan
                                                    </a>
                                                @else
                                                    <a href="/question/delete/{{ $question->id }}" class="btn btn-outline-danger btn-xs"
                                                        onclick="return confirm('Apakah Anda yakin menonaktifkan {{ $question->question }}?')">
                                                        Nonaktifkan
                                                    </a>
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
