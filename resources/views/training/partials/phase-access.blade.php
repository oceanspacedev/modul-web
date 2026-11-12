{{--
    Baris "Akses Pretest" / "Akses Posttest" / "Akses Presensi" di Kontrol Sesi: status (terkunci, terjadwal, terbuka,
    waktu habis), hitung mundur bila berbatas waktu, dan tombolnya. "Buka", "Ubah jadwal", dan "Atur waktu" membuka
    dialog #phaseTimeModal (jam buka, lalu jam tutup pasti / sekian menit / tanpa batas waktu). Saat jadwal buka tiba
    atau waktu habis, baris berganti status tanpa memuat ulang halaman.
    $phase: 'pretest' | 'posttest' | 'attendance' (Training::TIMED_STEPS).
    $heading: judul baris. $noun: sebutannya dalam kalimat ("Pretest" / "Kuis" / "Presensi").
    $questionCount: jumlah soal yang dipakai fase ini; null untuk presensi, yang tidak butuh soal.
    $emptyText: status saat soalnya belum ada. $toggleUrl: alamat buka/kunci.
    Opsional: $lockedText (status saat terkunci), $intro (penjelasan di dialog waktu), dan $extraControl
    ['url', 'label', 'confirm'] untuk satu tombol tambahan yang selalu tampil (mis. "Tidak Dipakai" pada presensi).
--}}
@php
    use App\Models\Training;

    $phaseActive = $training->isPhaseActive($phase);
    $phaseOpensAt = $training->phaseOpensAt($phase);
    $phaseClosesAt = $training->phaseClosesAt($phase);
    $phaseSecondsLeft = $training->phaseSecondsLeft($phase);
    $phaseDuration = $training->durationMinutes($phase);
    $phaseTimedOutText = 'Waktu habis, '.mb_strtolower($noun).' ditutup otomatis';
    $phaseOpenedText = $noun.' aktif'.($phaseClosesAt ? ' · tutup otomatis '.$phaseClosesAt->format('H:i') : ' · tanpa batas waktu');
    $questionCount ??= null;
    $lockedText ??= 'Pengerjaan '.mb_strtolower($noun).' terkunci';
    $intro ??= null;
    $extraControl ??= null;
