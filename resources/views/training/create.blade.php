@extends('layout.main_template')

@php
    use App\Support\QuizRules;

    // Tiga langkah dalam satu form: 1 info, 2 langkah peserta, 3 peserta & materi. Tanpa JavaScript semuanya tampil
    // sekaligus. Setelah validasi gagal, form dibuka di langkah yang memuat kesalahan pertama.
    $steps = [1 => 'Info pelatihan', 2 => 'Langkah peserta', 3 => 'Peserta & materi'];
    $stepOfField = fn (string $field) => match (true) {
        str_starts_with($field, 'quiz') || str_starts_with($field, 'has_') || $field === 'require_attendance_proof' => 2,
        str_starts_with($field, 'participant') || str_starts_with($field, 'documents') || $field === 'send_wa_now' => 3,
        default => 1,
    };
    $firstError = $errors->keys()[0] ?? null;
    $startStep = $firstError ? $stepOfField($firstError) : 1;

    $resubmitted = session()->hasOldInput();
    $old = fn (string $key, $fresh) => $resubmitted ? old($key) : $fresh;
    $pretestOn = (bool) $old('quiz.pretest', false);
    $posttestOn = (bool) $old('quiz.posttest', true);
    $attendanceOn = (bool) $old('has_attendance', true);
    $proofOn = (bool) $old('require_attendance_proof', false);
    $evaluationOn = (bool) $old('has_evaluation', false);
    $saveLabel = $pretestOn || $posttestOn ? 'Simpan & Buat Soal' : 'Simpan Pelatihan';

    // Detail kuis memakai nilai bawaan yang sama dengan form Aturan Kuis; disembunyikan supaya tidak perlu dipikirkan.
    $quizDetails = [
        'pretest' => ['label' => 'Pretest', 'max' => $old('quiz.pretest_max_attempts', 1), 'answers' => (int) (bool) $old('quiz.pretest_shows_answers', false),
            'mode' => $old('quiz.pretest_mode', 'formal') ?? 'formal', 'duration' => $old('quiz.pretest_duration_minutes', null)],
        'posttest' => ['label' => 'Posttest', 'max' => $old('quiz.posttest_max_attempts', 1), 'answers' => (int) (bool) $old('quiz.posttest_shows_answers', true),
            'mode' => $old('quiz.posttest_mode', 'formal') ?? 'formal', 'duration' => $old('quiz.posttest_duration_minutes', null)],
    ];
@endphp

@section('content')
<section class="content-header training-create-header">
    <div class="container-fluid">
        <x-page-header title="Buat Pelatihan" :breadcrumbs="['Pelatihan' => null, 'Jadwal Pelatihan' => route('training.index'), 'Buat' => null]" />
    </div>
</section>

