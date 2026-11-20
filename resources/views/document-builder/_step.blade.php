{{--
    Satu langkah di pengaturan Langkah Builder: ringkasan yang mudah dibaca, dan panel ubah yang terbuka saat tombol
    pensil diklik. $index = kunci array (atau __INDEX__ di template).
--}}
@php
    $isTitle = ($step['key'] ?? '') === 'title';
    $type = $step['type'] ?? 'textarea';
    $hasError = $errors->has("steps.$index.*");
@endphp
<div class="step-item {{ $hasError ? 'is-editing' : '' }}" data-row data-title="{{ $isTitle ? 1 : 0 }}">
    <div class="step-summary">
        <span class="step-num" data-row-num>1</span>
        <div class="step-text">
            <div class="step-label">
                <span data-view="label">{{ $step['label'] ?? '' }}</span>
                <span class="badge badge-danger-soft" data-view="required" @unless ($isTitle || ! empty($step['required'])) hidden @endunless>Wajib</span>
                <span class="badge badge-light border" data-view="type">{{ $type === 'input' ? 'Jawaban singkat' : 'Jawaban panjang' }}</span>
            </div>
            <div class="step-hint" data-view="hint" @unless (filled($step['hint'] ?? null)) hidden @endunless>{{ $step['hint'] ?? '' }}</div>
            <div class="step-example" data-view="placeholder" @unless (filled($step['placeholder'] ?? null)) hidden @endunless>{{ $step['placeholder'] ?? '' }}</div>
        </div>
        <div class="step-actions">
            <button type="button" class="btn btn-default btn-sm" data-move="up" title="Pindah ke atas" aria-label="Pindah ke atas"><i class="fas fa-arrow-up"></i></button>
            <button type="button" class="btn btn-default btn-sm" data-move="down" title="Pindah ke bawah" aria-label="Pindah ke bawah"><i class="fas fa-arrow-down"></i></button>
            <button type="button" class="btn btn-default btn-sm" data-edit title="Ubah" aria-label="Ubah"><i class="fas fa-pen"></i></button>
            <button type="button" class="btn btn-default btn-sm text-danger" data-remove @disabled($isTitle) title="{{ $isTitle ? 'Langkah judul wajib ada' : 'Hapus' }}" aria-label="Hapus"><i class="fas fa-trash"></i></button>
        </div>
    </div>

    <div class="step-editor">
        <input type="hidden" name="steps[{{ $index }}][key]" value="{{ $step['key'] ?? '' }}">

        <div class="form-group">
            <label class="step-field-label" for="label_{{ $index }}">Pertanyaan</label>
            <input type="text" class="form-control @error("steps.$index.label") is-invalid @enderror" id="label_{{ $index }}" name="steps[{{ $index }}][label]"
                value="{{ $step['label'] ?? '' }}" maxlength="100" required placeholder="Mis. Lokasi Penerapan" data-field="label">
            @error("steps.$index.label")<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="form-row">
            <div class="form-group col-md-6">
                <label class="step-field-label" for="hint_{{ $index }}">Keterangan <span class="text-muted">(opsional)</span></label>
                <input type="text" class="form-control" id="hint_{{ $index }}" name="steps[{{ $index }}][hint]" value="{{ $step['hint'] ?? '' }}" maxlength="150"
                    placeholder="Tampil kecil di bawah pertanyaan" data-field="hint">
            </div>
            <div class="form-group col-md-6">
                <label class="step-field-label" for="placeholder_{{ $index }}">Contoh jawaban <span class="text-muted">(opsional)</span></label>
                <input type="text" class="form-control" id="placeholder_{{ $index }}" name="steps[{{ $index }}][placeholder]" value="{{ $step['placeholder'] ?? '' }}" maxlength="200"
                    placeholder="Tampil samar di kotak jawaban" data-field="placeholder">
            </div>
        </div>

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <span class="step-field-label mb-0">Jawaban</span>
                @foreach (['input' => 'Singkat (1 baris)', 'textarea' => 'Panjang (beberapa baris)'] as $value => $label)
                    <div class="custom-control custom-radio custom-control-inline mr-0">
                        <input type="radio" class="custom-control-input" id="type_{{ $value }}_{{ $index }}" name="steps[{{ $index }}][type]" value="{{ $value }}"
                            @checked($type === $value) data-field="type">
                        <label class="custom-control-label" for="type_{{ $value }}_{{ $index }}">{{ $label }}</label>
                    </div>
                @endforeach
                <div class="custom-control custom-switch">
                    @if ($isTitle)
                        <input type="hidden" name="steps[{{ $index }}][required]" value="1">
                    @endif
                    <input type="checkbox" class="custom-control-input" id="req_{{ $index }}" name="steps[{{ $index }}][required]" value="1"
                        @checked($isTitle || ! empty($step['required'])) @disabled($isTitle) data-field="required">
                    <label class="custom-control-label" for="req_{{ $index }}">Wajib diisi</label>
                </div>
            </div>
            <button type="button" class="btn btn-primary btn-sm" data-done>Selesai</button>
        </div>
    </div>
</div>
