{{-- Satu baris tahap di editor Kategori Dokumen. $key = kunci array (atau __KEY__ di template), $used = jumlah dokumen di tahap ini. --}}
<tr data-stage-row data-used="{{ $used }}">
    <td class="text-muted" style="width: 36px;"><span data-stage-number>1</span>.</td>
    <td>
        @if (! empty($stage['id']))
            <input type="hidden" name="stages[{{ $key }}][id]" value="{{ $stage['id'] }}">
        @endif
        <input type="text" class="form-control form-control-sm mb-1 @error("stages.$key.name") is-invalid @enderror" name="stages[{{ $key }}][name]" maxlength="40" required
            value="{{ $stage['name'] ?? '' }}" placeholder="Nama tahap" aria-label="Nama tahap">
        @error("stages.$key.name")<div class="invalid-feedback">{{ $message }}</div>@enderror
        <input type="text" class="form-control form-control-sm @error("stages.$key.description") is-invalid @enderror" name="stages[{{ $key }}][description]" maxlength="300"
            value="{{ $stage['description'] ?? '' }}" placeholder="Penjelasan untuk pemohon (opsional)" aria-label="Penjelasan tahap">
        @error("stages.$key.description")<div class="invalid-feedback">{{ $message }}</div>@enderror
        @if ($used)<span class="small text-muted">{{ $used }} dokumen di tahap ini</span>@endif
    </td>
    <td class="text-right text-nowrap" style="width: 120px;">
        <button type="button" class="btn btn-default btn-xs" data-move="up" title="Naik" aria-label="Naik"><i class="fas fa-arrow-up"></i></button>
        <button type="button" class="btn btn-default btn-xs" data-move="down" title="Turun" aria-label="Turun"><i class="fas fa-arrow-down"></i></button>
        <button type="button" class="btn btn-default btn-xs text-danger" data-remove title="Hapus" aria-label="Hapus"><i class="fas fa-times"></i></button>
    </td>
</tr>
