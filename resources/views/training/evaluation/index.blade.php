@extends('layout.main_template')

@php
    use App\Models\TrainingEvaluationQuestion;

    // Data tiap pertanyaan untuk mengisi modal ubah.
    $questionPayload = fn (TrainingEvaluationQuestion $q) => [
        'action' => route('training.evaluation.update', [$training, $q]),
        'section' => $q->section,
        'type' => $q->type,
        'question' => $q->question,
        'low_label' => $q->low_label,
        'high_label' => $q->high_label,
        'options' => implode("\n", $q->options ?? []),
        'is_required' => $q->is_required,
        'id' => $q->id,
    ];

    // Isian yang gagal divalidasi (tambah atau ubah), untuk membuka kembali modal dengan nilai yang sama.
    $failedInput = null;
    if ($errors->any() && session()->hasOldInput('type')) {
        $failedQuestion = $questions->firstWhere('id', (int) old('editing_id'));
        $failedInput = [
            'action' => $failedQuestion ? route('training.evaluation.update', [$training, $failedQuestion]) : null,
            'id' => $failedQuestion?->id,
            'section' => old('section'),
            'type' => old('type'),
            'question' => old('question'),
            'low_label' => old('low_label'),
            'high_label' => old('high_label'),
            'options' => old('options'),
            'is_required' => (bool) old('is_required'),
        ];
    }