@endphp
<div class="list-group-item d-flex {{ $phaseActive || $phaseOpensAt ? 'flex-wrap' : '' }} align-items-center justify-content-between gap-2"
    @if($phaseSecondsLeft !== null) data-phase-timer="{{ $phaseSecondsLeft }}" data-timeout-text="{{ $phaseTimedOutText }}" @endif
    @if($phaseOpensAt) data-phase-starts="{{ (int) ceil(now()->diffInSeconds($phaseOpensAt)) }}" data-opened-text="{{ $phaseOpenedText }}" data-lock-label="Kunci {{ $noun }}" @endif>
    <div class="min-w-0">
        <div class="text-sm font-weight-bold">{{ $heading }}</div>
        <div class="text-muted text-xs" data-phase-status>
            @if($phaseOpensAt)
                Terjadwal buka {{ Training::timeLabel($phaseOpensAt) }}
                &middot; {{ $phaseClosesAt ? 'tutup '.$phaseClosesAt->format('H:i') : 'tanpa batas waktu' }}
            @elseif($phaseSecondsLeft !== null)
                {{ $noun }} aktif &middot; tutup otomatis {{ $phaseClosesAt->format('H:i') }}
                (sisa <span class="font-weight-bold" data-phase-countdown>{{ \App\Support\QuizRules::clock($phaseSecondsLeft) }}</span>)
            @elseif($phaseActive)
                {{ $noun }} aktif &middot; tanpa batas waktu
            @elseif($training->phaseTimedOut($phase))
                {{ $phaseTimedOutText }}
            @elseif($questionCount === 0)
                {{ $emptyText }}
            @else
                {{ $lockedText }}
            @endif
        </div>
    </div>

    {{-- Fase terbuka atau terjadwal: tombolnya di baris sendiri supaya statusnya tidak terjepit. --}}
    @if($phaseActive || $phaseOpensAt)
    <div class="d-flex flex-wrap justify-content-end gap-1 w-100" data-phase-open-controls>
        @if($extraControl)
            <form action="{{ $extraControl['url'] }}" method="POST" class="m-0" @isset($extraControl['confirm']) onsubmit="return confirm(@js($extraControl['confirm']))" @endisset>
                @csrf
                <button type="submit" class="btn btn-default btn-xs text-nowrap">{{ $extraControl['label'] }}</button>
            </form>
        @endif
        @if($phaseOpensAt)
            <button type="button" class="btn btn-default btn-xs text-nowrap" data-toggle="modal" data-target="#phaseTimeModal" @if($intro) data-intro="{{ $intro }}" @endif
                data-url="{{ $toggleUrl }}" data-phase="{{ $phase }}" data-open="1"
                data-title="Ubah jadwal {{ $noun }}" data-submit="Simpan Jadwal"
                data-open-time="{{ $phaseOpensAt->format('H:i') }}" data-time="{{ $phaseClosesAt?->format('H:i') }}">
                Ubah jadwal
            </button>
        @else
            @if($phaseClosesAt)
                <form action="{{ route('training.extend-quiz-time', $training) }}" method="POST" class="m-0">
                    @csrf
                    <input type="hidden" name="phase" value="{{ $phase }}">
                    <input type="hidden" name="minutes" value="5">
                    <button type="submit" class="btn btn-default btn-xs text-nowrap" title="Mundurkan jam tutup 5 menit">+5 menit</button>
                </form>
            @endif
            <button type="button" class="btn btn-default btn-xs text-nowrap" data-toggle="modal" data-target="#phaseTimeModal" @if($intro) data-intro="{{ $intro }}" @endif
                data-url="{{ route('training.quiz-deadline', $training) }}" data-phase="{{ $phase }}"
                data-title="Atur waktu {{ $noun }}" data-submit="Simpan Waktu"
                data-time="{{ $phaseClosesAt?->format('H:i') }}">
                Atur waktu
            </button>
        @endif
        <form action="{{ $toggleUrl }}" method="POST" class="m-0">
            @csrf
            <input type="hidden" name="open" value="0">
            <button type="submit" class="btn btn-default btn-xs text-danger text-nowrap" data-phase-lock>
                {{ $phaseOpensAt ? 'Batalkan jadwal' : 'Kunci '.$noun }}
            </button>
        </form>
    </div>
    @endif

    {{-- Fase tertutup (atau waktunya habis): buka atau jadwalkan lewat dialog waktu. --}}
    <div class="{{ $phaseActive || $phaseOpensAt ? 'd-none' : 'd-flex' }} flex-wrap justify-content-end gap-1 flex-shrink-0" data-phase-closed-controls>
        @if($extraControl)
            <form action="{{ $extraControl['url'] }}" method="POST" class="m-0" @isset($extraControl['confirm']) onsubmit="return confirm(@js($extraControl['confirm']))" @endisset>
                @csrf
                <button type="submit" class="btn btn-default btn-xs text-nowrap">{{ $extraControl['label'] }}</button>
            </form>
        @endif
        @if($questionCount === 0)
            <a href="{{ route('training.questions.index', $training) }}" class="btn btn-default btn-xs text-nowrap" title="{{ $noun }} belum punya soal">
                Buat soal dulu
            </a>
        @else
            <button type="button" class="btn btn-default btn-xs text-nowrap" data-toggle="modal" data-target="#phaseTimeModal" @if($intro) data-intro="{{ $intro }}" @endif
                data-url="{{ $toggleUrl }}" data-phase="{{ $phase }}" data-open="1"
                data-title="Buka {{ $noun }}" data-submit="Buka {{ $noun }}"
                data-minutes="{{ $phaseDuration }}">
                Buka {{ $noun }}
            </button>
        @endif
    </div>
</div>
