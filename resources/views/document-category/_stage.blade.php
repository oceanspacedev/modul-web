{{-- Satu baris tahap di editor Kategori Dokumen. $key = kunci array (atau __KEY__ di template), $used = jumlah dokumen di tahap ini. --}}
<tr data-stage-row data-used="{{ $used }}">
    <td class="text-muted align-middle" style="width: 36px;"><span data-stage-number>1</span>.</td>
    <td class="align-middle">
        @if (! empty($stage['id']))
            <input type="hidden" name="stages[{{ $key }}][id]" value="{{ $stage['id'] }}">
        @endif
        <input type="text" class="form-control form-control-sm @error("stages.$key.name") is-invalid @enderror" name="stages[{{ $key }}][name]" maxlength="40" required
            value="{{ $stage['name'] ?? '' }}" placeholder="Judul tahap" aria-label="Judul tahap">
        @error("stages.$key.name")<div class="invalid-feedback">{{ $message }}</div>@enderror
    </td>
    <td class="text-right text-nowrap align-middle" style="width: 170px;">
        @if ($used)<span class="small text-muted mr-1" title="Dokumen di tahap ini">{{ $used }} dok</span>@endif
        <button type="button" class="btn btn-default btn-xs" data-move="up" title="Naik" aria-label="Naik"><i class="fas fa-arrow-up"></i></button>
        <button type="button" class="btn btn-default btn-xs" data-move="down" title="Turun" aria-label="Turun"><i class="fas fa-arrow-down"></i></button>
        <button type="button" class="btn btn-default btn-xs text-danger" data-remove title="Hapus" aria-label="Hapus"><i class="fas fa-times"></i></button>
    </td>
</tr>
