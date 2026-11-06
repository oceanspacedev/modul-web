@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <x-page-header title="Edit Dokumen" :subtitle="$document->name" eyebrow="Konten / Dokumen">
                <x-slot:actions>
                    <span class="badge badge-secondary">v{{ $document->version ?? 1 }}</span>
                    <a href="{{ $document->file_url }}" target="_blank" class="btn btn-default">
                        <i class="fas fa-external-link-alt"></i> Buka File
                    </a>
                    <a href="{{ route('document.index') }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Kembali</a>
                </x-slot:actions>
            </x-page-header>

            <x-flash />

            <form action="{{ route('document.update', $document) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Informasi Dokumen</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-6 form-group">
                                <label for="file">
                                    Upload Versi Baru (PDF) <span class="badge badge-secondary ml-1">v{{ ($document->version ?? 1) + 1 }}</span>
                                </label>
                                <input type="file" accept="application/pdf" class="form-control" id="file" name="file">
                                <small class="form-text text-muted">Kosongkan jika tidak ada pembaruan file.</small>
                            </div>

                            <div class="col-lg-6 form-group">
                                <label for="change_note">Catatan Revisi</label>
                                <input type="text" class="form-control" id="change_note" name="change_note"
                                    placeholder="Catatan perubahan (opsional)">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-lg-3 form-group">
                                <label for="document_type">Tipe Dokumen</label>
                                <select class="custom-select" name="document_type" id="document_type" required>
                                    @foreach ($documentypes as $documentype)
                                        <option value="{{ $documentype->id }}"
                                            {{ $documentype->id == $document->document_type ? 'selected' : '' }}>
                                            {{ $documentype->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-lg-3 form-group">
                                <label for="divisi_id">Divisi</label>
                                <select class="custom-select select2" name="divisi_id" id="divisi_id" style="width: 100%;" required>
                                    <option value="">--Pilih Divisi--</option>
                                    @foreach ($divisis as $divisi)
                                        <option value="{{ $divisi->id }}"
                                            {{ $divisi->id == $document->divisi_id ? 'selected' : '' }}>
                                            {{ $divisi->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-lg-3 form-group">
                                <label for="role_id">Jabatan</label>
                                <select class="custom-select select2" name="role_id" id="role_id" style="width: 100%;" required>
                                    <option value="0">SEMUA JABATAN</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}" @selected($role->id == $document->role_id)>
                                            {{ $role->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        @include('learning_module.partials.material-quiz-fields', ['quiz' => $quiz])
                    </div>
                    <div class="card-footer d-flex justify-content-end gap-2">
                        <a href="{{ route('document.index') }}" class="btn btn-default">Batal</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan
                        </button>
                    </div>
                </div>
            </form>

            {{-- TABEL RIWAYAT VERSI DOKUMEN --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-history"></i> Riwayat Versi</h3>
                    <span class="badge badge-secondary">{{ $document->versions->count() }}</span>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover mb-0 text-sm">
                        <thead>
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
                                <tr>
                                    <td class="text-center">
                                        <span class="badge {{ $isLatest ? 'badge-success' : 'badge-secondary' }}">
                                            v{{ $ver->version_number }}
                                        </span>
                                    </td>
                                    <td>
                                        <i class="fas fa-file-pdf text-muted mr-1"></i>
                                        {{ $ver->file_name }}
                                    </td>
                                    <td>{{ $ver->change_note ?: '-' }}</td>
                                    <td class="text-muted">{{ $ver->created_by ?: 'Admin' }}</td>
                                    <td>{{ $ver->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="text-center">
                                        <a href="{{ $ver->file_url }}" target="_blank" class="btn btn-xs btn-default" title="Buka File">
                                            <i class="fas fa-eye"></i> Buka
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <x-empty-state icon="fa-history" title="Belum ada riwayat" />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
@endsection
