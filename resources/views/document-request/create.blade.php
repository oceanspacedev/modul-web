@extends('layout.main_template')

@php
    $old = fn ($key, $default = null) => old($key, $prefill[$key] ?? $default);
    $processes = old('proses', [['judul' => '', 'tujuan' => '', 'prioritas' => '', 'sistem' => '']]);
    $steps = ['Data pemohon', 'Dokumen', 'Observasi'];
    $chosen = (int) old('kategori', $selectedCategory);
    $templateMode = (bool) $categories->firstWhere('id', $chosen)?->hasTemplate();
    $icons = ['SOP' => 'fa-tasks', 'SE' => 'fa-envelope-open-text', 'MEMO' => 'fa-sticky-note', 'IK' => 'fa-tools', 'JD' => 'fa-user-tie', 'PL' => 'fa-bullseye', 'PLAN' => 'fa-bullseye'];
    $required = '<span class="text-danger">*</span>';
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

        <form action="{{ route('document-request.store') }}" method="POST" id="requestForm" class="card setup-wizard" enctype="multipart/form-data" novalidate>
            @csrf
            {{-- Nilai dari server: tanpa JavaScript sekalipun, kiriman ganda dari halaman ini punya kunci yang sama. --}}
            <input type="hidden" name="idempotency_key" id="idempotencyKey" value="{{ old('idempotency_key', $idempotencyKey) }}">
            <div style="position: absolute; left: -10000px;" aria-hidden="true">
                <label for="website">Website</label>
                <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="card-header">
                <ol class="request-stepper" aria-label="Langkah">
                    @foreach ($steps as $label)
                        <li data-step-item="{{ $loop->index }}">
                            <button type="button" data-step-tab="{{ $loop->index }}">
                                <span class="request-stepper-dot" data-step-dot>{{ $loop->iteration }}</span>
                                <span class="request-stepper-text">
                                    <span class="request-stepper-label">{{ $label }}</span>
                                    <small data-step-status></small>
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ol>
            </div>

            <div class="card-body">
                {{-- Langkah 1 --}}
                <div data-step="0">
                    <div class="request-section">
                        <div class="request-section-head">
                            <span class="request-section-icon"><i class="fas fa-id-card"></i></span>
                            <div>
                                <h3 class="section-title">Identitas</h3>
                                <p class="request-section-hint">Isi sesuai data karyawan. Tanda {!! $required !!} wajib diisi.</p>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="nama">Nama lengkap {!! $required !!}</label>
                                <input type="text" class="form-control @error('nama') is-invalid @enderror" id="nama" name="nama" maxlength="100" required autocomplete="name" value="{{ $old('nama') }}">
                                <div class="invalid-feedback{{ $errors->has('nama') ? ' d-block' : '' }}" data-feedback="nama">{{ $errors->first('nama') }}</div>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="divisi">Divisi / unit kerja {!! $required !!}</label>
                                <input type="text" class="form-control @error('divisi') is-invalid @enderror" id="divisi" name="divisi" maxlength="120" required value="{{ $old('divisi') }}" placeholder="Contoh: General Affair Aset">
                                <div class="invalid-feedback{{ $errors->has('divisi') ? ' d-block' : '' }}" data-feedback="divisi">{{ $errors->first('divisi') }}</div>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="departemen">Departemen {!! $required !!}</label>
                                <select class="custom-select @error('departemen') is-invalid @enderror" id="departemen" name="departemen" required>
                                    <option value="">Pilih departemen</option>
                                    @foreach (App\Models\DocumentRequest::DEPARTMENTS as $department)
                                        <option value="{{ $department }}" @selected($old('departemen') === $department)>{{ $department }}</option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback{{ $errors->has('departemen') ? ' d-block' : '' }}" data-feedback="departemen">{{ $errors->first('departemen') }}</div>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="jabatan">Jabatan {!! $required !!}</label>
                                <select class="custom-select @error('jabatan') is-invalid @enderror" id="jabatan" name="jabatan" required>
                                    <option value="">Pilih jabatan</option>
                                    @foreach (App\Models\DocumentRequest::JABATAN as $jabatan)
                                        <option value="{{ $jabatan }}" @selected($old('jabatan') === $jabatan)>{{ $jabatan }}</option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback{{ $errors->has('jabatan') ? ' d-block' : '' }}" data-feedback="jabatan">{{ $errors->first('jabatan') }}</div>
                            </div>
                            <div class="form-group col-md-6 mb-md-0">
                                <label for="badan_usaha">Badan usaha {!! $required !!}</label>
                                <select class="custom-select @error('badan_usaha') is-invalid @enderror" id="badan_usaha" name="badan_usaha" required>
                                    <option value="">Pilih badan usaha</option>
                                    @foreach ($businessUnits as $unit)
                                        <option value="{{ $unit }}" @selected($old('badan_usaha') === $unit)>{{ $unit }}</option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback{{ $errors->has('badan_usaha') ? ' d-block' : '' }}" data-feedback="badan_usaha">{{ $errors->first('badan_usaha') }}</div>
                            </div>
                            <div class="form-group col-md-6 mb-0">
                                <label for="atasan">Atasan langsung <span class="text-muted font-weight-normal">(opsional)</span></label>
                                <input type="text" class="form-control @error('atasan') is-invalid @enderror" id="atasan" name="atasan" maxlength="100" value="{{ $old('atasan') }}">
                                @error('atasan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="request-section">
                        <div class="request-section-head">
                            <span class="request-section-icon"><i class="fas fa-address-book"></i></span>
                            <div>
                                <h3 class="section-title">Kontak</h3>
                                <p class="request-section-hint">Kami menghubungi Anda lewat kontak ini.</p>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6 mb-md-0">
                                <label for="whatsapp">Nomor WhatsApp {!! $required !!}</label>
                                <input type="tel" class="form-control @error('whatsapp') is-invalid @enderror" id="whatsapp" name="whatsapp" maxlength="30" required inputmode="tel" autocomplete="tel" value="{{ $old('whatsapp') }}" placeholder="081234567890">
                                <div class="invalid-feedback{{ $errors->has('whatsapp') ? ' d-block' : '' }}" data-feedback="whatsapp">{{ $errors->first('whatsapp') }}</div>
                            </div>
                            <div class="form-group col-md-6 mb-0">
                                <label for="email">Email perusahaan {!! $required !!}</label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" maxlength="150" required autocomplete="email" value="{{ $old('email') }}">
                                <div class="invalid-feedback{{ $errors->has('email') ? ' d-block' : '' }}" data-feedback="email">{{ $errors->first('email') }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Langkah 2 --}}
                <div data-step="1">
                    <div class="request-section">
                        <div class="request-section-head">
                            <span class="request-section-icon"><i class="fas fa-folder-open"></i></span>
                            <div>
                                <h3 class="section-title">Kategori dokumen {!! $required !!}</h3>
                                <p class="request-section-hint">Pilih satu jenis dokumen.</p>
                            </div>
                        </div>
                        @if ($categories->isEmpty())
                            <p class="text-muted mb-0">Belum ada kategori dokumen yang dibuka.</p>
                        @else
                            <div class="choice-cards request-categories">
                                @foreach ($categories as $category)
                                    <label class="choice-card request-category" for="kategori-{{ $category->id }}">
                                        <input type="radio" class="@error('kategori') is-invalid @enderror" id="kategori-{{ $category->id }}" name="kategori" value="{{ $category->id }}" required
                                            data-needs-observation="{{ $category->needs_observation ? 1 : 0 }}" data-template="{{ $category->hasTemplate() ? 1 : 0 }}" @checked($chosen === $category->id)>
                                        <span class="request-category-check"><i class="fas fa-check"></i></span>
                                        <span class="request-category-icon"><i class="fas {{ $icons[strtoupper($category->code)] ?? 'fa-file-alt' }}"></i></span>
                                        <span class="choice-card-title">{{ $category->code }}</span>
                                        @if ($category->name !== $category->code)<span class="choice-card-text">{{ $category->name }}</span>@endif
                                    </label>
                                @endforeach
                            </div>
                            <div class="invalid-feedback text-danger small mt-2{{ $errors->has('kategori') ? ' d-block' : '' }}" data-feedback="kategori">{{ $errors->first('kategori') }}</div>

                            @foreach ($categories as $category)
                                @if ($category->description || $category->needs_observation || $category->hasTemplate() || $category->hasExample())
                                    <div class="request-info" data-category-info="{{ $category->id }}" hidden>
                                        <div class="request-info-title text-break"><i class="fas fa-lightbulb"></i> Apa itu {{ $category->name }}?</div>
                                        @if ($category->description)<p class="mb-0 text-break">{{ $category->description }}</p>@endif
                                        @if ($category->needs_observation)<p class="request-info-meta mb-0 mt-2">Termasuk observasi langsung oleh tim OD.</p>@endif
                                        @if ($category->hasTemplate())
                                            <div class="request-template mt-3">
                                                <strong class="text-break">Template {{ $category->name }}</strong>
                                                <p class="small text-muted mb-2">Unduh template, isi, lalu unggah kembali di kartu dokumen.</p>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <a href="{{ route('document-request.template', $category) }}" class="btn btn-default btn-sm"><i class="fas fa-download"></i> Unduh template</a>
                                                    @if ($category->hasExample())
                                                        <a href="{{ route('document-request.example', $category) }}" target="_blank" class="btn btn-default btn-sm"><i class="fas fa-eye"></i> Contoh pengisian</a>
                                                    @endif
                                                </div>
                                            </div>
                                        @elseif ($category->hasExample())
                                            <a href="{{ route('document-request.example', $category) }}" target="_blank" class="btn btn-default btn-sm mt-3"><i class="fas fa-eye"></i> Lihat contoh dokumen</a>
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        @endif
                    </div>

                    <div class="request-section">
                        <div class="request-section-head">
                            <span class="request-section-icon"><i class="fas fa-file-signature"></i></span>
                            <div>
                                <h3 class="section-title">Detail dokumen</h3>
                                <p class="request-section-hint" data-mode="normal" @if ($templateMode) hidden @endif>Satu kartu untuk setiap dokumen.</p>
                                <p class="request-section-hint" data-mode="template" @if (! $templateMode) hidden @endif>Isi lengkapnya ada di template. Satu kartu untuk setiap dokumen.</p>
                            </div>
                        </div>
                        <div id="processList">
                            @foreach ($processes as $i => $process)
                                @include('document-request._process', ['i' => $i, 'process' => $process, 'templateMode' => $templateMode])
                            @endforeach
                        </div>
                        @error('proses')<div class="alert alert-danger">{{ $message }}</div>@enderror
                        <button type="button" class="btn btn-default btn-block request-add" id="addProcess"><i class="fas fa-plus"></i> Tambah dokumen (maks. {{ App\Models\DocumentRequest::MAX_PROCESSES }})</button>
                    </div>
                </div>

                {{-- Langkah 3 --}}
                <div data-step="2">
                    <div class="request-section">
                        <div class="request-section-head">
                            <span class="request-section-icon"><i class="fas fa-calendar-alt"></i></span>
                            <div>
                                <h3 class="section-title">Jadwal observasi</h3>
                                <p class="request-section-hint">Tim OD akan mengamati langsung proses kerja. Jadwal final dikonfirmasi lewat WhatsApp atau email.</p>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="obs_tanggal_1">Pilihan tanggal 1 {!! $required !!}</label>
                                <input type="date" class="form-control @error('obs_tanggal_1') is-invalid @enderror" id="obs_tanggal_1" name="obs_tanggal_1" min="{{ today()->toDateString() }}" value="{{ old('obs_tanggal_1') }}">
                                <div class="invalid-feedback{{ $errors->has('obs_tanggal_1') ? ' d-block' : '' }}" data-feedback="obs_tanggal_1">{{ $errors->first('obs_tanggal_1') }}</div>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="obs_tanggal_2">Pilihan tanggal 2 <span class="text-muted font-weight-normal">(opsional)</span></label>
                                <input type="date" class="form-control @error('obs_tanggal_2') is-invalid @enderror" id="obs_tanggal_2" name="obs_tanggal_2" min="{{ today()->toDateString() }}" value="{{ old('obs_tanggal_2') }}">
                                <div class="invalid-feedback{{ $errors->has('obs_tanggal_2') ? ' d-block' : '' }}" data-feedback="obs_tanggal_2">{{ $errors->first('obs_tanggal_2') }}</div>
                            </div>
                            <div class="form-group col-12 mb-0">
                                <label for="obs_catatan">Preferensi jam / catatan <span class="text-muted font-weight-normal">(opsional)</span></label>
                                <input type="text" class="form-control @error('obs_catatan') is-invalid @enderror" id="obs_catatan" name="obs_catatan" maxlength="300" value="{{ old('obs_catatan') }}" placeholder="Contoh: pagi 09.00–11.00 WIB">
                                @error('obs_catatan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="request-chips mt-2">
                                    @foreach (['Pagi', 'Siang', 'Di lokasi kerja'] as $chip)
                                        <button type="button" class="btn btn-default btn-sm" data-note-chip="{{ $chip }}">{{ $chip }}</button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer d-flex flex-wrap justify-content-between gap-2">
                <button type="button" class="btn btn-default" id="prevStep"><i class="fas fa-arrow-left"></i> Kembali</button>
                <button type="button" class="btn btn-primary" id="nextStep">Lanjut <i class="fas fa-arrow-right"></i></button>
                <button type="submit" class="btn btn-primary" id="submitRequest"><i class="fas fa-paper-plane"></i> Kirim pengajuan</button>
            </div>
        </form>
    </div>
</section>

<template id="processTemplate">
    @include('document-request._process', ['i' => '__INDEX__', 'process' => [], 'templateMode' => $templateMode])
</template>
@endsection

@section('footer')
<script>
    (function () {
        const form = document.getElementById('requestForm');
        const steps = [...form.querySelectorAll('[data-step]')];
        const items = [...document.querySelectorAll('[data-step-item]')];
        const tabs = [...document.querySelectorAll('[data-step-tab]')];
        const list = document.getElementById('processList');
        const maxProcesses = {{ App\Models\DocumentRequest::MAX_PROCESSES }};
        const observationStep = 2;
        const observationDate = document.getElementById('obs_tanggal_1');
        let current = 0;

        // Tanpa JavaScript seluruh langkah tampil sekaligus (lihat .setup-wizard:not(.is-enhanced) di app.css).
        form.classList.add('is-enhanced');

        function chosenCategory() {
            return form.querySelector('input[name="kategori"]:checked');
        }

        // Langkah Observasi hanya untuk kategori yang butuh observasi.
        function needsObservation() {
            const chosen = chosenCategory();
            return !!chosen && chosen.dataset.needsObservation === '1';
        }

        function lastStep() {
            return needsObservation() ? observationStep : observationStep - 1;
        }

        // Kategori bertemplate (mis. Jobdesk): kartu dokumen berisi keterangan baru/revisi dan template terisi, bukan
        // uraian, lampiran, dan sistem. Bagian yang tidak dipakai dinonaktifkan supaya tidak divalidasi atau terkirim.
        function syncMode(root) {
            const chosen = chosenCategory();
            const template = !!chosen && chosen.dataset.template === '1';
            root.querySelectorAll('[data-mode]').forEach(part => {
                const off = part.dataset.mode !== (template ? 'template' : 'normal');
                part.hidden = off;
                if (part.tagName === 'FIELDSET') { part.disabled = off; }
            });
            root.querySelectorAll('[data-placeholder-normal]').forEach(input => {
                input.placeholder = template ? input.dataset.placeholderTemplate : input.dataset.placeholderNormal;
            });
        }

        // Lampiran: maksimal 5 file, total 10 MB, hanya Word, PDF, Excel, atau Visio.
        const lampiranTypes = @json(App\Models\OdDocumentAttachment::LAMPIRAN_EXTENSIONS);
        const maxFiles = {{ App\Models\OdDocumentAttachment::MAX_FILES }};
        const maxBytes = {{ App\Models\OdDocumentAttachment::MAX_KB * 1024 }};
        function checkFiles(input) {
            const files = [...input.files];
            let message = '';
            if (input.hasAttribute('data-lampiran')) {
                if (files.length > maxFiles) { message = 'Maksimal ' + maxFiles + ' file per dokumen.'; }
                else if (files.some(f => !lampiranTypes.includes(f.name.split('.').pop().toLowerCase()))) { message = 'Lampiran harus berupa Word, PDF, Excel, atau Visio.'; }
                else if (files.reduce((sum, f) => sum + f.size, 0) > maxBytes) { message = 'Total lampiran satu dokumen maksimal 10 MB.'; }
            } else if (files[0] && files[0].size > maxBytes) {
                message = 'Ukuran template maksimal 10 MB.';
            }
            input.setCustomValidity(message);
            mark(input, !message);
        }

        function syncCategory() {
            const chosen = chosenCategory();
            document.querySelectorAll('[data-category-info]').forEach(info => info.hidden = !chosen || info.dataset.categoryInfo !== chosen.value);
            syncMode(form);
            const needed = needsObservation();
            observationDate.required = needed;
            items[observationStep].hidden = !needed;
            show(current, false);
        }

        const keyInput = document.getElementById('idempotencyKey');
        // Kunci biasanya sudah dibuatkan server; ini cadangan kalau kolomnya kosong.
        if (!keyInput.value) {
            keyInput.value = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : String(Date.now()) + Math.random().toString(16).slice(2);
        }

        function show(index, scroll = true) {
            current = Math.min(index, lastStep());
            const total = lastStep() + 1;
            steps.forEach((step, i) => step.hidden = i !== current);
            items.forEach((item, i) => {
                item.classList.toggle('is-done', i < current);
                item.classList.toggle('is-current', i === current);
                item.querySelector('[data-step-dot]').innerHTML = i < current ? '<i class="fas fa-check"></i>' : i + 1;
                item.querySelector('[data-step-status]').textContent = i < current ? 'Selesai' : 'Langkah ' + (i + 1) + ' dari ' + total;
                tabs[i].disabled = i > current;
            });
            document.getElementById('prevStep').style.visibility = current === 0 ? 'hidden' : 'visible';
            document.getElementById('nextStep').hidden = current === lastStep();
            document.getElementById('submitRequest').hidden = current !== lastStep();
            if (scroll) { window.scrollTo({ top: 0, behavior: 'smooth' }); }
        }

        // Aturan browser (email, minlength, tanggal minimum) tidak punya pesan dari server, jadi kotaknya ([data-feedback])
        // selalu ada dan diisi di sini supaya tombol tidak diam tanpa penjelasan.
        function clientMessage(field) {
            if (field.validity.typeMismatch) { return 'Format email belum benar, contoh: nama@perusahaan.com.'; }
            if (field.validity.rangeUnderflow) { return 'Tanggal tidak boleh sebelum hari ini.'; }
            if (field.validity.tooShort) { return 'Isi minimal ' + field.minLength + ' karakter (baru ' + field.value.length + ').'; }
            if (field.validity.valueMissing) { return 'Bagian ini wajib diisi.'; }
            return field.validationMessage;
        }

        function mark(field, ok) {
            field.classList.toggle('is-invalid', !ok);
            const box = form.querySelector('[data-feedback="'+field.name+'"]');
            if (!box) { return; }
            box.textContent = ok ? '' : clientMessage(field);
            box.classList.toggle('d-block', !ok);
        }

        function valid(index) {
            const fields = [...steps[index].querySelectorAll('input, select, textarea')].filter(f => f.name && f.name !== 'website');
            let first = null;
            fields.forEach(field => {
                const fieldOk = field.checkValidity();
                mark(field, fieldOk);
                if (!fieldOk && !first) { first = field; }
            });
            if (first) {
                const select2Box = first.classList.contains('select2-hidden-accessible')
                    ? first.nextElementSibling?.querySelector('.select2-selection')
                    : null;
                (select2Box || first).focus();
            }
            return !first;
        }

        function renumber() {
            const cards = [...list.querySelectorAll('[data-process]')];
            cards.forEach((card, i) => {
                card.querySelector('[data-process-number]').textContent = i + 1;
                card.querySelector('[data-remove-process]').hidden = cards.length === 1;
            });
            document.getElementById('addProcess').disabled = cards.length >= maxProcesses;
        }

        form.addEventListener('change', event => {
            const field = event.target;
            if (field.classList && field.classList.contains('is-invalid') && field.name && !field.matches('input[type="file"]')) {
                mark(field, field.checkValidity());
            }
        });
        form.addEventListener('input', event => {
            const field = event.target;
            if (field.classList && field.classList.contains('is-invalid') && field.name && !field.matches('input[type="file"]')) {
                mark(field, field.checkValidity());
            }
        });

        document.getElementById('nextStep').addEventListener('click', () => valid(current) && show(current + 1));
        document.getElementById('prevStep').addEventListener('click', () => show(Math.max(0, current - 1)));
        tabs.forEach((tab, i) => tab.addEventListener('click', () => i < current && show(i)));

        document.getElementById('addProcess').addEventListener('click', () => {
            const index = Date.now();
            list.insertAdjacentHTML('beforeend', document.getElementById('processTemplate').innerHTML.replaceAll('__INDEX__', index));
            syncMode(list.lastElementChild);
            renumber();
        });
        form.querySelectorAll('input[name="kategori"]').forEach(radio => radio.addEventListener('change', syncCategory));
        list.addEventListener('change', event => {
            if (event.target.matches('input[type="file"]')) { checkFiles(event.target); }
        });
        list.addEventListener('click', event => {
            const button = event.target.closest('[data-remove-process]');
            if (button) { button.closest('[data-process]').remove(); renumber(); }
        });

        const note = document.getElementById('obs_catatan');
        document.querySelectorAll('[data-note-chip]').forEach(chip => chip.addEventListener('click', () => {
            note.value = (note.value ? note.value + ', ' : '') + chip.dataset.noteChip.toLowerCase();
            note.focus();
        }));

        form.addEventListener('submit', event => {
            if (!valid(current)) { event.preventDefault(); return; }
            document.getElementById('submitRequest').disabled = true;
        });

        // Setelah validasi server gagal, buka langkah yang berisi kesalahan pertama.
        const firstError = form.querySelector('.is-invalid');
        renumber();
        syncCategory();
        show(firstError ? steps.indexOf(firstError.closest('[data-step]')) : 0);
    })();
</script>
@endsection
