@extends('layout.main_template')

@php
    $isEdit = $module->exists;
    $materialLabels = $videos->mapWithKeys(fn ($v) => ['video:'.$v->id => 'Video: '.$v->title])
        ->merge($documents->mapWithKeys(fn ($d) => ['document:'.$d->id => 'Dokumen: '.$d->name]));
    $initialItems = collect(old('items', $selectedItems->map->key()->all()))
        ->filter(fn ($key) => $materialLabels->has($key))
        ->values();
    $selectedDivisis = collect(old('divisi_ids', $module->exists ? $module->divisis->pluck('id')->all() : []))->map(fn ($id) => (int) $id);
    $selectedRoles = collect(old('role_ids', $module->exists ? $module->roles->pluck('id')->all() : []))->map(fn ($id) => (int) $id);
@endphp

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark font-weight-bold" style="font-size: 1.5rem;">
                    {{ $isEdit ? 'Edit Modul Belajar' : 'Tambah Modul Belajar' }}
                </h1>
            </div>
            <div class="col-sm-6 text-right">
                <a href="{{ route('learning-module.index') }}" class="btn btn-default btn-sm">Kembali</a>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        @include('learning_module.partials.alerts')

        <form action="{{ $isEdit ? route('learning-module.update', $module) : route('learning-module.store') }}" method="POST" id="moduleForm">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header py-3">
                            <h3 class="card-title font-weight-bold">Informasi Modul</h3>
                        </div>
                        <div class="card-body p-4">
                            <div class="form-group mb-4">
                                <label for="title" class="font-weight-600 mb-2">Judul Modul <span class="text-danger">*</span></label>
                                <input type="text" id="title" name="title" class="form-control" value="{{ old('title', $module->title) }}"
                                    placeholder="Contoh: SOP Keselamatan Kerja Gudang" required maxlength="255">
                            </div>

                            <div class="form-group mb-4">
                                <label for="description" class="font-weight-600 mb-2">Deskripsi</label>
                                <textarea id="description" name="description" class="form-control" rows="3"
                                    placeholder="Tujuan dan gambaran singkat modul">{{ old('description', $module->description) }}</textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 form-group mb-4">
                                    <label for="passing_score" class="font-weight-600 mb-2">Nilai Minimal Lulus (KKM) <span class="text-danger">*</span></label>
                                    <input type="number" id="passing_score" name="passing_score" class="form-control" min="0" max="100"
                                        value="{{ old('passing_score', $module->passing_score) }}" required>
                                    <small class="text-muted">Posttest dinyatakan lulus bila nilainya &ge; KKM.</small>
                                </div>
                                <div class="col-md-6 form-group mb-4 d-flex align-items-center">
                                    <div class="custom-control custom-switch mt-md-3">
                                        <input type="checkbox" class="custom-control-input" id="shuffle_questions" name="shuffle_questions" value="1"
                                            {{ old('shuffle_questions', $module->shuffle_questions) ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="shuffle_questions">Acak urutan soal setiap percobaan</label>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 form-group mb-4">
                                    <label for="pretest_max_attempts" class="font-weight-600 mb-2">Batas Percobaan Pretest</label>
                                    <input type="number" id="pretest_max_attempts" name="pretest_max_attempts" class="form-control" min="1" max="255"
                                        value="{{ old('pretest_max_attempts', $module->exists ? $module->pretest_max_attempts : 1) }}" placeholder="Kosong = tanpa batas">
                                </div>
                                <div class="col-md-6 form-group mb-4">
                                    <label for="posttest_max_attempts" class="font-weight-600 mb-2">Batas Percobaan Posttest</label>
                                    <input type="number" id="posttest_max_attempts" name="posttest_max_attempts" class="form-control" min="1" max="255"
                                        value="{{ old('posttest_max_attempts', $module->posttest_max_attempts) }}" placeholder="Kosong = tanpa batas">
                                </div>
                            </div>

                            <div class="form-group mb-4">
                                <label for="divisi_ids" class="font-weight-600 mb-2">Divisi Sasaran</label>
                                <select id="divisi_ids" name="divisi_ids[]" class="form-control select2" multiple data-placeholder="Semua divisi">
                                    @foreach($divisis as $divisi)
                                        <option value="{{ $divisi->id }}" {{ $selectedDivisis->contains($divisi->id) ? 'selected' : '' }}>{{ $divisi->name }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Kosongkan untuk semua divisi.</small>
                            </div>

                            <div class="form-group mb-0">
                                <label for="role_ids" class="font-weight-600 mb-2">Jabatan Sasaran</label>
                                <select id="role_ids" name="role_ids[]" class="form-control select2" multiple data-placeholder="Semua jabatan">
                                    @foreach($roles as $role)
                                        <option value="{{ $role->id }}" {{ $selectedRoles->contains($role->id) ? 'selected' : '' }}>{{ $role->name }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Kosongkan untuk semua jabatan.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header py-3">
                            <h3 class="card-title font-weight-bold">Materi Modul</h3>
                        </div>
                        <div class="card-body p-4">
                            <p class="text-muted small mb-3">
                                Pilih dari <a href="{{ route('video.index') }}" target="_blank">Video Materi</a> dan
                                <a href="{{ route('document.index') }}" target="_blank">Dokumen</a> yang sudah diunggah.
                                Peserta wajib menyelesaikan semua materi setelah pretest sebelum posttest terbuka.
                            </p>

                            <div class="d-flex mb-3" style="gap: 8px;">
                                <div class="flex-grow-1" style="min-width: 0;">
                                    <select id="materialPicker" class="form-control" data-placeholder="Cari video atau dokumen...">
                                        <option value=""></option>
                                        <optgroup label="Video Materi">
                                            @foreach($videos as $video)
                                                <option value="video:{{ $video->id }}">{{ $video->title }}</option>
                                            @endforeach
                                        </optgroup>
                                        <optgroup label="Dokumen">
                                            @foreach($documents as $document)
                                                <option value="document:{{ $document->id }}">{{ $document->name }}</option>
                                            @endforeach
                                        </optgroup>
                                    </select>
                                </div>
                                <button type="button" class="btn btn-default" id="btnAddMaterial">Tambah</button>
                            </div>

                            <ul class="list-group" id="materialList"></ul>
                            <p class="text-muted small text-center py-4 mb-0 border rounded" id="materialEmpty">Belum ada materi dipilih.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-right mb-4">
                <a href="{{ route('learning-module.index') }}" class="btn btn-default mr-1">Batal</a>
                <button type="submit" class="btn btn-primary">
                    {{ $isEdit ? 'Simpan Perubahan' : 'Simpan & Lanjut Buat Soal' }}
                </button>
            </div>
        </form>
    </div>
</section>
@endsection

@section('footer')
<script>
$(function () {
    var labels = @json($materialLabels);
    var $list = $('#materialList');

    $('.select2').each(function () {
        $(this).select2({ width: '100%', placeholder: $(this).data('placeholder') });
    });
    $('#materialPicker').select2({ width: '100%', placeholder: $('#materialPicker').data('placeholder'), allowClear: true });

    function refresh() {
        $('#materialEmpty').toggle($list.children().length === 0);
        $list.children().each(function (index) {
            $(this).find('.material-number').text(index + 1);
        });
    }

    function addMaterial(key) {
        if (!key || !labels[key] || $list.find('input[value="' + key + '"]').length) {
            return;
        }
        var isVideo = key.indexOf('video:') === 0;
        var $row = $('<li class="list-group-item d-flex align-items-center py-2"></li>');
        $row.append('<span class="text-muted mr-2 material-number"></span>');
        $row.append('<i class="fas ' + (isVideo ? 'fa-play-circle text-danger' : 'fa-file-alt text-warning') + ' mr-2"></i>');
        $row.append($('<span class="flex-grow-1 text-truncate"></span>').text(labels[key]));
        $row.append($('<input type="hidden" name="items[]">').val(key));
        $row.append(
            '<div class="btn-group btn-group-sm ml-2">' +
                '<button type="button" class="btn btn-default btn-up" title="Naikkan"><i class="fas fa-arrow-up"></i></button>' +
                '<button type="button" class="btn btn-default btn-down" title="Turunkan"><i class="fas fa-arrow-down"></i></button>' +
                '<button type="button" class="btn btn-default text-danger btn-remove" title="Hapus"><i class="fas fa-times"></i></button>' +
            '</div>'
        );
        $list.append($row);
        refresh();
    }

    $('#btnAddMaterial').on('click', function () {
        addMaterial($('#materialPicker').val());
        $('#materialPicker').val(null).trigger('change');
    });

    $list.on('click', '.btn-up', function () {
        var $row = $(this).closest('li');
        $row.prev().before($row);
        refresh();
    }).on('click', '.btn-down', function () {
        var $row = $(this).closest('li');
        $row.next().after($row);
        refresh();
    }).on('click', '.btn-remove', function () {
        $(this).closest('li').remove();
        refresh();
    });

    @json($initialItems).forEach(addMaterial);
    refresh();
});
</script>
@endsection
