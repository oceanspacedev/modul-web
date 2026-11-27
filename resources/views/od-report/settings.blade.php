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

        <div class="card">
            <div class="card-header"><h3 class="card-title">Status dokumen</h3></div>
            <div class="card-body table-responsive p-0">
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
                                        <input type="text" name="name" value="{{ $status->name }}" maxlength="30" required class="form-control form-control-sm" style="min-width: 140px;" aria-label="Nama status">
                                        <select name="type" class="custom-select custom-select-sm form-control-sm" style="width: 120px;" aria-label="Tipe status">
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
                    <input type="text" name="name" maxlength="30" required placeholder="Status baru" class="form-control form-control-sm @error('name') is-invalid @enderror" style="flex: 1 1 180px; max-width: 240px;" value="{{ old('name') }}">
                    <select name="type" class="custom-select custom-select-sm form-control-sm" style="width: 120px;" aria-label="Tipe status baru">
                        @foreach (App\Models\OdDocumentStatus::TYPES as $type => $label)
                            <option value="{{ $type }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Tambah</button>
                    @error('name')<div class="text-danger small w-100">{{ $message }}</div>@enderror
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title" title="Daily Report yang sudah disimpan otomatis masuk ke spreadsheet Weekly Report">Sinkron Google Sheets</h3>
                @if ($sheet['enabled'])
                    <div class="card-tools d-flex flex-wrap gap-2">
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
                        <input type="url" name="sheet_link" value="{{ old('sheet_link', $sheet['link']) }}" maxlength="300" placeholder="Link spreadsheet Weekly Report" aria-label="Link spreadsheet" title="Kosongkan kedua link untuk mematikan sinkron" class="form-control form-control-sm flex-grow-1 @error('sheet_link') is-invalid @enderror" style="flex-basis: 260px; width: auto; min-width: 0; max-width: 100%;">
                        <input type="url" name="sheet_url" value="{{ old('sheet_url', $sheet['url']) }}" maxlength="300" placeholder="URL Web App (…/exec)" aria-label="URL Web App (Apps Script)" class="form-control form-control-sm flex-grow-1 @error('sheet_url') is-invalid @enderror" style="flex-basis: 260px; width: auto; min-width: 0; max-width: 100%;">
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

                <details class="apps-guide mt-3" @unless ($sheet['enabled']) open @endunless>
                    <summary>Cara pasang Apps Script</summary>

                    <ol class="apps-steps">
                        <li class="apps-step">
                            <span class="apps-step-num" aria-hidden="true">1</span>
                            <div class="apps-step-body">
                                <h4 class="apps-step-title">Buka editor Apps Script</h4>
                                <p>Buka spreadsheet Weekly Report, lalu pilih menu <span class="apps-path">Ekstensi › Apps Script</span>.</p>
                            </div>
                        </li>
                        <li class="apps-step">
                            <span class="apps-step-num" aria-hidden="true">2</span>
                            <div class="apps-step-body">
                                <h4 class="apps-step-title">Tempel kode</h4>
                                <p>Hapus semua isi <code>Code.gs</code>, tempel kode di bawah, lalu klik <b>Simpan</b> sebelum Deploy.</p>
                                <div class="apps-script">
                                    <div class="apps-script-bar">
                                        <span class="text-muted"><i class="fas fa-code mr-1"></i>Code.gs</span>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-primary btn-sm" data-copy-target="appsScriptCode">
                                                <i class="far fa-copy"></i> <span>Salin kode</span>
                                            </button>
                                            <a href="{{ route('od-report.settings.apps-script') }}" class="btn btn-default btn-sm"><i class="fas fa-download"></i> Unduh</a>
                                        </div>
                                    </div>
                                    <pre class="apps-script-code" id="appsScriptCode" tabindex="0"><code>{{ $sheet['script'] }}</code></pre>
                                </div>
                            </div>
                        </li>
                        <li class="apps-step">
                            <span class="apps-step-num" aria-hidden="true">3</span>
                            <div class="apps-step-body">
                                <h4 class="apps-step-title">Deploy sebagai aplikasi web</h4>
                                <p>Klik <span class="apps-path">Deploy › Deployment baru</span>, pilih jenis <b>Aplikasi web</b>, lalu atur:</p>
                                <dl class="apps-settings">
                                    <dt>Jalankan sebagai</dt><dd>Saya</dd>
                                    <dt>Yang memiliki akses</dt><dd>Siapa saja</dd>
                                </dl>
                                <p>Klik <b>Deploy</b>, lalu izinkan akses. Muncul "Google belum memverifikasi aplikasi ini"? Klik <b>Lanjutan › Buka (tidak aman)</b>.</p>
                            </div>
                        </li>
                        <li class="apps-step">
                            <span class="apps-step-num" aria-hidden="true">4</span>
                            <div class="apps-step-body">
                                <h4 class="apps-step-title">Hubungkan ke Modul</h4>
                                <p>Salin <b>URL Web App</b> (berakhiran <code>/exec</code>). Di kolom atas, isi link spreadsheet, URL Web App, dan tanggal mulai kirim, lalu klik <b>Simpan &amp; Tes</b>.</p>
                            </div>
                        </li>
                    </ol>

                    <div class="apps-notes">
                        <div class="apps-notes-title"><i class="fas fa-info-circle mr-1"></i>Perlu diketahui</div>
                        <ul>
                            <li><b>Kode diperbarui:</b> tempel kode baru, lalu <span class="apps-path">Deploy › Kelola deployment › Edit › Versi baru</span>. Tanpa versi baru, Web App tetap menjalankan kode lama. URL-nya tetap sama.</li>
                            <li><b>Ganti spreadsheet:</b> cukup ganti link di kolom atas, tidak perlu pasang ulang. Akun yang memasang skrip harus bisa mengedit spreadsheet baru, dan spreadsheet tempat skrip ditempel jangan dihapus.</li>
                            <li><b>Baris dari Modul:</b> ditandai kolom H "SYNC ID" (disembunyikan). Edit memperbarui baris itu, hapus mengosongkannya. Baris yang diketik manual tidak diubah.</li>
                            <li><b>Baris &amp; tab baru:</b> pekerjaan mengisi baris kosong pertama di blok POSISI + WEEK; kalau penuh, satu baris ditambah. Tab bulan baru dibuat otomatis dari tab bulan terakhir.</li>
                            <li><b>Keamanan:</b> siapa pun yang tahu URL Web App bisa mengirim baris ke spreadsheet, jadi jangan disebar.</li>
                        </ul>
                    </div>
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
                                <input type="url" id="import_{{ $type }}" name="link" value="{{ $bag->any() ? old('link') : $import[$type] }}" required maxlength="300" placeholder="Link Google Sheets" title="Akses sheet: siapa saja yang memiliki link" class="form-control form-control-sm flex-grow-1 @if ($bag->has('link')) is-invalid @endif" style="flex-basis: 220px; width: auto; min-width: 0; max-width: 100%;">
                                @if ($type === App\Services\OdSheetImport::WEEKLY)
                                    <input type="month" name="from" value="{{ $bag->any() ? old('from') : $import['from'] }}" required title="Mulai bulan" aria-label="Mulai bulan" class="form-control form-control-sm @if ($bag->has('from')) is-invalid @endif" style="max-width: 150px;">
                                @endif
                                <button type="submit" class="btn btn-primary btn-sm text-nowrap"><i class="fas fa-file-import"></i> Import</button>
                            </div>
                            @foreach (['link', 'from', 'positions.*'] as $field)
                                @if ($bag->has($field))<div class="text-danger mt-1">{{ $bag->first($field) }}</div>@endif
                            @endforeach

                            @if ($type === App\Services\OdSheetImport::WEEKLY && $import['positions'])
                                @php($unmatched = collect($import['positions'])->filter(fn ($userId, $position) => ! $userId && ! $import['auto'][$position])->count())
                                <details class="mt-2" @if ($bag->has('positions.*')) open @endif>
                                    <summary class="text-muted">Atur posisi @if ($unmatched)<span class="text-danger">· {{ $unmatched }} belum ada orangnya</span>@endif</summary>
                                @foreach ($import['positions'] as $position => $userId)
                                    <div class="form-row align-items-center mt-2">
                                        <div class="col-sm-4 text-muted text-truncate mb-1 mb-sm-0" title="{{ $position }}">{{ str_replace('ORGANIZATION DEVELOPMENT', 'OD', $position) }}</div>
                                        <div class="col-sm-8" style="min-width: 0;">
                                            <select name="positions[{{ $position }}]" class="custom-select custom-select-sm form-control-sm" style="width: 100%;" aria-label="Anggota untuk {{ $position }}">
                                                <option value="">Otomatis: {{ $import['auto'][$position]?->nama_lengkap ?? 'belum ada' }}</option>
                                                @foreach ($import['members'] as $member)
                                                    <option value="{{ $member->id }}" @selected($userId === $member->id)>{{ $member->nama_lengkap }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                @endforeach
                                </details>
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

@section('footer')
<script>
    // Tombol "Salin kode": salin isi Apps Script ke clipboard, dengan cadangan seleksi teks bila Clipboard API tidak ada.
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-copy-target]');
        if (!button) return;
        var source = document.getElementById(button.dataset.copyTarget);
        var label = button.querySelector('span');
        var text = source.textContent;

        function done(ok) {
            label.textContent = ok ? 'Tersalin' : 'Gagal, salin manual';
            setTimeout(function () { label.textContent = 'Salin kode'; }, 2000);
        }

        function fallback() {
            var range = document.createRange();
            range.selectNodeContents(source);
            var selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
            var ok = false;
            try { ok = document.execCommand('copy'); } catch (e) {}
            done(ok);
        }

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function () { done(true); }, fallback);
        } else {
            fallback();
        }
    });
</script>
@endsection
