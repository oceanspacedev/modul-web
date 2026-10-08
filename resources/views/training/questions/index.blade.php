@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <div class="mb-3">
            <div class="d-flex align-items-center mb-2">
                <a href="/training/{{ $training->id }}" class="btn btn-default btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="m-0 text-dark font-weight-bold" style="font-size: 1.5rem;">
                        Kelola Soal Kuis Pelatihan
                    </h1>
                    <span class="text-muted small">{{ $training->title }}</span>
                </div>
            </div>

            <!-- ACTION BUTTONS -->
            <div class="d-flex align-items-center flex-wrap mt-3" style="gap: 8px;">
                <button type="button" class="btn btn-primary btn-sm font-weight-bold" data-toggle="modal" data-target="#aiGenerateModal">
                    Buat Soal via AI
                </button>
                <button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#addQuestionModal">
                    Tambah Manual
                </button>
                <button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#importTextModal">
                    Import Teks Cepat
                </button>
                <button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#importExcelModal">
                    Import Excel / CSV
                </button>
                <a href="/training/{{ $training->id }}/questions/template" class="btn btn-default btn-sm">
                    Download Template
                </a>
                @if($questions->count() > 0)
                <form action="/training/{{ $training->id }}/questions/delete-all" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus SEMUA soal kuis ini?')">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        Hapus Semua Soal
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-exclamation-triangle mr-1"></i> {{ session('error') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        <div class="card mb-4">
            <div class="card-header py-3 d-flex align-items-center justify-content-between flex-wrap" style="gap: 10px;">
                <div>
                    <h3 class="card-title font-weight-bold m-0">
                        Daftar Pertanyaan ({{ $questions->count() }} Soal)
                    </h3>
                </div>
                <div>
                    @if($training->is_quiz_active)
                        <span class="badge badge-success px-2 py-1">Kuis Terbuka untuk Peserta</span>
                    @else
                        <span class="badge badge-secondary px-2 py-1">Kuis Terkunci</span>
                    @endif
                </div>
            </div>

            <div class="card-body p-4">
                @forelse($questions as $index => $q)
                @php
                    $isEssay = ($q->type === 'essay');
                    $activeOptions = [];
                    if (!$isEssay) {
                        foreach (['a', 'b', 'c', 'd', 'e', 'f'] as $l) {
                            if (!empty($q->{'option_' . $l})) {
                                $activeOptions[$l] = $q->{'option_' . $l};
                            }
                        }
                    }
                @endphp
                <div class="card mb-4 border">
                    <div class="card-header py-2 bg-light d-flex align-items-center justify-content-between">
                        <div>
                            <strong class="text-sm mr-2">Pertanyaan #{{ $index + 1 }}</strong>
                            @if($isEssay)
                                <span class="badge badge-info">ESSAY / URAIAN</span>
                            @else
                                <span class="badge badge-primary">PILIHAN GANDA ({{ count($activeOptions) }} Opsi: A-{{ strtoupper(array_key_last($activeOptions) ?? 'B') }})</span>
                            @endif
                        </div>
                        <div>
                            <button type="button" class="btn btn-default btn-xs mr-1" data-toggle="modal" data-target="#editModal_{{ $q->id }}">
                                Edit
                            </button>
                            <a href="/training/{{ $training->id }}/questions/{{ $q->id }}/delete" onclick="return confirm('Hapus soal ini?')" class="btn btn-default btn-xs text-danger">
                                Hapus
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-3">
                        <p class="font-weight-600 mb-3 text-dark" style="font-size: 1.05rem;">{{ $q->question }}</p>

                        @if($isEssay)
                            <div class="p-3 border rounded bg-light mb-2 text-sm">
                                <span class="text-muted d-block font-weight-bold mb-1">Pedoman / Kunci Acuan Jawaban:</span>
                                <div class="text-dark">{{ $q->correct_answer ?: '(Belum ada kunci acuan khusus. Penilaian bersifat deskriptif sesuai jawaban peserta)' }}</div>
                            </div>
                        @else
                            <div class="row text-sm">
                                @foreach($activeOptions as $optKey => $optVal)
                                    @php
                                        $isKey = (strtolower((string)$q->correct_answer) === $optKey);
                                    @endphp
                                    <div class="col-md-6 mb-2">
                                        <div class="p-2 border rounded {{ $isKey ? 'bg-success text-white font-weight-bold' : 'bg-light' }}">
                                            {{ strtoupper($optKey) }}. {{ $optVal }}
                                            @if($isKey)
                                                <span class="float-right text-xs badge badge-light text-success font-weight-bold">Kunci Jawaban</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if($q->explanation)
                            <div class="text-xs text-muted mt-2 pt-2 border-top">
                                <strong>Catatan Pembahasan:</strong> {{ $q->explanation }}
                            </div>
                        @endif
                    </div>
                </div>

                <!-- EDIT MODAL -->
                <div class="modal fade" id="editModal_{{ $q->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-lg" role="document">
                        <div class="modal-content">
                            <form action="/training/{{ $training->id }}/questions/{{ $q->id }}/update" method="POST">
                                @csrf
                                <div class="modal-header py-3">
                                    <h5 class="modal-title font-weight-bold">Edit Soal #{{ $index + 1 }}</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body p-4">
                                    <div class="form-group mb-3">
                                        <label class="font-weight-600 mb-1">Tipe Soal</label>
                                        <select name="type" class="form-control select-type" data-target="edit_opts_{{ $q->id }}">
                                            <option value="multiple_choice" {{ !$isEssay ? 'selected' : '' }}>Pilihan Ganda</option>
                                            <option value="essay" {{ $isEssay ? 'selected' : '' }}>Essay / Uraian</option>
                                        </select>
                                    </div>
                                    <div class="form-group mb-3">
                                        <label class="font-weight-600 mb-1">Pertanyaan</label>
                                        <textarea name="question" class="form-control" rows="3" required>{{ $q->question }}</textarea>
                                    </div>

                                    <!-- OPTIONS CONTAINER -->
                                    <div id="edit_opts_{{ $q->id }}" class="opts-container" style="{{ $isEssay ? 'display: none;' : '' }}">
                                        <div class="row mb-2">
                                            <div class="col-md-6 mb-2">
                                                <label class="font-weight-600 mb-1">Pilihan A <span class="text-danger">*</span></label>
                                                <input type="text" name="option_a" class="form-control" value="{{ $q->option_a }}">
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <label class="font-weight-600 mb-1">Pilihan B <span class="text-danger">*</span></label>
                                                <input type="text" name="option_b" class="form-control" value="{{ $q->option_b }}">
                                            </div>
                                        </div>
                                        <div class="row mb-2">
                                            <div class="col-md-6 mb-2">
                                                <label class="font-weight-600 mb-1">Pilihan C (Opsional)</label>
                                                <input type="text" name="option_c" class="form-control" value="{{ $q->option_c }}">
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <label class="font-weight-600 mb-1">Pilihan D (Opsional)</label>
                                                <input type="text" name="option_d" class="form-control" value="{{ $q->option_d }}">
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-6 mb-2">
                                                <label class="font-weight-600 mb-1">Pilihan E (Opsional)</label>
                                                <input type="text" name="option_e" class="form-control" value="{{ $q->option_e }}">
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <label class="font-weight-600 mb-1">Pilihan F (Opsional)</label>
                                                <input type="text" name="option_f" class="form-control" value="{{ $q->option_f }}">
                                            </div>
                                        </div>
                                        <div class="form-group mb-3">
                                            <label class="font-weight-600 mb-1">Kunci Jawaban Pilihan Ganda</label>
                                            <select name="correct_answer" class="form-control select-key">
                                                <option value="a" {{ $q->correct_answer === 'a' ? 'selected' : '' }}>Pilihan A</option>
                                                <option value="b" {{ $q->correct_answer === 'b' ? 'selected' : '' }}>Pilihan B</option>
                                                <option value="c" {{ $q->correct_answer === 'c' ? 'selected' : '' }}>Pilihan C</option>
                                                <option value="d" {{ $q->correct_answer === 'd' ? 'selected' : '' }}>Pilihan D</option>
                                                <option value="e" {{ $q->correct_answer === 'e' ? 'selected' : '' }}>Pilihan E</option>
                                                <option value="f" {{ $q->correct_answer === 'f' ? 'selected' : '' }}>Pilihan F</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- ESSAY KEY CONTAINER -->
                                    <div id="edit_essay_{{ $q->id }}" class="essay-key-container" style="{{ !$isEssay ? 'display: none;' : '' }}">
                                        <div class="form-group mb-3">
                                            <label class="font-weight-600 mb-1">Pedoman / Acuan Kunci Jawaban (Opsional)</label>
                                            <textarea name="correct_answer" class="form-control essay-input" rows="2" placeholder="Catatan kunci acuan jawaban benar...">{{ $isEssay ? $q->correct_answer : '' }}</textarea>
                                        </div>
                                    </div>

                                    <div class="form-group mb-0">
                                        <label class="font-weight-600 mb-1">Catatan Pembahasan (Opsional)</label>
                                        <textarea name="explanation" class="form-control" rows="2">{{ $q->explanation }}</textarea>
                                    </div>
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
                    <p class="mb-3 font-weight-bold text-dark">Belum ada soal kuis yang ditambahkan untuk pelatihan ini.</p>
                    <div class="d-flex justify-content-center flex-wrap" style="gap: 8px;">
                        <button type="button" class="btn btn-primary btn-sm font-weight-bold" data-toggle="modal" data-target="#aiGenerateModal">
                            Buat Soal via AI
                        </button>
                        <button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#addQuestionModal">
                            Tambah Manual
                        </button>
                        <button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#importTextModal">
                            Import Teks
                        </button>
                        <button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#importExcelModal">
                            Import Excel / CSV
                        </button>
                    </div>
                </div>
                @endforelse
            </div>
        </div>
    </div>
