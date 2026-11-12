{{--
    Baris Form Evaluasi di Kontrol Sesi Pelatihan (training/show.blade.php). Urutan barisnya mengikuti Urutan Langkah Peserta.
--}}
<!-- FORM EVALUASI -->
@php
    // Form evaluasi selalu punya jam sendiri, terpisah dari posttest.
    $evaluationTimed = (bool) $training->has_evaluation;
    $evaluationOpensAt = $training->evaluationOpensAt();
    $evaluationClosesAt = $training->evaluationClosesAt();
@endphp
<div class="list-group-item d-flex {{ $evaluationTimed ? 'flex-wrap' : '' }} align-items-start justify-content-between gap-2">
    <div class="min-w-0">
        <div class="text-sm font-weight-bold">Form Evaluasi</div>
        <div class="text-muted text-xs">
            @if(! $training->has_evaluation)
                Peserta tidak diminta mengisi evaluasi
            @else
                @if($training->evaluationFollowsPosttest())
                    Wajib diisi sebelum posttest &middot;
                @endif
                @if($evaluationOpensAt)
                    Terjadwal buka {{ \App\Models\Training::timeLabel($evaluationOpensAt) }}
                    &middot; {{ $training->evaluation_closes_at ? 'tutup '.$training->evaluation_closes_at->format('H:i') : 'tanpa batas waktu' }}
                @elseif($training->evaluationTimedOut())
                    Waktu pengisian habis (tutup {{ $training->evaluation_closes_at->format('H:i') }})
                @elseif($training->hasEvaluationSchedule())
                    Terbuka &middot; {{ $evaluationClosesAt ? 'tutup otomatis '.$evaluationClosesAt->format('H:i') : 'tanpa batas waktu' }}
                @else
                    Diisi peserta selama/sesudah sesi
                @endif
                &middot; {{ $evaluation['count'] }} dari {{ $stats['total'] }} mengisi
            @endif
        </div>
        @if($training->has_evaluation && $training->evaluationQuestions->isEmpty())
            <div class="text-warning text-xs">Belum ada pertanyaan, jadi form belum tampil ke peserta</div>
        @endif
        <a href="{{ route('training.evaluation.index', $training) }}" class="text-xs">Kelola pertanyaan ({{ $training->evaluationQuestions->count() }})</a>
    </div>
    <div class="d-flex flex-wrap justify-content-end gap-1 {{ $evaluationTimed ? 'w-100' : 'flex-shrink-0' }}">
        @if($evaluationTimed)
            @if($training->hasEvaluationSchedule())
                <form action="{{ route('training.evaluation-time', $training) }}" method="POST" class="m-0">
                    @csrf
                    <input type="hidden" name="reset" value="1">
                    <button type="submit" class="btn btn-default btn-xs text-nowrap" title="Kembali terbuka selama sesi berlangsung atau sudah selesai">Ikuti status</button>
                </form>
            @endif
            <button type="button" class="btn btn-default btn-xs text-nowrap" data-toggle="modal" data-target="#phaseTimeModal"
                data-url="{{ route('training.evaluation-time', $training) }}" data-phase="evaluation" data-open="1"
                data-title="Atur waktu Form Evaluasi" data-submit="Simpan Waktu"
                data-intro="Jam buka dan jam tutup berlaku sama untuk semua peserta. Setelah jam tutup lewat, form evaluasi tidak bisa diisi lagi."
                data-none-label="Tanpa batas waktu"
                data-open-time="{{ $evaluationOpensAt?->format('H:i') }}" data-time="{{ ($evaluationOpensAt || $evaluationClosesAt) ? $training->evaluation_closes_at?->format('H:i') : '' }}">
                Atur waktu
            </button>
        @endif
        <form action="{{ route('training.toggle-evaluation', $training) }}" method="POST" class="m-0">
            @csrf
            <button type="submit" class="btn btn-default btn-xs text-nowrap {{ $training->has_evaluation ? 'text-danger' : '' }}">
                {{ $training->has_evaluation ? 'Nonaktifkan' : 'Aktifkan' }}
            </button>
        </form>
    </div>
</div>
