@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Edit Pelatihan" :subtitle="$training->title" :breadcrumbs="['Pelatihan' => null, 'Jadwal Pelatihan' => route('training.index'), $training->title => route('training.show', $training), 'Edit' => null]">
            <x-slot:actions>
                <a href="/training/{{ $training->id }}" class="btn btn-default">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />

        <form action="{{ route('training.update', $training) }}" method="POST" id="trainingEditForm">
            @csrf
            @method('PUT')
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
                                <input type="text" id="title" name="title" class="form-control" value="{{ old('title', $training->title) }}" required>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="status">Status <span class="text-danger">*</span></label>
                                    <select id="status" name="status" class="form-control" required>
                                        @foreach (App\Enums\TrainingStatus::cases() as $status)
                                            <option value="{{ $status->value }}" @selected(old('status', $training->status->value) === $status->value)>{{ $status->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="trainer_id">Pemateri <span class="text-danger">*</span></label>
                                    <select id="trainer_id" name="trainer_id" class="form-control" required>
                                        @foreach($users as $u)
                                            <option value="{{ $u->id }}" {{ (old('trainer_id', $training->trainer_id) == $u->id) ? 'selected' : '' }}>
                                                {{ $u->nama_lengkap }} ({{ $u->divisi->name ?? 'Divisi Umum' }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="training_date">Tanggal <span class="text-danger">*</span></label>
                                    <input type="date" id="training_date" name="training_date" class="form-control"
                                           value="{{ old('training_date', \Carbon\Carbon::parse($training->training_date)->format('Y-m-d')) }}" required>
                                </div>
                                <div class="form-group col-md-3 col-6">
                                    <label for="start_time">Mulai <span class="text-danger">*</span></label>
                                    <input type="time" id="start_time" name="start_time" class="form-control"
                                           value="{{ old('start_time', substr($training->start_time, 0, 5)) }}" required>
                                </div>
                                <div class="form-group col-md-3 col-6">
                                    <label for="end_time">Selesai <span class="text-danger">*</span></label>
                                    <input type="time" id="end_time" name="end_time" class="form-control"
                                           value="{{ old('end_time', substr($training->end_time, 0, 5)) }}" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="zoom_link">Link Meeting <span class="text-danger">*</span></label>
                                <input type="url" id="zoom_link" name="zoom_link" class="form-control"
                                       value="{{ old('zoom_link', $training->zoom_link) }}" required>
                            </div>

                            <div class="form-group mb-0">
                                <label for="description">Deskripsi</label>
                                <textarea id="description" name="description" class="form-control" rows="3">{{ old('description', $training->description) }}</textarea>
                            </div>
                        </div>
                    </div>

                    {{-- MATERI & MODE KUIS --}}
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Materi & Kuis</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="documents">Dokumen Materi</label>
                                <select id="documents" name="documents[]" class="form-control select2" multiple="multiple" data-placeholder="Pilih dokumen (opsional)">
                                    @foreach($documents as $doc)
                                        <option value="{{ $doc->id }}" {{ in_array($doc->id, old('documents', $selectedDocuments ?? [])) ? 'selected' : '' }}>
                                            {{ $doc->name }} ({{ $doc->dokumentype->name ?? 'Dokumen' }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group mb-0">
                                <label class="d-block">Mode Kuis</label>
                                <div class="row">
                                    <div class="col-md-6 mb-2 mb-md-0">
                                        <div class="border rounded p-3 h-100">
                                            <div class="custom-control custom-radio">
                                                <input type="radio" id="mode_formal" name="quiz_mode" value="formal" class="custom-control-input" {{ old('quiz_mode', $training->quiz_mode ?? 'formal') === 'formal' ? 'checked' : '' }}>
                                                <label class="custom-control-label d-block" for="mode_formal">
                                                    <strong class="d-block">Formal</strong>
                                                    <span class="d-block text-muted small mt-1">Semua soal dalam satu halaman.</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="border rounded p-3 h-100">
                                            <div class="custom-control custom-radio">
                                                <input type="radio" id="mode_game" name="quiz_mode" value="game" class="custom-control-input" {{ old('quiz_mode', $training->quiz_mode ?? 'formal') === 'game' ? 'checked' : '' }}>
                                                <label class="custom-control-label d-block" for="mode_game">
                                                    <strong class="d-block">Game (Quizizz)</strong>
                                                    <span class="d-block text-muted small mt-1">1 soal per layar dengan timer.</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- PESERTA --}}
                <div class="col-lg-5">
                    <div class="card" id="peserta">
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
                            <p class="text-muted small mb-3">Menghapus centang peserta ikut menghapus presensi &amp; nilainya.</p>
                            <div class="form-row">
                                <div class="form-group col-sm-6">
                                    <select id="filterDivisi" class="form-control form-control-sm select2" aria-label="Divisi" style="width: 100%;">
                                        <option value="">Semua Divisi</option>
                                        @foreach($divisis as $div)
                                            <option value="{{ $div->id }}">{{ $div->name }} ({{ $div->users->count() }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-sm-6">
                                    <input type="text" id="searchParticipant" class="form-control form-control-sm" placeholder="Cari peserta...">
                                </div>
                            </div>

                            <div class="border rounded" style="max-height: 420px; overflow-y: auto;">
                                @foreach($users as $user)
                                <div class="participant-item d-flex align-items-center justify-content-between px-3 py-2 border-bottom"
                                     data-name="{{ strtolower($user->nama_lengkap) }}"
                                     data-idk="{{ strtolower($user->employee_id ?? '') }}"
                                     data-username="{{ strtolower($user->username ?? '') }}"
                                     data-divisi="{{ $user->divisi_id }}">
                                    <div class="custom-control custom-checkbox min-w-0">
                                        <input type="checkbox" name="participants[]" value="{{ $user->id }}"
                                               class="custom-control-input participant-checkbox" id="user_{{ $user->id }}"
                                               {{ in_array($user->id, old('participants', $selectedParticipants)) ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="user_{{ $user->id }}">
                                            <strong class="d-block text-dark">{{ $user->nama_lengkap }}</strong>
                                            <span class="text-muted small">
                                                {{ $user->divisi->name ?? 'Divisi Umum' }}
                                            </span>
                                        </label>
                                    </div>
                                    @unless($user->no_hp)
                                        <span class="badge badge-secondary ml-2" title="Nomor WhatsApp belum diisi">Tanpa WA</span>
                                    @endunless
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- PRESENSI --}}
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Pengaturan</h3>
                        </div>
                        <div class="card-body">
                            <div class="custom-control custom-switch mb-3">
                                <input type="checkbox" class="custom-control-input" id="is_attendance_active" name="is_attendance_active" value="1" {{ old('is_attendance_active', $training->is_attendance_active) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="is_attendance_active">
                                    Buka presensi
                                </label>
                            </div>
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="require_attendance_proof" name="require_attendance_proof" value="1" {{ old('require_attendance_proof', $training->require_attendance_proof) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="require_attendance_proof">
                                    Wajib upload bukti screenshot
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- SIMPAN --}}
                    <div class="card">
                        <div class="card-body">
                            <button type="submit" class="btn btn-primary btn-block">Simpan Perubahan</button>
                            <a href="/training/{{ $training->id }}" class="btn btn-default btn-block">Batal</a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.participant-checkbox');
    const badge = document.getElementById('selectedCountBadge');
    const filterDivisi = document.getElementById('filterDivisi');
    const searchInput = document.getElementById('searchParticipant');
    const items = document.querySelectorAll('.participant-item');

    function updateCount() {
        const count = document.querySelectorAll('.participant-checkbox:checked').length;
        badge.textContent = count;
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateCount);
    });
    updateCount();

    document.getElementById('btnSelectAll').addEventListener('click', function() {
        items.forEach(item => {
            if (!item.classList.contains('d-none')) {
                const cb = item.querySelector('.participant-checkbox');
                if (cb) cb.checked = true;
            }
        });
        updateCount();
    });

    document.getElementById('btnDeselectAll').addEventListener('click', function() {
        items.forEach(item => {
            if (!item.classList.contains('d-none')) {
                const cb = item.querySelector('.participant-checkbox');
                if (cb) cb.checked = false;
            }
        });
        updateCount();
    });

    function applyFilter() {
        const search = searchInput.value.toLowerCase().trim();
        const divId = filterDivisi.value;

        items.forEach(item => {
            const name = item.getAttribute('data-name');
            const idk = item.getAttribute('data-idk');
            const username = item.getAttribute('data-username');
            const itemDiv = item.getAttribute('data-divisi');

            const matchSearch = (!search || name.includes(search) || idk.includes(search) || username.includes(search));
            const matchDiv = (!divId || itemDiv === divId);

            // .d-flex memakai !important, jadi baris disembunyikan lewat kelas d-none, bukan style.display.
            const visible = matchSearch && matchDiv;
            item.classList.toggle('d-flex', visible);
            item.classList.toggle('d-none', !visible);
        });
    }

    filterDivisi.addEventListener('change', applyFilter);
    searchInput.addEventListener('input', applyFilter);

    // Peserta lama yang di-uncheck ikut dihapus datanya, jadi minta konfirmasi sebelum menyimpan.
    const savedIds = new Set(@json(array_map('strval', $selectedParticipants)));
    document.getElementById('trainingEditForm').addEventListener('submit', function (e) {
        const removed = Array.from(checkboxes)
            .filter(cb => savedIds.has(cb.value) && !cb.checked)
            .map(cb => cb.closest('.participant-item').querySelector('strong').textContent.trim());
        if (removed.length && !confirm(removed.length + ' peserta akan dikeluarkan beserta presensi & nilainya:\n- '
            + removed.slice(0, 10).join('\n- ') + (removed.length > 10 ? '\n- dan ' + (removed.length - 10) + ' lainnya' : '')
            + '\n\nLanjutkan simpan?')) {
            e.preventDefault();
        }
    });

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
