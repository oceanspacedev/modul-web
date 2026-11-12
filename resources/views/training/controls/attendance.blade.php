{{--
    Baris Presensi di Kontrol Sesi Pelatihan (training/show.blade.php). Urutan barisnya mengikuti Urutan Langkah Peserta.
--}}
<!-- AKSES ABSENSI: dibuka, dijadwalkan, dan dibatasi waktunya seperti pretest dan posttest -->
@if($training->has_attendance)
    @include('training.partials.phase-access', [
        'phase' => 'attendance', 'heading' => 'Akses Presensi', 'noun' => 'Presensi',
        'questionCount' => null, 'emptyText' => '', 'lockedText' => 'Presensi ditutup untuk peserta',
        'toggleUrl' => route('training.toggle-attendance', $training),
        'intro' => 'Jam buka dan jam tutup berlaku sama untuk semua peserta. Setelah jam tutup lewat, presensi tertutup sendiri dan peserta yang belum mengisi tercatat Belum Absen.',
        'extraControl' => [
            'url' => route('training.toggle-attendance-use', $training), 'label' => 'Tidak Dipakai',
            'confirm' => 'Pelatihan ini tidak memakai presensi? Kotak, kolom, dan langkah presensi disembunyikan; data yang sudah masuk tetap tersimpan.',
        ],
    ])
@else
    <div class="list-group-item d-flex align-items-center justify-content-between gap-2">
        <div class="min-w-0">
            <div class="text-sm font-weight-bold">Akses Presensi</div>
            <div class="text-muted text-xs">Presensi tidak dipakai di pelatihan ini</div>
        </div>
        <form action="{{ route('training.toggle-attendance-use', $training) }}" method="POST" class="m-0 flex-shrink-0">
            @csrf
            <button type="submit" class="btn btn-default btn-xs text-nowrap">Pakai Presensi</button>
        </form>
    </div>
@endif

<!-- SYARAT SCREENSHOT -->
@if($training->has_attendance)
<div class="list-group-item d-flex align-items-center justify-content-between gap-2">
    <div class="min-w-0">
        <div class="text-sm font-weight-bold">Bukti Foto{{ $training->isOnline() ? ' / SS' : '' }}</div>
        <div class="text-muted text-xs">
            {{ $training->require_attendance_proof ? ($training->isOnline() ? 'Wajib upload foto selfie/meeting' : 'Wajib upload foto kehadiran') : 'Presensi tanpa upload foto' }}
        </div>
    </div>
    <form action="/training/{{ $training->id }}/toggle-proof" method="POST" class="m-0">
        @csrf
        <button type="submit" class="btn btn-default btn-xs text-nowrap">
            {{ $training->require_attendance_proof ? 'Jadikan Opsional' : 'Wajibkan Foto' }}
        </button>
    </form>
</div>
@endif