@endphp

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Pertanyaan Form Evaluasi" :subtitle="$training->title"
            :breadcrumbs="['Pelatihan' => null, 'Jadwal Pelatihan' => route('training.index'), $training->title => route('training.show', $training), 'Form Evaluasi' => null]">
            <x-slot:actions>
                <a href="{{ route('training.show', $training) }}" class="btn btn-default">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#evaluationQuestionModal" data-mode="create">
                    <i class="fas fa-plus"></i> Tambah Pertanyaan
                </button>
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />

        @unless($training->has_evaluation)
            <div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-2">
                <span>Form evaluasi pelatihan ini sedang <strong>nonaktif</strong>, jadi peserta belum diminta mengisinya.</span>
                <form action="{{ route('training.toggle-evaluation', $training) }}" method="POST" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-default btn-sm">Aktifkan</button>
                </form>
            </div>
        @endunless

        @if($answerCount > 0)
            <div class="alert alert-info">
                Sudah ada <strong>{{ $answerCount }} peserta</strong> yang mengisi. Mengubah atau menghapus pertanyaan memengaruhi rekap jawaban yang sudah masuk; tipe pertanyaan tidak bisa diganti lagi.
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    Daftar Pertanyaan <span class="text-muted text-sm font-weight-normal">({{ $questions->count() }})</span>
                </h3>
            </div>

            @if($questions->isEmpty())
                <div class="card-body">
                    <x-empty-state icon="fa-clipboard-list" title="Belum ada pertanyaan"
                        text="Tanpa pertanyaan, peserta tidak diminta mengisi form evaluasi. Tambah pertanyaan sendiri atau mulai dari template bawaan.">
                        <form action="{{ route('training.evaluation.defaults', $training) }}" method="POST" class="m-0">
                            @csrf
                            <button type="submit" class="btn btn-default">Pakai template bawaan</button>
                        </form>
                    </x-empty-state>
                </div>
            @else
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 50px;">No</th>
                                <th style="width: 60%;">Pertanyaan</th>
                                <th>Tipe</th>
                                <th class="text-center">Wajib</th>
                                <th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($questions as $q)
                                <tr>
                                    <td class="text-center align-middle text-muted">{{ $loop->iteration }}</td>
                                    <td class="align-middle">
                                        @if(filled($q->section))
                                            <span class="cell-sub">{{ $q->section }}</span>
                                        @endif
                                        <span class="d-block font-weight-bold text-gray-950">{{ $q->question }}</span>
                                        @if($q->isScale() && (filled($q->low_label) || filled($q->high_label)))
                                            <span class="cell-sub">1 = {{ $q->low_label ?? '-' }} &middot; 5 = {{ $q->high_label ?? '-' }}</span>
                                        @elseif($q->type === TrainingEvaluationQuestion::TYPE_CHOICE)
                                            {{-- Daftar panjang (mis. nama toko) diringkas supaya baris tabel tidak memanjang. --}}
                                            @php
                                                $options = $q->options ?? [];
                                                $shown = $q->hasLongOptionList() ? array_slice($options, 0, TrainingEvaluationQuestion::SHORT_LIST_MAX) : $options;
                                                $more = count($options) - count($shown);
                                            @endphp
                                            <span class="cell-sub">{{ implode(' · ', $shown) }}{{ $more > 0 ? ' · +'.$more.' pilihan lain' : '' }}</span>
                                        @endif
                                    </td>
                                    <td class="align-middle text-nowrap">{{ $q->typeLabel() }}</td>
                                    <td class="text-center align-middle">
                                        <span class="badge {{ $q->is_required ? 'badge-primary' : 'badge-secondary' }}">{{ $q->is_required ? 'Wajib' : 'Opsional' }}</span>
                                    </td>
                                    <td class="text-right align-middle text-nowrap">
                                        <form action="{{ route('training.evaluation.move', [$training, $q]) }}" method="POST" class="d-inline m-0">
                                            @csrf
                                            <button type="submit" name="direction" value="up" class="btn btn-default btn-xs" title="Geser ke atas" aria-label="Geser ke atas" @disabled($loop->first)>
                                                <i class="fas fa-arrow-up"></i>
                                            </button>
                                            <button type="submit" name="direction" value="down" class="btn btn-default btn-xs" title="Geser ke bawah" aria-label="Geser ke bawah" @disabled($loop->last)>
                                                <i class="fas fa-arrow-down"></i>
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-default btn-xs" data-toggle="modal" data-target="#evaluationQuestionModal"
                                            data-mode="edit" data-question="{{ json_encode($questionPayload($q)) }}">
                                            Ubah
                                        </button>
                                        <form action="{{ route('training.evaluation.destroy', [$training, $q]) }}" method="POST" class="d-inline m-0"
                                            onsubmit="return confirm(@js($answerCount > 0 ? 'Hapus pertanyaan ini? Jawaban peserta untuk pertanyaan ini ikut hilang dari rekap.' : 'Hapus pertanyaan ini?'))">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-default btn-xs text-danger">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            <div class="card-footer text-muted text-sm">
                Nama, jabatan, divisi, tanggal, dan materi pelatihan tidak perlu ditanyakan: semuanya terisi otomatis dari data peserta dan jadwal.
            </div>
        </div>
    </div>
</section>

