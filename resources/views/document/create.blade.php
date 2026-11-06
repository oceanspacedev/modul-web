@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <x-page-header title="Tambah Dokumen" subtitle="Unggah dokumen PDF dan tentukan divisi serta jabatan yang dapat membukanya." eyebrow="Konten / Dokumen">
                <x-slot:actions>
                    <a href="{{ route('document.index') }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Kembali</a>
                </x-slot:actions>
            </x-page-header>

            <x-flash />

            <form action="{{ route('document.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Informasi Dokumen</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-5 form-group">
                                <label for="file">File Dokumen (PDF)</label>
                                <input type="file" accept="application/pdf" class="form-control"
                                    id="file" name="file" required>
                            </div>
                            <div class="col-lg-3 form-group">
                                <label for="document_type">Tipe Dokumen</label>
                                <select class="custom-select select2" name="document_type" id="document_type" style="width: 100%;"
                                    required>
                                    @foreach ($documentypes as $documentype)
                                        <option value="{{ $documentype->id }}">{{ $documentype->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-4 form-group">
                                <label for="change_note">Catatan</label>
                                <input type="text" class="form-control" id="change_note" name="change_note"
                                    placeholder="Catatan versi (opsional)">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-3 form-group mb-lg-0">
                                <label for="role_id">Jabatan</label>
                                <select class="custom-select col-lg-12 select2" multiple="multiple" name="role_id[]"
                                    id="role_id" required>
                                    <option value="0">SEMUA JABATAN</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-3 form-group mb-0">
                                <label for="divisi_id">Divisi</label>
                                <select class="custom-select select2" name="divisi_id" style="width: 100%;"
                                    id="divisi_id" required>
                                    <option value="">--Pilih Divisi--</option>
                                    @foreach ($divisis as $divisi)
                                        <option value="{{ $divisi->id }}">
                                            {{ $divisi->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mt-3">
                            @include('learning_module.partials.material-quiz-fields', ['quiz' => null])
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-end gap-2">
                        <a href="{{ route('document.index') }}" class="btn btn-default">Batal</a>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </div>
            </form>
        </div>
    </section>
@endsection
