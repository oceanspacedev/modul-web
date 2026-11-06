@extends('layout.main_template')

@section('content')
<style>
    /* Kunci jawaban yang ditandai pada kartu review soal AI */
    .ai-q-card .input-group.is-key .input-group-text,
    .ai-q-card .input-group.is-key .form-control {
        border-color: var(--success-600) !important;
        background-color: var(--success-50) !important;
    }
</style>

<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Kelola Soal Kuis Pelatihan" :subtitle="$training->title" eyebrow="Pelatihan / Soal Kuis">
            <x-slot:actions>
                <a href="/training/{{ $training->id }}" class="btn btn-default">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
                <div class="dropdown">
                    <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Import
                    </button>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="javascript:void(0)" data-toggle="modal" data-target="#importTextModal">Import Teks Cepat</a>
                        <a class="dropdown-item" href="javascript:void(0)" data-toggle="modal" data-target="#importExcelModal">Import Excel / CSV</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="/training/{{ $training->id }}/questions/template">Download Template</a>
                    </div>
                </div>
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
        <x-flash />

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    Daftar Pertanyaan <span class="badge badge-secondary">{{ $questions->count() }} soal</span>
                </h3>
                <div class="card-tools d-flex align-items-center gap-2">
                    @if($training->is_quiz_active)
                        <span class="badge badge-success">Kuis terbuka untuk peserta</span>
                    @else
                        <span class="badge badge-secondary">Kuis terkunci</span>
                    @endif
                    @if($questions->count() > 0)
                    <form action="/training/{{ $training->id }}/questions/delete-all" method="POST" class="d-inline m-0" onsubmit="return confirm('Apakah Anda yakin ingin menghapus SEMUA soal kuis ini?')">
                        @csrf
                        <button type="submit" class="btn btn-default btn-xs text-danger">
                            Hapus Semua Soal
                        </button>
                    </form>
                    @endif
                </div>
            </div>

            <div class="card-body">
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
                <div class="card mb-3">
                    <div class="card-header">
                        <div class="d-flex align-items-center gap-2">
                            <strong>Pertanyaan #{{ $index + 1 }}</strong>
                            @if($isEssay)
                                <span class="badge badge-secondary">Essay / Uraian</span>
                            @else
                                <span class="badge badge-secondary">Pilihan Ganda &middot; {{ count($activeOptions) }} opsi (A-{{ strtoupper(array_key_last($activeOptions) ?? 'B') }})</span>
                            @endif
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <button type="button" class="btn btn-default btn-xs" data-toggle="modal" data-target="#editModal_{{ $q->id }}">
                                Edit
                            </button>
                            <form action="{{ route('training.questions.destroy', [$training, $q]) }}" method="POST" class="d-inline m-0" onsubmit="return confirm('Hapus soal ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-default btn-xs text-danger">
                                    Hapus
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="section-title mb-3">{{ $q->question }}</p>

                        @if($isEssay)
                            <div class="p-3 border rounded bg-light small">
                                <span class="text-muted d-block mb-1">Pedoman / kunci acuan jawaban</span>
                                <div class="text-dark">{{ $q->correct_answer ?: '(Belum ada kunci acuan khusus. Penilaian bersifat deskriptif sesuai jawaban peserta)' }}</div>
                            </div>
                        @else
                            <div class="row small">
                                @foreach($activeOptions as $optKey => $optVal)
                                    @php
                                        $isKey = (strtolower((string)$q->correct_answer) === $optKey);
                                    @endphp
                                    <div class="col-md-6 mb-2">
                                        <div class="d-flex align-items-start justify-content-between gap-2 px-3 py-2 border rounded {{ $isKey ? 'bg-soft-success' : '' }}">
                                            <span><strong>{{ strtoupper($optKey) }}.</strong> {{ $optVal }}</span>
                                            @if($isKey)
                                                <span class="badge badge-success">Kunci jawaban</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if($q->explanation)
                            <div class="small text-muted mt-3 pt-2 border-top">
                                <strong>Catatan pembahasan:</strong> {{ $q->explanation }}
                            </div>
                        @endif
                    </div>
                </div>

                <!-- EDIT MODAL -->
                <div class="modal fade" id="editModal_{{ $q->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <form action="{{ route('training.questions.update', [$training, $q]) }}" method="POST">
                                @method('PUT')
                                @csrf
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit Soal #{{ $index + 1 }}</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">&times;</button>
                                </div>
                                <div class="modal-body">
                                    <div class="form-group">
                                        <label>Tipe Soal</label>
                                        <select name="type" class="form-control select-type" data-target="edit_opts_{{ $q->id }}">
                                            <option value="multiple_choice" {{ !$isEssay ? 'selected' : '' }}>Pilihan Ganda</option>
                                            <option value="essay" {{ $isEssay ? 'selected' : '' }}>Essay / Uraian</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Pertanyaan</label>
                                        <textarea name="question" class="form-control" rows="3" required>{{ $q->question }}</textarea>
                                    </div>

                                    <!-- OPTIONS CONTAINER -->
                                    <div id="edit_opts_{{ $q->id }}" class="opts-container" style="{{ $isEssay ? 'display: none;' : '' }}">
                                        <div class="form-row">
                                            <div class="form-group col-md-6">
                                                <label>Pilihan A <span class="text-danger">*</span></label>
                                                <input type="text" name="option_a" class="form-control" value="{{ $q->option_a }}">
                                            </div>
                                            <div class="form-group col-md-6">
                                                <label>Pilihan B <span class="text-danger">*</span></label>
                                                <input type="text" name="option_b" class="form-control" value="{{ $q->option_b }}">
                                            </div>
                                            <div class="form-group col-md-6">
                                                <label>Pilihan C <span class="text-muted">(opsional)</span></label>
                                                <input type="text" name="option_c" class="form-control" value="{{ $q->option_c }}">
                                            </div>
                                            <div class="form-group col-md-6">
                                                <label>Pilihan D <span class="text-muted">(opsional)</span></label>
                                                <input type="text" name="option_d" class="form-control" value="{{ $q->option_d }}">
                                            </div>
                                            <div class="form-group col-md-6">
                                                <label>Pilihan E <span class="text-muted">(opsional)</span></label>
                                                <input type="text" name="option_e" class="form-control" value="{{ $q->option_e }}">
                                            </div>
                                            <div class="form-group col-md-6">
                                                <label>Pilihan F <span class="text-muted">(opsional)</span></label>
                                                <input type="text" name="option_f" class="form-control" value="{{ $q->option_f }}">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label>Kunci Jawaban Pilihan Ganda</label>
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
                                        <div class="form-group">
                                            <label>Pedoman / Acuan Kunci Jawaban <span class="text-muted">(opsional)</span></label>
                                            <textarea name="correct_answer" class="form-control essay-input" rows="2" placeholder="Catatan kunci acuan jawaban benar...">{{ $isEssay ? $q->correct_answer : '' }}</textarea>
                                        </div>
                                    </div>

                                    <div class="form-group mb-0">
                                        <label>Catatan Pembahasan <span class="text-muted">(opsional)</span></label>
                                        <textarea name="explanation" class="form-control" rows="2">{{ $q->explanation }}</textarea>
                                    </div>
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
                <x-empty-state icon="fa-question-circle" title="Belum ada soal kuis" text="Tambahkan soal untuk pelatihan ini secara manual, lewat AI, atau import dari teks maupun Excel.">
                    <div class="d-flex justify-content-center flex-wrap gap-2">
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#aiGenerateModal">
                            <i class="fas fa-magic"></i> Buat Soal via AI
                        </button>
                        <button type="button" class="btn btn-default" data-toggle="modal" data-target="#addQuestionModal">
                            Tambah Manual
                        </button>
                        <button type="button" class="btn btn-default" data-toggle="modal" data-target="#importTextModal">
                            Import Teks
                        </button>
                        <button type="button" class="btn btn-default" data-toggle="modal" data-target="#importExcelModal">
                            Import Excel / CSV
                        </button>
                    </div>
                </x-empty-state>
                @endforelse
            </div>
        </div>
    </div>
