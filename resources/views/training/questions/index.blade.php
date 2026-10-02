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
                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addQuestionModal">
                    <i class="fas fa-plus mr-1"></i> Tambah Soal Manual
                </button>
                <button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#importTextModal">
                    <i class="fas fa-paste mr-1"></i> Import Teks Cepat
                </button>
                <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#importExcelModal">
                    <i class="fas fa-file-excel mr-1"></i> Import Excel / CSV
                </button>
                <a href="/training/{{ $training->id }}/questions/template" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-download mr-1"></i> Download Template Excel
                </a>
                @if($questions->count() > 0)
                <form action="/training/{{ $training->id }}/questions/delete-all" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus SEMUA soal kuis ini?')">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-trash-alt mr-1"></i> Hapus Semua Soal
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
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <a href="/training/{{ $training->id }}/questions/{{ $q->id }}/delete" onclick="return confirm('Hapus soal ini?')" class="btn btn-default btn-xs text-danger">
                                <i class="fas fa-trash"></i> Hapus
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
                    <i class="fas fa-clipboard-list fa-3x mb-3 text-secondary"></i>
                    <p class="mb-3 font-weight-bold">Belum ada soal kuis yang ditambahkan untuk pelatihan ini.</p>
                    <div class="d-flex justify-content-center flex-wrap" style="gap: 8px;">
                        <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addQuestionModal">
                            <i class="fas fa-plus mr-1"></i> Tambah Soal Manual
                        </button>
                        <button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#importTextModal">
                            <i class="fas fa-paste mr-1"></i> Import Teks Cepat
                        </button>
                        <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#importExcelModal">
                            <i class="fas fa-file-excel mr-1"></i> Import Excel / CSV
                        </button>
                    </div>
                </div>
                @endforelse
            </div>
        </div>
    </div>
</section>

<!-- 1. ADD QUESTION MODAL (MANUAL) -->
<div class="modal fade" id="addQuestionModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form action="/training/{{ $training->id }}/questions" method="POST">
                @csrf
                <div class="modal-header py-3">
                    <h5 class="modal-title font-weight-bold">Tambah Pertanyaan Kuis</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4">
                    <div class="form-group mb-3">
                        <label class="font-weight-600 mb-1">Tipe Soal <span class="text-danger">*</span></label>
                        <select name="type" id="add_type_select" class="form-control select-type" data-target="add_opts_container">
                            <option value="multiple_choice">Pilihan Ganda (Fleksibel A-B s/d A-F)</option>
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
                                <input type="text" name="option_c" class="form-control" placeholder="Teks opsi C (kosongkan jika True/False)">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="font-weight-600 mb-1">Pilihan D (Opsional)</label>
                                <input type="text" name="option_d" class="form-control" placeholder="Teks opsi D (kosongkan jika hanya A-C)">
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
                            <label class="font-weight-600 mb-1">Pedoman / Acuan Kunci Jawaban (Opsional)</label>
                            <textarea name="correct_answer" class="form-control essay-input" rows="2" placeholder="Catatan acuan jawaban yang benar untuk pedoman pemeriksaan..."></textarea>
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-600 mb-1">Catatan Pembahasan (Opsional)</label>
                        <textarea name="explanation" class="form-control" rows="2" placeholder="Catatan penjelasan jawaban..."></textarea>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan Soal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 2. IMPORT TEXT MODAL (COPY-PASTE CEPAT) -->
<div class="modal fade" id="importTextModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <form action="/training/{{ $training->id }}/questions/import-text" method="POST">
                @csrf
                <div class="modal-header py-3 bg-info text-white">
                    <h5 class="modal-title font-weight-bold">
                        <i class="fas fa-paste mr-2"></i> Import Soal via Copy-Paste Teks
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4">
                    <div class="row">
                        <!-- KOLOM KIRI: TEXTAREA BESAR & LEGA -->
                        <div class="col-lg-8 mb-3 mb-lg-0">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="font-weight-bold m-0 text-dark" style="font-size: 1rem;">
                                    <i class="fas fa-edit mr-1 text-info"></i> Area Teks Soal:
                                </label>
                                <button type="button" class="btn btn-outline-info btn-xs font-weight-bold" id="btnInsertSample">
                                    <i class="fas fa-magic mr-1"></i> Isi Contoh Teks Soal
                                </button>
                            </div>
                            <textarea name="raw_text" id="pasteTextarea" class="form-control" style="height: 480px !important; min-height: 440px !important; resize: vertical; font-size: 14px; line-height: 1.6; font-family: 'Courier New', Courier, monospace; border: 1.5px solid #17a2b8; border-radius: 6px; padding: 12px;" placeholder="Tempelkan (paste) teks soal kuis Anda di sini..." required></textarea>
                            <div class="d-flex justify-content-between align-items-center mt-2 text-muted small">
                                <span><i class="fas fa-info-circle mr-1"></i> Mendukung puluhan hingga ratusan soal pilihan ganda (A-B s/d A-F) & essay sekaligus.</span>
                                <span>Sudut kanan bawah bisa ditarik untuk memperlebar.</span>
                            </div>
                        </div>

                        <!-- KOLOM KANAN: PANDUAN FORMAT -->
                        <div class="col-lg-4">
                            <div class="card h-100 border bg-light mb-0 shadow-none">
                                <div class="card-header bg-white py-2 font-weight-bold text-dark text-sm">
                                    <i class="fas fa-book-open mr-1 text-primary"></i> Panduan Format Penulisan
                                </div>
                                <div class="card-body p-3 text-xs" style="overflow-y: auto; max-height: 480px;">
                                    <p class="mb-1 text-dark font-weight-bold">1. Pilihan Ganda Standar (A-D):</p>
                                    <div class="p-2 bg-white rounded border mb-3 font-monospace" style="font-size: 11px;">
1. Pertanyaan soal pilihan ganda...<br>
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
3. Mana departemen di bawah ini?<br>
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
                <div class="modal-footer py-2 bg-light">
                    <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-info btn-sm font-weight-bold px-4">
                        <i class="fas fa-cloud-upload-alt mr-1"></i> Proses & Simpan Semua Soal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 3. IMPORT EXCEL / CSV MODAL -->
<div class="modal fade" id="importExcelModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="/training/{{ $training->id }}/questions/import-excel" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header py-3 bg-success text-white">
                    <h5 class="modal-title font-weight-bold">
                        <i class="fas fa-file-excel mr-1"></i> Import Soal via File Excel / CSV
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <span class="text-muted small d-block mb-2">Gunakan template resmi agar susunan kolom sesuai:</span>
                        <a href="/training/{{ $training->id }}/questions/template" class="btn btn-outline-success btn-sm btn-block">
                            <i class="fas fa-download mr-1"></i> Unduh Format Template Excel (.xlsx)
                        </a>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-600 mb-1">Pilih File Spreadsheet (.xlsx, .xls, .csv)</label>
                        <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv" required>
                        <small class="text-muted mt-1 d-block">Maksimal ukuran file: 10MB.</small>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success btn-sm font-weight-bold px-4">
                        <i class="fas fa-file-import mr-1"></i> Import Sekarang
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
});
</script>
@endsection
