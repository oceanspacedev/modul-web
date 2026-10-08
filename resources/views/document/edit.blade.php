@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card border shadow-sm">
                            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                                <h3 class="card-title font-weight-bold text-dark m-0" style="font-size: 1.15rem;">
                                    <i class="fas fa-file-alt mr-1 text-primary"></i> {{ $document->name }}
                                </h3>
                                <div>
                                    <span class="badge badge-light border px-2 py-1">v{{ $document->version ?? 1 }}</span>
                                    <a href="{{ asset('storage/dokumen/' . $document->path) }}" target="_blank" class="btn btn-xs btn-default ml-2">
                                        <i class="fas fa-external-link-alt mr-1"></i> Buka File
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <form action="/document/{{ $document->id }}" method="POST" enctype="multipart/form-data">
                                    @csrf

                                    <div class="row">
                                        <div class="col-lg-6 mb-3">
                                            <label for="file" class="form-label font-weight-bold">
                                                Upload Versi Baru (PDF) <span class="badge badge-light border ml-1">v{{ ($document->version ?? 1) + 1 }}</span>
                                            </label>
                                            <input type="file" accept="application/pdf" class="form-control" id="file" name="file">
                                            <small class="text-muted">Kosongkan jika tidak ada pembaruan file.</small>
                                        </div>

                                        <div class="col-lg-6 mb-3">
                                            <label for="change_note" class="form-label font-weight-bold">Catatan Revisi</label>
                                            <input type="text" class="form-control" id="change_note" name="change_note"
                                                placeholder="Catatan perubahan (opsional)">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-lg-3 mb-3">
                                            <label for="document_type" class="form-label font-weight-bold">Document Type</label>
                                            <select class="custom-select" name="document_type" id="document_type" required>
                                                @foreach ($documentypes as $documentype)
                                                    <option value="{{ $documentype->id }}"
                                                        {{ $documentype->id == $document->document_type ? 'selected' : '' }}>
                                                        {{ $documentype->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="col-lg-3 mb-3">
                                            <label for="divisi_id" class="form-label font-weight-bold">Divisi</label>
                                            <select class="custom-select adduserdivisi" name="divisi_id" id="divisi_id" required>
                                                <option value="">--Pilih Divisi--</option>
                                                @foreach ($divisis as $divisi)
                                                    <option value="{{ $divisi->id }}"
                                                        {{ $divisi->id == $document->divisi_id ? 'selected' : '' }}>
                                                        {{ $divisi->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="col-lg-3 mb-3">
                                            <label for="sub_divisi_id" class="form-label font-weight-bold">Sub Divisi</label>
                                            <select class="custom-select addusersubdivisi" id="sub_divisi_id" name="sub_divisi_id">
                                                <option value="{{ $subdivisis->where('id', $document->sub_divisi_id)->first()->id ?? '' }}">
                                                    {{ $document->subdivisi->name ?? '--Pilih Sub Divisi--' }}
                                                </option>
                                            </select>
                                        </div>

                                        <div class="col-lg-3 mb-3">
                                            <label for="job_level_id" class="form-label font-weight-bold">Job Level</label>
                                            <select class="custom-select" name="job_level_id" id="job_level_id" required>
                                                @foreach ($joblevels as $joblevel)
                                                    <option value="{{ $joblevel->id }}"
                                                        {{ $joblevel->id == $document->job_level_id ? 'selected' : '' }}>
                                                        {{ $joblevel->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between mt-2 pt-3 border-top">
                                        <a href="/document" class="btn btn-secondary btn-sm">
                                            <i class="fas fa-arrow-left mr-1"></i> Kembali
                                        </a>
                                        <button type="submit" class="btn btn-primary btn-sm px-4">
                                            <i class="fas fa-save mr-1"></i> Simpan
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        {{-- TABEL RIWAYAT VERSI DOKUMEN --}}
                        <div class="card card-outline card-secondary shadow-sm mt-3">
                            <div class="card-header bg-light py-2">
                                <h3 class="card-title font-weight-bold text-sm m-0">
                                    <i class="fas fa-history mr-1"></i> Riwayat Versi ({{ $document->versions->count() }})
                                </h3>
                            </div>
                            <div class="card-body p-0 table-responsive">
                                <table class="table table-hover table-striped mb-0 text-sm">
                                    <thead class="thead-light">
                                        <tr>
                                            <th style="width: 80px;" class="text-center">Versi</th>
                                            <th>File</th>
                                            <th>Catatan</th>
                                            <th style="width: 120px;">Oleh</th>
                                            <th style="width: 140px;">Tanggal</th>
                                            <th style="width: 80px;" class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($document->versions as $index => $ver)
                                            @php $isLatest = ($index === 0); @endphp
                                            <tr class="{{ $isLatest ? 'table-success' : '' }}">
                                                <td class="text-center">
                                                    <span class="badge {{ $isLatest ? 'badge-success' : 'badge-secondary' }}">
                                                        v{{ $ver->version_number }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <i class="fas fa-file-pdf text-danger mr-1"></i>
                                                    {{ $ver->file_name }}
                                                </td>
                                                <td>{{ $ver->change_note ?: '-' }}</td>
                                                <td><span class="badge badge-light border">{{ $ver->created_by ?: 'Admin' }}</span></td>
                                                <td>{{ $ver->created_at->format('d/m/Y H:i') }}</td>
                                                <td class="text-center">
                                                    <a href="{{ $ver->file_url }}" target="_blank" class="btn btn-xs btn-outline-primary" title="Buka File">
                                                        <i class="fas fa-eye"></i> Buka
                                                    </a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center py-3 text-muted">Belum ada riwayat.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </section>
    </section>
@endsection
