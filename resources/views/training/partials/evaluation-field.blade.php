{{--
    Saklar Form Evaluasi di form pelatihan. Pertanyaannya dikelola per pelatihan di halaman Kelola Pertanyaan.
    $training: Training yang sedang diatur (null untuk pelatihan baru).
--}}
@php
    $evaluationTraining = $training ?? null;
    $evaluationOn = (bool) old('has_evaluation', $evaluationTraining?->has_evaluation ?? false);
@endphp
<div class="card" id="form-evaluasi">
    <div class="card-header">
        <h3 class="card-title">Form Evaluasi</h3>
        @if($evaluationTraining?->exists)
            <a href="{{ route('training.evaluation.index', $evaluationTraining) }}" class="small card-tools">Kelola pertanyaan ({{ $evaluationTraining->evaluationQuestions()->count() }})</a>
        @endif
    </div>
    <div class="card-body">
        <div class="quiz-rule mb-0">
            <div class="quiz-rule-head">
                <input type="hidden" name="has_evaluation" value="0">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="has_evaluation" name="has_evaluation" value="1" @checked($evaluationOn)>
                    <label class="custom-control-label" for="has_evaluation">Evaluasi pelatihan</label>
                </div>
                <span class="quiz-rule-hint">Sebelum posttest</span>
            </div>
        </div>
        <p class="text-muted small mb-0 mt-2">
            Peserta menilai pemateri dan pelatihan sebelum mengerjakan posttest. Form tertutup sampai Anda membukanya lewat
            <b>Buka Evaluasi</b> di Kontrol Sesi (sekarang atau terjadwal). Form dimulai dari pertanyaan bawaan
            ({{ count(\App\Support\TrainingEvaluationForm::defaultQuestions()) }} pertanyaan) dan bisa diubah per pelatihan lewat
            <b>Kelola pertanyaan</b>{{ $evaluationTraining?->exists ? '' : ' setelah pelatihan disimpan' }}.
            Nama, jabatan, divisi, tanggal, dan materi tidak perlu ditanyakan karena sudah ada di data peserta dan jadwal.
        </p>
    </div>
</div>
