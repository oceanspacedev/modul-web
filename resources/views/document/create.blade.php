@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <x-page-header title="Tambah Dokumen" subtitle="Unggah dokumen PDF dan tentukan divisi serta jabatan yang dapat membukanya." :breadcrumbs="['Dokumen' => route('document.index'), 'Tambah' => null]">
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
                                <label for="file">File Dokumen (PDF) <span class="text-danger">*</span></label>
                                <input type="file" accept="application/pdf" class="form-control"
                                    id="file" name="file" required>
                            </div>
                            <div class="col-lg-3 form-group">
                                <label for="document_type">Tipe Dokumen <span class="text-danger">*</span></label>
                                <select class="custom-select select2" name="document_type" id="document_type" style="width: 100%;"
                                    required>
                                    @foreach ($documentypes as $documentype)
                                        <option value="{{ $documentype->id }}" @selected(old('document_type') == $documentype->id)>{{ $documentype->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @if ($documentypes->isEmpty())
                                    <small class="form-text text-danger">
                                        Belum ada tipe dokumen. Tambahkan dulu di <a href="{{ route('dokumentype.index') }}">Master Data › Tipe Dokumen</a>.
                                    </small>
                                @endif
                            </div>
                            <div class="col-lg-4 form-group">
                                <label for="change_note">Catatan</label>
                                <input type="text" class="form-control" id="change_note" name="change_note" value="{{ old('change_note') }}"
                                    placeholder="Catatan versi (opsional)">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-5 form-group mb-lg-0">
                                <label for="role_id">Jabatan <span class="text-danger">*</span></label>
                                <select class="custom-select col-lg-12 select2" multiple="multiple" name="role_id[]" data-placeholder="Cari jabatan..."
                                    id="role_id" required>
                                    <option value="0" @selected(in_array('0', (array) old('role_id', []), true))>SEMUA JABATAN</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}" @selected(in_array((string) $role->id, array_map('strval', (array) old('role_id', [])), true))>{{ $role->name }}</option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">Beberapa jabatan = satu dokumen per jabatan, masing-masing dengan soal sendiri.</small>
                            </div>
                            <div class="col-lg-3 form-group mb-0">
                                <label for="divisi_id">Divisi <span class="text-danger">*</span></label>
                                <select class="custom-select select2" name="divisi_id" style="width: 100%;"
                                    id="divisi_id" required>
                                    <option value="">--Pilih Divisi--</option>
                                    @foreach ($divisis as $divisi)
                                        <option value="{{ $divisi->id }}" @selected(old('divisi_id') == $divisi->id)>
                                            {{ $divisi->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mt-3">
                            @include('learning_module.partials.quiz-rules-fields', ['quiz' => null])
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