</section>

<!-- AI QUESTION GENERATOR MODAL (GEMINI) -->
<div class="modal fade" id="aiGenerateModal" tabindex="-1" role="dialog" aria-labelledby="aiGenerateModalLabel" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <!-- MODAL HEADER -->
            <div class="modal-header">
                <h5 class="modal-title" id="aiGenerateModalLabel">Buat Soal via AI</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup" id="btnCloseAiModal">&times;</button>
            </div>

            <!-- MODAL BODY -->
            <div class="modal-body bg-light" style="min-height: 480px;">
                <!-- ALERT ERROR -->
                <div id="aiAlertError" class="alert alert-danger alert-dismissible fade show d-none mb-3" role="alert">
                    <span id="aiAlertErrorText"></span>
                </div>

                <!-- STEP 1: CONFIGURATION FORM -->
                <div id="aiStepConfig">
                    <div class="row">
                        <!-- LEFT COLUMN: DOKUMEN & MATERI -->
                        <div class="col-lg-6 mb-3">
                            <div class="card h-100 mb-0">
                                <div class="card-header">
                                    <h3 class="card-title">Dokumen Materi <span class="text-danger">*</span></h3>
                                    <span class="badge badge-secondary" id="docSelectedCount">0 Terpilih</span>
                                </div>
                                <div class="card-body">
                                    <!-- TRAINING LINKED DOCUMENTS -->
                                    <div class="mb-3">
                                        <div class="eyebrow mb-2">Dokumen pelatihan ini</div>
                                        @if($documents->isEmpty())
                                            <div class="p-2 border rounded bg-light text-muted small">
                                                Belum ada dokumen yang dilampirkan langsung pada pelatihan ini. Silakan pilih dari dokumen sistem di bawah.
                                            </div>
                                        @else
                                            <div class="list-group" style="max-height: 180px; overflow-y: auto;">
                                                @foreach($documents as $doc)
                                                <label class="list-group-item list-group-item-action d-flex align-items-center px-3 py-2 mb-0" style="cursor: pointer;">
                                                    <input type="checkbox" name="ai_document_ids[]" value="{{ $doc->id }}" class="mr-2 ai-doc-checkbox" checked>
                                                    <div class="text-truncate flex-grow-1 min-w-0">
                                                        <span class="d-block text-truncate">{{ $doc->name }}</span>
                                                        <span class="small text-muted">
                                                            <span class="badge badge-secondary">{{ $doc->dokumentype->name ?? 'Dokumen' }}</span>
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
                                        <div class="eyebrow mb-2">Dokumen lain dari sistem</div>
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
                            <div class="card h-100 mb-0">
                                <div class="card-header">
                                    <h3 class="card-title">Pengaturan Pembuatan Soal</h3>
                                </div>
                                <div class="card-body">
                                    <!-- JUMLAH SOAL -->
                                    <div class="form-group">
                                        <label class="d-block">Jumlah Soal</label>
                                        <div class="btn-group btn-group-toggle segmented w-100" data-toggle="buttons">
                                            <label class="btn btn-sm active" style="flex: 1;">
                                                <input type="radio" name="ai_question_count" value="5" checked> 5 Soal
                                            </label>
                                            <label class="btn btn-sm" style="flex: 1;">
                                                <input type="radio" name="ai_question_count" value="10"> 10 Soal
                                            </label>
                                            <label class="btn btn-sm" style="flex: 1;">
                                                <input type="radio" name="ai_question_count" value="15"> 15 Soal
                                            </label>
                                            <label class="btn btn-sm" style="flex: 1;">
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
                                    <div class="form-group">
                                        <label class="d-block">Tipe Soal</label>
                                        <div class="row">
                                            <div class="col-6">
                                                <div class="border rounded px-3 py-2">
                                                    <div class="custom-control custom-radio">
                                                        <input type="radio" id="ai_type_mc" name="ai_question_type" value="multiple_choice" class="custom-control-input" checked>
                                                        <label class="custom-control-label d-block" for="ai_type_mc">Pilihan Ganda</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="border rounded px-3 py-2">
                                                    <div class="custom-control custom-radio">
                                                        <input type="radio" id="ai_type_essay" name="ai_question_type" value="essay" class="custom-control-input">
                                                        <label class="custom-control-label d-block" for="ai_type_essay">Essay / Uraian</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- TINGKAT KESULITAN -->
                                    <div class="form-group">
                                        <label for="aiDifficulty">Tingkat Kesulitan</label>
                                        <select id="aiDifficulty" class="form-control form-control-sm">
                                            <option value="campuran" selected>Campuran</option>
                                            <option value="mudah">Mudah</option>
                                            <option value="sedang">Sedang</option>
                                            <option value="sulit">Sulit</option>
                                        </select>
                                    </div>

                                    <!-- INSTRUKSI KHUSUS -->
                                    <div class="form-group">
                                        <label for="aiCustomPrompt">Instruksi Khusus <span class="text-muted">(opsional)</span></label>
                                        <textarea id="aiCustomPrompt" class="form-control form-control-sm" rows="2" placeholder="Tulis catatan atau instruksi tambahan jika ada..."></textarea>
                                    </div>

                                    <!-- GEMINI API KEY STATUS / OVERRIDE -->
                                    <div class="p-3 rounded border bg-light">
                                        @if($isGeminiConfigured)
                                            <div class="d-flex align-items-center justify-content-between small">
                                                <span class="text-success">
                                                    <i class="fas fa-check-circle"></i> API key AI terhubung (.env)
                                                </span>
                                                <a href="javascript:void(0)" id="btnToggleApiKeyInput">Ubah key</a>
                                            </div>
                                            <div id="aiApiKeyWrapper" class="mt-2 d-none">
                                                <input type="password" id="aiApiKeyOverride" class="form-control form-control-sm" placeholder="Masukkan API Key kustom...">
                                            </div>
                                        @else
                                            <div>
                                                <label for="aiApiKeyOverride" class="d-block">API Key AI <span class="text-danger">*</span></label>
                                                <input type="text" id="aiApiKeyOverride" class="form-control form-control-sm" placeholder="Masukkan API Key...">
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TRIGGER BUTTON -->
                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
                        <button type="button" id="btnTriggerAiGenerate" class="btn btn-primary">
                            <i class="fas fa-magic"></i> Mulai Generate Soal
                        </button>
                    </div>
                </div>

                <!-- STEP 2: LOADING PROGRESS -->
                <div id="aiStepLoading" class="text-center py-5 d-none">
                    <div class="mb-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                    </div>
                    <h5 class="section-title mb-3" id="aiLoadingStatusTitle">
                        Memproses Soal via AI...
                    </h5>
                    <div class="progress mx-auto" style="height: 6px; max-width: 320px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" style="width: 100%"></div>
                    </div>
                </div>

                <!-- STEP 3: REVIEW & EDIT GENERATED QUESTIONS -->
                <div id="aiStepReview" class="d-none">
                    <div class="d-flex align-items-center justify-content-between mb-3 py-2 px-3 border rounded bg-white">
                        <strong>Hasil Soal AI (<span id="aiReviewCountText">0</span> Soal)</strong>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-xs btn-default" id="btnAiSelectAll">Pilih Semua</button>
                            <button type="button" class="btn btn-xs btn-default" id="btnAiDeselectAll">Batal Semua</button>
                        </div>
                    </div>

                    <!-- CONTAINER LIST SOAL -->
                    <div id="aiQuestionsContainer" style="max-height: 520px; overflow-y: auto; padding-right: 4px;">
                        <!-- JS WILL POPULATE CARDS HERE -->
                    </div>

                    <!-- FOOTER ACTIONS FOR REVIEW -->
                    <div class="d-flex align-items-center justify-content-between mt-3 pt-3 border-top">
                        <button type="button" class="btn btn-default" id="btnAiBackToConfig">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </button>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                            <button type="button" id="btnSaveAiBatch" class="btn btn-primary">
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
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <form action="/training/{{ $training->id }}/questions" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Pertanyaan Manual</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="add_type_select">Tipe Soal <span class="text-danger">*</span></label>
                        <select name="type" id="add_type_select" class="form-control select-type" data-target="add_opts_container">
                            <option value="multiple_choice">Pilihan Ganda (A-B s/d A-F)</option>
                            <option value="essay">Essay / Uraian</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Pertanyaan Soal <span class="text-danger">*</span></label>
                        <textarea name="question" class="form-control" rows="3" placeholder="Tuliskan pertanyaan di sini..." required></textarea>
                    </div>

                    <!-- OPTIONS CONTAINER -->
                    <div id="add_opts_container" class="opts-container">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Pilihan A <span class="text-danger">*</span></label>
                                <input type="text" name="option_a" class="form-control" placeholder="Teks opsi A">
                            </div>
                            <div class="form-group col-md-6">
                                <label>Pilihan B <span class="text-danger">*</span></label>
                                <input type="text" name="option_b" class="form-control" placeholder="Teks opsi B">
                            </div>
                            <div class="form-group col-md-6">
                                <label>Pilihan C <span class="text-muted">(opsional)</span></label>
                                <input type="text" name="option_c" class="form-control" placeholder="Teks opsi C">
                            </div>
                            <div class="form-group col-md-6">
                                <label>Pilihan D <span class="text-muted">(opsional)</span></label>
                                <input type="text" name="option_d" class="form-control" placeholder="Teks opsi D">
                            </div>
                            <div class="form-group col-md-6">
                                <label>Pilihan E <span class="text-muted">(opsional)</span></label>
                                <input type="text" name="option_e" class="form-control" placeholder="Teks opsi E">
                            </div>
                            <div class="form-group col-md-6">
                                <label>Pilihan F <span class="text-muted">(opsional)</span></label>
                                <input type="text" name="option_f" class="form-control" placeholder="Teks opsi F">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Kunci Jawaban Benar <span class="text-danger">*</span></label>
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
                        <div class="form-group">
                            <label>Pedoman Kunci Jawaban <span class="text-muted">(opsional)</span></label>
                            <textarea name="correct_answer" class="form-control essay-input" rows="2" placeholder="Catatan acuan jawaban benar..."></textarea>
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label>Catatan Pembahasan <span class="text-muted">(opsional)</span></label>
                        <textarea name="explanation" class="form-control" rows="2" placeholder="Catatan penjelasan jawaban..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Soal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 2. IMPORT TEXT MODAL (COPY-PASTE CEPAT) -->
