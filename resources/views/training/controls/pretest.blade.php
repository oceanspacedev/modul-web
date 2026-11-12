{{--
    Baris Pretest di Kontrol Sesi Pelatihan (training/show.blade.php). Urutan barisnya mengikuti Urutan Langkah Peserta.
--}}
<!-- AKSES PRETEST -->
@if($training->has_pretest)
    @include('training.partials.phase-access', [
        'phase' => 'pretest', 'heading' => 'Akses Pretest', 'noun' => 'Pretest',
        'questionCount' => $pretestQuestionCount, 'emptyText' => 'Belum ada soal untuk pretest',
        'toggleUrl' => route('training.toggle-pretest', $training),
    ])
@endif
