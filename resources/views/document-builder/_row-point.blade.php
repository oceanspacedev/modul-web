<div class="builder-row" data-row>
    <span class="builder-row-num" data-row-num></span>
    <textarea class="form-control form-control-sm flex-fill min-w-0" name="content[{{ $key }}][]" rows="1" maxlength="{{ App\Support\DocumentBuilderContent::MAX_POINT }}" aria-label="Poin" data-autosize>{{ $point }}</textarea>
    <div class="builder-row-tools flex-shrink-0">
        <button type="button" class="btn btn-default btn-xs" data-move="up" title="Naik" aria-label="Naik"><i class="fas fa-chevron-up"></i></button>
        <button type="button" class="btn btn-default btn-xs" data-move="down" title="Turun" aria-label="Turun"><i class="fas fa-chevron-down"></i></button>
    </div>
    <button type="button" class="btn btn-default btn-sm text-danger flex-shrink-0" data-remove title="Hapus" aria-label="Hapus"><i class="fas fa-trash"></i></button>
</div>