<div class="modal fade" id="importTextModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <form action="/training/{{ $training->id }}/questions/import-text" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Import Soal via Teks</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <!-- KOLOM KIRI: TEXTAREA -->
                        <div class="col-lg-8 mb-3 mb-lg-0">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label for="pasteTextarea" class="mb-0">Teks Soal</label>
                                <button type="button" class="btn btn-default btn-xs" id="btnInsertSample">
                                    Isi Contoh
                                </button>
                            </div>
                            <textarea name="raw_text" id="pasteTextarea" class="form-control text-monospace" style="height: 480px !important; min-height: 440px !important; resize: vertical;" placeholder="Tempelkan teks soal kuis Anda di sini..." required></textarea>
                        </div>

                        <!-- KOLOM KANAN: PANDUAN FORMAT -->
                        <div class="col-lg-4">
                            <div class="card h-100 mb-0">
                                <div class="card-header">
                                    <h3 class="card-title">Panduan Format Penulisan</h3>
                                </div>
                                <div class="card-body small" style="overflow-y: auto; max-height: 480px;">
                                    <p class="mb-1 text-dark"><strong>1. Pilihan Ganda (A-D)</strong></p>
                                    <div class="p-2 bg-light rounded border mb-3 text-monospace">
1. Pertanyaan soal...<br>
A. Opsi A<br>
B. Opsi B<br>
C. Opsi C<br>
D. Opsi D<br>
KUNCI: A<br>
PENJELASAN: Catatan pembahasan (opsional)
                                    </div>

                                    <p class="mb-1 text-dark"><strong>2. True / False (Hanya A & B)</strong></p>
                                    <div class="p-2 bg-light rounded border mb-3 text-monospace">
