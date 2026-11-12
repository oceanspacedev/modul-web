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

        <form action="/training" method="POST" id="trainingForm">
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

                            <div class="form-group">
                                <label for="zoom_link">Link Meeting <span class="text-danger">*</span></label>
                                <input type="url" id="zoom_link" name="zoom_link" class="form-control"
                                    value="{{ old('zoom_link') }}" placeholder="https://zoom.us/j/..." required>
                            </div>

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
                                <button type="button" id="btnSelectAll" class="btn btn-xs btn-default">Pilih semua</button>
                                <button type="button" id="btnDeselectAll" class="btn btn-xs btn-default">Kosongkan</button>
                            </div>
                        </div>

                        <div class="card-body">
                            @include('training.partials.participant-picker', ['selected' => old('participants', []), 'saved' => []])
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
});
</script>
@endsection
