{{-- Satu kartu proses di form Permintaan Dokumen. $i = indeks (atau __INDEX__ di template), $process = nilai lama. --}}
<div class="card" data-process>
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title">Proses <span data-process-number>1</span></h3>
        <button type="button" class="btn btn-default btn-sm ml-auto text-danger" data-remove-process><i class="fas fa-times"></i> Hapus</button>
    </div>
    <div class="card-body">
        <div class="form-group">
            <label for="proses-{{ $i }}-judul">Judul proses</label>
            <input type="text" class="form-control @error("proses.$i.judul") is-invalid @enderror" id="proses-{{ $i }}-judul" name="proses[{{ $i }}][judul]" maxlength="200" required
                value="{{ $process['judul'] ?? '' }}" placeholder="Contoh: Penerimaan barang di gudang">
            @error("proses.$i.judul")<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="form-group">
            <label for="proses-{{ $i }}-tujuan">Tujuan / alasan dibuatkan IK</label>
            <textarea class="form-control @error("proses.$i.tujuan") is-invalid @enderror" id="proses-{{ $i }}-tujuan" name="proses[{{ $i }}][tujuan]" rows="3" minlength="10" maxlength="2000" required>{{ $process['tujuan'] ?? '' }}</textarea>
            @error("proses.$i.tujuan")<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="form-row">
            <div class="form-group col-md-6 mb-md-0">
                <label for="proses-{{ $i }}-prioritas">Prioritas</label>
                <select class="custom-select @error("proses.$i.prioritas") is-invalid @enderror" id="proses-{{ $i }}-prioritas" name="proses[{{ $i }}][prioritas]" required>
                    <option value="">Pilih prioritas</option>
                    @foreach (App\Models\OdDocument::PRIORITAS as $priority)
                        <option value="{{ $priority }}" @selected(($process['prioritas'] ?? '') === $priority)>{{ $priority }}</option>
                    @endforeach
                </select>
                @error("proses.$i.prioritas")<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="form-group col-md-6 mb-0">
                <label for="proses-{{ $i }}-sistem">Sistem / alat <span class="text-muted font-weight-normal">(opsional)</span></label>
                <input type="text" class="form-control @error("proses.$i.sistem") is-invalid @enderror" id="proses-{{ $i }}-sistem" name="proses[{{ $i }}][sistem]" maxlength="200"
                    value="{{ $process['sistem'] ?? '' }}" placeholder="Contoh: Accurate, Excel">
                @error("proses.$i.sistem")<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>