</section>

<!-- AI QUESTION GENERATOR MODAL (GEMINI) -->
<div class="modal fade" id="aiGenerateModal" tabindex="-1" role="dialog" aria-labelledby="aiGenerateModalLabel" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content shadow border-0" style="border-radius: 8px;">
            <!-- MODAL HEADER -->
            <div class="modal-header bg-white text-dark py-3 px-4 border-bottom">
                <h5 class="modal-title font-weight-bold mb-0 text-dark" id="aiGenerateModalLabel">
                    Buat Soal via AI
                </h5>
                <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Close" id="btnCloseAiModal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <!-- MODAL BODY -->
            <div class="modal-body p-4" style="background-color: #f8fafc; min-height: 480px;">
                <!-- ALERT ERROR -->
                <div id="aiAlertError" class="alert alert-danger alert-dismissible fade show d-none mb-3" role="alert">
                    <span id="aiAlertErrorText"></span>
                </div>

                <!-- STEP 1: CONFIGURATION FORM -->
                <div id="aiStepConfig">
                    <div class="row">
                        <!-- LEFT COLUMN: DOKUMEN & MATERI -->
                        <div class="col-lg-6 mb-3">
                            <div class="card h-100 border shadow-none bg-white">
                                <div class="card-header bg-white py-2 font-weight-bold d-flex justify-content-between align-items-center">
                                    <span>Pilih Dokumen Materi <span class="text-danger">*</span></span>
                                    <span class="badge badge-light border text-muted" id="docSelectedCount">0 Terpilih</span>
                                </div>
                                <div class="card-body p-3">
                                    <!-- TRAINING LINKED DOCUMENTS -->
                                    <div class="mb-3">
                                        <label class="text-xs font-weight-bold text-uppercase text-secondary mb-1 d-block">Dokumen Pelatihan Ini:</label>
                                        @if($documents->isEmpty())
                                            <div class="p-2 border rounded bg-light text-muted text-xs mb-2">
                                                Belum ada dokumen yang dilampirkan langsung pada pelatihan ini. Silakan pilih dari dokumen sistem di bawah.
                                            </div>
                                        @else
                                            <div class="list-group mb-2 border rounded" style="max-height: 180px; overflow-y: auto;">
                                                @foreach($documents as $doc)
                                                <label class="list-group-item list-group-item-action d-flex align-items-center px-3 py-2 mb-0 border-0 border-bottom" style="cursor: pointer;">
                                                    <input type="checkbox" name="ai_document_ids[]" value="{{ $doc->id }}" class="mr-2 ai-doc-checkbox" checked>
                                                    <div class="text-truncate flex-grow-1">
                                                        <strong class="text-sm d-block text-dark text-truncate">{{ $doc->name }}</strong>
                                                        <span class="text-xs text-muted">
                                                            <span class="badge badge-light border">{{ $doc->dokumentype->name ?? 'Dokumen' }}</span>
                                                            v{{ $doc->version ?? 1 }}
                                                        </span>
                                                    </div>
                                                </label>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>

                                    <!-- CHOOSE OTHER DOCUMENTS -->
                                    <div>
                                        <label class="text-xs font-weight-bold text-uppercase text-secondary mb-1 d-flex justify-content-between align-items-center">
                                            <span>Dokumen Lain dari Sistem:</span>
                                        </label>
                                        <select id="aiSelectOtherDocs" class="form-control form-control-sm select2" multiple="multiple" style="width: 100%;" data-placeholder="Cari & pilih dokumen lain...">
                                            @foreach($allDocuments as $ad)
                                                @if(!$documents->contains('id', $ad->id))
                                                <option value="{{ $ad->id }}">
                                                    {{ $ad->name }} ({{ $ad->dokumentype->name ?? 'Dokumen' }})
                                                </option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- RIGHT COLUMN: PARAMETER GENERASI -->
                        <div class="col-lg-6 mb-3">
                            <div class="card h-100 border shadow-none bg-white">
                                <div class="card-header bg-white py-2 font-weight-bold">
                                    Pengaturan Pembuatan Soal
                                </div>
                                <div class="card-body p-3">
                                    <!-- JUMLAH SOAL -->
                                    <div class="form-group mb-3">
                                        <label class="font-weight-600 text-sm mb-1 d-block">Jumlah Soal:</label>
                                        <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                                            <label class="btn btn-outline-secondary btn-sm active" style="flex: 1;">
                                                <input type="radio" name="ai_question_count" value="5" checked> 5 Soal
                                            </label>
                                            <label class="btn btn-outline-secondary btn-sm" style="flex: 1;">
                                                <input type="radio" name="ai_question_count" value="10"> 10 Soal
                                            </label>
                                            <label class="btn btn-outline-secondary btn-sm" style="flex: 1;">
                                                <input type="radio" name="ai_question_count" value="15"> 15 Soal
                                            </label>
                                            <label class="btn btn-outline-secondary btn-sm" style="flex: 1;">
                                                <input type="radio" name="ai_question_count" value="custom"> Kustom
                                            </label>
                                        </div>
                                        <div id="aiCustomCountWrapper" class="mt-2 d-none">
                                            <div class="input-group input-group-sm">
                                                <input type="number" id="aiCustomCount" class="form-control" min="1" max="100" value="20" placeholder="Ketik jumlah butir soal (1 - 100)">
                                                <div class="input-group-append">
                                                    <span class="input-group-text">Butir Soal</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- TIPE SOAL -->
                                    <div class="form-group mb-3">
                                        <label class="font-weight-600 text-sm mb-1 d-block">Tipe Soal:</label>
                                        <div class="row">
                                            <div class="col-6">
                                                <div class="custom-control custom-radio p-2 border rounded bg-light">
                                                    <input type="radio" id="ai_type_mc" name="ai_question_type" value="multiple_choice" class="custom-control-input" checked>
                                                    <label class="custom-control-label font-weight-bold text-sm text-dark d-block" for="ai_type_mc" style="cursor: pointer;">
                                                        Pilihan Ganda
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="custom-control custom-radio p-2 border rounded bg-light">
                                                    <input type="radio" id="ai_type_essay" name="ai_question_type" value="essay" class="custom-control-input">
                                                    <label class="custom-control-label font-weight-bold text-sm text-dark d-block" for="ai_type_essay" style="cursor: pointer;">
                                                        Essay / Uraian
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- TINGKAT KESULITAN -->
                                    <div class="form-group mb-3">
                                        <label class="font-weight-600 text-sm mb-1">Tingkat Kesulitan:</label>
                                        <select id="aiDifficulty" class="form-control form-control-sm">
                                            <option value="campuran" selected>Campuran</option>
                                            <option value="mudah">Mudah</option>
                                            <option value="sedang">Sedang</option>
                                            <option value="sulit">Sulit</option>
                                        </select>
                                    </div>

                                    <!-- INSTRUKSI KHUSUS -->
                                    <div class="form-group mb-3">
                                        <label class="font-weight-600 text-sm mb-1">Instruksi Khusus (Opsional):</label>
                                        <textarea id="aiCustomPrompt" class="form-control form-control-sm" rows="2" placeholder="Tulis catatan atau instruksi tambahan jika ada..."></textarea>
                                    </div>

                                    <!-- GEMINI API KEY STATUS / OVERRIDE -->
                                    <div class="form-group mb-0 p-2 rounded border bg-light">
                                        @if($isGeminiConfigured)
                                            <div class="d-flex align-items-center justify-content-between">
                                                <span class="text-xs text-success font-weight-bold">
                                                    AI API Key Terhubung (.env)
                                                </span>
                                                <a href="javascript:void(0)" class="text-xs text-secondary" id="btnToggleApiKeyInput">Ubah Key</a>
                                            </div>
                                            <div id="aiApiKeyWrapper" class="mt-2 d-none">
                                                <input type="password" id="aiApiKeyOverride" class="form-control form-control-sm" placeholder="Masukkan API Key kustom...">
                                            </div>
                                        @else
                                            <div>
                                                <label class="text-xs font-weight-bold text-danger mb-1 d-block">
                                                    Masukkan API Key:
                                                </label>
                                                <input type="text" id="aiApiKeyOverride" class="form-control form-control-sm" placeholder="Masukkan API Key...">
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TRIGGER BUTTON -->
                    <div class="text-right mt-3">
                        <button type="button" class="btn btn-default px-4 mr-2" data-dismiss="modal">Tutup</button>
                        <button type="button" id="btnTriggerAiGenerate" class="btn btn-primary font-weight-bold px-4">
                            Mulai Generate Soal
                        </button>
                    </div>
                </div>

                <!-- STEP 2: LOADING PROGRESS -->
                <div id="aiStepLoading" class="text-center py-5 d-none">
                    <div class="mb-4">
                        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                    </div>
                    <h5 class="font-weight-bold text-dark mb-3" id="aiLoadingStatusTitle">
                        Memproses Soal via AI...
                    </h5>
                    <div class="progress mx-auto" style="height: 6px; max-width: 320px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" style="width: 100%"></div>
                    </div>
                </div>

                <!-- STEP 3: REVIEW & EDIT GENERATED QUESTIONS -->
                <div id="aiStepReview" class="d-none">
                    <div class="d-flex align-items-center justify-content-between mb-3 py-2 px-3 border rounded bg-light">
                        <div>
                            <strong class="text-dark">Hasil Soal AI (<span id="aiReviewCountText">0</span> Soal)</strong>
                        </div>
                        <div class="d-flex align-items-center" style="gap: 8px;">
                            <button type="button" class="btn btn-xs btn-default" id="btnAiSelectAll">Pilih Semua</button>
                            <button type="button" class="btn btn-xs btn-default" id="btnAiDeselectAll">Batal Semua</button>
                        </div>
                    </div>

                    <!-- CONTAINER LIST SOAL -->
                    <div id="aiQuestionsContainer" style="max-height: 520px; overflow-y: auto; padding-right: 4px;">
                        <!-- JS WILL POPULATE CARDS HERE -->
                    </div>

                    <!-- FOOTER ACTIONS FOR REVIEW -->
                    <div class="d-flex align-items-center justify-content-between mt-3 pt-3 border-top bg-white px-2">
                        <button type="button" class="btn btn-default btn-sm" id="btnAiBackToConfig">
                            Kembali
                        </button>
                        <div class="d-flex align-items-center" style="gap: 10px;">
                            <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Batal</button>
                            <button type="button" id="btnSaveAiBatch" class="btn btn-primary btn-sm font-weight-bold px-4">
                                Simpan <span id="aiSelectedToSaveCount">0</span> Soal
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 1. ADD QUESTION MODAL (MANUAL) -->
<div class="modal fade" id="addQuestionModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content shadow border-0" style="border-radius: 8px;">
            <form action="/training/{{ $training->id }}/questions" method="POST">
                @csrf
                <div class="modal-header bg-white border-bottom py-3">
                    <h5 class="modal-title font-weight-bold text-dark">Tambah Pertanyaan Manual</h5>
                    <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4">
                    <div class="form-group mb-3">
                        <label class="font-weight-600 mb-1">Tipe Soal <span class="text-danger">*</span></label>
                        <select name="type" id="add_type_select" class="form-control select-type" data-target="add_opts_container">
                            <option value="multiple_choice">Pilihan Ganda (A-B s/d A-F)</option>
                            <option value="essay">Essay / Uraian</option>
                        </select>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-600 mb-1">Pertanyaan Soal <span class="text-danger">*</span></label>
                        <textarea name="question" class="form-control" rows="3" placeholder="Tuliskan pertanyaan di sini..." required></textarea>
                    </div>

                    <!-- OPTIONS CONTAINER -->
                    <div id="add_opts_container" class="opts-container">
                        <div class="row mb-2">
                            <div class="col-md-6 mb-2">
                                <label class="font-weight-600 mb-1">Pilihan A <span class="text-danger">*</span></label>
                                <input type="text" name="option_a" class="form-control" placeholder="Teks opsi A">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="font-weight-600 mb-1">Pilihan B <span class="text-danger">*</span></label>
                                <input type="text" name="option_b" class="form-control" placeholder="Teks opsi B">
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-6 mb-2">
                                <label class="font-weight-600 mb-1">Pilihan C (Opsional)</label>
                                <input type="text" name="option_c" class="form-control" placeholder="Teks opsi C">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="font-weight-600 mb-1">Pilihan D (Opsional)</label>
                                <input type="text" name="option_d" class="form-control" placeholder="Teks opsi D">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6 mb-2">
                                <label class="font-weight-600 mb-1">Pilihan E (Opsional)</label>
                                <input type="text" name="option_e" class="form-control" placeholder="Teks opsi E">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="font-weight-600 mb-1">Pilihan F (Opsional)</label>
                                <input type="text" name="option_f" class="form-control" placeholder="Teks opsi F">
                            </div>
                        </div>
                        <div class="form-group mb-3">
                            <label class="font-weight-600 mb-1">Kunci Jawaban Benar <span class="text-danger">*</span></label>
                            <select name="correct_answer" class="form-control select-key">
                                <option value="a">Pilihan A</option>
                                <option value="b">Pilihan B</option>
                                <option value="c">Pilihan C</option>
                                <option value="d">Pilihan D</option>
                                <option value="e">Pilihan E</option>
                                <option value="f">Pilihan F</option>
                            </select>
                        </div>
                    </div>

                    <!-- ESSAY KEY CONTAINER -->
                    <div id="add_essay_container" class="essay-key-container" style="display: none;">
                        <div class="form-group mb-3">
                            <label class="font-weight-600 mb-1">Pedoman Kunci Jawaban (Opsional)</label>
                            <textarea name="correct_answer" class="form-control essay-input" rows="2" placeholder="Catatan acuan jawaban benar..."></textarea>
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-600 mb-1">Catatan Pembahasan (Opsional)</label>
                        <textarea name="explanation" class="form-control" rows="2" placeholder="Catatan penjelasan jawaban..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top py-2">
                    <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3">Simpan Soal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 2. IMPORT TEXT MODAL (COPY-PASTE CEPAT) -->
