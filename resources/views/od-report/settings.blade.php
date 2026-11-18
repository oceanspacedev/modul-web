@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Pengaturan OD Report" subtitle="Status dokumen" eyebrow="OD Report" />
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />

        <div class="row">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Status dokumen</h3></div>
                    <div class="card-body p-0">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Tipe</th>
                                    <th class="text-right">Dipakai</th>
                                    <th class="text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($statuses as $status)
                                    <tr>
                                        <td colspan="2">
                                            <form action="{{ route('od-report.settings.statuses.update', $status) }}" method="POST" class="d-flex gap-2 m-0" id="status-form-{{ $status->id }}">
                                                @csrf
                                                @method('PUT')
                                                <input type="text" name="name" value="{{ $status->name }}" maxlength="30" required class="form-control form-control-sm" aria-label="Nama status">
                                                <select name="type" class="custom-select custom-select-sm" style="width: 120px;" aria-label="Tipe status">
                                                    @foreach (App\Models\OdDocumentStatus::TYPES as $type => $label)
                                                        <option value="{{ $type }}" @selected($status->type === $type)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        </td>
                                        <td class="text-right">{{ $status->documents_count }}</td>
                                        <td class="text-right text-nowrap">
                                            <button type="submit" form="status-form-{{ $status->id }}" class="btn btn-default btn-sm" title="Simpan"><i class="fas fa-save"></i></button>
                                            <form action="{{ route('od-report.settings.statuses.destroy', $status) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus status {{ $status->name }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-default btn-sm text-danger" title="Hapus" @disabled($status->documents_count > 0)><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer">
                        <form action="{{ route('od-report.settings.statuses.store') }}" method="POST" class="d-flex flex-wrap gap-2 m-0">
                            @csrf
                            <input type="text" name="name" maxlength="30" required placeholder="Status baru" class="form-control form-control-sm @error('name') is-invalid @enderror" style="max-width: 240px;" value="{{ old('name') }}">
                            <select name="type" class="custom-select custom-select-sm" style="width: 120px;" aria-label="Tipe status baru">
                                @foreach (App\Models\OdDocumentStatus::TYPES as $type => $label)
                                    <option value="{{ $type }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Tambah</button>
                            @error('name')<div class="text-danger small w-100">{{ $message }}</div>@enderror
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card card-body small">
                    <p class="mb-2">Jenis dokumen, tahapan dan PIC bawaan diatur di Kategori Dokumen.</p>
                    <a href="{{ route('document-category.index') }}" class="btn btn-default btn-sm align-self-start"><i class="fas fa-tags"></i> Buka Kategori Dokumen</a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
