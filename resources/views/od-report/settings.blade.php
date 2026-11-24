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
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Google Sheets</h3></div>
                    <div class="card-body small">
                        <p class="mb-2">Daily Report yang sudah disimpan otomatis masuk ke spreadsheet Weekly Report.</p>

                        @if ($sheet['enabled'])
                            <div class="mb-1">Menunggu dikirim: <b>{{ $sheet['pending'] }}</b></div>
                            @if ($sheet['last'])
                                <div class="mb-2 {{ $sheet['last']['ok'] ? 'text-success' : 'text-danger' }}">
                                    {{ $sheet['last']['at']->locale('id')->diffForHumans() }}: {{ $sheet['last']['message'] }}
                                </div>
                            @endif
                            <div class="d-flex gap-2 mb-3">
                                <a href="{{ $sheet['open'] }}" target="_blank" rel="noopener" class="btn btn-default btn-sm"><i class="fas fa-table"></i> Buka Spreadsheet</a>
                                <form action="{{ route('od-report.settings.sheet-sync') }}" method="POST" class="m-0">
                                    @csrf
                                    <button type="submit" class="btn btn-default btn-sm"><i class="fas fa-sync-alt"></i> Sinkron Sekarang</button>
                                </form>
                            </div>
                        @endif

                        <form action="{{ route('od-report.settings.sheet.update') }}" method="POST" class="m-0">
                            @csrf
                            @method('PUT')
                            <div class="form-group mb-2">
                                <label for="sheet_link" class="mb-1">Link spreadsheet</label>
                                <input type="url" id="sheet_link" name="sheet_link" value="{{ old('sheet_link', $sheet['link']) }}" maxlength="300" placeholder="https://docs.google.com/spreadsheets/d/.../edit" class="form-control form-control-sm @error('sheet_link') is-invalid @enderror">
                                @error('sheet_link')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group mb-2">
                                <label for="sheet_url" class="mb-1">URL Web App (Apps Script)</label>
                                <input type="url" id="sheet_url" name="sheet_url" value="{{ old('sheet_url', $sheet['url']) }}" maxlength="300" placeholder="https://script.google.com/macros/s/.../exec" class="form-control form-control-sm @error('sheet_url') is-invalid @enderror">
                                @error('sheet_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group mb-2">
                                <label for="sheet_from" class="mb-1">Kirim mulai tanggal</label>
                                <input type="date" id="sheet_from" name="sheet_from" value="{{ old('sheet_from', $sheet['enabled'] ? $sheet['from'] : today()->toDateString()) }}" class="form-control form-control-sm @error('sheet_from') is-invalid @enderror" style="max-width: 180px;">
                                @error('sheet_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <p class="text-muted mb-2">Ganti spreadsheet: atur juga tanggal mulai agar pekerjaan lama tidak ikut terkirim. Kosongkan kedua link untuk mematikan.</p>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Simpan &amp; Tes Koneksi</button>
                        </form>

                        <details class="mt-3">
                            <summary>Cara pasang Apps Script</summary>
                            <ol class="text-muted pl-3 mt-2 mb-0">
                                <li>Buka spreadsheet → <b>Ekstensi → Apps Script</b>, tempel isi <code>apps-script.gs</code>, simpan.</li>
                                <li><b>Deploy → Deployment baru → Aplikasi web</b>. Jalankan sebagai: Saya, Akses: Siapa saja.</li>
                                <li>Salin URL berakhiran <code>/exec</code> ke kolom di atas.</li>
                            </ol>
                            <p class="text-muted mt-2 mb-0">Web App yang sama bisa dipakai untuk spreadsheet baru, asalkan pemilik Apps Script punya akses edit ke spreadsheet itu.</p>
                        </details>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h3 class="card-title">Import dari Google Sheets</h3></div>
                    <div class="card-body small">
                        <p class="text-muted mb-2">Akses sheet: "Siapa saja yang memiliki link". Aman diulang, baris yang sama diperbarui.</p>

                        @foreach (App\Services\OdSheetImport::TYPES as $type => $label)
                            @php($bag = $errors->getBag("import-{$type}"))
                            <form action="{{ route('od-report.settings.sheet-import', $type) }}" method="POST" class="{{ $loop->last ? 'm-0' : 'mb-3 pb-3 border-bottom' }}">
                                @csrf
                                @method('PUT')
                                <label for="import_{{ $type }}" class="mb-1">{{ $label }}</label>
                                <input type="url" id="import_{{ $type }}" name="link" value="{{ $bag->any() ? old('link') : $import[$type] }}" required maxlength="300" placeholder="https://docs.google.com/spreadsheets/d/.../edit" class="form-control form-control-sm @if ($bag->has('link')) is-invalid @endif">
                                @if ($bag->has('link'))<div class="invalid-feedback">{{ $bag->first('link') }}</div>@endif

                                @if ($type === App\Services\OdSheetImport::WEEKLY && $import['positions'])
                                    <table class="table table-sm table-borderless mt-2 mb-0">
                                        @foreach ($import['positions'] as $position => $userId)
                                            <tr>
                                                <td class="align-middle pl-0">{{ $position }}</td>
                                                <td class="pr-0" style="width: 45%;">
                                                    <select name="positions[{{ $position }}]" class="custom-select custom-select-sm" aria-label="Anggota untuk {{ $position }}">
                                                        <option value="">Lewati</option>
                                                        @foreach ($import['members'] as $member)
                                                            <option value="{{ $member->id }}" @selected($userId === $member->id)>{{ $member->nama_lengkap }}</option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </table>
                                @endif

                                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                                    @if ($type === App\Services\OdSheetImport::WEEKLY)
                                        <label for="import_from" class="mb-0">Mulai</label>
                                        <input type="month" id="import_from" name="from" value="{{ $bag->any() ? old('from') : $import['from'] }}" required class="form-control form-control-sm @if ($bag->has('from')) is-invalid @endif" style="max-width: 160px;">
                                    @endif
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-file-import"></i> Import</button>
                                </div>
                                @foreach (['from', 'positions.*'] as $field)
                                    @if ($bag->has($field))<div class="text-danger small mt-1">{{ $bag->first($field) }}</div>@endif
                                @endforeach

                                @if ($last = $import['last'][$type])
                                    <div class="mt-2 {{ $last['ok'] ? 'text-success' : 'text-danger' }}">
                                        {{ $last['at']->locale('id')->diffForHumans() }}: {{ $last['message'] }}
                                    </div>
                                @endif
                            </form>
                        @endforeach
                    </div>
                </div>

                <div class="card card-body small">
                    <p class="mb-2">Jenis dokumen, tahapan dan PIC bawaan diatur di Kategori Dokumen.</p>
                    <a href="{{ route('document-category.index') }}" class="btn btn-default btn-sm align-self-start"><i class="fas fa-tags"></i> Buka Kategori Dokumen</a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
