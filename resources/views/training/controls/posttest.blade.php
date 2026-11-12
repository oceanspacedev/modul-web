{{--
    Baris Posttest di Kontrol Sesi Pelatihan (training/show.blade.php). Urutan barisnya mengikuti Urutan Langkah Peserta.
--}}
<!-- AKSES POSTTEST (KUIS UTAMA) -->
@if($training->has_posttest)
    @include('training.partials.phase-access', [
        'phase' => 'posttest', 'heading' => $training->has_pretest ? 'Akses Posttest' : 'Akses Kuis', 'noun' => 'Kuis',
        'questionCount' => $training->questionsFor('posttest')->count(), 'emptyText' => 'Belum ada soal kuis',
        'toggleUrl' => route('training.toggle-quiz', $training),
    ])
@endif