<section class="content training-create">
    <div class="container-fluid">
        <x-flash />

        <form action="/training" method="POST" id="trainingForm" enctype="multipart/form-data" data-start-step="{{ $startStep }}">
            @csrf

            <div class="training-create-layout">
            <div class="training-create-main">
            <ol class="training-create-stepper" aria-label="Langkah membuat pelatihan">
                @foreach($steps as $number => $label)
                    <li data-stepper="{{ $number }}"><span class="training-create-stepper-number">{{ $number }}</span>{{ $label }}</li>
                @endforeach
            </ol>

            {{-- 1. INFO PELATIHAN --}}
            <section class="training-create-step" data-step="1" aria-labelledby="create-step-1">
                <h2 class="training-create-title" id="create-step-1">Info pelatihan</h2>

                <div class="form-group">
                    <label for="title">Judul</label>
                    <input type="text" id="title" name="title" class="form-control" value="{{ old('title') }}"
                        placeholder="Contoh: Pengenalan SOP Keamanan" required>
                </div>

                <div class="form-group">
                    <label for="trainer_id">Pemateri</label>
                    <select id="trainer_id" name="trainer_id" class="form-control select2" style="width: 100%;" required>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" @selected(old('trainer_id', auth()->id()) == $u->id)>
                                {{ $u->nama_lengkap }} ({{ $divisiNames[$u->divisi_id] ?? 'Divisi Umum' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group col-sm-6">
                        <label for="training_date">Tanggal</label>
                        <input type="date" id="training_date" name="training_date" class="form-control"
                            value="{{ old('training_date', date('Y-m-d')) }}" required>
                    </div>
                    <div class="form-group col-sm-3 col-6">
                        <label for="start_time">Mulai</label>
                        <input type="time" id="start_time" name="start_time" class="form-control" value="{{ old('start_time', '09:00') }}" required>
                    </div>
                    <div class="form-group col-sm-3 col-6">
                        <label for="end_time">Selesai</label>
                        <input type="time" id="end_time" name="end_time" class="form-control" value="{{ old('end_time', '11:00') }}" required>
                    </div>
                </div>

                @include('training.partials.meeting-fields', ['training' => null])

                <div class="form-group mb-0">
                    <label for="description">Deskripsi <span class="training-create-optional">opsional</span></label>
                    <textarea id="description" name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                </div>
            </section>

            {{-- 2. LANGKAH PESERTA: semua pilihan Ya/Tidak di satu daftar, sesuai urutan peserta di portal. --}}
            <section class="training-create-step" data-step="2" aria-labelledby="create-step-2">
                <h2 class="training-create-title" id="create-step-2">Langkah peserta</h2>
                <p class="training-create-help">Nyalakan yang dipakai. Semuanya bisa diubah lagi nanti dari halaman pelatihan.</p>

                <ul class="training-create-toggles">
                    <li>
                        <input type="hidden" name="quiz[pretest]" value="0">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="quiz_pretest" name="quiz[pretest]" value="1" data-quiz-toggle @checked($pretestOn)>
                            <label class="custom-control-label" for="quiz_pretest">Pretest</label>
                        </div>
                        <span class="training-create-hint">Kuis sebelum sesi dimulai</span>
                    </li>
                    <li>
                        <input type="hidden" name="has_attendance" value="0">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="has_attendance" name="has_attendance" value="1" @checked($attendanceOn)>
                            <label class="custom-control-label" for="has_attendance">Presensi</label>
                        </div>
                        <span class="training-create-hint">Peserta menekan Hadir di portal</span>
                        <div class="training-create-sub" data-show-when="has_attendance" @unless($attendanceOn) hidden @endunless>
                            <input type="hidden" name="require_attendance_proof" value="0">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="require_attendance_proof" name="require_attendance_proof" value="1" @checked($proofOn)>
                                <label class="custom-control-label" for="require_attendance_proof">Wajib upload foto bukti hadir</label>
                            </div>
                        </div>
                    </li>
                    <li>
                        <input type="hidden" name="has_evaluation" value="0">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="has_evaluation" name="has_evaluation" value="1" @checked($evaluationOn)>
                            <label class="custom-control-label" for="has_evaluation">Form evaluasi</label>
                        </div>
                        <span class="training-create-hint">Peserta menilai pemateri dan pelatihan</span>
                    </li>
                    <li>
                        <input type="hidden" name="quiz[posttest]" value="0">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="quiz_posttest" name="quiz[posttest]" value="1" data-quiz-toggle @checked($posttestOn)>
                            <label class="custom-control-label" for="quiz_posttest">Posttest</label>
                        </div>
                        <span class="training-create-hint">Kuis setelah sesi selesai</span>
                    </li>
                </ul>

                <details class="training-create-details" data-quiz-details @if(! $pretestOn && ! $posttestOn) hidden @endif @if($firstError && str_starts_with($firstError, 'quiz.')) open @endif>
                    <summary>Atur detail kuis <span class="training-create-optional">opsional</span></summary>
                    <p class="training-create-help">Bawaan: dikerjakan sekali, mode formal, tanpa batas waktu.</p>
                    @foreach($quizDetails as $phase => $rule)
                        <fieldset class="training-create-quiz" data-quiz-phase="{{ $phase }}">
                            <legend>{{ $rule['label'] }}</legend>
                            <div class="form-row">
                                <div class="form-group col-sm-6">
                                    <label for="quiz_{{ $phase }}_max">Boleh dikerjakan</label>
                                    <select class="form-control" id="quiz_{{ $phase }}_max" name="quiz[{{ $phase }}_max_attempts]">
                                        @foreach(QuizRules::attemptOptions($rule['max'] === null ? null : (int) $rule['max']) as $value => $label)
                                            <option value="{{ $value }}" @selected((string) $rule['max'] === (string) $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-sm-6">
                                    <label for="quiz_{{ $phase }}_answers">Setelah selesai, peserta melihat</label>
                                    <select class="form-control" id="quiz_{{ $phase }}_answers" name="quiz[{{ $phase }}_shows_answers]">
                                        @foreach(QuizRules::answerOptions() as $value => $label)
                                            <option value="{{ $value }}" @selected((string) $rule['answers'] === (string) $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-sm-6 mb-sm-0">
                                    <label for="quiz_{{ $phase }}_mode">Mode</label>
                                    <select class="form-control" id="quiz_{{ $phase }}_mode" name="quiz[{{ $phase }}_mode]">
                                        <option value="formal" @selected($rule['mode'] !== 'game')>Formal: semua soal satu halaman</option>
                                        <option value="game" @selected($rule['mode'] === 'game')>Game: satu soal per layar</option>
                                    </select>
                                </div>
                                <div class="form-group col-sm-6 mb-0">
                                    <label for="quiz_{{ $phase }}_duration">Batas waktu sejak dibuka</label>
                                    <select class="form-control" id="quiz_{{ $phase }}_duration" name="quiz[{{ $phase }}_duration_minutes]">
                                        @foreach(QuizRules::durationOptions(is_numeric($rule['duration']) ? (int) $rule['duration'] : null) as $value => $label)
                                            <option value="{{ $value }}" @selected((string) $rule['duration'] === (string) $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </fieldset>
                    @endforeach
                </details>
            </section>

            {{-- 3. PESERTA & MATERI --}}
            <section class="training-create-step" data-step="3" aria-labelledby="create-step-3">
                <div class="training-create-title-row">
                    <h2 class="training-create-title" id="create-step-3">Peserta <span class="training-create-count"><span id="selectedCountBadge">0</span> dipilih</span></h2>
                    <div class="training-create-actions">
                        <button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#importParticipantsModal">
                            <i class="fas fa-file-excel" aria-hidden="true"></i> Impor Excel
                        </button>
                        <button type="button" id="btnSelectAll" class="btn btn-default btn-sm">Pilih semua</button>
                        <button type="button" id="btnDeselectAll" class="btn btn-default btn-sm">Kosongkan</button>
                    </div>
                </div>

                @include('training.partials.participant-picker', ['selected' => old('participants', []), 'saved' => []])

                <div id="participantFileSummary" class="border rounded p-2 mt-3 d-none flex-wrap align-items-center gap-2">
                    <i class="fas fa-file-excel text-success" aria-hidden="true"></i>
                    <span class="small flex-grow-1 text-break" style="min-width: 12rem;">
                        Impor dari Excel: <b id="participantFileName"></b><br>
                        <span class="text-muted">Diproses setelah pelatihan disimpan; hasilnya dikirim ke Notifikasi.</span>
                    </span>
                    <button type="button" class="btn btn-xs btn-default" data-toggle="modal" data-target="#importParticipantsModal">Ganti</button>
                    <button type="button" class="btn btn-xs btn-default" id="btnClearParticipantFile">Hapus</button>
                </div>
                @error('participant_file')
                    <div class="invalid-feedback d-block">{{ $message }} Pilih ulang file Excel-nya.</div>
                @enderror
                <p class="invalid-feedback" id="participantsRequired">Pilih minimal satu peserta atau impor peserta dari file Excel.</p>

                {{-- IMPORT PESERTA DARI EXCEL: file ikut terkirim bersama form dan diproses setelah pelatihan disimpan. --}}
                <div class="modal fade" id="importParticipantsModal" tabindex="-1" role="dialog" aria-labelledby="importParticipantsModalTitle" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="importParticipantsModalTitle">Import Peserta dari Excel</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                            </div>
                            <div class="modal-body">
                                <a href="{{ route('training.participant-template') }}" class="btn btn-default btn-block mb-3">
                                    <i class="fas fa-download"></i> Unduh Template Excel (.xlsx)
                                </a>
                                <div class="form-group">
                                    <label for="importParticipantsFile">File Spreadsheet (.xlsx, .xls, .csv)</label>
                                    <input type="file" name="participant_file" id="importParticipantsFile" class="form-control" accept=".xlsx,.xls,.csv">
                                </div>
                                <p class="small mb-2">
                                    Peserta dari file ditambahkan <b>setelah pelatihan disimpan</b>, bersama peserta yang Anda pilih di daftar.
                                    Undangan WhatsApp untuk mereka mengikuti pilihan <b>Kirim undangan WhatsApp</b> di form ini.
                                </p>
                                @include('training.partials.participant-import-help')
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
                                <button type="button" class="btn btn-primary" data-dismiss="modal">Gunakan File</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group mt-4">
                    <label for="documents">Dokumen materi <span class="training-create-optional">opsional</span></label>
                    <select id="documents" name="documents[]" class="form-control select2" multiple="multiple" style="width: 100%;" data-placeholder="Pilih dokumen">
                        @foreach($documents as $doc)
                            <option value="{{ $doc->id }}" @selected(is_array(old('documents')) && in_array($doc->id, old('documents')))>
                                {{ $doc->name }} ({{ $doc->dokumentype->name ?? 'Dokumen' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="send_wa_now" name="send_wa_now" value="1" @checked($old('send_wa_now', true))>
                    <label class="custom-control-label" for="send_wa_now">Kirim undangan WhatsApp ke peserta setelah disimpan</label>
                </div>
            </section>

            <div class="training-create-nav">
                <a href="{{ route('training.index') }}" class="btn btn-default" data-nav-cancel>Batal</a>
                <button type="button" class="btn btn-default" data-nav-back hidden>Kembali</button>
                <span class="training-create-nav-spacer"></span>
                <button type="button" class="btn btn-primary" data-nav-next hidden>Lanjut</button>
            </div>
            </div>

            {{-- RINGKASAN: ikut terisi saat form diisi, dengan tombol simpan yang selalu terlihat. --}}
            <aside class="training-create-summary" aria-labelledby="create-summary-title">
                <h2 class="training-create-title" id="create-summary-title">Ringkasan</h2>
                <dl>
                    <div>
                        <dt>Judul</dt>
                        <dd data-summary="title" data-empty="Belum diisi">{{ old('title') ?: 'Belum diisi' }}</dd>
                    </div>
                    <div>
                        <dt>Jadwal</dt>
                        <dd data-summary="schedule"></dd>
                    </div>
                    <div>
                        <dt>Tempat</dt>
                        <dd data-summary="place">Tatap muka</dd>
                    </div>
                    <div>
                        <dt>Langkah peserta</dt>
                        <dd data-summary="steps"></dd>
                    </div>
                    <div>
                        <dt>Peserta</dt>
                        <dd data-summary="participants">{{ count(old('participants', [])) }} dipilih</dd>
                    </div>
                    <div>
                        <dt>Dokumen materi</dt>
                        <dd data-summary="documents">{{ count(old('documents', [])) ?: 'Tidak ada' }}</dd>
                    </div>
                </dl>
                <button type="submit" class="btn btn-primary btn-block" data-summary-submit>{{ $saveLabel }}</button>
                <p class="training-create-help mb-0 mt-2">Semua bisa diubah lagi setelah disimpan.</p>
            </aside>
            </div>
        </form>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('trainingForm');
    const steps = Array.from(form.querySelectorAll('[data-step]'));
    const stepper = Array.from(form.querySelectorAll('[data-stepper]'));
    const back = form.querySelector('[data-nav-back]');
    const next = form.querySelector('[data-nav-next]');
    const cancel = form.querySelector('[data-nav-cancel]');
    const quizToggles = Array.from(form.querySelectorAll('[data-quiz-toggle]'));
    const fileInput = document.getElementById('importParticipantsFile');
    let current = 1;

    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('#documents').select2({ width: '100%', placeholder: 'Pilih dokumen' });
        $('#trainer_id').select2({ width: '100%' });
    }

    // Satu langkah per layar; tanpa JavaScript semua langkah tetap tampil sebagai satu form.
    form.classList.add('is-stepped');

    function show(step) {
        current = Math.max(1, Math.min(step, steps.length));
        steps.forEach(section => { section.hidden = Number(section.dataset.step) !== current; });
        stepper.forEach(item => {
            const number = Number(item.dataset.stepper);
            item.classList.toggle('is-current', number === current);
            item.classList.toggle('is-done', number < current);
            if (number === current) item.setAttribute('aria-current', 'step'); else item.removeAttribute('aria-current');
        });
        back.hidden = current === 1;
        cancel.hidden = current !== 1;
        next.hidden = current === steps.length;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Isian wajib di langkah ini harus benar dulu sebelum lanjut.
    function stepIsValid(step) {
        const fields = Array.from(steps[step - 1].querySelectorAll('input, select, textarea')).filter(field => !field.disabled);
        const invalid = fields.find(field => !field.checkValidity());
        if (invalid) {
            invalid.reportValidity();
            return false;
        }
        return true;
    }

    function participantsChosen() {
        const count = parseInt(document.getElementById('selectedCountBadge').textContent, 10) || 0;
        return count > 0 || (fileInput.files && fileInput.files.length > 0);
    }

    next.addEventListener('click', () => { if (stepIsValid(current)) show(current + 1); });
    back.addEventListener('click', () => show(current - 1));
    stepper.forEach(item => item.addEventListener('click', () => {
        const target = Number(item.dataset.stepper);
        if (target < current || stepIsValid(current)) show(target);
    }));

    // Simpan bisa ditekan dari langkah mana pun (tombol di Ringkasan), jadi semua langkah diperiksa di sini dan
    // langkah pertama yang belum lengkap dibuka. Validasi bawaan browser tidak bisa menunjuk isian di langkah tersembunyi.
    form.noValidate = true;
    form.addEventListener('submit', function (e) {
        const stop = step => {
            e.preventDefault();
            e.stopImmediatePropagation();
            show(step);
        };
        for (let step = 1; step <= steps.length; step++) {
            const invalid = Array.from(steps[step - 1].querySelectorAll('input, select, textarea'))
                .find(field => !field.disabled && !field.checkValidity());
            if (invalid) {
                stop(step);
                setTimeout(() => invalid.reportValidity(), 300);
                return;
            }
        }
        const missing = !participantsChosen();
        document.getElementById('participantsRequired').classList.toggle('d-block', missing);
        if (missing) stop(3);
    }, true);

    // Ringkasan di samping form mengikuti isian.
    const summaryField = name => form.querySelector('[data-summary="' + name + '"]');
    const dateFormat = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
    function refreshSummary() {
        const title = document.getElementById('title').value.trim();
        summaryField('title').textContent = title || summaryField('title').dataset.empty;
        summaryField('title').classList.toggle('is-empty', !title);

        const date = document.getElementById('training_date').value;
        const start = document.getElementById('start_time').value;
        const end = document.getElementById('end_time').value;
        summaryField('schedule').textContent = (date ? dateFormat.format(new Date(date + 'T00:00')) : '-') + ' · ' + (start || '--:--') + '–' + (end || '--:--') + ' WIB';

        const platform = document.getElementById('meeting_platform');
        summaryField('place').textContent = platform && platform.value ? platform.options[platform.selectedIndex].text : 'Tatap muka';

        const used = [
            ['quiz_pretest', 'Pretest'],
            ['has_attendance', document.getElementById('require_attendance_proof').checked ? 'Presensi (wajib foto)' : 'Presensi'],
            ['has_evaluation', 'Form evaluasi'], ['quiz_posttest', 'Posttest'],
        ].filter(([id]) => document.getElementById(id).checked).map(([, label]) => label);
        summaryField('steps').textContent = used.length ? used.join(' › ') : 'Tidak ada';

        const count = parseInt(document.getElementById('selectedCountBadge').textContent, 10) || 0;
        summaryField('participants').textContent = count + ' dipilih' + (fileInput.files && fileInput.files.length ? ' + file Excel' : '');

        const documents = document.getElementById('documents').selectedOptions.length;
        summaryField('documents').textContent = documents ? documents + ' dokumen' : 'Tidak ada';
    }
    form.addEventListener('input', refreshSummary);
    form.addEventListener('change', refreshSummary);
    if (typeof $ !== 'undefined') $('#documents, #meeting_platform, #trainer_id').on('change', refreshSummary);
    new MutationObserver(refreshSummary).observe(document.getElementById('selectedCountBadge'), { childList: true, characterData: true, subtree: true });

    // Tombol simpan menyebut langkah berikutnya: buat soal bila ada kuis.
    const saveButtons = form.querySelectorAll('[data-summary-submit]');
    function refreshQuiz() {
        const anyQuiz = quizToggles.some(toggle => toggle.checked);
        saveButtons.forEach(button => { button.textContent = anyQuiz ? 'Simpan & Buat Soal' : 'Simpan Pelatihan'; });
        form.querySelector('[data-quiz-details]').hidden = !anyQuiz;
        form.querySelectorAll('[data-quiz-phase]').forEach(fieldset => {
            fieldset.hidden = !document.getElementById('quiz_' + fieldset.dataset.quizPhase).checked;
        });
    }
    quizToggles.forEach(toggle => toggle.addEventListener('change', refreshQuiz));
    refreshQuiz();

    // Pilihan tambahan presensi hanya tampil saat presensi dipakai.
    const attendance = document.getElementById('has_attendance');
    attendance.addEventListener('change', function () {
        form.querySelector('[data-show-when="has_attendance"]').hidden = !attendance.checked;
    });

    // Ringkasan file peserta yang akan diimpor, supaya terlihat tanpa membuka modal.
    const summary = document.getElementById('participantFileSummary');
    const showFile = function () {
        const file = fileInput.files[0];
        document.getElementById('participantFileName').textContent = file ? file.name : '';
        summary.classList.toggle('d-none', !file);
        summary.classList.toggle('d-flex', !!file);
        if (file) document.getElementById('participantsRequired').classList.remove('d-block');
    };
    fileInput.addEventListener('change', showFile);
    document.getElementById('btnClearParticipantFile').addEventListener('click', function () {
        fileInput.value = '';
        showFile();
        refreshSummary();
    });
    showFile();

    show(Number(form.dataset.startStep) || 1);
    refreshSummary();
});
</script>
@endsection
