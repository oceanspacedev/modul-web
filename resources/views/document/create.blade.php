@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card border shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h3 class="card-title font-weight-bold text-dark m-0" style="font-size: 1.15rem;">Tambah Dokumen Baru</h3>
                            </div>
                            <div class="card-body">
                                <form action="{{ route('document.store') }}" method="POST" enctype="multipart/form-data">
                                    @csrf
                                    @include('layout.partials.validation-errors', ['class' => 'mb-3'])
                                    <div class="row">
                                        <div class="col-lg-5">
                                            <label for="file" class="form-label col-lg-12 font-weight-bold">File Dokumen (PDF)</label>
                                            <input type="file" accept="application/pdf" class="form-control"
                                                id="file" name="file" required>
                                        </div>
                                        <div class="col-lg-3">
                                            <label for="document_type" class="form-label col-lg-12 font-weight-bold">Document Type</label>
                                            <select class="custom-select col-lg-12" name="document_type" id="document_type"
                                                required>
                                                @foreach ($documentypes as $documentype)
                                                    <option value="{{ $documentype->id }}">{{ $documentype->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-lg-4">
                                            <label for="change_note" class="form-label col-lg-12 font-weight-bold">Catatan</label>
                                            <input type="text" class="form-control" id="change_note" name="change_note"
                                                placeholder="Catatan versi (opsional)">
                                        </div>
                                    </div>
                                    <div class="my-3">
                                        <div class="row">
                                            <div class="col-lg-3">
                                                <label for="role_id" class="form-label col-lg-12 font-weight-bold">Jabatan</label>
                                                <select class="custom-select col-lg-12 select2" multiple="multiple" name="role_id[]"
                                                    id="role_id" required>
                                                    <option value="0">SEMUA JABATAN</option>
                                                    @foreach ($roles as $role)
                                                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-lg-3">
                                                <label for="divisi_id" class="form-label col-lg-12 font-weight-bold">Divisi</label>
                                                <select class="custom-select col-lg-12" name="divisi_id"
                                                    id="divisi_id" required>
                                                    <option value="">--Pilih Divisi--</option>
                                                    @foreach ($divisis as $divisi)
                                                        <option value="{{ $divisi->id }}">
                                                            {{ $divisi->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                        </div>
                                    </div>
                                    <a href="{{ route('document.index') }}" class="btn btn-secondary mt-3 mr-1">Kembali</a>
                                    <button type="submit" class="btn btn-primary mt-3">Simpan</button>
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
