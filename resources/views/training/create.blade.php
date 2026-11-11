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
                                            {{ $u->nama_lengkap }} ({{ $u->divisi->name ?? 'Divisi Umum' }})
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
                                        <option value="{{ $doc->id }}" {{ (is_array(old('documents')) && in_array($doc->id, old('documents'))) ? 'selected' : '' }}>
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
                                                <input type="radio" id="mode_formal" name="quiz_mode" value="formal" class="custom-control-input" {{ old('quiz_mode', 'formal') === 'formal' ? 'checked' : '' }}>
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
                                                <input type="radio" id="mode_game" name="quiz_mode" value="game" class="custom-control-input" {{ old('quiz_mode') === 'game' ? 'checked' : '' }}>
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
                                @forelse($users as $user)
                                <div class="participant-item d-flex align-items-center justify-content-between px-3 py-2 border-bottom"
                                     data-name="{{ strtolower($user->nama_lengkap) }}"
                                     data-idk="{{ strtolower($user->employee_id ?? '') }}"
                                     data-username="{{ strtolower($user->username ?? '') }}"
                                     data-divisi="{{ $user->divisi_id }}">
                                    <div class="custom-control custom-checkbox min-w-0">
                                        <input type="checkbox" name="participants[]" value="{{ $user->id }}"
                                               class="custom-control-input participant-checkbox" id="user_{{ $user->id }}"
                                               {{ is_array(old('participants')) && in_array($user->id, old('participants')) ? 'checked' : '' }}>
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
                                @empty
                                <x-empty-state icon="fa-users" title="Belum ada data user" text="Peserta dipilih dari data user. Tambahkan user terlebih dahulu.">
                                    @can('create', \App\Models\User::class)
                                        <a href="{{ route('user.create') }}" class="btn btn-primary btn-sm">Tambah User</a>
                                    @endcan
                                </x-empty-state>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    {{-- PRESENSI & NOTIFIKASI --}}
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Pengaturan</h3>
                        </div>
                        <div class="card-body">
                            <div class="custom-control custom-switch mb-3">
                                <input type="checkbox" class="custom-control-input" id="is_attendance_active" name="is_attendance_active" value="1" {{ old('is_attendance_active') ? 'checked' : '' }}>
                                <label class="custom-control-label" for="is_attendance_active">
                                    Buka presensi sekarang
                                </label>
                            </div>
                            <div class="custom-control custom-switch mb-3">
                                <input type="checkbox" class="custom-control-input" id="require_attendance_proof" name="require_attendance_proof" value="1" {{ old('require_attendance_proof') ? 'checked' : '' }}>
                                <label class="custom-control-label" for="require_attendance_proof">
                                    Wajib upload bukti screenshot
                                </label>
                            </div>
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
                            <button type="submit" class="btn btn-primary btn-block">Simpan Pelatihan</button>
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