2. Apakah kehadiran wajib diisi?<br>
A. Ya<br>
B. Tidak<br>
KUNCI: A
                                    </div>

                                    <p class="mb-1 text-dark"><strong>3. Opsi Panjang (A s/d F)</strong></p>
                                    <div class="p-2 bg-light rounded border mb-3 text-monospace">
3. Pertanyaan soal opsi banyak...<br>
A. Opsi 1<br>
B. Opsi 2<br>
C. Opsi 3<br>
D. Opsi 4<br>
E. Opsi 5<br>
F. Opsi 6<br>
KUNCI: E
                                    </div>

                                    <p class="mb-1 text-dark"><strong>4. Soal Essay / Uraian</strong></p>
                                    <div class="p-2 bg-light rounded border mb-0 text-monospace">
4. Jelaskan materi setelah zoom!<br>
TIPE: ESSAY<br>
KUNCI: Acuan poin jawaban peserta
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Semua Soal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 3. IMPORT EXCEL / CSV MODAL -->
<div class="modal fade" id="importExcelModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form action="/training/{{ $training->id }}/questions/import-excel" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Import Soal via Excel / CSV</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">&times;</button>
                </div>
                <div class="modal-body">
                    <a href="/training/{{ $training->id }}/questions/template" class="btn btn-default btn-block mb-3">
                        <i class="fas fa-download"></i> Unduh Template Excel (.xlsx)
                    </a>

                    <div class="form-group mb-0">
                        <label for="importExcelFile">File Spreadsheet (.xlsx, .xls, .csv)</label>
                        <input type="file" name="file" id="importExcelFile" class="form-control" accept=".xlsx,.xls,.csv" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Import File</button>
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
            docSelectedCount.className = count > 0 ? 'badge badge-success' : 'badge badge-secondary';
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
            card.className = 'card mb-3 ai-q-card';
            card.setAttribute('data-index', index);

            var isEssay = (q.type === 'essay');

            var cardHtml = '<div class="card-header">' +
                '<div class="d-flex align-items-center gap-2">' +
                    '<div class="custom-control custom-checkbox">' +
                        '<input type="checkbox" class="custom-control-input ai-include-cb" id="ai_inc_' + index + '" checked>' +
                        '<label class="custom-control-label text-dark" for="ai_inc_' + index + '">Soal #' + (index + 1) + '</label>' +
                    '</div>' +
                    '<span class="badge badge-secondary">' +
                        (isEssay ? 'Essay' : 'Pilihan Ganda') +
                    '</span>' +
                '</div>' +
                '<small class="text-muted">AI</small>' +
            '</div>';

            cardHtml += '<div class="card-body">' +
                '<div class="form-group">' +
                    '<label>Pertanyaan</label>' +
                    '<textarea class="form-control ai-q-text" rows="3">' + escapeHtml(q.question) + '</textarea>' +
                '</div>';

            if (!isEssay) {
                cardHtml += '<div class="form-group">' +
                    '<label>Pilihan Jawaban <span class="text-muted">(tandai kunci jawaban)</span></label>' +
                    '<div class="row">' +
                    ['a', 'b', 'c', 'd', 'e'].map(function(opt) {
                        var optVal = q['option_' + opt] || '';
                        var isChecked = (q.correct_answer === opt) ? 'checked' : '';
                        var keyClass = (q.correct_answer === opt) ? ' is-key' : '';
                        return '<div class="col-md-6 mb-2">' +
                            '<div class="input-group' + keyClass + '">' +
                                '<div class="input-group-prepend">' +
                                    '<span class="input-group-text" style="cursor: pointer;" title="Tandai sebagai kunci">' +
                                        '<input type="radio" name="ai_correct_' + index + '" value="' + opt + '" ' + isChecked + ' class="mr-1 ai-radio-key" style="cursor: pointer;">' +
                                        '<span class="text-dark">' + opt.toUpperCase() + '</span>' +
                                    '</span>' +
                                '</div>' +
                                '<input type="text" class="form-control ai-opt-' + opt + '" value="' + escapeHtml(optVal) + '" placeholder="Opsi ' + opt.toUpperCase() + '">' +
                            '</div>' +
                        '</div>';
                    }).join('') +
                    '</div>' +
                '</div>';
            } else {
                cardHtml += '<div class="form-group">' +
                    '<label>Pedoman Kunci Jawaban</label>' +
                    '<textarea class="form-control ai-essay-key" rows="3">' + escapeHtml(q.correct_answer || '') + '</textarea>' +
                '</div>';
            }

            cardHtml += '<div class="form-group mb-0">' +
                '<label>Pembahasan / Penjelasan</label>' +
                '<textarea class="form-control ai-q-explanation" rows="3" placeholder="Penjelasan rujukan dokumen mengapa jawaban ini benar...">' + escapeHtml(q.explanation || '') + '</textarea>' +
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
                        ig.classList.remove('is-key');
                    });
                    var parentGroup = this.closest('.input-group');
                    if (parentGroup) {
                        parentGroup.classList.add('is-key');
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