<div class="modal fade" id="importTextModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content shadow border-0" style="border-radius: 8px;">
            <form action="/training/{{ $training->id }}/questions/import-text" method="POST">
                @csrf
                <div class="modal-header bg-white border-bottom py-3">
                    <h5 class="modal-title font-weight-bold text-dark">
                        Import Soal via Teks
                    </h5>
                    <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4">
                    <div class="row">
                        <!-- KOLOM KIRI: TEXTAREA -->
                        <div class="col-lg-8 mb-3 mb-lg-0">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="font-weight-bold m-0 text-dark">
                                    Area Teks Soal:
                                </label>
                                <button type="button" class="btn btn-default btn-xs" id="btnInsertSample">
                                    Isi Contoh
                                </button>
                            </div>
                            <textarea name="raw_text" id="pasteTextarea" class="form-control" style="height: 480px !important; min-height: 440px !important; resize: vertical; font-size: 14px; line-height: 1.6; font-family: 'Courier New', Courier, monospace; border: 1px solid #ced4da; border-radius: 6px; padding: 12px;" placeholder="Tempelkan teks soal kuis Anda di sini..." required></textarea>
                        </div>

                        <!-- KOLOM KANAN: PANDUAN FORMAT -->
                        <div class="col-lg-4">
                            <div class="card h-100 border bg-light mb-0 shadow-none">
                                <div class="card-header bg-white py-2 font-weight-bold text-dark text-sm">
                                    Panduan Format Penulisan
                                </div>
                                <div class="card-body p-3 text-xs" style="overflow-y: auto; max-height: 480px;">
                                    <p class="mb-1 text-dark font-weight-bold">1. Pilihan Ganda (A-D):</p>
                                    <div class="p-2 bg-white rounded border mb-3 font-monospace" style="font-size: 11px;">
