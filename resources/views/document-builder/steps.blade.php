@extends('layout.main_template')

@php
    $steps = old('steps', $template['questions']);
    $types = ['text' => 'Paragraf', 'list' => 'Poin', 'items' => 'Daftar', 'steps' => 'Langkah'];
@endphp

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header :title="'Langkah Builder · '.$category->code" subtitle="Pertanyaan yang dijawab saat menyusun dokumen, urut dari atas"
            :breadcrumbs="['Kategori Dokumen' => route('document-category.index'), $category->code => route('document-category.edit', $category), 'Langkah Builder' => null]">
            <x-slot:actions>
                @if ($category->is_active)
                    <a href="{{ route('document-builder.form', $category) }}" class="btn btn-default" target="_blank"><i class="far fa-eye"></i> Lihat Form</a>
                @else
                    {{-- Form builder menolak kategori nonaktif (404), jadi tautannya tidak boleh ditawarkan. --}}
                    <button type="button" class="btn btn-default" disabled title="Kategori ini sedang dinonaktifkan sehingga formulirnya tidak bisa dibuka. Aktifkan lagi untuk melihat formulir."><i class="far fa-eye"></i> Lihat Form</button>
                @endif
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />

        <div class="row">
            <div class="col-xl-8">
                <form action="{{ route('document-category.builder-steps.update', $category) }}" method="POST" id="stepsForm">
                    @csrf
                    @method('PUT')
                    <div class="card">
                        <div class="card-header d-flex align-items-center">
                            <h3 class="card-title" id="stepCount">{{ count($steps) }} Langkah</h3>
                            <span class="ml-auto badge {{ $template['customized'] ? 'badge-primary' : 'badge-light border' }}">{{ $template['customized'] ? 'Sudah diubah' : 'Bawaan' }}</span>
                        </div>
                        <div class="card-body p-0">
                            @foreach ($errors->get('steps') as $message)
                                <div class="alert alert-danger m-3">{{ $message }}</div>
                            @endforeach
                            <div id="stepList">
                                @foreach ($steps as $index => $step)
                                    @include('document-builder._step', ['index' => $index, 'step' => $step])
                                @endforeach
                            </div>
                            <button type="button" class="step-add" id="addStep"><i class="fas fa-plus"></i> Tambah langkah</button>
                        </div>
                        <div class="card-footer d-flex flex-wrap align-items-center gap-2">
                            <span class="small text-warning" data-dirty-note hidden><i class="fas fa-circle" style="font-size: .5rem;"></i> Belum disimpan</span>
                            <div class="ml-auto d-flex flex-wrap align-items-center gap-2">
                                @if ($template['customized'])
                                    <button type="submit" form="resetForm" class="btn btn-link btn-sm text-muted" onclick="return confirm('Kembalikan langkah ke bawaan? Perubahan sekarang dihapus.')">Kembalikan ke bawaan</button>
                                @endif
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                            </div>
                        </div>
                    </div>
                </form>
                <form action="{{ route('document-category.builder-steps.reset', $category) }}" method="POST" id="resetForm" class="d-none">
                    @csrf
                    @method('DELETE')
                </form>
            </div>

            <div class="col-xl-4">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Isi Dokumen</h3></div>
                    <div class="card-body small">
                        <p class="text-muted mb-2">Bagian yang ditulis AI dari jawaban langkah. Tetap, tidak ikut berubah.</p>
                        <ol class="pl-3 mb-0">
                            @foreach ($template['sections'] as $section)
                                <li>{{ $section['label'] }} <span class="text-muted">· {{ $types[$section['type']] }}</span></li>
                            @endforeach
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<template id="stepTemplate">
    @include('document-builder._step', ['index' => '__INDEX__', 'step' => ['type' => 'textarea']])
</template>

