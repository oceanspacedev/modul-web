<div class="builder-row" data-row>
    <span class="builder-row-num" data-row-num></span>
    <div class="flex-fill min-w-0">
        <input type="text" class="form-control form-control-sm font-weight-bold mb-1" name="content[{{ $key }}][{{ $index }}][title]" value="{{ $item['title'] ?? '' }}"
            maxlength="{{ App\Support\DocumentBuilderContent::MAX_TITLE }}" placeholder="{{ $labels[0] }}" aria-label="{{ $labels[0] }}">
        <textarea class="form-control form-control-sm" name="content[{{ $key }}][{{ $index }}][text]" rows="2" maxlength="{{ App\Support\DocumentBuilderContent::MAX_POINT * 3 }}"
            placeholder="{{ $labels[1] }}" aria-label="{{ $labels[1] }}" data-autosize>{{ $item['text'] ?? '' }}</textarea>
    </div>
    <div class="builder-row-tools flex-shrink-0">
        <button type="button" class="btn btn-default btn-xs" data-move="up" title="Naik" aria-label="Naik"><i class="fas fa-chevron-up"></i></button>
        <button type="button" class="btn btn-default btn-xs" data-move="down" title="Turun" aria-label="Turun"><i class="fas fa-chevron-down"></i></button>
    </div>
    <button type="button" class="btn btn-default btn-sm text-danger flex-shrink-0" data-remove title="Hapus" aria-label="Hapus"><i class="fas fa-trash"></i></button>
</div>
