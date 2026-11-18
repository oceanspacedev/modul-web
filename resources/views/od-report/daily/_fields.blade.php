{{-- Isian aktivitas Daily (tambah & ubah). $activity null = tambah. --}}
<div class="form-row">
    <div class="form-group col-md-8">
        <label for="name">Nama pekerjaan</label>
        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" maxlength="200" required
            value="{{ old('name', $activity?->name) }}" placeholder="Contoh: Review draf IK penerimaan barang">
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="form-group col-md-4">
        <label for="result">Hasil</label>
        <select class="custom-select @error('result') is-invalid @enderror" id="result" name="result">
            @foreach (App\Models\OdActivity::RESULTS as $result)
                <option value="{{ $result }}" @selected(old('result', $activity?->result ?? App\Models\OdActivity::RESULT_DONE) === $result)>{{ $result }}</option>
            @endforeach
        </select>
    </div>
</div>

<details class="mb-3" @if ($activity?->od_document_id || $activity?->kendala || $activity?->solusi || $activity?->keterangan || $errors->hasAny(['od_document_id', 'kendala', 'solusi', 'keterangan', 'date'])) open @endif>
    <summary class="small text-muted mb-2">Detail tambahan</summary>
    <div class="form-row">
        <div class="form-group col-md-6">
            <label for="od_document_id">Dokumen terkait</label>
            <select class="custom-select @error('od_document_id') is-invalid @enderror" id="od_document_id" name="od_document_id">
                <option value="">Tidak ada</option>
                @foreach ($documents as $document)
                    <option value="{{ $document->id }}" @selected((int) old('od_document_id', $activity?->od_document_id) === $document->id)>{{ $document->jenis }} · {{ $document->title }}</option>
                @endforeach
            </select>
            @error('od_document_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="form-group col-md-6">
            <label for="date">Tanggal</label>
            <input type="date" class="form-control @error('date') is-invalid @enderror" id="date" name="date" max="{{ today()->toDateString() }}"
                value="{{ old('date', ($activity?->date ?? today())->toDateString()) }}">
            @error('date')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="form-group col-md-4">
            <label for="kendala">Kendala</label>
            <input type="text" class="form-control" id="kendala" name="kendala" maxlength="300" value="{{ old('kendala', $activity?->kendala) }}">
        </div>
        <div class="form-group col-md-4">
            <label for="solusi">Solusi</label>
            <input type="text" class="form-control" id="solusi" name="solusi" maxlength="300" value="{{ old('solusi', $activity?->solusi) }}">
        </div>
        <div class="form-group col-md-4">
            <label for="keterangan">Keterangan</label>
            <input type="text" class="form-control" id="keterangan" name="keterangan" maxlength="300" value="{{ old('keterangan', $activity?->keterangan) }}">
        </div>
    </div>
</details>
