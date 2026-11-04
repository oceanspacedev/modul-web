@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <div class="mb-3 d-flex flex-wrap justify-content-between align-items-center" style="gap: 12px;">
            <div class="d-flex align-items-center">
                <a href="{{ route('learning-module.index') }}" class="btn btn-default btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="m-0 text-dark font-weight-bold" style="font-size: 1.5rem;">Soal Pretest & Posttest</h1>
                    <span class="text-muted small">{{ $module->title }} &middot; soal yang sama dipakai untuk pretest dan posttest</span>
                </div>
            </div>

            <div class="d-flex align-items-center flex-wrap justify-content-lg-end ml-lg-auto" style="gap: 8px;">
                <button type="button" class="btn btn-primary btn-sm font-weight-bold" data-toggle="modal" data-target="#aiGenerateModal">
                    Buat Soal via AI
                </button>
                <button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#addQuestionModal">
                    Tambah Manual
                </button>
                <button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#importExcelModal">
                    Import Excel / CSV
                </button>
                <a href="{{ route('learning-module.questions.template', $module) }}" class="btn btn-default btn-sm">
                    Download Template
                </a>
                @if($questions->isNotEmpty())
                <form action="{{ route('learning-module.questions.delete-all', $module) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus SEMUA soal modul ini? Riwayat nilai peserta tetap tersimpan.')">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm">Hapus Semua Soal</button>
                </form>
                @endif
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        @include('learning_module.partials.alerts')

        <div class="card mb-4">
            <div class="card-header py-3 d-flex align-items-center justify-content-between flex-wrap" style="gap: 10px;">
                <h3 class="card-title font-weight-bold m-0">Daftar Pertanyaan ({{ $questions->count() }} Soal)</h3>
                <div class="d-flex align-items-center" style="gap: 8px;">
                    @if($module->is_published)
                        <span class="badge badge-success px-2 py-1">Dipublikasikan</span>
                    @else
                        <span class="badge badge-secondary px-2 py-1">Draft</span>
                    @endif
                    <form action="{{ route('learning-module.publish', $module) }}" method="POST" class="m-0">
                        @csrf
                        <button type="submit" class="btn btn-xs {{ $module->is_published ? 'btn-outline-secondary' : 'btn-success' }}">
                            {{ $module->is_published ? 'Tarik Publikasi' : 'Publikasikan Modul' }}
                        </button>
                    </form>
                </div>
            </div>

            <div class="card-body p-4">
                @forelse($questions as $index => $q)
                <div class="card mb-4 border">
                    <div class="card-header py-2 bg-light d-flex align-items-center justify-content-between">
                        <strong class="text-sm">Pertanyaan #{{ $index + 1 }}</strong>
                        <div>
                            <button type="button" class="btn btn-default btn-xs mr-1" data-toggle="modal" data-target="#editModal_{{ $q->id }}">Edit</button>
                            <form action="{{ route('learning-module.questions.destroy', [$module, $q]) }}" method="POST" class="d-inline m-0" onsubmit="return confirm('Hapus soal ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-default btn-xs text-danger">Hapus</button>
                            </form>
                        </div>
                    </div>
                    <div class="card-body p-3">
                        <p class="font-weight-600 mb-3 text-dark" style="font-size: 1.05rem; white-space: pre-line;">{{ $q->question }}</p>
                        <div class="row text-sm">
                            @foreach($q->options() as $letter => $text)
                                @php $isKey = $q->correct_answer === $letter; @endphp
                                <div class="col-md-6 mb-2">
                                    <div class="p-2 border rounded {{ $isKey ? 'bg-success text-white font-weight-bold' : 'bg-light' }}">
                                        {{ strtoupper($letter) }}. {{ $text }}
                                        @if($isKey)
                                            <span class="float-right text-xs badge badge-light text-success font-weight-bold">Kunci Jawaban</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @if($q->explanation)
                            <div class="text-xs text-muted mt-2 pt-2 border-top">
                                <strong>Pembahasan:</strong> {{ $q->explanation }}
                            </div>
                        @endif
                    </div>
                </div>

                <div class="modal fade" id="editModal_{{ $q->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-lg" role="document">
                        <div class="modal-content">
                            <form action="{{ route('learning-module.questions.update', [$module, $q]) }}" method="POST">
                                @csrf
                                @method('PUT')
                                <div class="modal-header py-3">
                                    <h5 class="modal-title font-weight-bold">Edit Soal #{{ $index + 1 }}</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                </div>
                                <div class="modal-body p-4">
                                    @include('learning_module.partials.question-fields', ['question' => $q])
                                </div>
                                <div class="modal-footer py-2">
                                    <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-primary btn-sm">Simpan Perubahan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @empty
                <div class="text-center py-5 text-muted">
                    <p class="mb-3 font-weight-bold text-dark">Belum ada soal untuk modul ini.</p>
                    <p class="small mb-0">Buat soal dengan AI dari materi modul, tambah manual, atau import dari Excel.</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>
</section>

