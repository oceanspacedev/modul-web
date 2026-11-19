@extends('layout.main_template')

@php
    $isLeader = auth()->user()->can('manage-od-report');
    $chipUrl = fn ($status) => route('od-report.documents.index', array_filter([...request()->except(['status', 'page']), 'status' => $status]));
@endphp

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Approval Dokumen" :subtitle="$isLeader ? 'Dokumen yang ditangani tim OD' : 'Dokumen yang Anda tangani'" eyebrow="Dokumen">
            <x-slot:actions>
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addDocumentModal">
                    <i class="fas fa-plus"></i> Tambah Dokumen
                </button>
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />
        @foreach ($errors->getBag('release')->all() as $message)
            <div class="alert alert-danger">{{ $message }}</div>
        @endforeach

        <div class="card">
            <div class="card-header filter-toolbar">
                <div class="btn-group btn-group-sm filter-tabs flex-wrap">
                    <a href="{{ $chipUrl(null) }}" class="btn {{ request('status') ? 'btn-default' : 'btn-secondary active' }}">Semua ({{ $counts->sum() }})</a>
                    @foreach ($statuses as $status)
                        <a href="{{ $chipUrl($status->id) }}" class="btn {{ (int) request('status') === $status->id ? 'btn-secondary active' : 'btn-default' }}">{{ $status->name }} ({{ $counts[$status->id] ?? 0 }})</a>
                    @endforeach
                </div>
                <form action="{{ route('od-report.documents.index') }}" method="GET" class="d-flex flex-wrap align-items-center gap-2 ml-auto m-0">
                    @if (request('status'))
                        <input type="hidden" name="status" value="{{ request('status') }}">
                    @endif
                    <select name="jenis" class="custom-select custom-select-sm" style="width: 150px;" onchange="this.form.submit()" aria-label="Jenis">
                        <option value="">Semua jenis</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->code }}" @selected(request('jenis') === $category->code)>{{ $category->code }}</option>
                        @endforeach
                    </select>
                    <select name="sort" class="custom-select custom-select-sm" style="width: 170px;" onchange="this.form.submit()" aria-label="Urutkan">
                        @foreach (App\Http\Controllers\OdDocumentController::SORTS as $value => $label)
                            <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-search-field placeholder="Cari judul..." style="width: 220px; max-width: 100%;"
                        :clear-url="route('od-report.documents.index', request()->except(['search', 'page']))" />
                </form>
            </div>

            <div class="card-body table-responsive p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Dokumen</th>
                            <th>Jenis</th>
                            <th>Badan Usaha</th>
                            <th>Diajukan</th>
                            <th>Catatan</th>
                            <th style="width: 170px;">Status</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($documents as $document)
                            @php($canEdit = $document->canBeEditedBy(auth()->user()))
                            <tr>
                                <td style="white-space: normal; min-width: 240px;">
                                    <strong>{{ $document->title }}</strong>
                                    @if ($document->hasFile())
                                        <a href="{{ route('od-report.documents.file', $document) }}" target="_blank" class="ml-1" title="{{ $document->file_name }}"><i class="fas fa-paperclip"></i></a>
                                    @endif
                                    <div class="small text-muted">
                                        @if ($document->documentRequest)
                                            <span class="badge badge-info">{{ $document->documentRequest->code }}</span>
                                            <span>{{ $document->stage }}</span>
                                        @endif
                                        @if ($document->activities_count)
                                            <span class="ml-1"><i class="fas fa-tasks"></i> {{ $document->activities_count }} aktivitas</span>
                                        @endif
                                    </div>
                                </td>
                                <td>{{ $document->jenis }}</td>
                                <td>{{ $document->badan_usaha }}</td>
                                <td>
                                    {{ $document->submitted_at->format('d/m/Y') }}
                                    @if ($document->received_at)
                                        <div class="small text-muted">Selesai {{ $document->received_at->format('d/m/Y') }}</div>
                                    @endif
                                </td>
                                <td class="small" style="white-space: pre-line; min-width: 180px; max-width: 280px;">{{ $document->catatan ?: '-' }}</td>
                                <td>
                                    @if ($canEdit)
                                        <form action="{{ route('od-report.documents.status', $document) }}" method="POST" class="m-0">
                                            @csrf
                                            @method('PATCH')
                                            <select name="od_document_status_id" class="custom-select custom-select-sm" aria-label="Status {{ $document->title }}"
                                                data-status-select data-current="{{ $document->od_document_status_id }}" data-title="{{ $document->title }}"
                                                data-needs-file="{{ $document->needsFileToRelease() && ! $document->status->isDone() ? 1 : 0 }}">
                                                @foreach ($statuses as $status)
                                                    <option value="{{ $status->id }}" data-done="{{ $status->isDone() ? 1 : 0 }}" @selected($status->id === $document->od_document_status_id)>{{ $status->name }}</option>
                                                @endforeach
                                            </select>
                                        </form>
                                    @else
                                        <span class="badge {{ $document->status->badgeClass() }}">{{ $document->status->name }}</span>
                                    @endif
                                </td>
                                <td class="text-right text-nowrap">
                                    @if ($canEdit)
                                        <a href="{{ route('od-report.documents.edit', $document) }}" class="btn btn-default btn-sm" title="Ubah"><i class="fas fa-pen"></i></a>
                                        @unless ($document->isFromRequest())
                                            <form action="{{ route('od-report.documents.destroy', $document) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus dokumen ini?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-default btn-sm text-danger" title="Hapus"><i class="fas fa-trash"></i></button>
                                            </form>
                                        @endunless
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <x-empty-state icon="fa-file-signature" title="Belum ada dokumen" text="Tambah dokumen atau ubah filter." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($documents->hasPages())
                <div class="card-footer d-flex justify-content-end">
                    {{ $documents->links('pagination::bootstrap-4') }}
                </div>
            @endif
        </div>
    </div>