<style>
    .step-item { border-bottom: 1px solid var(--ring); }
    .step-summary { display: flex; align-items: flex-start; gap: .75rem; padding: .85rem 1.25rem; }
    .step-num { flex: 0 0 28px; height: 28px; border-radius: 50%; background: var(--gray-100); color: var(--gray-700); font-size: .8rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; }
    .step-text { flex: 1 1 auto; min-width: 0; }
    .step-label { font-weight: 600; display: flex; flex-wrap: wrap; align-items: center; gap: .4rem; }
    .step-label .badge { font-weight: 500; font-size: .7rem; }
    .badge-danger-soft { background: var(--danger-50); color: var(--danger-700); }
    .step-hint { font-size: .85rem; color: var(--gray-500); margin-top: 2px; }
    .step-example { font-size: .8rem; color: var(--gray-500); font-style: italic; margin-top: 1px; }
    .step-actions { display: flex; gap: .25rem; flex-shrink: 0; }
    .step-actions .btn { width: 32px; padding-left: 0; padding-right: 0; }
    .step-editor { display: none; margin: 0 1.25rem 1rem 3.7rem; padding: 1rem; background: var(--gray-100); border: 1px solid var(--ring); border-radius: var(--radius-lg); }
    .step-item.is-editing .step-editor { display: block; }
    .step-item.is-editing .step-summary { background: var(--primary-50); }
    .step-item.is-editing [data-edit] { color: var(--on-primary) !important; background: var(--primary-600) !important; border-color: var(--primary-600) !important; }
    .step-field-label { font-size: .8rem; font-weight: 600; margin-bottom: .25rem; }
    .step-add { display: block; width: 100%; padding: .75rem 1.25rem; border: 0; background: none; color: var(--primary-700); font-weight: 600; text-align: left; }
    .step-add:hover:not(:disabled) { background: var(--primary-50); }
    .step-add:disabled { color: var(--gray-400); }
    @media (max-width: 575.98px) {
        .step-summary { flex-wrap: wrap; padding: .85rem 1rem; }
        .step-actions { width: 100%; justify-content: flex-end; }
        .step-editor { margin: 0 1rem 1rem; }
    }
</style>
@endsection

@section('footer')
<script>
    (function () {
        const list = document.getElementById('stepList');
        const max = {{ App\Http\Controllers\DocumentBuilderStepController::MAX_STEPS }};
        const dirtyNote = document.querySelector('[data-dirty-note]');
        let dirty = false;

        function markDirty() {
            if (dirty) return;
            dirty = true;
            dirtyNote.hidden = false;
        }

        function renumber() {
            const rows = [...list.children];
            rows.forEach((row, i) => {
                row.querySelector('[data-row-num]').textContent = i + 1;
                row.querySelector('[data-move="up"]').disabled = i === 0;
                row.querySelector('[data-move="down"]').disabled = i === rows.length - 1;
            });
            document.getElementById('addStep').disabled = rows.length >= max;
            document.getElementById('stepCount').textContent = rows.length + ' Langkah';
        }

        // Ringkasan baris mengikuti isian di panel ubah.
        function refreshSummary(row) {
            const field = name => row.querySelector(`[data-field="${name}"]`);
            const view = name => row.querySelector(`[data-view="${name}"]`);
            const label = field('label').value.trim();
            view('label').textContent = label || 'Langkah baru';
            view('label').classList.toggle('text-muted', !label);
            view('required').hidden = !field('required').checked;
            view('type').textContent = row.querySelector('[data-field="type"]:checked')?.value === 'input' ? 'Jawaban singkat' : 'Jawaban panjang';
            ['hint', 'placeholder'].forEach(name => {
                view(name).textContent = field(name).value.trim();
                view(name).hidden = !field(name).value.trim();
            });
        }

        function edit(row, open) {
            if (open) [...list.children].forEach(other => other !== row && other.classList.remove('is-editing'));
            row.classList.toggle('is-editing', open);
            if (open) row.querySelector('[data-field="label"]').focus();
        }

        document.getElementById('addStep').addEventListener('click', () => {
            list.insertAdjacentHTML('beforeend', document.getElementById('stepTemplate').innerHTML.replaceAll('__INDEX__', 'n' + Date.now()));
            const row = list.lastElementChild;
            refreshSummary(row);
            renumber();
            edit(row, true);
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            markDirty();
        });

        list.addEventListener('click', event => {
            const button = event.target.closest('button');
            if (!button) return;
            const row = button.closest('[data-row]');

            if (button.hasAttribute('data-edit')) return edit(row, !row.classList.contains('is-editing'));
            if (button.hasAttribute('data-done')) {
                const label = row.querySelector('[data-field="label"]');
                if (!label.value.trim()) { label.reportValidity(); return; }
                return edit(row, false);
            }
            if (button.dataset.move === 'up' && row.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
            if (button.dataset.move === 'down' && row.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
            if (button.hasAttribute('data-remove') && row.dataset.title !== '1') {
                const name = row.querySelector('[data-field="label"]').value.trim();
                if (name && !confirm(`Hapus langkah "${name}"?`)) return;
                row.remove();
            }
            renumber();
            markDirty();
        });

        list.addEventListener('input', event => { refreshSummary(event.target.closest('[data-row]')); markDirty(); });
        list.addEventListener('change', event => { refreshSummary(event.target.closest('[data-row]')); markDirty(); });

        // Pertanyaan kosong di baris yang tertutup: buka barisnya supaya pesan wajib terlihat.
        document.getElementById('stepsForm').addEventListener('submit', event => {
            const empty = [...list.children].find(row => !row.querySelector('[data-field="label"]').value.trim());
            if (empty) { event.preventDefault(); edit(empty, true); empty.querySelector('[data-field="label"]').reportValidity(); return; }
            dirty = false;
        });
        window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });

        renumber();
    })();
</script>
@endsection
