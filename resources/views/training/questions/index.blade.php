@extends('layout.main_template')

@section('content')

<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Kelola Soal Kuis Pelatihan" :subtitle="$training->title" :breadcrumbs="['Pelatihan' => null, 'Jadwal Pelatihan' => route('training.index'), $training->title => route('training.show', $training), 'Soal Kuis' => null]">
            <x-slot:actions>
                <a href="/training/{{ $training->id }}" class="btn btn-default">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
                <div class="dropdown">
                    <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Import / Export
                    </button>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="javascript:void(0)" data-toggle="modal" data-target="#importTextModal">Import Teks Cepat</a>
                        <a class="dropdown-item" href="javascript:void(0)" data-toggle="modal" data-target="#importExcelModal">Import Excel / CSV</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="/training/{{ $training->id }}/questions/template">Download Template</a>
                        @if($questions->isNotEmpty())
                            <form action="{{ route('training.questions.export', $training) }}" method="POST" class="m-0">
                                @csrf
                                <button type="submit" class="dropdown-item">Export Soal &amp; Kunci Jawaban</button>
                            </form>
                        @endif
                    </div>
                </div>
                {{-- Setelah kunci jawaban diperbaiki, nilai yang sudah tersimpan baru berubah lewat tombol ini. --}}
                @if($resultCount > 0)
                    <form action="{{ route('training.regrade', $training) }}" method="POST" class="d-inline m-0"
                        onsubmit="return confirm(@js('Hitung ulang nilai '.$resultCount.' hasil kuis memakai kunci jawaban saat ini? Jawaban peserta dan nilai essay tidak berubah.'))">
                        @csrf
                        <button type="submit" class="btn btn-default" title="Nilai peserta dihitung lagi memakai kunci jawaban saat ini">
                            <i class="fas fa-calculator"></i> Hitung Ulang Nilai
                        </button>
                    </form>
                @endif
                <button type="button" class="btn btn-default" data-toggle="modal" data-target="#addQuestionModal">
                    <i class="fas fa-plus"></i> Tambah Manual
                </button>
                @php $showSetupNext = $training->needsSetup() && $questions->isNotEmpty(); @endphp
                <button type="button" class="btn {{ $showSetupNext ? 'btn-default' : 'btn-primary' }}" data-toggle="modal" data-target="#aiGenerateModal">
                    <i class="fas fa-magic"></i> Buat Soal via AI
                </button>
                @if($showSetupNext)
                    <a href="{{ route('training.setup', $training) }}" class="btn btn-primary">
                        Lanjut: Pengaturan <i class="fas fa-arrow-right"></i>
                    </a>
                @endif
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
                    @if($training->isPhaseActive('posttest'))
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
                @php $invalidKeyCount = $questions->reject->hasValidKey()->count(); @endphp
                @if($invalidKeyCount > 0)
                    <div class="alert alert-danger">
                        <strong>{{ $invalidKeyCount }} soal pilihan ganda tidak punya kunci jawaban yang valid.</strong>
                        Jawaban peserta pada soal itu selalu dihitung salah, dan kuis tidak bisa dibuka sampai diperbaiki.
                        Buka <strong>Edit</strong> pada soal bertanda "Kunci jawaban tidak valid", pilih kunci yang benar, lalu simpan.
                        @if($resultCount > 0)
                            Setelah itu tekan <strong>Hitung Ulang Nilai</strong> supaya nilai {{ $resultCount }} hasil kuis yang sudah masuk ikut diperbaiki.
                        @endif
                    </div>
                @endif
                @forelse($questions as $index => $q)
                @php
                    $isEssay = ($q->type === 'essay');
                    $activeOptions = $isEssay ? [] : $q->options();
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
                            @unless($q->hasValidKey())
                                <span class="badge badge-danger">Kunci jawaban tidak valid</span>
                            @endunless
                            @if($training->hasBothPhases() && ($q->used_in ?? 'both') !== 'both')
                                <span class="badge badge-primary">{{ $q->usedInLabel() }}</span>
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
                        @if($q->imageUrl())
                            <a href="{{ $q->imageUrl() }}" target="_blank" rel="noopener" class="d-inline-block mb-3">
                                <img src="{{ $q->imageUrl() }}" alt="Gambar soal" class="quiz-img">
                            </a>
                        @endif

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
                                            <span>
                                                <strong>{{ strtoupper($optKey) }}.</strong> {{ $optVal }}
                                                @if($q->optionImageUrl($optKey))
                                                    <a href="{{ $q->optionImageUrl($optKey) }}" target="_blank" rel="noopener" class="d-block mt-1">
                                                        <img src="{{ $q->optionImageUrl($optKey) }}" alt="Gambar pilihan {{ strtoupper($optKey) }}" class="quiz-img quiz-opt-img">
                                                    </a>
                                                @endif
                                            </span>
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
                            <form action="{{ route('training.questions.update', [$training, $q]) }}" method="POST" enctype="multipart/form-data" class="ai-revise-form" data-question-id="{{ $q->id }}">
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
                                    @include('training.questions.used-in-field', ['current' => $q->used_in ?? 'both', 'fieldId' => 'edit_used_in_'.$q->id])
                                    <div class="form-group">
                                        <label>Pertanyaan</label>
                                        <textarea name="question" class="form-control" rows="3" placeholder="Teks pertanyaan (boleh kosong kalau soalnya berupa gambar)">{{ $q->question }}</textarea>
                                        <x-quiz-image-field class="mt-2" name="question_image" remove-name="remove_question_image" :current="$q->imageUrl()" label="Tambah gambar soal" />
                                    </div>

                                    <!-- OPTIONS CONTAINER -->
                                    <div id="edit_opts_{{ $q->id }}" class="opts-container" style="{{ $isEssay ? 'display: none;' : '' }}">
                                        @include('training.questions.partials.option-fields', ['question' => $q])
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
<div class="modal fade modal-center" id="aiGenerateModal" tabindex="-1" role="dialog" aria-labelledby="aiGenerateModalLabel" aria-hidden="true" data-backdrop="static">
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
            <form action="/training/{{ $training->id }}/questions" method="POST" enctype="multipart/form-data">
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
                    @include('training.questions.used-in-field', ['current' => 'both', 'fieldId' => 'add_used_in', 'followsType' => 'add_type_select'])

                    <div class="form-group">
                        <label>Pertanyaan Soal <span class="text-danger">*</span></label>
                        <textarea name="question" class="form-control" rows="3" placeholder="Tuliskan pertanyaan di sini, atau tambahkan gambar saja di bawah..."></textarea>
                        <x-quiz-image-field class="mt-2" name="question_image" remove-name="remove_question_image" label="Tambah gambar soal" />
                    </div>

                    <!-- OPTIONS CONTAINER -->
                    <div id="add_opts_container" class="opts-container">
                        @include('training.questions.partials.option-fields', ['question' => null])
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
                            <textarea name="raw_text" id="pasteTextarea" class="form-control text-monospace" style="height: 480px !important; min-height: 440px !important; resize: vertical;" placeholder="Tempelkan teks soal kuis Anda di sini..." required>{{ old('raw_text') }}</textarea>
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
                                    <p class="mt-3 mb-1 text-dark"><strong>Aturan baca</strong></p>
                                    <ul class="pl-3 mb-0">
                                        <li>Tiap soal pilihan ganda <b>wajib</b> punya baris KUNCI berisi huruf pilihannya (boleh juga "B." atau teks pilihannya).</li>
                                        <li>Soal tanpa pilihan dibaca sebagai essay.</li>
                                        <li>Baris tanpa awalan melanjutkan bagian di atasnya (pertanyaan, pilihan, atau penjelasan).</li>
                                        <li>Satu soal yang tidak jelas membatalkan semuanya; teks Anda dikembalikan untuk diperbaiki.</li>
                                    </ul>
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
                    <ul class="small text-muted pl-3 mt-3 mb-0">
                        <li>Baris pertama harus berisi judul kolom seperti di template; kolom dibaca dari judulnya. Hanya sheet pertama yang dibaca.</li>
                        <li><b>kunci_jawaban</b> soal pilihan ganda harus huruf pilihan yang terisi (A-F), atau teks yang sama persis dengan salah satu pilihan.</li>
                        <li><b>tipe_soal</b>: PG atau ESSAY. <b>dipakai_di</b> (opsional): KEDUANYA, PRETEST, atau POSTTEST.</li>
                        <li>Satu baris yang tidak jelas (kunci tidak cocok, pilihan kembar, pertanyaan kosong) membatalkan seluruh file, dan nomor barisnya dikabarkan lewat Notifikasi.</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Import File</button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('layout.partials.quiz-image-slot-script', ['uploadUrl' => route('training.questions.upload-image', $training)])