1. Pertanyaan soal...<br>
A. Opsi A<br>
B. Opsi B<br>
C. Opsi C<br>
D. Opsi D<br>
KUNCI: A<br>
PENJELASAN: Catatan pembahasan (opsional)
                                    </div>

                                    <p class="mb-1 text-dark font-weight-bold">2. True / False (Hanya A & B):</p>
                                    <div class="p-2 bg-white rounded border mb-3 font-monospace" style="font-size: 11px;">
2. Apakah kehadiran wajib diisi?<br>
A. Ya<br>
B. Tidak<br>
KUNCI: A
                                    </div>

                                    <p class="mb-1 text-dark font-weight-bold">3. Opsi Panjang (A s/d F):</p>
                                    <div class="p-2 bg-white rounded border mb-3 font-monospace" style="font-size: 11px;">
3. Pertanyaan soal opsi banyak...<br>
A. Opsi 1<br>
B. Opsi 2<br>
C. Opsi 3<br>
D. Opsi 4<br>
E. Opsi 5<br>
F. Opsi 6<br>
KUNCI: E
                                    </div>

                                    <p class="mb-1 text-dark font-weight-bold">4. Soal Essay / Uraian:</p>
                                    <div class="p-2 bg-white rounded border mb-0 font-monospace" style="font-size: 11px;">
