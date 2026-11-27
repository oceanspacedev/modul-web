{{-- Satu kartu dokumen di form Permintaan Dokumen. $i = indeks (atau __INDEX__ di template), $process = nilai lama,
     $templateMode = kategori terpilih bertemplate (mis. Jobdesk). Bagian [data-mode] yang tidak dipakai disembunyikan dan
     dinonaktifkan supaya tidak ikut terkirim; JavaScript form menyesuaikannya saat kategori diganti. --}}
@php
    // Titik prioritas: isian status yang sama di tema terang dan gelap (kontras >= 3:1 di kedua permukaan).
    $priorityColors = ['Urgent' => 'var(--danger-solid)', 'High' => '#ea580c', 'Medium' => '#ca8a04', 'Low' => 'var(--success-solid)'];
    $templateMode ??= false;
    $lampiranError = $errors->first("proses.$i.lampiran*");
    $templateError = $errors->has("proses.$i.template");
@endphp
<div class="request-doc" data-process>
    <div class="request-doc-head gap-2">
        <span><span class="request-doc-number" data-process-number>1</span> Dokumen</span>
        <button type="button" class="btn btn-link btn-sm text-danger ml-auto p-0 flex-shrink-0" data-remove-process><i class="fas fa-trash-alt"></i> Hapus</button>
    </div>
    <div class="request-doc-body">
        <div class="form-group">
            <label for="proses-{{ $i }}-judul">
                <span data-mode="normal" @if ($templateMode) hidden @endif>Judul / nama proses</span><span data-mode="template" @if (! $templateMode) hidden @endif>Judul yang diajukan</span>
                <span class="text-danger">*</span>
            </label>
            <input type="text" class="form-control @error("proses.$i.judul") is-invalid @enderror" id="proses-{{ $i }}-judul" name="proses[{{ $i }}][judul]" maxlength="200" required
                value="{{ $process['judul'] ?? '' }}" placeholder="{{ $templateMode ? 'Contoh: Admin Gudang' : 'Contoh: Penerimaan barang di gudang' }}"
                data-placeholder-normal="Contoh: Penerimaan barang di gudang" data-placeholder-template="Contoh: Admin Gudang">
            <div class="invalid-feedback{{ $errors->has("proses.$i.judul") ? ' d-block' : '' }}" data-feedback="proses[{{ $i }}][judul]">{{ $errors->first("proses.$i.judul") }}</div>
        </div>

        <fieldset data-mode="normal" @if ($templateMode) hidden disabled @endif>
            <div class="form-group">
                <label for="proses-{{ $i }}-tujuan">Uraian <span class="text-danger">*</span></label>
                <textarea class="form-control @error("proses.$i.tujuan") is-invalid @enderror" id="proses-{{ $i }}-tujuan" name="proses[{{ $i }}][tujuan]" rows="3" minlength="10" maxlength="2000" required>{{ $process['tujuan'] ?? '' }}</textarea>
                <div class="invalid-feedback{{ $errors->has("proses.$i.tujuan") ? ' d-block' : '' }}" data-feedback="proses[{{ $i }}][tujuan]">{{ $errors->first("proses.$i.tujuan") }}</div>
            </div>
            <div class="form-group">
                <label for="proses-{{ $i }}-lampiran">Lampiran <span class="text-muted font-weight-normal">(opsional)</span></label>
                {{-- Pesan error memakai kotak [data-feedback] di bawah (diisi juga oleh JavaScript form), jadi :error-key="false". --}}
                <x-file-dropzone name="proses[{{ $i }}][lampiran][]" id="proses-{{ $i }}-lampiran" multiple compact
                    :extensions="App\Models\OdDocumentAttachment::LAMPIRAN_EXTENSIONS" :max-files="App\Models\OdDocumentAttachment::MAX_FILES"
                    :max-mb="intdiv(App\Models\OdDocumentAttachment::MAX_KB, 1024)" types="Word, PDF, Excel, atau Visio"
                    :error-key="false" :class="$lampiranError ? 'is-invalid' : ''" data-lampiran />
                <small class="form-text text-muted">Total maks. {{ intdiv(App\Models\OdDocumentAttachment::MAX_KB, 1024) }} MB.</small>
                <div class="invalid-feedback{{ $lampiranError ? ' d-block' : '' }}" data-feedback="proses[{{ $i }}][lampiran][]">{{ $lampiranError }}</div>
            </div>
        </fieldset>

        <fieldset data-mode="template" @if (! $templateMode) hidden disabled @endif>
            <div class="form-group">
                <label id="proses-{{ $i }}-jenis">Keterangan <span class="text-danger">*</span></label>
                <div class="request-seg" role="radiogroup" aria-labelledby="proses-{{ $i }}-jenis">
                    @foreach (App\Models\OdDocument::JENIS_PENGAJUAN as $jenis)
                        <label>
                            <input type="radio" class="@error("proses.$i.jenis_pengajuan") is-invalid @enderror" name="proses[{{ $i }}][jenis_pengajuan]" value="{{ $jenis }}" required @checked(($process['jenis_pengajuan'] ?? '') === $jenis)>
                            {{ $jenis }}
                        </label>
                    @endforeach
                </div>
                <div class="invalid-feedback{{ $errors->has("proses.$i.jenis_pengajuan") ? ' d-block' : '' }}" data-feedback="proses[{{ $i }}][jenis_pengajuan]">{{ $errors->first("proses.$i.jenis_pengajuan") }}</div>
            </div>
            <div class="form-group">
                <label for="proses-{{ $i }}-template">Template terisi <span class="text-danger">*</span></label>
                <x-file-dropzone name="proses[{{ $i }}][template]" id="proses-{{ $i }}-template" compact required
                    :extensions="App\Models\OdDocumentAttachment::TEMPLATE_EXTENSIONS" :max-mb="intdiv(App\Models\OdDocumentAttachment::MAX_KB, 1024)"
                    types="Word, Excel, atau PDF" :error-key="false" :class="$templateError ? 'is-invalid' : ''" data-template-file />
                <div class="invalid-feedback{{ $errors->has("proses.$i.template") ? ' d-block' : '' }}" data-feedback="proses[{{ $i }}][template]">{{ $errors->first("proses.$i.template") }}</div>
            </div>
        </fieldset>

        <div class="form-row">
            <div class="form-group col-md-7 mb-md-0">
                <label id="proses-{{ $i }}-prioritas">Prioritas <span class="text-danger">*</span></label>
                <div class="request-seg" role="radiogroup" aria-labelledby="proses-{{ $i }}-prioritas">
                    @foreach (App\Models\OdDocument::PRIORITAS as $priority)
                        <label>
                            <input type="radio" class="@error("proses.$i.prioritas") is-invalid @enderror" name="proses[{{ $i }}][prioritas]" value="{{ $priority }}" required @checked(($process['prioritas'] ?? '') === $priority)>
                            <span class="request-seg-dot" style="background: {{ $priorityColors[$priority] ?? 'var(--gray-400)' }}"></span>{{ $priority }}
                        </label>
                    @endforeach
                </div>
                <div class="invalid-feedback{{ $errors->has("proses.$i.prioritas") ? ' d-block' : '' }}" data-feedback="proses[{{ $i }}][prioritas]">{{ $errors->first("proses.$i.prioritas") }}</div>
            </div>
            <fieldset class="form-group col-md-5 mb-0" data-mode="normal" @if ($templateMode) hidden disabled @endif>
                <label for="proses-{{ $i }}-sistem">Sistem / alat <span class="text-muted font-weight-normal">(opsional)</span></label>
                <input type="text" class="form-control @error("proses.$i.sistem") is-invalid @enderror" id="proses-{{ $i }}-sistem" name="proses[{{ $i }}][sistem]" maxlength="200"
                    value="{{ $process['sistem'] ?? '' }}" placeholder="Contoh: Accurate, Excel">
                @error("proses.$i.sistem")<div class="invalid-feedback">{{ $message }}</div>@enderror
            </fieldset>
        </div>
    </div>
</div>
