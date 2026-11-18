@extends('layout.main_template')

@php
    $old = fn ($key, $default = null) => old($key, $prefill[$key] ?? $default);
    $processes = old('proses', [['judul' => '', 'tujuan' => '', 'prioritas' => '', 'sistem' => '']]);
    $steps = ['Data pemohon', 'Dokumen', 'Observasi'];
    $chosen = (int) old('kategori', $selectedCategory);
@endphp

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Permintaan Dokumen" subtitle="Ajukan pembuatan dokumen ke tim Organization Development" eyebrow="Human Capital">
            <x-slot:actions>
                <a href="{{ route('document-request.track') }}" class="btn btn-default"><i class="fas fa-search"></i> Lacak</a>
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid" style="max-width: 860px;">
        <x-flash />

        <ol class="d-flex list-unstyled gap-2 mb-3 request-steps" aria-label="Langkah">
            @foreach ($steps as $label)
                <li class="flex-fill" data-step-item="{{ $loop->index }}">
                    <button type="button" class="btn btn-block btn-default text-left" data-step-tab="{{ $loop->index }}">
                        <span class="badge badge-secondary mr-1">{{ $loop->iteration }}</span> {{ $label }}
                    </button>
                </li>
            @endforeach
        </ol>

        <form action="{{ route('document-request.store') }}" method="POST" id="requestForm" novalidate>
            @csrf
            <input type="hidden" name="idempotency_key" id="idempotencyKey" value="{{ old('idempotency_key') }}">
            <div style="position: absolute; left: -10000px;" aria-hidden="true">
                <label for="website">Website</label>
                <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
            </div>

            {{-- Langkah 1 --}}
            <div class="card" data-step="0">
                <div class="card-header"><h3 class="card-title">Data pemohon</h3></div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="nama">Nama lengkap</label>
                            <input type="text" class="form-control @error('nama') is-invalid @enderror" id="nama" name="nama" maxlength="100" required autocomplete="name" value="{{ $old('nama') }}">
                            @error('nama')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-group col-md-6">
                            <label for="divisi">Divisi / unit kerja</label>
                            <input type="text" class="form-control @error('divisi') is-invalid @enderror" id="divisi" name="divisi" maxlength="120" required value="{{ $old('divisi') }}" placeholder="Contoh: General Affair Aset">
                            @error('divisi')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-group col-md-6">
                            <label for="departemen">Departemen</label>
                            <select class="custom-select @error('departemen') is-invalid @enderror" id="departemen" name="departemen" required>
                                <option value="">Pilih departemen</option>
                                @foreach (App\Models\DocumentRequest::DEPARTMENTS as $department)
                                    <option value="{{ $department }}" @selected($old('departemen') === $department)>{{ $department }}</option>
                                @endforeach
                            </select>
                            @error('departemen')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-group col-md-6">
                            <label for="jabatan">Jabatan</label>
                            <select class="custom-select @error('jabatan') is-invalid @enderror" id="jabatan" name="jabatan" required>
                                <option value="">Pilih jabatan</option>
                                @foreach (App\Models\DocumentRequest::JABATAN as $jabatan)
                                    <option value="{{ $jabatan }}" @selected($old('jabatan') === $jabatan)>{{ $jabatan }}</option>
                                @endforeach
                            </select>
                            @error('jabatan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-group col-md-6">
                            <label for="whatsapp">Nomor WhatsApp</label>
                            <input type="tel" class="form-control @error('whatsapp') is-invalid @enderror" id="whatsapp" name="whatsapp" maxlength="30" required inputmode="tel" autocomplete="tel" value="{{ $old('whatsapp') }}" placeholder="081234567890">
                            @error('whatsapp')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-group col-md-6">
                            <label for="email">Email perusahaan</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" maxlength="150" required autocomplete="email" value="{{ $old('email') }}">
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-group col-md-6 mb-0">
                            <label for="atasan">Atasan langsung <span class="text-muted font-weight-normal">(opsional)</span></label>
                            <input type="text" class="form-control @error('atasan') is-invalid @enderror" id="atasan" name="atasan" maxlength="100" value="{{ $old('atasan') }}">
                            @error('atasan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Langkah 2 --}}
            <div data-step="1">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Kategori dokumen</h3></div>
                    <div class="card-body">
                        @forelse ($categories as $category)
                            <div class="custom-control custom-radio {{ $loop->last ? '' : 'mb-2' }}">
                                <input type="radio" class="custom-control-input @error('kategori') is-invalid @enderror" id="kategori-{{ $category->id }}" name="kategori" value="{{ $category->id }}" required
                                    data-needs-observation="{{ $category->needs_observation ? 1 : 0 }}" @checked($chosen === $category->id)>
                                <label class="custom-control-label" for="kategori-{{ $category->id }}">
                                    <strong>{{ $category->label() }}</strong>
                                    @if ($category->code === 'IK')<a href="{{ route('document-request.example') }}" target="_blank" class="small ml-1">Lihat contoh</a>@endif
                                    @if ($category->description)<span class="d-block small text-muted">{{ $category->description }}</span>@endif
                                    <span class="d-block small text-muted">{{ implode(' → ', $category->stageNames()) }}</span>
                                </label>
                            </div>
                        @empty
                            <p class="text-muted mb-0">Belum ada kategori dokumen yang dibuka.</p>
                        @endforelse
                        @error('kategori')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div id="processList">
                    @foreach ($processes as $i => $process)
                        @include('document-request._process', ['i' => $i, 'process' => $process])
                    @endforeach
                </div>
                @error('proses')<div class="alert alert-danger">{{ $message }}</div>@enderror
                <button type="button" class="btn btn-default btn-block mb-3" id="addProcess"><i class="fas fa-plus"></i> Tambah dokumen (maks. {{ App\Models\DocumentRequest::MAX_PROCESSES }})</button>
            </div>

            {{-- Langkah 3 --}}
            <div class="card" data-step="2">
                <div class="card-header"><h3 class="card-title">Jadwal observasi</h3></div>
                <div class="card-body">
                    <p class="text-muted small">Tim OD akan mengamati langsung proses kerja. Pilih tanggal yang memungkinkan.</p>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="obs_tanggal_1">Pilihan tanggal 1</label>
                            <input type="date" class="form-control @error('obs_tanggal_1') is-invalid @enderror" id="obs_tanggal_1" name="obs_tanggal_1" min="{{ today()->toDateString() }}" value="{{ old('obs_tanggal_1') }}">
                            @error('obs_tanggal_1')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-group col-md-6">
                            <label for="obs_tanggal_2">Pilihan tanggal 2 <span class="text-muted font-weight-normal">(opsional)</span></label>
                            <input type="date" class="form-control @error('obs_tanggal_2') is-invalid @enderror" id="obs_tanggal_2" name="obs_tanggal_2" min="{{ today()->toDateString() }}" value="{{ old('obs_tanggal_2') }}">
                            @error('obs_tanggal_2')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-group col-12 mb-0">
                            <label for="obs_catatan">Preferensi jam / catatan <span class="text-muted font-weight-normal">(opsional)</span></label>
                            <input type="text" class="form-control @error('obs_catatan') is-invalid @enderror" id="obs_catatan" name="obs_catatan" maxlength="300" value="{{ old('obs_catatan') }}" placeholder="Contoh: pagi 09.00–11.00 WIB">
                            @error('obs_catatan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between mb-4">
                <button type="button" class="btn btn-default" id="prevStep"><i class="fas fa-arrow-left"></i> Kembali</button>
                <button type="button" class="btn btn-primary" id="nextStep">Lanjut <i class="fas fa-arrow-right"></i></button>
                <button type="submit" class="btn btn-primary" id="submitRequest"><i class="fas fa-paper-plane"></i> Kirim pengajuan</button>
            </div>
        </form>
    </div>
</section>

<template id="processTemplate">
    @include('document-request._process', ['i' => '__INDEX__', 'process' => []])
</template>
@endsection

@section('footer')
<script>
    (function () {
        const form = document.getElementById('requestForm');
        const steps = [...form.querySelectorAll('[data-step]')];
        const tabs = [...document.querySelectorAll('[data-step-tab]')];
        const list = document.getElementById('processList');
        const maxProcesses = {{ App\Models\DocumentRequest::MAX_PROCESSES }};
        const observationStep = 2;
        const observationDate = document.getElementById('obs_tanggal_1');
        let current = 0;

        // Langkah Observasi hanya untuk kategori yang butuh observasi.
        function needsObservation() {
            const chosen = form.querySelector('input[name="kategori"]:checked');
            return !!chosen && chosen.dataset.needsObservation === '1';
        }

        function lastStep() {
            return needsObservation() ? observationStep : observationStep - 1;
        }

        function syncObservation() {
            const needed = needsObservation();
            observationDate.required = needed;
            document.querySelector('[data-step-item="' + observationStep + '"]').hidden = !needed;
            show(current, false);
        }

        const keyInput = document.getElementById('idempotencyKey');
        if (!keyInput.value) {
            keyInput.value = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : String(Date.now()) + Math.random().toString(16).slice(2);
        }

        function show(index, scroll = true) {
            current = Math.min(index, lastStep());
            steps.forEach((step, i) => step.hidden = i !== current);
            tabs.forEach((tab, i) => {
                tab.classList.toggle('btn-primary', i === current);
                tab.classList.toggle('btn-default', i !== current);
                tab.disabled = i > current;
            });
            document.getElementById('prevStep').style.visibility = current === 0 ? 'hidden' : 'visible';
            document.getElementById('nextStep').hidden = current === lastStep();
            document.getElementById('submitRequest').hidden = current !== lastStep();
            if (scroll) { window.scrollTo({ top: 0, behavior: 'smooth' }); }
        }

        function valid(index) {
            const fields = [...steps[index].querySelectorAll('input, select, textarea')].filter(f => f.name && f.name !== 'website');
            let ok = true;
            fields.forEach(field => {
                const fieldOk = field.checkValidity();
                field.classList.toggle('is-invalid', !fieldOk);
                if (!fieldOk && ok) { field.focus(); }
                ok = ok && fieldOk;
            });
            return ok;
        }

        function renumber() {
            const cards = [...list.querySelectorAll('[data-process]')];
            cards.forEach((card, i) => {
                card.querySelector('[data-process-number]').textContent = i + 1;
                card.querySelector('[data-remove-process]').hidden = cards.length === 1;
            });
            document.getElementById('addProcess').disabled = cards.length >= maxProcesses;
        }

        document.getElementById('nextStep').addEventListener('click', () => valid(current) && show(current + 1));
        document.getElementById('prevStep').addEventListener('click', () => show(Math.max(0, current - 1)));
        tabs.forEach((tab, i) => tab.addEventListener('click', () => i < current && show(i)));

        document.getElementById('addProcess').addEventListener('click', () => {
            const index = Date.now();
            list.insertAdjacentHTML('beforeend', document.getElementById('processTemplate').innerHTML.replaceAll('__INDEX__', index));
            renumber();
        });
        form.querySelectorAll('input[name="kategori"]').forEach(radio => radio.addEventListener('change', syncObservation));
        list.addEventListener('click', event => {
            const button = event.target.closest('[data-remove-process]');
            if (button) { button.closest('[data-process]').remove(); renumber(); }
        });

        form.addEventListener('submit', event => {
            if (!valid(current)) { event.preventDefault(); return; }
            document.getElementById('submitRequest').disabled = true;
        });

        // Setelah validasi server gagal, buka langkah yang berisi kesalahan pertama.
        const firstError = form.querySelector('.is-invalid');
        renumber();
        syncObservation();
        show(firstError ? steps.indexOf(firstError.closest('[data-step]')) : 0);
    })();
</script>
@endsection