{{-- MODAL TAMBAH / UBAH PERTANYAAN --}}
<div class="modal fade" id="evaluationQuestionModal" tabindex="-1" role="dialog" aria-labelledby="evaluationQuestionModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form action="{{ route('training.evaluation.store', $training) }}" method="POST" class="modal-content" id="evaluationQuestionForm"
            data-store-action="{{ route('training.evaluation.store', $training) }}">
            @csrf
            <input type="hidden" name="_method" value="POST" id="evaluationQuestionMethod">
            {{-- Pertanyaan yang sedang diubah; dipakai untuk membuka kembali form ini bila validasi gagal. --}}
            <input type="hidden" name="editing_id" value="" id="evaluationQuestionEditing">
            <div class="modal-header">
                <h5 class="modal-title" id="evaluationQuestionModalTitle">Tambah Pertanyaan</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="eq_question">Pertanyaan <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="eq_question" name="question" rows="2" maxlength="500" required></textarea>
                </div>
                <div class="row">
                    <div class="col-sm-6 form-group">
                        <label for="eq_type">Tipe jawaban</label>
                        <select class="form-control" id="eq_type" name="type">
                            @foreach(TrainingEvaluationQuestion::TYPES as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 form-group">
                        <label for="eq_section">Bagian <span class="text-muted font-weight-normal">(opsional)</span></label>
                        <input type="text" class="form-control" id="eq_section" name="section" maxlength="100" list="eq_sections" placeholder="mis. Pemateri">
                        <datalist id="eq_sections">
                            @foreach($sectionNames as $name)
                                <option value="{{ $name }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                </div>
                <div class="row" data-for-type="scale">
                    <div class="col-sm-6 form-group">
                        <label for="eq_low">Label nilai 1</label>
                        <input type="text" class="form-control" id="eq_low" name="low_label" maxlength="60" placeholder="Sangat Tidak Setuju">
                    </div>
                    <div class="col-sm-6 form-group">
                        <label for="eq_high">Label nilai 5</label>
                        <input type="text" class="form-control" id="eq_high" name="high_label" maxlength="60" placeholder="Sangat Setuju">
                    </div>
                </div>
                <div class="form-group" data-for-type="choice">
                    <label for="eq_options">Pilihan jawaban <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="eq_options" name="options" rows="4" placeholder="Satu pilihan per baris"></textarea>
                    <small class="form-text text-muted">
                        Peserta memilih satu. Tulis 2 sampai {{ TrainingEvaluationQuestion::MAX_OPTIONS }} pilihan;
                        lebih dari {{ TrainingEvaluationQuestion::SHORT_LIST_MAX }} pilihan tampil sebagai dropdown.
                    </small>
                </div>
                <input type="hidden" name="is_required" value="0">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="eq_required" name="is_required" value="1" checked>
                    <label class="custom-control-label" for="eq_required">Wajib diisi peserta</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary" id="evaluationQuestionSubmit">Tambah</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('footer')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('evaluationQuestionForm');
    const type = document.getElementById('eq_type');
    const fields = {
        question: document.getElementById('eq_question'),
        section: document.getElementById('eq_section'),
        low_label: document.getElementById('eq_low'),
        high_label: document.getElementById('eq_high'),
        options: document.getElementById('eq_options'),
    };

    // Label skala hanya untuk tipe skala, daftar pilihan hanya untuk tipe pilihan.
    function showFieldsForType() {
        form.querySelectorAll('[data-for-type]').forEach(function (group) {
            group.hidden = group.dataset.forType !== type.value;
        });
        fields.options.required = type.value === 'choice';
    }
    type.addEventListener('change', showFieldsForType);

    // Isi form: data pertanyaan untuk ubah, kosong untuk tambah.
    function fill(data, editing) {
        form.action = editing ? data.action : form.dataset.storeAction;
        document.getElementById('evaluationQuestionMethod').value = editing ? 'PUT' : 'POST';
        document.getElementById('evaluationQuestionEditing').value = editing ? data.id : '';
        document.getElementById('evaluationQuestionModalTitle').textContent = editing ? 'Ubah Pertanyaan' : 'Tambah Pertanyaan';
        document.getElementById('evaluationQuestionSubmit').textContent = editing ? 'Simpan' : 'Tambah';

        Object.keys(fields).forEach(function (name) { fields[name].value = data[name] || ''; });
        type.value = data.type || 'scale';
        document.getElementById('eq_required').checked = 'is_required' in data ? !!data.is_required : true;
        showFieldsForType();
    }

    let keepValues = false;
    $('#evaluationQuestionModal').on('show.bs.modal', function (event) {
        if (keepValues) { keepValues = false; return; }
        const trigger = event.relatedTarget;
        const editing = !!trigger && trigger.dataset.mode === 'edit';
        fill(editing ? JSON.parse(trigger.dataset.question) : {}, editing);
    });

    // Validasi gagal: buka lagi form dengan isian yang tadi diketik, supaya tidak perlu mengulang.
    const failed = @json($failedInput);
    if (failed) {
        fill(failed, !!failed.action);
        keepValues = true;
        $('#evaluationQuestionModal').modal('show');
    }

    showFieldsForType();
});
</script>
@endsection