4. Jelaskan materi setelah zoom!<br>
TIPE: ESSAY<br>
KUNCI: Acuan poin jawaban peserta
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top py-2">
                    <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4">
                        Simpan Semua Soal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 3. IMPORT EXCEL / CSV MODAL -->
<div class="modal fade" id="importExcelModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow border-0" style="border-radius: 8px;">
            <form action="/training/{{ $training->id }}/questions/import-excel" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header bg-white border-bottom py-3">
                    <h5 class="modal-title font-weight-bold text-dark">
                        Import Soal via Excel / CSV
                    </h5>
                    <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <a href="/training/{{ $training->id }}/questions/template" class="btn btn-default btn-sm btn-block">
                            Unduh Template Excel (.xlsx)
                        </a>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-600 mb-1">Pilih File Spreadsheet (.xlsx, .xls, .csv)</label>
                        <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv" required>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top py-2">
                    <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4">
                        Import File
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Dynamic switch for Type (Multiple Choice vs Essay)
    function handleTypeChange(selectElem) {
        var isEssay = (selectElem.value === 'essay');
        var form = selectElem.closest('form');
        var optsContainer = form.querySelector('.opts-container');
        var essayContainer = form.querySelector('.essay-key-container');

        if (isEssay) {
            if (optsContainer) optsContainer.style.display = 'none';
            if (essayContainer) essayContainer.style.display = 'block';

            // Disable select key in opts, enable essay text
            var selectKey = form.querySelector('.select-key');
            if (selectKey) selectKey.disabled = true;
            var essayInput = form.querySelector('.essay-input');
            if (essayInput) essayInput.disabled = false;
        } else {
            if (optsContainer) optsContainer.style.display = 'block';
            if (essayContainer) essayContainer.style.display = 'none';

            var selectKey = form.querySelector('.select-key');
            if (selectKey) selectKey.disabled = false;
            var essayInput = form.querySelector('.essay-input');
            if (essayInput) essayInput.disabled = true;
        }
    }

    document.querySelectorAll('.select-type').forEach(function (select) {
        select.addEventListener('change', function () {
            handleTypeChange(this);
        });
        // initial run
        handleTypeChange(select);
    });

    // Insert sample text into textarea
    var btnSample = document.getElementById('btnInsertSample');
    var pasteTextarea = document.getElementById('pasteTextarea');
    if (btnSample && pasteTextarea) {
        btnSample.addEventListener('click', function () {
            var sampleText = "1. Apa fungsi dari modul approval dokumen?\n" +
                "A. Mengesahkan dokumen agar sah digunakan\n" +
                "B. Menghapus dokumen lama dari server\n" +
                "C. Mengarsipkan dokumen tanpa persetujuan\n" +
                "D. Mengubah tipe dokumen secara acak\n" +
                "KUNCI: A\n" +
                "PENJELASAN: Modul approval memastikan dokumen divalidasi oleh atasan sebelum diedarkan.\n\n" +
                "2. Apakah kehadiran pada sesi Zoom wajib dikonfirmasi sebelum kuis?\n" +
                "A. Ya, wajib hadir\n" +
                "B. Tidak wajib\n" +
                "KUNCI: A\n\n" +
                "3. Mana saja departemen pendukung operasional kantor?\n" +
                "A. Marketing\n" +
                "B. Sales\n" +
                "C. HRD\n" +
                "D. Finance\n" +
                "E. Legal\n" +
                "F. General Affair\n" +
                "KUNCI: C\n\n" +
                "4. Jelaskan secara ringkas materi utama yang telah dipaparkan pada sesi Zoom tadi!\n" +
                "TIPE: ESSAY\n" +
                "KUNCI: Pemaparan mengenai alur kerja sistem, hak akses user, dan verifikasi absensi.";

            pasteTextarea.value = sampleText;
            pasteTextarea.focus();
        });
    }

    // ===============================================================
    // AI QUESTION GENERATOR LOGIC (GEMINI)
    // ===============================================================
    var aiModal = $('#aiGenerateModal');
    var aiStepConfig = document.getElementById('aiStepConfig');
    var aiStepLoading = document.getElementById('aiStepLoading');
    var aiStepReview = document.getElementById('aiStepReview');
    var aiAlertError = document.getElementById('aiAlertError');
    var aiAlertErrorText = document.getElementById('aiAlertErrorText');
    var aiQuestionsContainer = document.getElementById('aiQuestionsContainer');
    var aiSelectedToSaveCount = document.getElementById('aiSelectedToSaveCount');
    var docSelectedCount = document.getElementById('docSelectedCount');
    var aiReviewCountText = document.getElementById('aiReviewCountText');

    var generatedQuestionsCache = [];

    // Initialize select2 for other docs
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('#aiSelectOtherDocs').select2({
            theme: 'bootstrap4',
            dropdownParent: aiModal,
            placeholder: 'Cari & pilih dokumen lain...',
            width: '100%'
        }).on('change', updateDocCount);
    }

    // Auto-open modal if URL contains open_ai=1
    if (window.location.search.indexOf('open_ai=1') !== -1) {
        aiModal.modal('show');
    }

    // Update document selection counter
    function updateDocCount() {
        var count = 0;
        document.querySelectorAll('.ai-doc-checkbox:checked').forEach(function() { count++; });
        var otherSelected = $('#aiSelectOtherDocs').val() || [];
        count += otherSelected.length;
        if (docSelectedCount) {
            docSelectedCount.textContent = count + ' Terpilih';
            docSelectedCount.className = count > 0 ? 'badge badge-success' : 'badge badge-light border text-muted';
        }
    }

    document.querySelectorAll('.ai-doc-checkbox').forEach(function(cb) {
        cb.addEventListener('change', updateDocCount);
    });
    updateDocCount();

    // Toggle custom question count input
    document.querySelectorAll('input[name="ai_question_count"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            var customWrapper = document.getElementById('aiCustomCountWrapper');
            var customInput = document.getElementById('aiCustomCount');
            if (this.value === 'custom') {
                if (customWrapper) customWrapper.classList.remove('d-none');
                if (customInput) customInput.focus();
            } else {
                if (customWrapper) customWrapper.classList.add('d-none');
            }
        });
    });

    // Toggle API Key input
    var btnToggleKey = document.getElementById('btnToggleApiKeyInput');
    if (btnToggleKey) {
        btnToggleKey.addEventListener('click', function() {
            var wrapper = document.getElementById('aiApiKeyWrapper');
            if (wrapper) wrapper.classList.toggle('d-none');
        });
    }

    // Helper: show error alert
    function showAiError(msg) {
        if (aiAlertError && aiAlertErrorText) {
            aiAlertErrorText.textContent = msg;
            aiAlertError.classList.remove('d-none');
        } else {
            alert(msg);
        }
    }

    // Helper: hide error alert
    function hideAiError() {
        if (aiAlertError) aiAlertError.classList.add('d-none');
    }

    // Reset back to config step
    document.getElementById('btnAiBackToConfig').addEventListener('click', function() {
        aiStepReview.classList.add('d-none');
        aiStepLoading.classList.add('d-none');
        aiStepConfig.classList.remove('d-none');
        hideAiError();
    });

    // TRIGGER AI GENERATE
    document.getElementById('btnTriggerAiGenerate').addEventListener('click', function() {
        hideAiError();

        // 1. Gather documents
        var docIds = [];
        document.querySelectorAll('.ai-doc-checkbox:checked').forEach(function(cb) {
            docIds.push(cb.value);
        });
        var otherDocs = $('#aiSelectOtherDocs').val() || [];
        otherDocs.forEach(function(id) {
            if (docIds.indexOf(id) === -1) docIds.push(id);
        });

        if (docIds.length === 0) {
            showAiError('Silakan pilih minimal 1 dokumen materi acuan terlebih dahulu!');
            return;
        }

        // 2. Question count
        var countVal = document.querySelector('input[name="ai_question_count"]:checked').value;
        var count = parseInt(countVal);
        if (countVal === 'custom') {
            count = parseInt(document.getElementById('aiCustomCount').value) || 20;
            if (count < 1 || count > 100) {
                showAiError('Jumlah soal kustom harus antara 1 sampai 100 butir (AI akan menyesuaikan dengan kelengkapan dokumen).');
                return;
            }
        }

        // 3. Question type & parameters
        var type = document.querySelector('input[name="ai_question_type"]:checked').value;
        var difficulty = document.getElementById('aiDifficulty').value;
        var customPrompt = document.getElementById('aiCustomPrompt').value;
        var apiKeyOverride = document.getElementById('aiApiKeyOverride') ? document.getElementById('aiApiKeyOverride').value : null;

        // Transition to Loading
        aiStepConfig.classList.add('d-none');
        aiStepReview.classList.add('d-none');
        aiStepLoading.classList.remove('d-none');

        // Dynamic status text
        var statusTitles = [
            'Mempersiapkan Dokumen Materi...',
            'Menghubungkan ke AI Engine...',
            'Menganalisis Konsep & Fakta Materi...',
            'Menyusun Soal & Opsi Jawaban...',
            'Memverifikasi Kunci & Penjelasan...'
        ];
        var sIndex = 0;
        var statusInterval = setInterval(function() {
            sIndex = (sIndex + 1) % statusTitles.length;
            var stEl = document.getElementById('aiLoadingStatusTitle');
            if (stEl) stEl.textContent = statusTitles[sIndex];
        }, 2500);

        // Fetch API
        fetch('/training/{{ $training->id }}/questions/generate-ai', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                document_ids: docIds,
                question_count: count,
                question_type: type,
                difficulty: difficulty,
                custom_prompt: customPrompt,
                api_key: apiKeyOverride
            })
        })
        .then(function(res) {
            clearInterval(statusInterval);
            return res.json().then(function(data) {
                return { status: res.status, ok: res.ok, data: data };
            });
        })
        .then(function(result) {
            aiStepLoading.classList.add('d-none');

            if (!result.ok || !result.data.success) {
                aiStepConfig.classList.remove('d-none');
                var errMsg = (result.data && result.data.message) ? result.data.message : 'Terjadi kesalahan saat memproses AI.';
                showAiError(errMsg);
                return;
            }

            // Success: render questions
            generatedQuestionsCache = result.data.questions || [];
            renderGeneratedQuestions(generatedQuestionsCache, type);
            aiStepReview.classList.remove('d-none');
        })
        .catch(function(err) {
            clearInterval(statusInterval);
            aiStepLoading.classList.add('d-none');
            aiStepConfig.classList.remove('d-none');
            showAiError('Gagal menghubungi server atau jaringan terputus: ' + err.message);
        });
    });

    // RENDER GENERATED QUESTIONS CARDS
    function renderGeneratedQuestions(questions, type) {
        aiQuestionsContainer.innerHTML = '';
        aiReviewCountText.textContent = questions.length;

        questions.forEach(function(q, index) {
            var card = document.createElement('div');
            card.className = 'card mb-3 border shadow-sm ai-q-card';
            card.setAttribute('data-index', index);

            var isEssay = (q.type === 'essay');

            var cardHtml = '<div class="card-header py-2 bg-light d-flex align-items-center justify-content-between">' +
                '<div class="d-flex align-items-center">' +
                    '<div class="custom-control custom-checkbox mr-2">' +
                        '<input type="checkbox" class="custom-control-input ai-include-cb" id="ai_inc_' + index + '" checked>' +
                        '<label class="custom-control-label font-weight-bold text-dark" for="ai_inc_' + index + '">Soal #' + (index + 1) + '</label>' +
                    '</div>' +
                    '<span class="badge ' + (isEssay ? 'badge-info' : 'badge-primary') + ' ml-1">' +
                        (isEssay ? 'ESSAY' : 'PILIHAN GANDA') +
                    '</span>' +
                '</div>' +
                '<small class="text-muted">AI</small>' +
            '</div>';

            cardHtml += '<div class="card-body p-4">' +
                '<div class="form-group mb-3">' +
                    '<label class="font-weight-bold text-dark text-sm mb-1">Pertanyaan:</label>' +
                    '<textarea class="form-control ai-q-text" rows="3" style="font-size: 0.95rem; font-weight: 500; line-height: 1.6; min-height: 90px; border-radius: 8px; resize: vertical; border-color: #cbd5e1; padding: 10px 12px;">' + escapeHtml(q.question) + '</textarea>' +
                '</div>';

            if (!isEssay) {
                cardHtml += '<div class="mb-3">' +
                    '<label class="font-weight-bold text-dark text-sm mb-2">Pilihan Jawaban (Tandai kunci jawaban):</label>' +
                    '<div class="row">' +
                    ['a', 'b', 'c', 'd', 'e'].map(function(opt) {
                        var optVal = q['option_' + opt] || '';
                        var isChecked = (q.correct_answer === opt) ? 'checked' : '';
                        var highlightStyle = (q.correct_answer === opt) ? 'border-color: #3b82f6; background-color: #eff6ff;' : '';
                        return '<div class="col-md-6 mb-2">' +
                            '<div class="input-group" style="border-radius: 8px; ' + highlightStyle + '">' +
                                '<div class="input-group-prepend">' +
                                    '<span class="input-group-text bg-light font-weight-bold" style="cursor: pointer;" title="Tandai sebagai kunci">' +
                                        '<input type="radio" name="ai_correct_' + index + '" value="' + opt + '" ' + isChecked + ' class="mr-1 ai-radio-key" style="cursor: pointer;">' +
                                        '<span class="text-uppercase text-dark font-weight-bold">' + opt + '</span>' +
                                    '</span>' +
                                '</div>' +
                                '<input type="text" class="form-control ai-opt-' + opt + '" value="' + escapeHtml(optVal) + '" placeholder="Opsi ' + opt.toUpperCase() + '" style="font-size: 0.9rem;">' +
                            '</div>' +
                        '</div>';
                    }).join('') +
                    '</div>' +
                '</div>';
            } else {
                cardHtml += '<div class="form-group mb-3">' +
                    '<label class="font-weight-bold text-dark text-sm mb-1">Pedoman Kunci Jawaban:</label>' +
                    '<textarea class="form-control ai-essay-key" rows="3" style="font-size: 0.9rem; line-height: 1.5; min-height: 85px; border-radius: 8px; resize: vertical; padding: 10px 12px;">' + escapeHtml(q.correct_answer || '') + '</textarea>' +
                '</div>';
            }

            cardHtml += '<div class="form-group mb-0">' +
                '<label class="font-weight-bold text-dark text-sm mb-1">Pembahasan / Penjelasan:</label>' +
                '<textarea class="form-control ai-q-explanation" rows="3" placeholder="Penjelasan rujukan dokumen mengapa jawaban ini benar..." style="font-size: 0.9rem; line-height: 1.5; min-height: 85px; border-radius: 8px; resize: vertical; background-color: #f8fafc; border-color: #e2e8f0; padding: 10px 12px;">' + escapeHtml(q.explanation || '') + '</textarea>' +
            '</div>' +
            '</div>';

            card.innerHTML = cardHtml;
            aiQuestionsContainer.appendChild(card);
        });

        // Auto-expand all textareas so none are clipped
        setTimeout(function() {
            document.querySelectorAll('.ai-q-text, .ai-q-explanation, .ai-essay-key').forEach(function(ta) {
                autoResize(ta);
                ta.addEventListener('input', function() {
                    autoResize(this);
                });
            });
        }, 50);

        // Add change listener to include checkboxes
        document.querySelectorAll('.ai-include-cb').forEach(function(cb) {
            cb.addEventListener('change', updateSaveCount);
        });

        // Highlight selected option radio button
        document.querySelectorAll('.ai-radio-key').forEach(function(r) {
            r.addEventListener('change', function() {
                var card = this.closest('.ai-q-card');
                if (card) {
                    card.querySelectorAll('.input-group').forEach(function(ig) {
                        ig.style.borderColor = '';
                        ig.style.backgroundColor = '';
                    });
                    var parentGroup = this.closest('.input-group');
                    if (parentGroup) {
                        parentGroup.style.borderColor = '#3b82f6';
                        parentGroup.style.backgroundColor = '#eff6ff';
                    }
                }
            });
        });

        updateSaveCount();
    }

    function autoResize(el) {
        if (!el) return;
        el.style.height = 'auto';
        var scrollH = el.scrollHeight;
        if (scrollH > 0) {
            el.style.height = (scrollH + 8) + 'px';
        }
    }

    function updateSaveCount() {
        var count = document.querySelectorAll('.ai-include-cb:checked').length;
        aiSelectedToSaveCount.textContent = count;
        var saveBtn = document.getElementById('btnSaveAiBatch');
        if (saveBtn) {
            saveBtn.disabled = (count === 0);
        }
    }

    // Select/Deselect all review
    document.getElementById('btnAiSelectAll').addEventListener('click', function() {
        document.querySelectorAll('.ai-include-cb').forEach(function(cb) { cb.checked = true; });
        updateSaveCount();
    });
    document.getElementById('btnAiDeselectAll').addEventListener('click', function() {
        document.querySelectorAll('.ai-include-cb').forEach(function(cb) { cb.checked = false; });
        updateSaveCount();
    });

    // SAVE AI BATCH TO DATABASE
    document.getElementById('btnSaveAiBatch').addEventListener('click', function() {
        var btn = this;
        var cards = document.querySelectorAll('.ai-q-card');
        var payload = [];

        cards.forEach(function(card) {
            var cb = card.querySelector('.ai-include-cb');
            if (cb && cb.checked) {
                var index = parseInt(card.getAttribute('data-index'));
                var baseQ = generatedQuestionsCache[index] || {};
                var isEssay = (baseQ.type === 'essay');

                var qText = card.querySelector('.ai-q-text').value.trim();
                var explanation = card.querySelector('.ai-q-explanation').value.trim();

                var item = {
                    type: isEssay ? 'essay' : 'multiple_choice',
                    question: qText,
                    explanation: explanation
                };

                if (!isEssay) {
                    item.option_a = (card.querySelector('.ai-opt-a') ? card.querySelector('.ai-opt-a').value.trim() : '');
                    item.option_b = (card.querySelector('.ai-opt-b') ? card.querySelector('.ai-opt-b').value.trim() : '');
                    item.option_c = (card.querySelector('.ai-opt-c') ? card.querySelector('.ai-opt-c').value.trim() : '');
                    item.option_d = (card.querySelector('.ai-opt-d') ? card.querySelector('.ai-opt-d').value.trim() : '');
                    item.option_e = (card.querySelector('.ai-opt-e') ? card.querySelector('.ai-opt-e').value.trim() : '');
                    var selectedKey = card.querySelector('input[type="radio"]:checked');
                    item.correct_answer = selectedKey ? selectedKey.value : 'a';
                } else {
                    item.correct_answer = card.querySelector('.ai-essay-key') ? card.querySelector('.ai-essay-key').value.trim() : '';
                }

                if (item.question.length > 0) {
                    payload.push(item);
                }
            }
        });

        if (payload.length === 0) {
            alert('Tidak ada soal yang dipilih untuk disimpan.');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm mr-1"></span> Menyimpan...';

        fetch('/training/{{ $training->id }}/questions/save-ai-batch', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                questions: payload
            })
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                aiModal.modal('hide');
                window.location.reload();
            } else {
                alert('Gagal menyimpan soal: ' + (data.message || 'Terjadi kesalahan'));
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save mr-1"></i> Simpan Soal ke Kuis Pelatihan';
            }
        })
        .catch(function(err) {
            alert('Gagal menyimpan: ' + err.message);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save mr-1"></i> Simpan Soal ke Kuis Pelatihan';
        });
    });

    // Simple HTML escape helper
    function escapeHtml(text) {
        if (!text) return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});
</script>
@endsection
