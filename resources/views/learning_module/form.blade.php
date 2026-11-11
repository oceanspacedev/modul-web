@extends('layout.main_template')

@php
    $isEdit = $module->exists;
    $materialLabels = $videos->toBase()->mapWithKeys(fn ($v) => ['video:'.$v->id => 'Video: '.$v->title])
        ->merge($documents->toBase()->mapWithKeys(fn ($d) => ['document:'.$d->id => 'Dokumen: '.$d->name]));
    $initialItems = collect(old('items', $selectedItems->map->key()->all()))
        ->filter(fn ($key) => $materialLabels->has($key))
        ->values();
    $selectedDivisis = collect(old('divisi_ids', $module->exists ? $module->divisis->pluck('id')->all() : []))->map(fn ($id) => (int) $id);
    $selectedRoles = collect(old('role_ids', $module->exists ? $module->roles->pluck('id')->all() : []))->map(fn ($id) => (int) $id);
@endphp

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header :title="$isEdit ? 'Edit Modul Belajar' : 'Tambah Modul Belajar'" :subtitle="$isEdit ? $module->title : 'Atur informasi modul, sasaran peserta, dan urutan materi.'" 
            :breadcrumbs="$isEdit
                ? ['Modul Belajar' => null, 'Kelola Modul' => route('learning-module.index'), $module->title => route('learning-module.show', $module), 'Edit' => null]
                : ['Modul Belajar' => null, 'Kelola Modul' => route('learning-module.index'), 'Tambah' => null]">
            <x-slot:actions>
                <a href="{{ $isEdit ? route('learning-module.show', $module) : route('learning-module.index') }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Kembali</a>
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        @include('learning_module.partials.alerts')
        @unless($module->is_published)
            @include('learning_module.partials.setup-steps', ['page' => $isEdit ? 'edit' : 'create'])
        @endunless

        <form action="{{ $isEdit ? route('learning-module.update', $module) : route('learning-module.store') }}" method="POST" id="moduleForm">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <div class="row">
                <div class="col-lg-7 d-flex flex-column">
                    {{-- INFORMASI MODUL --}}
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Informasi Modul</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="title">Judul Modul <span class="text-danger">*</span></label>
                                <input type="text" id="title" name="title" class="form-control" value="{{ old('title', $module->title) }}"
                                    placeholder="Contoh: SOP Keselamatan Kerja Gudang" required maxlength="255">
                            </div>

                            <div class="form-group mb-0">
                                <label for="description">Deskripsi</label>
                                <textarea id="description" name="description" class="form-control" rows="3"
                                    placeholder="Tujuan dan gambaran singkat modul">{{ old('description', $module->description) }}</textarea>
                            </div>
                        </div>
                    </div>

                    {{-- MATERI MODUL --}}
                    <div class="card flex-grow-1" id="materi">
                        <div class="card-header">
                            <h3 class="card-title">Materi Modul</h3>
                        </div>
                        <div class="card-body">
                            @if($videos->isEmpty() && $documents->isEmpty())
                                <div class="alert alert-warning small d-flex flex-wrap align-items-center gap-2">
                                    <span class="mr-auto">Belum ada video atau dokumen untuk dipilih.</span>
                                    <div class="d-flex flex-wrap gap-2">
                                        @can('create', \App\Models\Video::class)
                                            <a href="{{ route('video.create') }}" target="_blank" rel="noopener" class="btn btn-default btn-sm"><i class="fas fa-video"></i> Upload Video</a>
                                        @endcan
                                        @can('create', \App\Models\Document::class)
                                            <a href="{{ route('document.create') }}" target="_blank" rel="noopener" class="btn btn-default btn-sm"><i class="fas fa-file-upload"></i> Upload Dokumen</a>
                                        @endcan
                                    </div>
                                </div>
                            @endif
                            <p class="text-muted small mb-3">
                                Pilih dari <a href="{{ route('video.index') }}" target="_blank" rel="noopener">Video Materi</a> dan
                                <a href="{{ route('document.index') }}" target="_blank" rel="noopener">Dokumen</a>. Urutan daftar = urutan belajar peserta.
                            </p>

                            <div class="mb-3">
                                <div class="min-w-0">
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
                            </div>

                            <ul class="list-group" id="materialList"></ul>
                            <div class="border rounded" id="materialEmpty">
                                <x-empty-state icon="fa-photo-video" title="Belum ada materi dipilih" text="Cari video atau dokumen di atas." class="py-4" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    {{-- ATURAN KUIS --}}
                    @include('learning_module.partials.quiz-rules-fields', ['quiz' => $module, 'card' => true, 'defaultOn' => true])

                    {{-- SASARAN PESERTA --}}
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Sasaran Peserta</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="divisi_ids">Divisi</label>
                                <select id="divisi_ids" name="divisi_ids[]" class="form-control select2" multiple data-placeholder="Semua divisi">
                                    @foreach($divisis as $divisi)
                                        <option value="{{ $divisi->id }}" {{ $selectedDivisis->contains($divisi->id) ? 'selected' : '' }}>{{ $divisi->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group mb-0">
                                <label for="role_ids">Jabatan</label>
                                <select id="role_ids" name="role_ids[]" class="form-control select2" multiple data-placeholder="Semua jabatan">
                                    @foreach($roles as $role)
                                        <option value="{{ $role->id }}" {{ $selectedRoles->contains($role->id) ? 'selected' : '' }}>{{ $role->name }}</option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">Kosongkan untuk semua divisi/jabatan.</small>
                            </div>
                        </div>
                    </div>

                    {{-- SIMPAN --}}
                    <div class="card">
                        <div class="card-body">
                            <button type="submit" class="btn btn-primary btn-block">
                                {{ $isEdit ? 'Simpan Perubahan' : 'Simpan & Lanjut' }}
                            </button>
                            <a href="{{ route('learning-module.index') }}" class="btn btn-default btn-block">Batal</a>
                        </div>
                    </div>
                </div>
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

    // Hanya <select>: kotak buatan Select2 juga ber-class "select2".
    $('select.select2').each(function () {
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
        $row.append('<i class="fas ' + (isVideo ? 'fa-play-circle' : 'fa-file-alt') + ' text-muted mr-2"></i>');
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

    // Materi yang dipilih langsung masuk daftar (items[]), jadi pasti ikut tersimpan.
    $('#materialPicker').on('select2:select', function () {
        addMaterial($(this).val());
        $(this).val(null).trigger('change');
    });
    $('#moduleForm').on('submit', function () {
        addMaterial($('#materialPicker').val());
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
