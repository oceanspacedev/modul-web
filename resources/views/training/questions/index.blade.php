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
                        Soal Kuis
                    </h1>
                    <span class="text-muted small">{{ $training->title }}</span>
                </div>
            </div>
            <div class="mt-2">
                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addQuestionModal">
                    <i class="fas fa-plus mr-1"></i> Tambah Soal
                </button>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                {{ session('success') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        <div class="card mb-4">
            <div class="card-header py-3 d-flex align-items-center justify-content-between">
                <h3 class="card-title font-weight-bold">
                    Daftar Pertanyaan ({{ $questions->count() }} Soal)
                </h3>
                <div>
                    @if($training->is_quiz_active)
                        <span class="badge badge-success px-2 py-1">Kuis Terbuka</span>
                    @else
                        <span class="badge badge-secondary px-2 py-1">Kuis Terkunci</span>
                    @endif
                </div>
            </div>

            <div class="card-body p-4">
                @forelse($questions as $index => $q)
                <div class="card mb-4 border">
                    <div class="card-header py-2 bg-light d-flex align-items-center justify-content-between">
                        <strong class="text-sm">Pertanyaan #{{ $index + 1 }}</strong>
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
                        <p class="font-weight-600 mb-3 text-dark">{{ $q->question }}</p>

                        <div class="row text-sm">
                            <div class="col-md-6 mb-2">
                                <div class="p-2 border rounded {{ $q->correct_answer === 'a' ? 'bg-success text-white font-weight-bold' : 'bg-light' }}">
                                    A. {{ $q->option_a }}
                                    @if($q->correct_answer === 'a') <span class="float-right text-xs">Kunci Jawaban</span> @endif
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="p-2 border rounded {{ $q->correct_answer === 'b' ? 'bg-success text-white font-weight-bold' : 'bg-light' }}">
                                    B. {{ $q->option_b }}
                                    @if($q->correct_answer === 'b') <span class="float-right text-xs">Kunci Jawaban</span> @endif
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="p-2 border rounded {{ $q->correct_answer === 'c' ? 'bg-success text-white font-weight-bold' : 'bg-light' }}">
                                    C. {{ $q->option_c }}
                                    @if($q->correct_answer === 'c') <span class="float-right text-xs">Kunci Jawaban</span> @endif
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="p-2 border rounded {{ $q->correct_answer === 'd' ? 'bg-success text-white font-weight-bold' : 'bg-light' }}">
                                    D. {{ $q->option_d }}
                                    @if($q->correct_answer === 'd') <span class="float-right text-xs">Kunci Jawaban</span> @endif
                                </div>
                            </div>
                        </div>

                        @if($q->explanation)
                            <div class="text-xs text-muted mt-2 pt-2 border-top">
                                <strong>Catatan:</strong> {{ $q->explanation }}
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
                                        <label class="font-weight-600 mb-1">Pertanyaan</label>
                                        <textarea name="question" class="form-control" rows="3" required>{{ $q->question }}</textarea>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6 mb-2 mb-md-0">
                                            <label class="font-weight-600 mb-1">Pilihan A</label>
                                            <input type="text" name="option_a" class="form-control" value="{{ $q->option_a }}" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="font-weight-600 mb-1">Pilihan B</label>
                                            <input type="text" name="option_b" class="form-control" value="{{ $q->option_b }}" required>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6 mb-2 mb-md-0">
                                            <label class="font-weight-600 mb-1">Pilihan C</label>
                                            <input type="text" name="option_c" class="form-control" value="{{ $q->option_c }}" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="font-weight-600 mb-1">Pilihan D</label>
                                            <input type="text" name="option_d" class="form-control" value="{{ $q->option_d }}" required>
                                        </div>
                                    </div>
                                    <div class="form-group mb-3">
                                        <label class="font-weight-600 mb-1">Kunci Jawaban</label>
                                        <select name="correct_answer" class="form-control" required>
                                            <option value="a" {{ $q->correct_answer === 'a' ? 'selected' : '' }}>Pilihan A</option>
                                            <option value="b" {{ $q->correct_answer === 'b' ? 'selected' : '' }}>Pilihan B</option>
                                            <option value="c" {{ $q->correct_answer === 'c' ? 'selected' : '' }}>Pilihan C</option>
                                            <option value="d" {{ $q->correct_answer === 'd' ? 'selected' : '' }}>Pilihan D</option>
                                        </select>
                                    </div>
                                    <div class="form-group mb-0">
                                        <label class="font-weight-600 mb-1">Catatan Pembahasan (Opsional)</label>
                                        <textarea name="explanation" class="form-control" rows="2">{{ $q->explanation }}</textarea>
                                    </div>
                                </div>
                                <div class="modal-footer py-2">
                                    <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @empty
                <div class="text-center py-5 text-muted">
                    <p class="mb-2">Belum ada soal kuis yang ditambahkan.</p>
                    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addQuestionModal">
                        Tambah Soal Pertama
                    </button>
                </div>
                @endforelse
            </div>
        </div>
    </div>
</section>

<!-- ADD QUESTION MODAL -->
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
                        <label class="font-weight-600 mb-1">Pertanyaan Soal <span class="text-danger">*</span></label>
                        <textarea name="question" class="form-control" rows="3" placeholder="Tuliskan pertanyaan di sini..." required></textarea>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6 mb-2 mb-md-0">
                            <label class="font-weight-600 mb-1">Pilihan A <span class="text-danger">*</span></label>
                            <input type="text" name="option_a" class="form-control" placeholder="Teks opsi A" required>
                        </div>
                        <div class="col-md-6">
                            <label class="font-weight-600 mb-1">Pilihan B <span class="text-danger">*</span></label>
                            <input type="text" name="option_b" class="form-control" placeholder="Teks opsi B" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6 mb-2 mb-md-0">
                            <label class="font-weight-600 mb-1">Pilihan C <span class="text-danger">*</span></label>
                            <input type="text" name="option_c" class="form-control" placeholder="Teks opsi C" required>
                        </div>
                        <div class="col-md-6">
                            <label class="font-weight-600 mb-1">Pilihan D <span class="text-danger">*</span></label>
                            <input type="text" name="option_d" class="form-control" placeholder="Teks opsi D" required>
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="font-weight-600 mb-1">Kunci Jawaban Benar <span class="text-danger">*</span></label>
                        <select name="correct_answer" class="form-control" required>
                            <option value="a">Pilihan A</option>
                            <option value="b">Pilihan B</option>
                            <option value="c">Pilihan C</option>
                            <option value="d">Pilihan D</option>
                        </select>
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
@endsection
