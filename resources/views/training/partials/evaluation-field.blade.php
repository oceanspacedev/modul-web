{{--
    Saklar Form Evaluasi di form pelatihan. Pertanyaannya tetap (App\Support\TrainingEvaluationForm).
    $training: Training yang sedang diatur (null untuk pelatihan baru).
--}}
@php
    $evaluationOn = (bool) old('has_evaluation', ($training ?? null)?->has_evaluation ?? false);
    $evaluationCount = count(\App\Support\TrainingEvaluationForm::questions());
@endphp
<div class="card" id="form-evaluasi">
    <div class="card-header">
        <h3 class="card-title">Form Evaluasi</h3>
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
            Peserta menilai pemateri dan pelatihan ({{ $evaluationCount }} skala 1-5 dan masukan opsional) sebelum mengerjakan posttest.
            Nama, jabatan, divisi, tanggal, dan materi tidak ditanyakan lagi karena sudah ada di data peserta dan jadwal.
        </p>
    </div>
</div>
