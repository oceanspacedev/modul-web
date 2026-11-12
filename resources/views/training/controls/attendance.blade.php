{{--
    Baris Presensi di Kontrol Sesi Pelatihan (training/show.blade.php). Urutan barisnya mengikuti Urutan Langkah Peserta.
--}}
<!-- AKSES ABSENSI -->
<div class="list-group-item d-flex {{ $training->has_attendance ? 'flex-wrap' : '' }} align-items-center justify-content-between gap-2">
    <div class="min-w-0">
        <div class="text-sm font-weight-bold">Akses Presensi</div>
        <div class="text-muted text-xs">
            @if(! $training->has_attendance)
                Presensi tidak dipakai di pelatihan ini
            @else
                {{ $training->is_attendance_active ? 'Peserta dapat mengisi presensi' : 'Presensi ditutup untuk peserta' }}
            @endif
        </div>
    </div>
    <div class="d-flex flex-wrap justify-content-end gap-1 {{ $training->has_attendance ? 'w-100' : 'flex-shrink-0' }}">
        <form action="{{ route('training.toggle-attendance-use', $training) }}" method="POST" class="m-0"
            @if($training->has_attendance) onsubmit="return confirm('Pelatihan ini tidak memakai presensi? Kotak, kolom, dan langkah presensi disembunyikan; data yang sudah masuk tetap tersimpan.')" @endif>
            @csrf
            <button type="submit" class="btn btn-default btn-xs text-nowrap">
                {{ $training->has_attendance ? 'Tidak Dipakai' : 'Pakai Presensi' }}
            </button>
        </form>
        @if($training->has_attendance)
            <form action="/training/{{ $training->id }}/toggle-attendance" method="POST" class="m-0">
                @csrf
                @if($training->is_attendance_active)
                    <button type="submit" class="btn btn-default btn-xs text-danger text-nowrap">
                        Kunci Presensi
                    </button>
                @else
                    <button type="submit" class="btn btn-default btn-xs text-nowrap">
                        Buka Presensi
                    </button>
                @endif
            </form>
        @endif
    </div>
</div>

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