{{-- TAMBAH MANUAL --}}
<div class="modal fade" id="addQuestionModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form action="{{ route('learning-module.questions.store', $module) }}" method="POST">
                @csrf
                <div class="modal-header py-3">
                    <h5 class="modal-title font-weight-bold">Tambah Soal Pilihan Ganda</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body p-4">
                    @include('learning_module.partials.question-fields', ['question' => null])
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan Soal</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- IMPORT EXCEL --}}
<div class="modal fade" id="importExcelModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('learning-module.questions.import-excel', $module) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header py-3">
                    <h5 class="modal-title font-weight-bold">Import Soal dari Excel / CSV</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small">
                        Gunakan <a href="{{ route('learning-module.questions.template', $module) }}">template Excel</a>.
                        Kolom: pertanyaan, kunci_jawaban (A-E), pilihan_a s/d pilihan_e, penjelasan. Minimal isi pilihan A dan B.
                    </p>
                    <input type="file" name="file" class="form-control-file" accept=".xlsx,.xls,.csv" required>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- AI GENERATOR --}}
<div class="modal fade" id="aiGenerateModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header py-3">
                <h5 class="modal-title font-weight-bold">Buat Soal dengan AI dari Materi Modul</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-danger d-none" id="aiError"></div>

                {{-- STEP 1: CONFIG --}}
                <div id="aiStepConfig">
                    @unless($isGeminiConfigured)
                        <div class="alert alert-warning">GEMINI_API_KEY belum dikonfigurasi di server, fitur AI belum bisa dipakai.</div>
                    @endunless

                    <label class="font-weight-600 mb-2">Materi acuan</label>
                    @forelse($aiItems as $item)
                        <div class="custom-control custom-checkbox mb-1">
                            <input type="checkbox" class="custom-control-input ai-item" id="ai_item_{{ $item->id }}" value="{{ $item->id }}" checked>
                            <label class="custom-control-label" for="ai_item_{{ $item->id }}">
                                <i class="fas {{ $item->isVideo() ? 'fa-play-circle text-danger' : 'fa-file-alt text-warning' }} mr-1"></i>
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
                            <label class="font-weight-600 mb-1">Jumlah soal</label>
                            <input type="number" id="aiCount" class="form-control" min="1" max="100" value="10">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-600 mb-1">Tingkat kesulitan</label>
                            <select id="aiDifficulty" class="form-control">
                                <option value="campuran">Campuran</option>
                                <option value="mudah">Mudah</option>
                                <option value="sedang">Sedang</option>
                                <option value="sulit">Sulit</option>
                            </select>
                        </div>
                        <div class="col-md-12 form-group mb-0">
                            <label class="font-weight-600 mb-1">Instruksi tambahan (opsional)</label>
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
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="font-weight-bold"><span id="aiReviewCount">0</span> soal dihasilkan. Periksa dan sunting sebelum disimpan.</span>
                        <div>
                            <button type="button" class="btn btn-default btn-xs" id="btnAiSelectAll">Pilih semua</button>
                            <button type="button" class="btn btn-default btn-xs" id="btnAiDeselectAll">Kosongkan</button>
                        </div>
                    </div>
                    <div id="aiQuestions"></div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnAiGenerate" {{ $isGeminiConfigured && $aiItems->isNotEmpty() ? '' : 'disabled' }}>Buat Soal</button>
                <button type="button" class="btn btn-success btn-sm d-none" id="btnAiSave">Simpan <span id="aiSaveCount">0</span> Soal</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer')
<script>
$(function () {
    var csrf = '{{ csrf_token() }}';
    var generateUrl = '{{ route('learning-module.questions.generate-ai', $module) }}';
    var statusUrl = '{{ url('learning-module/'.$module->id.'/questions/ai-status') }}/';
    var saveUrl = '{{ route('learning-module.questions.save-ai-batch', $module) }}';
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
        fetch(statusUrl + token, { headers: { 'Accept': 'application/json' } })
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
                return '<div class="col-md-6 mb-2"><div class="input-group input-group-sm">' +
                    '<div class="input-group-prepend"><span class="input-group-text">' +
                        '<input type="radio" name="ai_key_' + index + '" value="' + letter + '" class="mr-1 ai-key"' + (q.correct_answer === letter ? ' checked' : '') + '>' +
                        letter.toUpperCase() +
                    '</span></div>' +
                    '<input type="text" class="form-control ai-opt" data-letter="' + letter + '" value="' + escapeHtml(q['option_' + letter]) + '">' +
                '</div></div>';
            }).join('');

            $container.append(
                '<div class="card mb-3 border ai-card">' +
                    '<div class="card-header py-2 bg-light">' +
                        '<div class="custom-control custom-checkbox">' +
                            '<input type="checkbox" class="custom-control-input ai-include" id="ai_inc_' + index + '" checked>' +
                            '<label class="custom-control-label font-weight-bold" for="ai_inc_' + index + '">Soal #' + (index + 1) + '</label>' +
                        '</div>' +
                    '</div>' +
                    '<div class="card-body p-3">' +
                        '<textarea class="form-control mb-2 ai-question" rows="2">' + escapeHtml(q.question) + '</textarea>' +
                        '<div class="row">' + options + '</div>' +
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
                correct_answer: $card.find('.ai-key:checked').val() || 'a'
            };
            $card.find('.ai-opt').each(function () {
                item['option_' + $(this).data('letter')] = $.trim($(this).val()) || null;
            });
            payload.push(item);
        });

        var $btn = $(this).prop('disabled', true);
        postJson(saveUrl, { questions: payload }).then(function (result) {
            if (result.ok && result.data.success) {
                window.location.reload();
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