@include('layout.partials.ai-revise-script')
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

    // AI generate/revise run on the server queue; results are fetched by polling this address.
    var aiStatusUrlTemplate = @json(route('training.questions.ai-status', [$training, '00000000-0000-0000-0000-000000000000']));
    function aiStatusUrl(token) {
        return aiStatusUrlTemplate.replace('00000000-0000-0000-0000-000000000000', token);
    }

    // Initialize select2 for other docs
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('#aiSelectOtherDocs').select2({
            dropdownParent: aiModal,
            placeholder: 'Cari & pilih dokumen lain...',
            width: '100%'
        }).on('change', updateDocCount);
    }

    // Import teks yang ditolak: buka lagi dialognya dengan teks yang tadi ditempel supaya tinggal diperbaiki.
    @if(old('raw_text'))
        if (typeof $ !== 'undefined') $('#importTextModal').modal('show');
    @endif

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

    // Dokumen acuan yang dipilih (pelatihan + dokumen lain); dipakai saat generate dan saat "Ubah dengan AI".
    function selectedAiDocIds() {
        var docIds = [];
        document.querySelectorAll('.ai-doc-checkbox:checked').forEach(function(cb) {
            docIds.push(cb.value);
        });
        var otherDocs = $('#aiSelectOtherDocs').val() || [];
        otherDocs.forEach(function(id) {
            if (docIds.indexOf(id) === -1) docIds.push(id);
        });
        return docIds;
    }

    // TRIGGER AI GENERATE
    document.getElementById('btnTriggerAiGenerate').addEventListener('click', function() {
        hideAiError();

        // 1. Gather documents
        var docIds = selectedAiDocIds();

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

        function aiGenerateFailed(message) {
            clearInterval(statusInterval);
            aiStepLoading.classList.add('d-none');
            aiStepConfig.classList.remove('d-none');
            showAiError(message);
        }

        // The questions are written on the server queue (reading documents is too heavy for one request):
        // wait for the result by polling.
        function pollAiQuestions(token, delay) {
            setTimeout(function() {
                fetch(aiStatusUrl(token), { headers: { 'Accept': 'application/json' } })
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        if (data.status === 'done') {
                            clearInterval(statusInterval);
                            aiStepLoading.classList.add('d-none');
                            generatedQuestionsCache = data.questions || [];
                            renderGeneratedQuestions(generatedQuestionsCache, type);
                            aiStepReview.classList.remove('d-none');
                        } else if (data.status === 'failed') {
                            aiGenerateFailed(data.message || 'Terjadi kesalahan saat memproses AI.');
                        } else {
                            pollAiQuestions(token, 4000);
                        }
                    })
                    .catch(function() { pollAiQuestions(token, 8000); });
            }, delay);
        }

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
            return res.json().then(function(data) {
                return { status: res.status, ok: res.ok, data: data };
            });
        })
        .then(function(result) {
            if (!result.ok || !result.data.success || !result.data.token) {
                var errors = result.data && result.data.errors ? Object.values(result.data.errors)[0][0] : null;
                aiGenerateFailed(errors || (result.data && result.data.message) || 'Terjadi kesalahan saat memproses AI.');
                return;
            }

            pollAiQuestions(result.data.token, 2000);
        })
        .catch(function(err) {
            aiGenerateFailed('Gagal menghubungi server atau jaringan terputus: ' + err.message);
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
            card.setAttribute('data-type', isEssay ? 'essay' : 'multiple_choice');

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
                aiReviseButton() +
            '</div>';

            cardHtml += '<div class="card-body">' +
                aiRevisePanel(index, isEssay) +
                '<div class="form-group">' +
                    '<label>Pertanyaan</label>' +
                    '<textarea class="form-control ai-q-text" rows="3">' + escapeHtml(q.question) + '</textarea>' +
                    '<div class="ai-q-image mt-2">' + quizImageSlot('Tambah gambar soal') + '</div>' +
                '</div>';

            if (!isEssay) {
                cardHtml += '<div class="quiz-options-block">' +
                    '<label>Pilihan Jawaban <span class="text-muted">(tandai kunci jawaban)</span></label>' +
                    ['a', 'b', 'c', 'd', 'e'].map(function(opt) {
                        var optVal = q['option_' + opt] || '';
                        var isChecked = (q.correct_answer === opt) ? 'checked' : '';
                        var keyClass = (q.correct_answer === opt) ? ' is-key' : '';
                        return '<div class="mb-2">' +
                            '<div class="quiz-option-row' + keyClass + '">' +
                                '<label class="quiz-option-letter" title="Tandai sebagai kunci">' +
                                    '<input type="radio" name="ai_correct_' + index + '" value="' + opt + '" ' + isChecked + ' class="ai-radio-key">' +
                                    opt.toUpperCase() +
                                '</label>' +
                                '<input type="text" class="form-control ai-opt-' + opt + '" value="' + escapeHtml(optVal) + '" placeholder="Opsi ' + opt.toUpperCase() + '">' +
                            '</div>' +
                            '<div class="ai-opt-image mt-1" data-letter="' + opt + '">' + quizImageSlot('Gambar ' + opt.toUpperCase()) + '</div>' +
                        '</div>';
                    }).join('') +
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
                    card.querySelectorAll('.quiz-option-row').forEach(function(ig) {
                        ig.classList.remove('is-key');
                    });
                    var parentGroup = this.closest('.quiz-option-row');
                    if (parentGroup) {
                        parentGroup.classList.add('is-key');
                    }
                }
            });
        });

        updateSaveCount();
    }

    // "Ubah dengan AI" per kartu soal.
    bindAiRevise(aiQuestionsContainer, {
        url: '{{ route('training.questions.revise-ai', $training) }}',
        statusUrl: aiStatusUrl,
        cardSelector: '.ai-q-card',
        read: function(card) {
            var isEssay = card.getAttribute('data-type') === 'essay';
            var q = {
                type: isEssay ? 'essay' : 'multiple_choice',
                question: card.querySelector('.ai-q-text').value,
                explanation: card.querySelector('.ai-q-explanation').value
            };
            if (isEssay) {
                q.correct_answer = card.querySelector('.ai-essay-key').value;
            } else {
                ['a', 'b', 'c', 'd', 'e'].forEach(function(opt) {
                    q['option_' + opt] = card.querySelector('.ai-opt-' + opt).value;
                });
                var key = card.querySelector('.ai-radio-key:checked');
                q.correct_answer = key ? key.value : 'a';
            }
            return q;
        },
        write: function(card, q) {
            card.querySelector('.ai-q-text').value = q.question || '';
            card.querySelector('.ai-q-explanation').value = q.explanation || '';
            if (card.getAttribute('data-type') === 'essay') {
                card.querySelector('.ai-essay-key').value = q.correct_answer || '';
            } else {
                ['a', 'b', 'c', 'd', 'e'].forEach(function(opt) {
                    card.querySelector('.ai-opt-' + opt).value = q['option_' + opt] || '';
                });
                var radio = card.querySelector('.ai-radio-key[value="' + (q.correct_answer || 'a') + '"]');
                if (radio) {
                    radio.checked = true;
                    radio.dispatchEvent(new Event('change'));
                }
            }
            card.querySelectorAll('.ai-q-text, .ai-q-explanation, .ai-essay-key').forEach(autoResize);
        },
        // AI membaca ulang dokumen acuan yang dipilih saat membuat soal.
        extra: function() {
            var apiKey = document.getElementById('aiApiKeyOverride');
            return Object.assign({ document_ids: selectedAiDocIds() }, apiKey && apiKey.value ? { api_key: apiKey.value } : {});
        }
    });

    // "Ubah dengan AI" di form edit soal tersimpan. Materi diambil server dari dokumen pelatihan ini.
    document.querySelectorAll('form.ai-revise-form').forEach(function(form) {
        mountAiRevise(form, 'edit_' + form.dataset.questionId);
    });
    bindAiRevise(document.body, {
        url: '{{ route('training.questions.revise-ai', $training) }}',
        statusUrl: aiStatusUrl,
        cardSelector: 'form.ai-revise-form',
        collectOthers: false,
        read: function(form) {
            var isEssay = form.elements.type.value === 'essay';
            var q = {
                type: isEssay ? 'essay' : 'multiple_choice',
                question: form.elements.question.value,
                explanation: form.elements.explanation.value,
                correct_answer: form.querySelector(isEssay ? '.essay-input' : '.select-key').value,
                // Opsi F tidak dibuat AI; disimpan di sini supaya "Kembalikan" ikut memulihkannya.
                option_f: form.elements.option_f.value
            };
            ['a', 'b', 'c', 'd', 'e'].forEach(function(opt) { q['option_' + opt] = form.elements['option_' + opt].value; });
            return q;
        },
        write: function(form, q, part) {
            form.elements.question.value = q.question || '';
            form.elements.explanation.value = q.explanation || '';
            if (form.elements.type.value === 'essay') {
                form.querySelector('.essay-input').value = q.correct_answer || '';
                return;
            }
            ['a', 'b', 'c', 'd', 'e'].forEach(function(opt) { form.elements['option_' + opt].value = q['option_' + opt] || ''; });
            // AI menyusun 5 pilihan (A–E); opsi F lama dikosongkan bila pilihan jawaban ikut diubah.
            if (part !== 'question') {
                form.elements.option_f.value = q.option_f || '';
            }
            form.querySelector('.select-key').value = q.correct_answer || 'a';
        },
        extra: function(form) {
            var apiKey = document.getElementById('aiApiKeyOverride');
            return Object.assign({ question_id: parseInt(form.dataset.questionId, 10) }, apiKey && apiKey.value ? { api_key: apiKey.value } : {});
        }
    });

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
                    explanation: explanation,
                    question_image: quizImageSlotValue(card.querySelector('.ai-q-image .quiz-img-slot'))
                };

                if (!isEssay) {
                    item.option_images = {};
                    card.querySelectorAll('.ai-opt-image').forEach(function(box) {
                        var path = quizImageSlotValue(box.querySelector('.quiz-img-slot'));
                        if (path) {
                            item.option_images[box.getAttribute('data-letter')] = path;
                        }
                    });
                }

                if (!isEssay) {
                    item.option_a = (card.querySelector('.ai-opt-a') ? card.querySelector('.ai-opt-a').value.trim() : '');
                    item.option_b = (card.querySelector('.ai-opt-b') ? card.querySelector('.ai-opt-b').value.trim() : '');
                    item.option_c = (card.querySelector('.ai-opt-c') ? card.querySelector('.ai-opt-c').value.trim() : '');
                    item.option_d = (card.querySelector('.ai-opt-d') ? card.querySelector('.ai-opt-d').value.trim() : '');
                    item.option_e = (card.querySelector('.ai-opt-e') ? card.querySelector('.ai-opt-e').value.trim() : '');
                    // Hanya radio kunci jawaban: kartu juga memuat radio panel "Ubah dengan AI" (pertanyaan/jawaban/semuanya).
                    var selectedKey = card.querySelector('.ai-radio-key:checked');
                    item.correct_answer = selectedKey ? selectedKey.value : '';
                } else {
                    item.correct_answer = card.querySelector('.ai-essay-key') ? card.querySelector('.ai-essay-key').value.trim() : '';
                }

                // Soal boleh berupa teks, gambar, atau keduanya.
                if (item.question.length > 0 || item.question_image) {
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
                if (data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    window.location.reload();
                }
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
