{{--
    Peringatan batas waktu pretest/posttest/presensi di portal peserta: jam tutup otomatis dan hitung mundurnya. Selalu
    ada di halaman (tersembunyi bila tertutup atau tanpa batas waktu) supaya bisa dimunculkan dari pengecekan status
    berkala tanpa memuat ulang.
    $phase: 'pretest' | 'posttest' | 'attendance'.
--}}
@php
    $deadlineSeconds = $training->phaseSecondsLeft($phase);
    $deadlineAt = $deadlineSeconds === null ? null : $training->phaseClosesAt($phase);
@endphp
<div class="alert alert-warning py-2 px-3 small mb-3" data-deadline="{{ $phase }}" data-seconds-left="{{ $deadlineSeconds }}" @unless($deadlineAt) hidden @endunless>
    <i class="far fa-clock mr-1"></i>
    Ditutup otomatis pukul <strong data-deadline-time>{{ $deadlineAt?->format('H:i') }}</strong> WIB
    &middot; sisa waktu <strong data-deadline-clock>{{ $deadlineSeconds === null ? '' : \App\Support\QuizRules::clock($deadlineSeconds) }}</strong>
</div>
