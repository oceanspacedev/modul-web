{{--
    Baris Form Evaluasi di Kontrol Sesi Pelatihan (training/show.blade.php). Urutan barisnya mengikuti Urutan Langkah Peserta.
--}}
<!-- FORM EVALUASI: dibuka, dijadwalkan, dan dibatasi waktunya pemateri seperti presensi, pretest, dan posttest -->
@if($training->has_evaluation)
    @php $evaluationQuestionCount = $training->evaluationQuestions->count(); @endphp
    @include('training.partials.phase-access', [
        'phase' => 'evaluation', 'heading' => 'Form Evaluasi', 'noun' => 'Evaluasi',
        'questionCount' => $evaluationQuestionCount, 'emptyText' => 'Belum ada pertanyaan, jadi form belum bisa dibuka',
        'emptyAction' => ['url' => route('training.evaluation.index', $training), 'label' => 'Buat pertanyaan dulu'],
        'lockedText' => 'Form evaluasi ditutup untuk peserta',
        'toggleUrl' => route('training.toggle-evaluation-access', $training),
        'intro' => 'Jam buka dan jam tutup berlaku sama untuk semua peserta. Setelah jam tutup lewat, form evaluasi tidak bisa diisi lagi.',
        'subtext' => ($training->evaluationFollowsPosttest() ? 'Wajib diisi sebelum posttest selama form terbuka · ' : '').$evaluation['count'].' dari '.$stats['total'].' mengisi',
        'link' => ['url' => route('training.evaluation.index', $training), 'label' => 'Kelola pertanyaan ('.$evaluationQuestionCount.')'],
        'extraControl' => ['url' => route('training.toggle-evaluation', $training), 'label' => 'Nonaktifkan', 'class' => 'text-danger'],
    ])
@else
    <div class="list-group-item d-flex align-items-start justify-content-between gap-2">
        <div class="min-w-0">
            <div class="text-sm font-weight-bold">Form Evaluasi</div>
            <div class="text-muted text-xs">Peserta tidak diminta mengisi evaluasi</div>
            <a href="{{ route('training.evaluation.index', $training) }}" class="text-xs">Kelola pertanyaan ({{ $training->evaluationQuestions->count() }})</a>
        </div>
        <form action="{{ route('training.toggle-evaluation', $training) }}" method="POST" class="m-0 flex-shrink-0">
            @csrf
            <button type="submit" class="btn btn-default btn-xs text-nowrap">Aktifkan</button>
        </form>
    </div>
@endif
