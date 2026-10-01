@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <!-- WILL ADD ABSENT TABLE HERE -->
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                                    <h3 class="card-title mr-3">
                                        <i class="fas fa-calendar-check text-success mr-1"></i> Data Presence
                                    </h3>
                                    <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#exportAbsent">
                                        <i class="fas fa-file-export mr-1"></i> Export Data
                                    </button>
                                </div>
                                <div class="card-tools ml-auto">
                                    <form action="absent" class="d-inline-flex">
                                        <div class="input-group input-group-sm" style="width: 250px;">
                                            <input type="text" class="form-control" value="" name="date"
                                                id="tanggal" placeholder="Filter tanggal" required>
                                            <div class="input-group-append">
                                                <button type="submit" class="btn btn-default">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="card-body table-responsive p-0" style="height: 500px;">
                                <table class="table table-head-fixed text-nowrap">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>User</th>
                                            <th>Tanggal Check In</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($absents as $absent)
                                            <tr>
                                                <td>{{ $loop->iteration }}</td>
                                                <td>{{ $absent->user->full_name ?? 'Nonactive user'}}</td>
                                                <td>{{ $absent->created_at->format('d M Y H:i') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                        </div>
                        <!-- /.card -->
                    </div>
                </div>
            </div>
            @if (count($absents) == 10)
                    <div class="d-flex justify-content-center">
                        {{ $absents->links() }}
                    </div>
                @endif
        </section>
    </section>

    <!-- Modal -->
    <form action="absent/export" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="modal fade" id="exportAbsent" aria-labelledby="exportAbsenttLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exportAbsenttLabel">Export Absent</h5>
                    </div>
                    <div class="modal-body">
                        <label for="date" class="form-label">Tanggal</label>
                        <div class="row">
                            <div class="input-group input-group-sm mr-3" style="widows: 400px;">
                                <form action="absent" class="d-inline-flex">
                                    <input type="text" class="form-control float-right" value="" name="exportAbsent"
                                        id="tanggalExport" required>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Export</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

@endsection
