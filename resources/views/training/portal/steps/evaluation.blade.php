{{--
    Kartu langkah Form Evaluasi di portal peserta. Urutan dan nomor langkahnya diatur pemateri (Training::portalSteps()); variabel
    lain berasal dari training/portal/index.blade.php.
--}}
<!-- FORM EVALUASI PELATIHAN -->
@if($training->hasEvaluationForm())
<div class="content-card" id="evaluationCard">
    <div class="content-card-header">
        <span><span class="badge badge-primary mr-1">Langkah {{ $stepNumber['evaluation'] }}</span> Evaluasi Pelatihan</span>
        @if($evaluation)
            <span class="badge badge-success">Terisi</span>
        @else
            <span class="text-muted small">{{ $training->evaluationQuestions->count() }} Pertanyaan</span>
        @endif
    </div>

    @if($evaluation)
        <div class="alert alert-success mb-0">
            <i class="fas fa-check-circle mr-1"></i>
            <strong>Terima kasih, evaluasi Anda sudah tersimpan.</strong>
            <span class="small d-block mt-1">Dikirim pada {{ $evaluation->submitted_at->format('d F Y, H:i') }} WIB.</span>
        </div>
    @elseif($training->isEvaluationOpen())
        <p class="text-muted small mb-3">
            Beri penilaian singkat tentang pemateri dan pelatihan ini{{ $evaluationFollowsPosttest ? ' sebelum mengerjakan kuis' : '' }}.
            Nama, jabatan, dan data pelatihan sudah terisi otomatis.
        </p>
        @if($training->evaluationClosesAt())
            <div class="alert alert-warning py-2 px-3 small mb-3">
                <i class="far fa-clock mr-1"></i> Bisa diisi sampai pukul <strong>{{ $training->evaluationClosesAt()->format('H:i') }}</strong> WIB.
            </div>
        @endif
        <a href="{{ $evaluationUrl }}" class="btn btn-primary btn-block">
            Isi Form Evaluasi
        </a>
    @else
        <div class="text-center py-4 text-muted">
            @if($training->evaluationOpensAt())
                Form evaluasi dibuka otomatis {{ \App\Models\Training::timeLabel($training->evaluationOpensAt()) }} WIB.
            @elseif($training->evaluationTimedOut())
                Waktu pengisian form evaluasi sudah habis.
            @elseif($training->hasEvaluationSchedule())
                Form evaluasi belum dibuka oleh pemateri.
            @else
                Form evaluasi dibuka saat sesi berlangsung.
            @endif
        </div>
    @endif
</div>
@endif
