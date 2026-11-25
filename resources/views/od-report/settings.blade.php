@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Pengaturan" subtitle="Status dokumen" eyebrow="Report" />
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />

        <div class="row">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Status dokumen</h3></div>
                    <div class="card-body p-0">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Tipe</th>
                                    <th class="text-right" title="Termasuk dokumen yang sudah diarsipkan">Dipakai</th>
                                    <th class="text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($statuses as $status)
                                    <tr>
                                        <td colspan="2">
                                            <form action="{{ route('od-report.settings.statuses.update', $status) }}" method="POST" class="d-flex gap-2 m-0" id="status-form-{{ $status->id }}">
                                                @csrf
                                                @method('PUT')
                                                <input type="text" name="name" value="{{ $status->name }}" maxlength="30" required class="form-control form-control-sm" aria-label="Nama status">
                                                <select name="type" class="custom-select custom-select-sm" style="width: 120px;" aria-label="Tipe status">
                                                    @foreach (App\Models\OdDocumentStatus::TYPES as $type => $label)
                                                        <option value="{{ $type }}" @selected($status->type === $type)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        </td>
                                        <td class="text-right">{{ $status->documents_count }}</td>
                                        <td class="text-right text-nowrap">
                                            <button type="submit" form="status-form-{{ $status->id }}" class="btn btn-default btn-sm" title="Simpan"><i class="fas fa-save"></i></button>
                                            <form action="{{ route('od-report.settings.statuses.destroy', $status) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus status {{ $status->name }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-default btn-sm text-danger" title="Hapus" @disabled($status->documents_count > 0)><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer">
                        <form action="{{ route('od-report.settings.statuses.store') }}" method="POST" class="d-flex flex-wrap gap-2 m-0">
                            @csrf
                            <input type="text" name="name" maxlength="30" required placeholder="Status baru" class="form-control form-control-sm @error('name') is-invalid @enderror" style="max-width: 240px;" value="{{ old('name') }}">
                            <select name="type" class="custom-select custom-select-sm" style="width: 120px;" aria-label="Tipe status baru">
                                @foreach (App\Models\OdDocumentStatus::TYPES as $type => $label)
                                    <option value="{{ $type }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Tambah</button>
                            @error('name')<div class="text-danger small w-100">{{ $message }}</div>@enderror
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card card-body small">
                    <p class="mb-2">Jenis dokumen, tahapan dan PIC bawaan diatur di Kategori Dokumen.</p>
                    <a href="{{ route('document-category.index') }}" class="btn btn-default btn-sm align-self-start"><i class="fas fa-tags"></i> Buka Kategori Dokumen</a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title" title="Daily Report yang sudah disimpan otomatis masuk ke spreadsheet Weekly Report">Sinkron Google Sheets</h3>
                @if ($sheet['enabled'])
                    <div class="card-tools d-flex gap-2">
                        <a href="{{ $sheet['open'] }}" target="_blank" rel="noopener" class="btn btn-default btn-sm"><i class="fas fa-table"></i> Buka</a>
                        <form action="{{ route('od-report.settings.sheet-sync') }}" method="POST" class="m-0">
                            @csrf
                            <button type="submit" class="btn btn-default btn-sm"><i class="fas fa-sync-alt"></i> Sinkron Sekarang</button>
                        </form>
                    </div>
                @endif
            </div>
            <div class="card-body small">
                <form action="{{ route('od-report.settings.sheet.update') }}" method="POST" class="m-0">
                    @csrf
                    @method('PUT')
                    <div class="d-flex flex-wrap gap-2">
                        <input type="url" name="sheet_link" value="{{ old('sheet_link', $sheet['link']) }}" maxlength="300" placeholder="Link spreadsheet Weekly Report" aria-label="Link spreadsheet" title="Kosongkan kedua link untuk mematikan sinkron" class="form-control form-control-sm flex-grow-1 @error('sheet_link') is-invalid @enderror" style="flex-basis: 260px; width: auto;">
                        <input type="url" name="sheet_url" value="{{ old('sheet_url', $sheet['url']) }}" maxlength="300" placeholder="URL Web App (…/exec)" aria-label="URL Web App (Apps Script)" class="form-control form-control-sm flex-grow-1 @error('sheet_url') is-invalid @enderror" style="flex-basis: 260px; width: auto;">
                        <input type="date" name="sheet_from" value="{{ old('sheet_from', $sheet['enabled'] ? $sheet['from'] : today()->toDateString()) }}" aria-label="Kirim mulai tanggal" title="Kirim mulai tanggal. Ganti spreadsheet: atur juga tanggal ini agar pekerjaan lama tidak ikut terkirim." class="form-control form-control-sm @error('sheet_from') is-invalid @enderror" style="width: 160px;">
                        <button type="submit" class="btn btn-primary btn-sm text-nowrap"><i class="fas fa-save"></i> Simpan &amp; Tes</button>
                    </div>
                    @foreach (['sheet_link', 'sheet_url', 'sheet_from'] as $field)
                        @error($field)<div class="text-danger mt-1">{{ $message }}</div>@enderror
                    @endforeach
                </form>

                @if ($sheet['enabled'])
                    <div class="mt-2 {{ $sheet['last'] && ! $sheet['last']['ok'] ? 'text-danger' : 'text-muted' }}">
                        Menunggu dikirim: <b>{{ $sheet['pending'] }}</b>
                        @if ($sheet['last'])
                            · {{ $sheet['last']['at']->locale('id')->diffForHumans() }} · {{ $sheet['last']['message'] }}
                        @endif
                    </div>
                @endif

                <details class="mt-2">
                    <summary class="text-muted">Cara pasang Apps Script</summary>
                    <ol class="text-muted pl-3 mt-2 mb-0">
                        <li>Buka spreadsheet → <b>Ekstensi → Apps Script</b>, tempel isi <code>apps-script.gs</code>, simpan.</li>
                        <li><b>Deploy → Deployment baru → Aplikasi web</b>. Jalankan sebagai: Saya, Akses: Siapa saja.</li>
                        <li>Salin URL berakhiran <code>/exec</code> ke kolom URL Web App.</li>
                    </ol>
                </details>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Import Google Sheets</h3></div>
            <div class="card-body small">
                <div class="row">
                    @foreach (App\Services\OdSheetImport::TYPES as $type => $label)
                        @php($bag = $errors->getBag("import-{$type}"))
                        <form action="{{ route('od-report.settings.sheet-import', $type) }}" method="POST" class="col-lg-6 mb-3 mb-lg-0">
                            @csrf
                            @method('PUT')
                            <label for="import_{{ $type }}" class="mb-1">{{ $label }}</label>
                            <div class="d-flex flex-wrap gap-2">
                                <input type="url" id="import_{{ $type }}" name="link" value="{{ $bag->any() ? old('link') : $import[$type] }}" required maxlength="300" placeholder="Link Google Sheets" title="Akses sheet: siapa saja yang memiliki link" class="form-control form-control-sm flex-grow-1 @if ($bag->has('link')) is-invalid @endif" style="flex-basis: 220px; width: auto;">
                                @if ($type === App\Services\OdSheetImport::WEEKLY)
                                    <input type="month" name="from" value="{{ $bag->any() ? old('from') : $import['from'] }}" required title="Mulai bulan" aria-label="Mulai bulan" class="form-control form-control-sm @if ($bag->has('from')) is-invalid @endif" style="max-width: 150px;">
                                @endif
                                <button type="submit" class="btn btn-primary btn-sm text-nowrap"><i class="fas fa-file-import"></i> Import</button>
                            </div>
                            @foreach (['link', 'from', 'positions.*'] as $field)
                                @if ($bag->has($field))<div class="text-danger mt-1">{{ $bag->first($field) }}</div>@endif
                            @endforeach

                            @if ($type === App\Services\OdSheetImport::WEEKLY)
                                @foreach ($import['positions'] as $position => $userId)
                                    <div class="d-flex align-items-center gap-2 mt-2">
                                        <span class="text-muted text-truncate flex-shrink-0" style="width: 130px;" title="{{ $position }}">{{ str_replace('ORGANIZATION DEVELOPMENT', 'OD', $position) }}</span>
                                        <div class="flex-grow-1" style="min-width: 0;">
                                            <select name="positions[{{ $position }}]" class="custom-select custom-select-sm" style="width: 100%;" aria-label="Anggota untuk {{ $position }}">
                                                <option value="">Otomatis: {{ $import['auto'][$position]?->nama_lengkap ?? 'belum ada' }}</option>
                                                @foreach ($import['members'] as $member)
                                                    <option value="{{ $member->id }}" @selected($userId === $member->id)>{{ $member->nama_lengkap }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                @endforeach
                            @endif

                            @if ($last = $import['last'][$type])
                                <div class="mt-2 {{ $last['ok'] ? 'text-muted' : 'text-danger' }}">
                                    {{ $last['at']->locale('id')->diffForHumans() }} · {{ $last['message'] }}
                                </div>
                            @endif
                        </form>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
