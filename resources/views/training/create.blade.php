@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Tambah Pelatihan Baru" subtitle="Jadwal, materi, dan peserta." :breadcrumbs="['Pelatihan' => null, 'Jadwal Pelatihan' => route('training.index'), 'Tambah' => null]">
            <x-slot:actions>
                <a href="/training" class="btn btn-default">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />

        <form action="/training" method="POST" id="trainingForm" enctype="multipart/form-data">
            @csrf
            <div class="row">
                <div class="col-lg-7">
                    {{-- INFORMASI PELATIHAN --}}
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Informasi Pelatihan</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="title">Judul <span class="text-danger">*</span></label>
                                <input type="text" id="title" name="title" class="form-control" value="{{ old('title') }}"
                                    placeholder="Contoh: Pengenalan SOP Keamanan" required>
                            </div>

                            <div class="form-group">
                                <label for="trainer_id">Pemateri <span class="text-danger">*</span></label>
                                <select id="trainer_id" name="trainer_id" class="form-control" required>
                                    <option value="">Pilih pemateri</option>
                                    @foreach($users as $u)
                                        <option value="{{ $u->id }}" {{ (old('trainer_id', auth()->id()) == $u->id) ? 'selected' : '' }}>
                                            {{ $u->nama_lengkap }} ({{ $divisiNames[$u->divisi_id] ?? 'Divisi Umum' }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="training_date">Tanggal <span class="text-danger">*</span></label>
                                    <input type="date" id="training_date" name="training_date" class="form-control"
                                        value="{{ old('training_date', date('Y-m-d')) }}" required>
                                </div>
                                <div class="form-group col-md-3 col-6">
                                    <label for="start_time">Mulai <span class="text-danger">*</span></label>
                                    <input type="time" id="start_time" name="start_time" class="form-control"
                                        value="{{ old('start_time', '09:00') }}" required>
                                </div>
                                <div class="form-group col-md-3 col-6">
                                    <label for="end_time">Selesai <span class="text-danger">*</span></label>
                                    <input type="time" id="end_time" name="end_time" class="form-control"
                                        value="{{ old('end_time', '11:00') }}" required>
                                </div>
                            </div>

                            @include('training.partials.meeting-fields', ['training' => null])

                            <div class="form-group mb-0">
                                <label for="description">Deskripsi</label>
                                <textarea id="description" name="description" class="form-control" rows="3"
                                    placeholder="Opsional">{{ old('description') }}</textarea>
                            </div>
                        </div>
                    </div>

                    {{-- MATERI --}}
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Materi</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="documents">Dokumen Materi</label>
                                <select id="documents" name="documents[]" class="form-control select2" multiple="multiple" data-placeholder="Pilih dokumen (opsional)">
                                    @foreach($documents as $doc)
                                        <option value="{{ $doc->id }}" {{ (is_array(old('documents')) && in_array($doc->id, old('documents'))) ? 'selected' : '' }}>
                                            {{ $doc->name }} ({{ $doc->dokumentype->name ?? 'Dokumen' }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- ATURAN KUIS --}}
                    <div id="aturan-kuis">
                        @include('learning_module.partials.quiz-rules-fields', ['trainingRules' => new \App\Models\Training, 'card' => true])
                    </div>

                    {{-- FORM EVALUASI --}}
                    @include('training.partials.evaluation-field', ['training' => null])
                </div>

                {{-- PESERTA --}}
                <div class="col-lg-5">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                Peserta <span class="text-danger">*</span> <span class="badge badge-secondary"><span id="selectedCountBadge">0</span> dipilih</span>
                            </h3>
                            <div class="card-tools d-flex gap-1">
                                <button type="button" class="btn btn-xs btn-default" data-toggle="modal" data-target="#importParticipantsModal">
                                    <i class="fas fa-file-excel" aria-hidden="true"></i> Impor Excel
                                </button>
                                <button type="button" id="btnSelectAll" class="btn btn-xs btn-default">Pilih semua</button>
                                <button type="button" id="btnDeselectAll" class="btn btn-xs btn-default">Kosongkan</button>
                            </div>
                        </div>

                        <div class="card-body">
                            @include('training.partials.participant-picker', ['selected' => old('participants', []), 'saved' => []])

                            <div id="participantFileSummary" class="border rounded p-2 mt-3 d-none flex-wrap align-items-center gap-2">
                                <i class="fas fa-file-excel text-success" aria-hidden="true"></i>
                                <span class="small flex-grow-1 text-break" style="min-width: 12rem;">
                                    Impor dari Excel: <b id="participantFileName"></b><br>
                                    <span class="text-muted">Diproses setelah pelatihan disimpan; hasilnya dikirim ke Notifikasi.</span>
                                </span>
                                <button type="button" class="btn btn-xs btn-default" data-toggle="modal" data-target="#importParticipantsModal">Ganti</button>
                                <button type="button" class="btn btn-xs btn-default" id="btnClearParticipantFile">Hapus</button>
                            </div>
                            @error('participant_file')
                                <div class="invalid-feedback d-block">{{ $message }} Pilih ulang file Excel-nya.</div>
                            @enderror
                        </div>
                    </div>

                    {{-- IMPORT PESERTA DARI EXCEL: file ikut terkirim bersama form dan diproses setelah pelatihan disimpan. --}}
                    <div class="modal fade" id="importParticipantsModal" tabindex="-1" role="dialog" aria-labelledby="importParticipantsModalTitle" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="importParticipantsModalTitle">Import Peserta dari Excel</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                                </div>
                                <div class="modal-body">
                                    <a href="{{ route('training.participant-template') }}" class="btn btn-default btn-block mb-3">
                                        <i class="fas fa-download"></i> Unduh Template Excel (.xlsx)
                                    </a>
                                    <div class="form-group">
                                        <label for="importParticipantsFile">File Spreadsheet (.xlsx, .xls, .csv)</label>
                                        <input type="file" name="participant_file" id="importParticipantsFile" class="form-control" accept=".xlsx,.xls,.csv">
                                    </div>
                                    <p class="small mb-2">
                                        Peserta dari file ditambahkan <b>setelah pelatihan disimpan</b>, bersama peserta yang Anda pilih di daftar.
                                        Undangan WhatsApp untuk mereka mengikuti pilihan <b>Kirim notifikasi WhatsApp</b> di form ini.
                                    </p>
                                    @include('training.partials.participant-import-help')
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
                                    <button type="button" class="btn btn-primary" data-dismiss="modal">Gunakan File</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- NOTIFIKASI (presensi & kuis ditanyakan setelah soal dibuat) --}}
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Notifikasi</h3>
                        </div>
                        <div class="card-body">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="send_wa_now" name="send_wa_now" value="1" checked>
                                <label class="custom-control-label" for="send_wa_now">
                                    Kirim notifikasi WhatsApp
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- SIMPAN --}}
                    <div class="card">
                        <div class="card-body">
                            <button type="submit" class="btn btn-primary btn-block">Simpan &amp; Lanjut Buat Soal</button>
                            <a href="/training" class="btn btn-default btn-block">Batal</a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize select2 for documents
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('#documents').select2({
            width: '100%',
            placeholder: 'Pilih dokumen (opsional)'
        });
    }

    // Ringkasan file peserta yang akan diimpor, supaya terlihat tanpa membuka modal.
    const fileInput = document.getElementById('importParticipantsFile');
    const summary = document.getElementById('participantFileSummary');
    const showFile = function () {
        const file = fileInput.files[0];
        document.getElementById('participantFileName').textContent = file ? file.name : '';
        summary.classList.toggle('d-none', !file);
        summary.classList.toggle('d-flex', !!file);
    };
    fileInput.addEventListener('change', showFile);
    document.getElementById('btnClearParticipantFile').addEventListener('click', function () {
        fileInput.value = '';
        showFile();
    });
    showFile();
});
</script>
@endsection
