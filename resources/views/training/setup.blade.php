@extends('layout.main_template')

@php
    // Pertanyaan pengaturan sesi; ditampilkan satu per layar. Tanpa JavaScript semuanya tampil sekaligus.
    // Aturan kuis (pretest/posttest, batas percobaan, hasil) diatur di blok Aturan Kuis pada form pelatihan.
    $questions = [
        'has_attendance' => [
            'question' => 'Pakai presensi kehadiran?',
            'yes' => 'Peserta mengonfirmasi hadir lewat portal.',
            'no' => 'Pelatihan tanpa presensi; kotak dan kolom presensi tidak tampil.',
        ],
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
                    @php
                        $options = $q['options'] ?? ['1' => ['Ya', $q['yes']], '0' => ['Tidak', $q['no']]];
                        // Presensi yang dijadwalkan atau waktunya habis dinilai dari keadaan nyatanya, bukan saklarnya saja.
                        $stored = $field === 'is_attendance_active' ? $training->isAttendanceOpenOrScheduled() : $training->{$field};
                        $current = session()->hasOldInput($field) ? (string) old($field) : (is_bool($stored) ? ($stored ? '1' : '0') : (string) $stored);
                    @endphp
                    <fieldset class="setup-wizard-step card-body" data-step="{{ $loop->index }}">
                        <legend class="setup-wizard-question">{{ $q['question'] }}</legend>
                        <div class="choice-cards">
                            @foreach($options as $value => [$optionTitle, $optionText])
                                <label class="choice-card">
                                    <input type="radio" name="{{ $field }}" value="{{ $value }}" @checked($current === (string) $value)>
                                    <span class="choice-card-title">{{ $optionTitle }}</span>
                                    <span class="choice-card-text">{{ $optionText }}</span>
                                </label>
                            @endforeach
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
                    <p class="text-muted text-xs mb-0">
                        Semua bisa diubah lagi kapan saja di Kontrol Sesi.
                        @if($training->hasQuiz())
                            Aturan kuis saat ini:
                            @foreach(\App\Models\Training::PHASES as $phase)
                                @if($training->hasPhase($phase))
                                    {{ \App\Models\Training::phaseLabel($phase) }} {{ mb_strtolower(\App\Support\QuizRules::summary($training->maxAttempts($phase), $training->showsAnswers($phase))) }}{{ $loop->last ? '.' : ';' }}
                                @endif
                            @endforeach
                        @endif
                    </p>
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

    // Tanpa presensi, pertanyaan presensi lainnya dilewati.
    function attendanceOff() {
        const choice = form.querySelector('input[name="has_attendance"]:checked');
        return choice !== null && choice.value === '0';
    }

    function fillSummary() {
        form.querySelectorAll('[data-summary-for]').forEach(function (row) {
            row.hidden = attendanceOff() && row.dataset.summaryFor !== 'has_attendance';
            const checked = form.querySelector('input[name="' + row.dataset.summaryFor + '"]:checked');
            row.querySelector('[data-answer]').textContent = checked ? checked.closest('.choice-card').querySelector('.choice-card-title').textContent : '-';
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

    // Pertanyaan pertama adalah "pakai presensi?"; bila tidak, langsung ke ringkasan (dan sebaliknya saat kembali).
    function move(delta) {
        if (attendanceOff() && ((delta > 0 && current === 0) || (delta < 0 && current === total))) {
            show(delta > 0 ? total : 0);
            return;
        }
        show(current + delta);
    }

    back.addEventListener('click', function () { move(-1); });
    next.addEventListener('click', function () { move(1); });

    // Memilih jawaban langsung lanjut ke pertanyaan berikutnya.
    form.querySelectorAll('.choice-card input').forEach(function (input) {
        // 'click' juga terpicu saat memilih jawaban yang sudah terpilih (nilai bawaan).
        input.addEventListener('click', function () { setTimeout(function () { move(1); }, 180); });
    });

    show(0);
});
</script>
@endsection
