@extends('layout.main_template')

@php
    $subtitle = ($module->isOwned() ? ($module->isOwnedByVideo() ? 'Video' : 'Dokumen').': ' : '').$module->title.' · soal yang sama dipakai untuk pretest dan posttest';
@endphp

@section('content')
<style>
    /* Pilihan jawaban hasil AI: kotak huruf dan kolom teks setinggi, kunci jawaban ditandai hijau. */
    .ai-option { display: flex; align-items: stretch; }
    .ai-option-key {
        display: flex; align-items: center; gap: 6px; flex: 0 0 56px; justify-content: center;
        margin: 0; padding: 0 10px; border: 1px solid var(--gray-200); border-right: 0;
        border-radius: var(--radius-lg) 0 0 var(--radius-lg);
        background: var(--gray-50); color: var(--gray-700); font-weight: 600; cursor: pointer;
    }
    .ai-option-key input { margin: 0; cursor: pointer; }
    .ai-option .ai-opt { height: 38px; border-radius: 0 var(--radius-lg) var(--radius-lg) 0; }
    .ai-option.is-key .ai-option-key { background: var(--success-50); border-color: var(--success-600); color: var(--success-700); }
    .ai-option.is-key .ai-opt { border-color: var(--success-600); }
</style>

<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Soal Pretest & Posttest" :subtitle="$subtitle" eyebrow="Modul Belajar / Kelola Soal">
            <x-slot:actions>
                <a href="{{ $module->managementUrl() }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Kembali</a>
                <a href="{{ $module->url('questions.template') }}" class="btn btn-default">
                    <i class="fas fa-download"></i> Template
                </a>
                <button type="button" class="btn btn-default" data-toggle="modal" data-target="#importExcelModal">
                    <i class="fas fa-file-import"></i> Import Excel / CSV
                </button>
                <button type="button" class="btn btn-default" data-toggle="modal" data-target="#addQuestionModal">
                    <i class="fas fa-plus"></i> Tambah Manual
                </button>
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#aiGenerateModal">
                    <i class="fas fa-magic"></i> Buat Soal via AI
                </button>
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        @include('learning_module.partials.alerts')

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Daftar Pertanyaan <span class="text-muted font-weight-normal">({{ $questions->count() }} soal)</span></h3>
                <div class="card-tools d-flex align-items-center flex-wrap gap-2">
                    @if($module->isOwned())
                        {{-- Kuis milik video/dokumen aktif otomatis saat pretest/posttest nyala dan sudah ada soal. --}}
                        @if($module->is_published)
                            <span class="badge badge-success">Kuis aktif</span>
                        @else
                            <span class="badge badge-secondary">Kuis belum aktif</span>
                        @endif
                        <a href="{{ $module->url('results') }}" class="btn btn-xs btn-default">Rekap Nilai</a>
                    @else
                        @if($module->is_published)
                            <span class="badge badge-success">Dipublikasikan</span>
                        @else
                            <span class="badge badge-secondary">Draft</span>
                        @endif
                        <form action="{{ route('learning-module.publish', $module) }}" method="POST" class="m-0">
                            @csrf
                            <button type="submit" class="btn btn-xs btn-default">
                                {{ $module->is_published ? 'Tarik Publikasi' : 'Publikasikan Modul' }}
                            </button>
                        </form>
                    @endif
                    @if($questions->isNotEmpty())
                        <form action="{{ $module->url('questions.delete-all') }}" method="POST" class="m-0" onsubmit="return confirm('Hapus SEMUA soal modul ini? Riwayat nilai peserta tetap tersimpan.')">
                            @csrf
                            <button type="submit" class="btn btn-xs btn-default text-danger">Hapus Semua Soal</button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="card-body">
                @forelse($questions as $index => $q)
                <div class="border rounded mb-3">
                    <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                        <strong class="small">Pertanyaan #{{ $index + 1 }}</strong>
                        <div class="d-flex align-items-center gap-1">
                            <button type="button" class="btn btn-default btn-xs" data-toggle="modal" data-target="#editModal_{{ $q->id }}">Edit</button>
                            <form action="{{ $module->url('questions.destroy', ['question' => $q]) }}" method="POST" class="d-inline m-0" onsubmit="return confirm('Hapus soal ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-default btn-xs text-danger">Hapus</button>
                            </form>
                        </div>
                    </div>
                    <div class="p-3">
                        <p class="font-weight-bold mb-3" style="white-space: pre-line;">{{ $q->question }}</p>
                        @if($q->imageUrl())
                            <a href="{{ $q->imageUrl() }}" target="_blank" rel="noopener" class="d-inline-block mb-3">
                                <img src="{{ $q->imageUrl() }}" alt="Gambar soal" class="quiz-img">
                            </a>
                        @endif
                        <div class="row text-sm">
                            @foreach($q->options() as $letter => $text)
                                @php $isKey = $q->correct_answer === $letter; @endphp
                                <div class="col-md-6 mb-2">
                                    <div class="d-flex align-items-start justify-content-between gap-2 px-2 py-1 border rounded {{ $isKey ? 'bg-soft-success font-weight-bold' : '' }}">
                                        <span>
                                            {{ strtoupper($letter) }}. {{ $text }}
                                            @if($q->optionImageUrl($letter))
                                                <a href="{{ $q->optionImageUrl($letter) }}" target="_blank" rel="noopener" class="d-block mt-1">
                                                    <img src="{{ $q->optionImageUrl($letter) }}" alt="Gambar pilihan {{ strtoupper($letter) }}" class="quiz-img quiz-opt-img">
                                                </a>
                                            @endif
                                        </span>
                                        @if($isKey)
                                            <span class="badge badge-success">Kunci</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @if($q->explanation)
                            <div class="small text-muted mt-2 pt-2 border-top">
                                <strong>Pembahasan:</strong> {{ $q->explanation }}
                            </div>
                        @endif
                    </div>
                </div>

                <div class="modal fade" id="editModal_{{ $q->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <form action="{{ $module->url('questions.update', ['question' => $q]) }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                @method('PUT')
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit Soal #{{ $index + 1 }}</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">&times;</button>
                                </div>
                                <div class="modal-body">
                                    @include('learning_module.partials.question-fields', ['question' => $q])
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @empty
                <x-empty-state icon="fa-question-circle" title="Belum ada soal untuk modul ini" text="Buat soal dengan AI dari materi modul, tambah manual, atau import dari Excel.">
                    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#aiGenerateModal">Buat Soal via AI</button>
                    <button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#addQuestionModal">Tambah Manual</button>
                </x-empty-state>
                @endforelse
            </div>
        </div>
    </div>
</section>

{{-- TAMBAH MANUAL --}}
<div class="modal fade" id="addQuestionModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <form action="{{ $module->url('questions.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Soal Pilihan Ganda</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">&times;</button>
                </div>
                <div class="modal-body">
                    @include('learning_module.partials.question-fields', ['question' => null])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Soal</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- IMPORT EXCEL --}}
<div class="modal fade" id="importExcelModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form action="{{ $module->url('questions.import-excel') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Import Soal dari Excel / CSV</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">&times;</button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        Gunakan <a href="{{ $module->url('questions.template') }}">template Excel</a>.
                        Kolom: pertanyaan, kunci_jawaban (A-E), pilihan_a s/d pilihan_e, penjelasan. Minimal isi pilihan A dan B.
                    </p>
                    <input type="file" name="file" class="form-control-file" accept=".xlsx,.xls,.csv" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- AI GENERATOR --}}
<div class="modal fade modal-full" id="aiGenerateModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Buat Soal dengan AI dari Materi Modul</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">&times;</button>
            </div>
            <div class="modal-body">
                <div class="modal-full-inner">
                <div class="alert alert-danger d-none" id="aiError"></div>

                {{-- STEP 1: CONFIG --}}
                <div id="aiStepConfig">
                    @unless($isGeminiConfigured)
                        <div class="alert alert-warning">GEMINI_API_KEY belum dikonfigurasi di server, fitur AI belum bisa dipakai.</div>
                    @endunless

                    <label class="mb-2">Materi acuan</label>
                    @forelse($aiItems as $item)
                        <div class="custom-control custom-checkbox mb-1">
                            <input type="checkbox" class="custom-control-input ai-item" id="ai_item_{{ $item->id }}" value="{{ $item->id }}" checked>
                            <label class="custom-control-label" for="ai_item_{{ $item->id }}">
                                <i class="fas {{ $item->isVideo() ? 'fa-play-circle' : 'fa-file-alt' }} text-muted mr-1"></i>
                                {{ $item->title() }}
                            </label>
                        </div>
                    @empty
                        <p class="text-muted small">
                            Modul belum punya materi yang bisa dibaca AI.
                            <a href="{{ route('learning-module.edit', $module) }}">Tambahkan materi</a> terlebih dahulu.
                        </p>
                    @endforelse
                    @foreach($unsupportedAiItems as $item)
                        <div class="custom-control custom-checkbox mb-1">
                            <input type="checkbox" class="custom-control-input" disabled>
                            <label class="custom-control-label text-muted">
                                {{ $item->title() }} <small>(link Drive/Vimeo tidak bisa dibaca AI; gunakan file upload atau YouTube publik)</small>
                            </label>
                        </div>
                    @endforeach
                    <small class="text-muted d-block mt-1 mb-3">Video akan diunggah ke Gemini lalu dianalisis; prosesnya bisa memakan beberapa menit.</small>

                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label for="aiCount">Jumlah soal</label>
                            <input type="number" id="aiCount" class="form-control" min="1" max="100" value="10">
                        </div>
                        <div class="col-md-4 form-group">
                            <label for="aiDifficulty">Tingkat kesulitan</label>
                            <select id="aiDifficulty" class="form-control">
                                <option value="campuran">Campuran</option>
                                <option value="mudah">Mudah</option>
                                <option value="sedang">Sedang</option>
                                <option value="sulit">Sulit</option>
                            </select>
                        </div>
                        <div class="col-md-12 form-group mb-0">
                            <label for="aiPrompt">Instruksi tambahan (opsional)</label>
                            <textarea id="aiPrompt" class="form-control" rows="2" maxlength="1000" placeholder="Contoh: fokus pada prosedur darurat"></textarea>
                        </div>
                    </div>
                </div>

                {{-- STEP 2: LOADING --}}
                <div id="aiStepLoading" class="text-center py-5 d-none">
                    <div class="spinner-border text-primary mb-3" role="status"></div>
                    <p class="font-weight-bold mb-1">AI sedang membaca materi dan menyusun soal...</p>
                    <p class="text-muted small mb-0" id="aiElapsed">0 detik</p>
                </div>

                {{-- STEP 3: REVIEW --}}
                <div id="aiStepReview" class="d-none">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <span class="font-weight-bold"><span id="aiReviewCount">0</span> soal dihasilkan. Periksa dan sunting sebelum disimpan.</span>
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-default btn-xs" id="btnAiSelectAll">Pilih semua</button>
                            <button type="button" class="btn btn-default btn-xs" id="btnAiDeselectAll">Kosongkan</button>
                        </div>
                    </div>
                    <div id="aiQuestions"></div>
                </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary" id="btnAiGenerate" {{ $isGeminiConfigured && $aiItems->isNotEmpty() ? '' : 'disabled' }}>Buat Soal</button>
                <button type="button" class="btn btn-primary d-none" id="btnAiSave">Simpan <span id="aiSaveCount">0</span> Soal</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer')
@include('layout.partials.quiz-image-slot-script', ['uploadUrl' => $module->url('questions.upload-image')])
<script>
$(function () {
    var csrf = '{{ csrf_token() }}';
    var generateUrl = '{{ $module->url('questions.generate-ai') }}';
    var statusUrlTemplate = @json($module->url('questions.ai-status', ['token' => '00000000-0000-0000-0000-000000000000']));
    var saveUrl = '{{ $module->url('questions.save-ai-batch') }}';
    var letters = ['a', 'b', 'c', 'd', 'e'];
    var pollTimer = null;
    var elapsedTimer = null;

    function showStep(step) {
        $('#aiStepConfig, #aiStepLoading, #aiStepReview').addClass('d-none');
        $('#' + step).removeClass('d-none');
        $('#btnAiGenerate').toggleClass('d-none', step !== 'aiStepConfig');
        $('#btnAiSave').toggleClass('d-none', step !== 'aiStepReview');
    }

    function showError(message) {
        $('#aiError').text(message).removeClass('d-none');
    }

    function stopTimers() {
        clearTimeout(pollTimer);
        clearInterval(elapsedTimer);
    }

    function escapeHtml(text) {
        return $('<div>').text(text || '').html();
    }

    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify(body)
        }).then(function (res) {
            return res.json().then(function (data) { return { ok: res.ok, data: data }; });
        });
    }

    function poll(token) {
        fetch(statusUrlTemplate.replace('00000000-0000-0000-0000-000000000000', token), { headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.status === 'done') {
                    stopTimers();
                    renderQuestions(data.questions || []);
                    showStep('aiStepReview');
                } else if (data.status === 'failed') {
                    stopTimers();
                    showStep('aiStepConfig');
                    showError(data.message || 'Gagal membuat soal.');
                } else {
                    pollTimer = setTimeout(function () { poll(token); }, 4000);
                }
            })
            .catch(function () {
                pollTimer = setTimeout(function () { poll(token); }, 8000);
            });
    }

    $('#btnAiGenerate').on('click', function () {
        var itemIds = $('.ai-item:checked').map(function () { return parseInt(this.value, 10); }).get();
        $('#aiError').addClass('d-none');
        if (itemIds.length === 0) {
            showError('Pilih minimal satu materi.');
            return;
        }

        showStep('aiStepLoading');
        var seconds = 0;
        $('#aiElapsed').text('0 detik');
        elapsedTimer = setInterval(function () { $('#aiElapsed').text(++seconds + ' detik'); }, 1000);

        postJson(generateUrl, {
            item_ids: itemIds,
            question_count: parseInt($('#aiCount').val(), 10) || 10,
            difficulty: $('#aiDifficulty').val(),
            custom_prompt: $('#aiPrompt').val()
        }).then(function (result) {
            if (!result.ok || !result.data.success) {
                stopTimers();
                showStep('aiStepConfig');
                showError((result.data && result.data.message) || 'Gagal memulai proses AI.');
                return;
            }
            poll(result.data.token);
        }).catch(function (err) {
            stopTimers();
            showStep('aiStepConfig');
            showError('Gagal menghubungi server: ' + err.message);
        });
    });

    function renderQuestions(questions) {
        var $container = $('#aiQuestions').empty();
        $('#aiReviewCount').text(questions.length);

        questions.forEach(function (q, index) {
            var options = letters.map(function (letter) {
                var id = 'ai_key_' + index + '_' + letter;
                var isKey = q.correct_answer === letter;
                return '<div class="mb-2"><div class="ai-option' + (isKey ? ' is-key' : '') + '">' +
                    '<label class="ai-option-key" for="' + id + '" title="Tandai sebagai kunci jawaban">' +
                        '<input type="radio" id="' + id + '" name="ai_key_' + index + '" value="' + letter + '" class="ai-key"' + (isKey ? ' checked' : '') + '>' +
                        letter.toUpperCase() +
                    '</label>' +
                    '<input type="text" class="form-control ai-opt" data-letter="' + letter + '" value="' + escapeHtml(q['option_' + letter]) + '" placeholder="Pilihan ' + letter.toUpperCase() + ' (opsional)">' +
                '</div>' +
                '<div class="ai-opt-image mt-1" data-letter="' + letter + '">' + quizImageSlot('Gambar ' + letter.toUpperCase()) + '</div>' +
                '</div>';
            }).join('');

            $container.append(
                '<div class="card mb-3 ai-card">' +
                    '<div class="card-header">' +
                        '<div class="custom-control custom-checkbox">' +
                            '<input type="checkbox" class="custom-control-input ai-include" id="ai_inc_' + index + '" checked>' +
                            '<label class="custom-control-label font-weight-bold" for="ai_inc_' + index + '">Soal #' + (index + 1) + '</label>' +
                        '</div>' +
                    '</div>' +
                    '<div class="card-body p-3">' +
                        '<label class="small font-weight-bold text-muted mb-1">Pertanyaan</label>' +
                        '<textarea class="form-control mb-2 ai-question" rows="2">' + escapeHtml(q.question) + '</textarea>' +
                        '<div class="ai-q-image">' + quizImageSlot('Tambah gambar soal') + '</div>' +
                        '<div class="quiz-options-block">' +
                            '<label class="small font-weight-bold text-muted mb-1">Pilihan jawaban <span class="font-weight-normal">(pilih huruf untuk menandai kunci jawaban)</span></label>' +
                            options +
                        '</div>' +
                        '<label class="small font-weight-bold text-muted mb-1">Pembahasan</label>' +
                        '<textarea class="form-control form-control-sm ai-explanation" rows="2" placeholder="Pembahasan">' + escapeHtml(q.explanation) + '</textarea>' +
                    '</div>' +
                '</div>'
            );
        });
        updateSaveCount();
    }

    function updateSaveCount() {
        var count = $('.ai-include:checked').length;
        $('#aiSaveCount').text(count);
        $('#btnAiSave').prop('disabled', count === 0);
    }

    $('#aiQuestions').on('change', '.ai-include', updateSaveCount);
    $('#aiQuestions').on('change', '.ai-key', function () {
        $(this).closest('.ai-card').find('.ai-option').removeClass('is-key');
        $(this).closest('.ai-option').addClass('is-key');
    });
    $('#btnAiSelectAll').on('click', function () { $('.ai-include').prop('checked', true); updateSaveCount(); });
    $('#btnAiDeselectAll').on('click', function () { $('.ai-include').prop('checked', false); updateSaveCount(); });

    $('#btnAiSave').on('click', function () {
        var payload = [];
        $('.ai-card').each(function () {
            var $card = $(this);
            if (!$card.find('.ai-include').is(':checked')) {
                return;
            }
            var item = {
                question: $.trim($card.find('.ai-question').val()),
                explanation: $.trim($card.find('.ai-explanation').val()),
                correct_answer: $card.find('.ai-key:checked').val() || 'a',
                question_image: quizImageSlotValue($card.find('.ai-q-image .quiz-img-slot')[0]),
                option_images: {}
            };
            $card.find('.ai-opt').each(function () {
                item['option_' + $(this).data('letter')] = $.trim($(this).val()) || null;
            });
            $card.find('.ai-opt-image').each(function () {
                var path = quizImageSlotValue($(this).find('.quiz-img-slot')[0]);
                if (path) {
                    item.option_images[$(this).data('letter')] = path;
                }
            });
            payload.push(item);
        });

        var $btn = $(this).prop('disabled', true);
        postJson(saveUrl, { questions: payload }).then(function (result) {
            if (result.ok && result.data.success) {
                // Back to the material (e.g. the video page) once the questions are saved.
                window.location.href = result.data.redirect || window.location.href;
                return;
            }
            var errors = result.data && result.data.errors ? Object.values(result.data.errors)[0][0] : null;
            showError(errors || (result.data && result.data.message) || 'Gagal menyimpan soal.');
            $btn.prop('disabled', false);
        }).catch(function (err) {
            showError('Gagal menyimpan: ' + err.message);
            $btn.prop('disabled', false);
        });
    });

    $('#aiGenerateModal').on('hidden.bs.modal', function () {
        stopTimers();
        $('#aiError').addClass('d-none');
        showStep('aiStepConfig');
    });
});
</script>
@endsection
