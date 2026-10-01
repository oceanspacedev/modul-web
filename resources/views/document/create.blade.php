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
                                <h3 class="card-title">CREATE &raquo; Document</h3>
                            </div>
                            <div class="card-body">
                                <form action="/document" method="POST" enctype="multipart/form-data">
                                    @csrf
                                    <div class="row">
                                        <div class="col-lg-5">
                                            <label for="file" class="form-label col-lg-12">Document File</label>
                                            <input type="file" accept="application/pdf" class="form-control"
                                                id="file" name="file" required>
                                        </div>
                                        <div class="col-lg-3">
                                            <label for="document_type" class="form-label col-lg-12">Document Type</label>
                                            <select class="custom-select col-lg-12" name="document_type" id="document_type"
                                                required>
                                                @foreach ($documentypes as $documentype)
                                                    <option value="{{ $documentype->id }}">{{ $documentype->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="my-3">
                                        <div class="row">
                                            <div class="col-lg-3">
                                                <label for="job_level_id" class="form-label col-lg-12">Job Level</label>
                                                <select class="custom-select col-lg-12 select2 bg-black" multiple="multiple" name="job_level_id[]"
                                                    id="job_level_id" required>
                                                    @foreach ($joblevels as $joblevel)
                                                        <option value="{{ $joblevel->id }}">{{ $joblevel->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-lg-3">
                                                <label for="divisi_id" class="form-label col-lg-12 ">Divisi</label>
                                                <select class="custom-select col-lg-12 adduserdivisi" name="divisi_id"
                                                    id="divisi_id" required>
                                                    <option value="">--Choose divisi--</option>
                                                    @foreach ($divisis as $divisi)
                                                        <option value="{{ $divisi->id }}">
                                                            {{ $divisi->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-lg-3">
                                                <label for="sub_divisi_id" class="form-label col-lg-12">Sub Divisi</label>
                                                <select class="custom-select col-lg-12 addusersubdivisi" id="sub_divisi_id"
                                                    name="sub_divisi_id">
                                                </select>
                                            </div>

                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-success mt-3">Simpan</button>
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