</section>

<div class="modal fade" id="releaseModal" tabindex="-1" role="dialog" aria-labelledby="releaseTitle" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form method="POST" class="modal-content" enctype="multipart/form-data" id="releaseForm">
            @csrf
            @method('PATCH')
            <input type="hidden" name="od_document_status_id" id="releaseStatus">
            <div class="modal-header">
                <h5 class="modal-title" id="releaseTitle">Rilis dokumen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="mb-2"><strong id="releaseDocument"></strong></p>
                <label for="releaseFile">File dokumen</label>
                <input type="file" class="form-control-file" id="releaseFile" name="file" required accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx">
                <small class="form-text text-muted">PDF, Word, Excel, PowerPoint. Maks 50 MB. File dikirim ke pemohon lewat WhatsApp.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Rilis</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="addDocumentModal" tabindex="-1" role="dialog" aria-labelledby="addDocumentTitle" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="{{ route('od-report.documents.store') }}" method="POST" class="modal-content" enctype="multipart/form-data">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="addDocumentTitle">Tambah Dokumen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                @include('od-report.documents._fields', ['document' => null])
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('footer')
<script>
    $(function () {
        @if ($errors->getBag('default')->any())
            $('#addDocumentModal').modal('show');
        @endif

        // Status selesai untuk dokumen dari permintaan yang belum punya file: minta file dulu lewat modal.
        let pending = null;
        $('[data-status-select]').on('change', function () {
            const option = this.options[this.selectedIndex];
            if (this.dataset.needsFile === '1' && option.dataset.done === '1') {
                pending = this;
                $('#releaseForm').attr('action', this.form.action);
                $('#releaseStatus').val(this.value);
                $('#releaseDocument').text(this.dataset.title);
                $('#releaseFile').val('');
                $('#releaseModal').modal('show');
                return;
            }
            this.form.submit();
        });
        $('#releaseModal').on('hidden.bs.modal', function () {
            if (pending) { pending.value = pending.dataset.current; pending = null; }
        });
        $('#releaseForm').on('submit', function () { pending = null; });
    });
</script>
@endsection
