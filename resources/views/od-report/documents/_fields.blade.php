@php
    $defaultStatus = $statuses->firstWhere('type', App\Models\OdDocumentStatus::TYPE_OPEN);
@endphp

<div class="form-group">
    <label for="title">Judul dokumen</label>
    <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" maxlength="250" required
        value="{{ old('title', $document?->title) }}">
    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="form-row">
    <div class="form-group col-sm-6">
        <label for="jenis">Jenis</label>
        <select class="custom-select @error('jenis') is-invalid @enderror" id="jenis" name="jenis" required>
            @foreach (App\Models\OdDocument::JENIS as $jenis)
                <option value="{{ $jenis }}" @selected(old('jenis', $document?->jenis) === $jenis)>{{ $jenis }}</option>
            @endforeach
        </select>
        @error('jenis')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="form-group col-sm-6">
        <label for="badan_usaha">Badan usaha</label>
        <input type="text" class="form-control @error('badan_usaha') is-invalid @enderror" id="badan_usaha" name="badan_usaha" maxlength="120"
            list="badanUsahaList" value="{{ old('badan_usaha', $document && $document->badan_usaha !== '-' ? $document->badan_usaha : '') }}">
        <datalist id="badanUsahaList">
            @foreach (App\Models\OdDocument::BADAN_USAHA as $bu)
                <option value="{{ $bu }}">
            @endforeach
        </datalist>
        @error('badan_usaha')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="form-row">
    <div class="form-group col-12">
        <label for="od_document_status_id">Status</label>
        <select class="custom-select @error('od_document_status_id') is-invalid @enderror" id="od_document_status_id" name="od_document_status_id" required>
            @foreach ($statuses as $status)
                <option value="{{ $status->id }}" @selected((int) old('od_document_status_id', $document?->od_document_status_id ?? $defaultStatus?->id) === $status->id)>{{ $status->name }}</option>
            @endforeach
        </select>
        @error('od_document_status_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="form-group mb-0">
    <label for="catatan">Catatan</label>
    <textarea class="form-control @error('catatan') is-invalid @enderror" id="catatan" name="catatan" rows="2" maxlength="300">{{ old('catatan', $document?->catatan) }}</textarea>
    @error('catatan')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
