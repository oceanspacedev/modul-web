{{-- Satu kartu dokumen di form Permintaan Dokumen. $i = indeks (atau __INDEX__ di template), $process = nilai lama. --}}
@php
    $priorityColors = ['Urgent' => '#b91c1c', 'High' => '#dc2626', 'Medium' => '#d97706', 'Low' => '#059669'];
@endphp
<div class="request-doc" data-process>
    <div class="request-doc-head">
        <span><span class="request-doc-number" data-process-number>1</span> Dokumen</span>
        <button type="button" class="btn btn-link btn-sm text-danger ml-auto p-0" data-remove-process><i class="fas fa-trash-alt"></i> Hapus</button>
    </div>
    <div class="request-doc-body">
        <div class="form-group">
            <label for="proses-{{ $i }}-judul">Judul / nama proses <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error("proses.$i.judul") is-invalid @enderror" id="proses-{{ $i }}-judul" name="proses[{{ $i }}][judul]" maxlength="200" required
                value="{{ $process['judul'] ?? '' }}" placeholder="Contoh: Penerimaan barang di gudang">
            @error("proses.$i.judul")<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="form-group">
            <label for="proses-{{ $i }}-tujuan">Tujuan / alasan dibuatkan <span class="text-danger">*</span></label>
            <textarea class="form-control @error("proses.$i.tujuan") is-invalid @enderror" id="proses-{{ $i }}-tujuan" name="proses[{{ $i }}][tujuan]" rows="3" minlength="10" maxlength="2000" required>{{ $process['tujuan'] ?? '' }}</textarea>
            @error("proses.$i.tujuan")<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
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
                @error("proses.$i.prioritas")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="form-group col-md-5 mb-0">
                <label for="proses-{{ $i }}-sistem">Sistem / alat <span class="text-muted font-weight-normal">(opsional)</span></label>
                <input type="text" class="form-control @error("proses.$i.sistem") is-invalid @enderror" id="proses-{{ $i }}-sistem" name="proses[{{ $i }}][sistem]" maxlength="200"
                    value="{{ $process['sistem'] ?? '' }}" placeholder="Contoh: Accurate, Excel">
                @error("proses.$i.sistem")<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>
