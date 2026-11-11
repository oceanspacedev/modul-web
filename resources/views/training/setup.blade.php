@extends('layout.main_template')

@php
    // Pertanyaan pengaturan; ditampilkan satu per layar. Tanpa JavaScript semuanya tampil sekaligus.
    $questions = [
        'is_attendance_active' => [
            'question' => 'Buka presensi sekarang?',
            'yes' => 'Peserta langsung bisa absen.',
            'no' => 'Buka nanti dari Kontrol Sesi saat sesi mulai.',
        ],
        'require_attendance_proof' => [
            'question' => 'Wajib upload bukti foto saat presensi?',
            'yes' => 'Peserta upload foto/screenshot saat absen.',
            'no' => 'Peserta cukup klik Hadir.',
        ],
        'show_quiz_answers' => [
            'question' => 'Tampilkan jawaban benar/salah setelah kuis?',
            'yes' => 'Peserta melihat kunci dan pembahasan.',
            'no' => 'Peserta hanya melihat nilai.',
        ],
        'allow_quiz_retake' => [
            'question' => 'Boleh mengulang kuis?',
            'yes' => 'Tanpa batas, nilai tertinggi yang dipakai.',
            'no' => 'Kuis hanya bisa dikerjakan sekali.',
        ],
    ];
    $total = count($questions);
@endphp

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Pengaturan Pelatihan" :subtitle="$training->title"
            :breadcrumbs="['Pelatihan' => null, 'Jadwal Pelatihan' => route('training.index'), $training->title => route('training.show', $training), 'Pengaturan' => null]" />
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />

        <form action="{{ route('training.setup.save', $training) }}" method="POST" class="setup-wizard" id="setupWizard">
            @csrf
            <div class="card">
                <div class="setup-wizard-progress">
                    <span class="setup-wizard-count">Pertanyaan <span id="wizardStepNumber">1</span> dari {{ $total }}</span>
                    <div class="progress"><div class="progress-bar" id="wizardProgress" style="width: {{ round(100 / ($total + 1)) }}%"></div></div>
                </div>

                @foreach($questions as $field => $q)
                    @php $value = (bool) old($field, $training->{$field}); @endphp
                    <fieldset class="setup-wizard-step card-body" data-step="{{ $loop->index }}">
                        <legend class="setup-wizard-question">{{ $q['question'] }}</legend>
                        <div class="choice-cards">
                            <label class="choice-card">
                                <input type="radio" name="{{ $field }}" value="1" @checked($value)>
                                <span class="choice-card-title">Ya</span>
                                <span class="choice-card-text">{{ $q['yes'] }}</span>
                            </label>
                            <label class="choice-card">
                                <input type="radio" name="{{ $field }}" value="0" @checked(! $value)>
                                <span class="choice-card-title">Tidak</span>
                                <span class="choice-card-text">{{ $q['no'] }}</span>
                            </label>
                        </div>
                    </fieldset>
                @endforeach

                {{-- Ringkasan --}}
                <div class="setup-wizard-step card-body" data-step="{{ $total }}" data-summary>
                    <p class="setup-wizard-question">Sudah sesuai?</p>
                    <ul class="setup-wizard-summary">
                        @foreach($questions as $field => $q)
                            <li data-summary-for="{{ $field }}">
                                <span>{{ $q['question'] }}</span>
                                <strong data-answer></strong>
                            </li>
                        @endforeach
                    </ul>
                    <p class="text-muted text-xs mb-0">Semua bisa diubah lagi kapan saja di Kontrol Sesi.</p>
                </div>

                <div class="card-footer d-flex justify-content-between gap-2">
                    <button type="button" class="btn btn-default" id="wizardBack">Kembali</button>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary" id="wizardNext">Lanjut</button>
                        <button type="submit" class="btn btn-primary" id="wizardSave">Simpan</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>
@endsection

@section('footer')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('setupWizard');
    const steps = Array.from(form.querySelectorAll('.setup-wizard-step'));
    const total = steps.length - 1; // tanpa ringkasan
    const back = document.getElementById('wizardBack');
    const next = document.getElementById('wizardNext');
    const save = document.getElementById('wizardSave');
    let current = 0;

    form.classList.add('is-enhanced');

    function fillSummary() {
        form.querySelectorAll('[data-summary-for]').forEach(function (row) {
            const checked = form.querySelector('input[name="' + row.dataset.summaryFor + '"]:checked');
            row.querySelector('[data-answer]').textContent = checked && checked.value === '1' ? 'Ya' : 'Tidak';
        });
    }

    function show(index) {
        current = Math.max(0, Math.min(index, steps.length - 1));
        steps.forEach(function (step, i) { step.hidden = i !== current; });
        const onSummary = current === total;
        if (onSummary) fillSummary();
        document.getElementById('wizardStepNumber').textContent = Math.min(current + 1, total);
        document.querySelector('.setup-wizard-count').style.visibility = onSummary ? 'hidden' : '';
        document.getElementById('wizardProgress').style.width = Math.round((current + 1) / (total + 1) * 100) + '%';
        back.style.visibility = current === 0 ? 'hidden' : '';
        next.hidden = onSummary;
        save.hidden = !onSummary;
    }

    back.addEventListener('click', function () { show(current - 1); });
    next.addEventListener('click', function () { show(current + 1); });

    // Memilih jawaban langsung lanjut ke pertanyaan berikutnya.
    form.querySelectorAll('.choice-card input').forEach(function (input) {
        // 'click' juga terpicu saat memilih jawaban yang sudah terpilih (nilai bawaan).
        input.addEventListener('click', function () { setTimeout(function () { show(current + 1); }, 180); });
    });

    show(0);
});
</script>
@endsection
