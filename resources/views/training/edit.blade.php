@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark font-weight-bold" style="font-size: 1.5rem;">
                    Edit Pelatihan
                </h1>
            </div>
            <div class="col-sm-6 text-right">
                <a href="/training/{{ $training->id }}" class="btn btn-default btn-sm">
                    Kembali ke Detail
                </a>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <strong>Terdapat kesalahan pengisian:</strong>
                <ul class="mb-0 pl-3 mt-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        <form action="/training/{{ $training->id }}/update" method="POST" id="trainingEditForm">
            @csrf
            <div class="row">
                <!-- FORM DETAIL -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header py-3">
                            <h3 class="card-title font-weight-bold">
                                Informasi Pelatihan
                            </h3>
                        </div>
                        <div class="card-body p-4">
                            <div class="form-group mb-4">
                                <label for="title" class="font-weight-600 mb-2">Judul / Topik Pelatihan <span class="text-danger">*</span></label>
                                <input type="text" id="title" name="title" class="form-control" value="{{ old('title', $training->title) }}" required>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label for="status" class="font-weight-600 mb-2">Status Pelatihan <span class="text-danger">*</span></label>
                                    <select id="status" name="status" class="form-control" required>
                                        <option value="scheduled" {{ old('status', $training->status) === 'scheduled' ? 'selected' : '' }}>Dijadwalkan</option>
                                        <option value="ongoing" {{ old('status', $training->status) === 'ongoing' ? 'selected' : '' }}>Berlangsung</option>
                                        <option value="completed" {{ old('status', $training->status) === 'completed' ? 'selected' : '' }}>Selesai</option>
                                        <option value="cancelled" {{ old('status', $training->status) === 'cancelled' ? 'selected' : '' }}>Dibatalkan</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="trainer_id" class="font-weight-600 mb-2">Pemateri (Trainer) <span class="text-danger">*</span></label>
                                    <select id="trainer_id" name="trainer_id" class="form-control" required>
                                        @foreach($users as $u)
                                            <option value="{{ $u->id }}" {{ (old('trainer_id', $training->trainer_id) == $u->id) ? 'selected' : '' }}>
                                                {{ $u->full_name }} ({{ $u->divisi->name ?? 'Divisi Umum' }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label for="training_date" class="font-weight-600 mb-2">Tanggal Pelaksanaan <span class="text-danger">*</span></label>
                                    <input type="date" id="training_date" name="training_date" class="form-control" 
                                           value="{{ old('training_date', \Carbon\Carbon::parse($training->training_date)->format('Y-m-d')) }}" required>
                                </div>
                                <div class="col-md-3 col-6">
                                    <label for="start_time" class="font-weight-600 mb-2">Jam Mulai <span class="text-danger">*</span></label>
                                    <input type="time" id="start_time" name="start_time" class="form-control" 
                                           value="{{ old('start_time', substr($training->start_time, 0, 5)) }}" required>
                                </div>
                                <div class="col-md-3 col-6">
                                    <label for="end_time" class="font-weight-600 mb-2">Jam Selesai <span class="text-danger">*</span></label>
                                    <input type="time" id="end_time" name="end_time" class="form-control" 
                                           value="{{ old('end_time', substr($training->end_time, 0, 5)) }}" required>
                                </div>
                            </div>

                            <div class="form-group mb-4">
                                <label for="zoom_link" class="font-weight-600 mb-2">Tautan Zoom / Google Meet <span class="text-danger">*</span></label>
                                <input type="url" id="zoom_link" name="zoom_link" class="form-control" 
                                       value="{{ old('zoom_link', $training->zoom_link) }}" required>
                            </div>

                            <div class="form-group mb-4">
                                <label for="description" class="font-weight-600 mb-2">Deskripsi / Silabus Materi</label>
                                <textarea id="description" name="description" class="form-control" rows="3">{{ old('description', $training->description) }}</textarea>
                            </div>

                            <div class="form-group mb-0 pt-3 border-top">
                                <label class="font-weight-600 mb-2 d-block">Tampilan / Mode Kuis Peserta</label>
                                <div class="row">
                                    <div class="col-md-6 mb-2 mb-md-0">
                                        <div class="custom-control custom-radio p-3 border rounded h-100 bg-light">
                                            <input type="radio" id="mode_formal" name="quiz_mode" value="formal" class="custom-control-input" {{ old('quiz_mode', $training->quiz_mode ?? 'formal') === 'formal' ? 'checked' : '' }}>
                                            <label class="custom-control-label font-weight-bold text-dark d-block" for="mode_formal" style="cursor: pointer;">
                                                <i class="fas fa-file-alt text-primary mr-1"></i> Mode Formal (Ujian)
                                                <span class="d-block text-muted text-xs font-weight-normal mt-1">
                                                    Formulir ujian lengkap, anti-cheat ketat, dan cocok untuk evaluasi akademik/essay.
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="custom-control custom-radio p-3 border rounded h-100 bg-light">
                                            <input type="radio" id="mode_game" name="quiz_mode" value="game" class="custom-control-input" {{ old('quiz_mode', $training->quiz_mode ?? 'formal') === 'game' ? 'checked' : '' }}>
                                            <label class="custom-control-label font-weight-bold text-dark d-block" for="mode_game" style="cursor: pointer;">
                                                <i class="fas fa-gamepad text-success mr-1"></i> Mode Game (Quizizz)
                                                <span class="d-block text-muted text-xs font-weight-normal mt-1">
                                                    1 soal per layar, tombol warna-warni, timer per soal, sound effect, dan podium juara.
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group mb-0 pt-3 border-top">
                                <div class="custom-control custom-checkbox mb-2">
                                    <input type="checkbox" class="custom-control-input" id="is_attendance_active" name="is_attendance_active" value="1" {{ old('is_attendance_active', $training->is_attendance_active) ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold" for="is_attendance_active">
                                        Buka akses presensi kehadiran peserta
                                    </label>
                                    <small class="form-text text-muted mt-0">Bisa dibuka/ditutup cepat dari halaman detail pelatihan.</small>
                                </div>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="require_attendance_proof" name="require_attendance_proof" value="1" {{ old('require_attendance_proof', $training->require_attendance_proof) ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold" for="require_attendance_proof">
                                        Wajibkan peserta unggah screenshot bukti Zoom/Pelatihan
                                    </label>
                                    <small class="form-text text-muted mt-0">Peserta harus melampirkan screenshot layar Zoom saat konfirmasi kehadiran.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FORM PESERTA -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header py-3 d-flex align-items-center justify-content-between">
                            <h3 class="card-title font-weight-bold">
                                Kelola Peserta (<span id="selectedCountBadge">0</span> dipilih)
                            </h3>
                            <div>
                                <button type="button" id="btnSelectAll" class="btn btn-xs btn-default mr-1">Pilih Semua</button>
                                <button type="button" id="btnDeselectAll" class="btn btn-xs btn-default">Batal Semua</button>
                            </div>
                        </div>

                        <div class="card-body p-4">
                            <div class="row mb-3">
                                <div class="col-sm-6 mb-2 mb-sm-0">
                                    <select id="filterDivisi" class="form-control form-control-sm">
                                        <option value="">Semua Divisi</option>
                                        @foreach($divisis as $div)
                                            <option value="{{ $div->id }}">{{ $div->name }} ({{ $div->users->count() }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-sm-6">
                                    <input type="text" id="searchParticipant" class="form-control form-control-sm" placeholder="Cari nama peserta...">
                                </div>
                            </div>

                            <div class="border rounded p-2" style="height: 380px; overflow-y: auto; background-color: #fafbfc;">
                                @foreach($users as $user)
                                <div class="participant-item d-flex align-items-center justify-content-between p-3 mb-2 bg-white border rounded"
                                     data-name="{{ strtolower($user->full_name) }}"
                                     data-idk="{{ strtolower($user->id_karyawan ?? '') }}"
                                     data-divisi="{{ $user->divisi_id }}">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" name="participants[]" value="{{ $user->id }}"
                                               class="custom-control-input participant-checkbox" id="user_{{ $user->id }}"
                                               {{ in_array($user->id, old('participants', $selectedParticipants)) ? 'checked' : '' }}>
                                        <label class="custom-control-label font-weight-normal text-dark ml-2" for="user_{{ $user->id }}" style="cursor: pointer;">
                                            <strong class="d-block">{{ $user->full_name }}</strong>
                                            <span class="text-muted small">
                                                {{ $user->id_karyawan ?? '-' }} &bull; {{ $user->divisi->name ?? 'Divisi Umum' }}
                                            </span>
                                        </label>
                                    </div>
                                    <div class="text-right">
                                        @if($user->no_wa)
                                            <span class="text-muted small">{{ $user->no_wa }}</span>
                                        @else
                                            <span class="text-muted small font-italic">No WA -</span>
                                        @endif
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="card-footer bg-white border-top py-3 text-right">
                            <a href="/training/{{ $training->id }}" class="btn btn-default mr-2">Batal</a>
                            <button type="submit" class="btn btn-primary px-4 font-weight-bold">
                                Simpan Perubahan
                            </button>
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
            if (item.style.display !== 'none') {
                const cb = item.querySelector('.participant-checkbox');
                if (cb) cb.checked = true;
            }
        });
        updateCount();
    });

    document.getElementById('btnDeselectAll').addEventListener('click', function() {
        items.forEach(item => {
            if (item.style.display !== 'none') {
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
            const itemDiv = item.getAttribute('data-divisi');

            const matchSearch = (!search || name.includes(search) || idk.includes(search));
            const matchDiv = (!divId || itemDiv === divId);

            if (matchSearch && matchDiv) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }

    filterDivisi.addEventListener('change', applyFilter);
    searchInput.addEventListener('input', applyFilter);
});
</script>
@endsection
