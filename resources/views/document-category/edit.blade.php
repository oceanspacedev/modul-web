@extends('layout.main_template')

@php
    $stages = old('stages', $category->stages->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->all());
    $usageById = $category->stages->mapWithKeys(fn ($s) => [$s->id => $stageUsage[$s->name] ?? 0]);
@endphp

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header :title="$category->label()" subtitle="Ubah kategori dan tahapannya"
            :breadcrumbs="['Kategori Dokumen' => route('document-category.index'), $category->code => null]" />
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />

        <form action="{{ route('document-category.update', $category) }}" method="POST" id="categoryForm" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="row">
                <div class="col-lg-5">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Kategori</h3></div>
                        <div class="card-body">
                            <div class="form-row">
                                <div class="form-group col-5">
                                    <label for="code">Kode</label>
                                    <input type="text" class="form-control @error('code') is-invalid @enderror" id="code" name="code" maxlength="30" required
                                        value="{{ old('code', $category->code) }}" @readonly($inUse)>
                                    @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group col-7">
                                    <label for="name">Nama</label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" maxlength="100" required value="{{ old('name', $category->name) }}">
                                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            @if ($inUse)<p class="small text-muted mt-n2">Kode terkunci karena sudah dipakai dokumen.</p>@endif

                            <div class="form-group">
                                <label for="description">Keterangan <span class="text-muted font-weight-normal">(tampil di form)</span></label>
                                <input type="text" class="form-control @error('description') is-invalid @enderror" id="description" name="description" maxlength="300" value="{{ old('description', $category->description) }}">
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="form-group">
                                <label for="example">File contoh <span class="text-muted font-weight-normal">(maks. 20 MB)</span></label>
                                @if ($category->hasExample())
                                    <div class="d-flex align-items-center mb-2 small">
                                        <i class="far fa-file mr-1"></i>
                                        <a href="{{ route('document-request.example', $category) }}" target="_blank" class="text-truncate">{{ $category->exampleFileName() }}</a>
                                        @if ($category->hasUploadedExample())
                                            <div class="custom-control custom-checkbox ml-auto">
                                                <input type="checkbox" class="custom-control-input" id="remove_example" name="remove_example" value="1" @checked(old('remove_example'))>
                                                <label class="custom-control-label" for="remove_example">Hapus</label>
                                            </div>
                                        @else
                                            <span class="text-muted ml-auto">bawaan</span>
                                        @endif
                                    </div>
                                @endif
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input @error('example') is-invalid @enderror" id="example" name="example" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png">
                                    <label class="custom-file-label" for="example" data-browse="Pilih">{{ $category->hasExample() ? 'Ganti file...' : 'Pilih file...' }}</label>
                                    @error('example')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-8">
                                    <label for="pic_user_id">PIC bawaan</label>
                                    <select class="custom-select @error('pic_user_id') is-invalid @enderror" id="pic_user_id" name="pic_user_id">
                                        <option value="">-</option>
                                        @foreach ($team as $member)
                                            <option value="{{ $member->id }}" @selected((int) old('pic_user_id', $category->pic_user_id) === $member->id)>{{ $member->nama_lengkap }}</option>
                                        @endforeach
                                    </select>
                                    @error('pic_user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group col-4">
                                    <label for="sort">Urutan</label>
                                    <input type="number" class="form-control @error('sort') is-invalid @enderror" id="sort" name="sort" min="0" max="999" value="{{ old('sort', $category->sort) }}">
                                    @error('sort')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="custom-control custom-switch mb-2">
                                <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" @checked(old('is_active', $category->is_active))>
                                <label class="custom-control-label" for="is_active">Tampil di form permintaan</label>
                            </div>
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="needs_observation" name="needs_observation" value="1" @checked(old('needs_observation', $category->needs_observation))>
                                <label class="custom-control-label" for="needs_observation">Butuh jadwal observasi</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card">
                        <div class="card-header d-flex align-items-center">
                            <h3 class="card-title">Tahapan</h3>
                            <span class="ml-auto small text-muted">Tahap terakhir = selesai</span>
                        </div>
                        <div class="card-body p-0">
                            @foreach ($errors->get('stages') as $message)
                                <div class="alert alert-danger m-3">{{ $message }}</div>
                            @endforeach
                            <table class="table mb-0">
                                <tbody id="stageList">
                                    @foreach ($stages as $key => $stage)
                                        @include('document-category._stage', ['key' => $key, 'stage' => $stage, 'used' => $usageById[$stage['id'] ?? 0] ?? 0])
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer d-flex justify-content-between">
                            <button type="button" class="btn btn-default btn-sm" id="addStage"><i class="fas fa-plus"></i> Tambah tahap</button>
                            <div>
                                <a href="{{ route('document-category.index') }}" class="btn btn-default btn-sm">Kembali</a>
                                <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>

<template id="stageTemplate">
    @include('document-category._stage', ['key' => '__KEY__', 'stage' => [], 'used' => 0])
</template>
@endsection

@section('footer')
<script>
    (function () {
        const list = document.getElementById('stageList');
        const max = {{ App\Models\DocumentCategory::MAX_STAGES }};
        const min = {{ App\Models\DocumentCategory::MIN_STAGES }};

        function renumber() {
            const rows = [...list.querySelectorAll('[data-stage-row]')];
            rows.forEach((row, i) => {
                row.querySelector('[data-stage-number]').textContent = i + 1;
                row.querySelector('[data-move="up"]').disabled = i === 0;
                row.querySelector('[data-move="down"]').disabled = i === rows.length - 1;
                const remove = row.querySelector('[data-remove]');
                remove.disabled = row.dataset.used !== '0' || rows.length <= min;
            });
            document.getElementById('addStage').disabled = rows.length >= max;
        }

        document.getElementById('addStage').addEventListener('click', () => {
            list.insertAdjacentHTML('beforeend', document.getElementById('stageTemplate').innerHTML.replaceAll('__KEY__', 'n' + Date.now()));
            renumber();
            list.lastElementChild.querySelector('input[name$="[name]"]').focus();
        });

        list.addEventListener('click', event => {
            const button = event.target.closest('button');
            if (!button) return;
            const row = button.closest('[data-stage-row]');
            if (button.dataset.move === 'up' && row.previousElementSibling) row.parentNode.insertBefore(row, row.previousElementSibling);
            if (button.dataset.move === 'down' && row.nextElementSibling) row.parentNode.insertBefore(row.nextElementSibling, row);
            if (button.hasAttribute('data-remove')) row.remove();
            renumber();
        });

        renumber();

        const example = document.getElementById('example');
        example.addEventListener('change', () => {
            if (example.files.length) example.nextElementSibling.textContent = example.files[0].name;
        });
    })();
</script>
@endsection
