@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap" style="gap: 12px;">
                                <h3 class="card-title font-weight-bold text-dark m-0" style="font-size: 1.2rem;">
                                    Data Dokumen
                                </h3>
                                <div class="card-tools m-0 d-flex align-items-center" style="gap: 8px;">
                                    @can('create', App\Models\Document::class)
                                        <a href="{{ route('document.create') }}" class="btn btn-sm btn-primary">
                                            Tambah Dokumen
                                        </a>
                                    @endcan
                                </div>
                            </div>

                            <!-- Dedicated Filter & Search Bar -->
                            <div class="filter-toolbar">
                                <form action="{{ route('document.index') }}" method="GET" class="d-flex flex-wrap align-items-center justify-content-end" style="gap: 12px;">
                                    <div class="filter-group">
                                        <div class="filter-group-icon">
                                            <i class="fas fa-building text-muted"></i>
                                        </div>
                                        <select class="custom-select" name="divisi_id" id="divisi_id" onchange="this.form.submit()">
                                            <option value="">Semua Divisi</option>
                                            @foreach ($divisis as $divisi)
                                                <option value="{{ $divisi->id }}" {{ request('divisi_id') == $divisi->id ? 'selected' : '' }}>{{ $divisi->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="filter-group">
                                        <div class="filter-group-icon">
                                            <i class="fas fa-tags text-muted"></i>
                                        </div>
                                        <select class="custom-select" name="doctype_id" id="doctype_id" onchange="this.form.submit()">
                                            <option value="">Semua Type</option>
                                            @foreach ($doctypes as $doctype)
                                                <option value="{{ $doctype->id }}" {{ request('doctype_id') == $doctype->id ? 'selected' : '' }}>{{ $doctype->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="filter-search" style="width: 260px;">
                                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama dokumen...">
                                        <button type="submit" title="Cari Dokumen">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                    @if (request('divisi_id') || request('doctype_id') || request('search'))
                                        <div>
                                            <a href="{{ route('document.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset Filter" style="height: 38px; display: inline-flex; align-items: center; border-radius: 10px;">
                                                <i class="fas fa-undo mr-1"></i> Reset
                                            </a>
                                        </div>
                                    @endif
                                </form>
                            </div>

                        @if ($message = Session::get('success'))
                            <div class="alert alert-success alert-dismissible fade show mx-3 mt-3 mb-0" role="alert">
                                <strong>{{ $message }}</strong>
                            </div>
                        @endif
                        @if ($message = Session::get('error'))
                            <div class="alert alert-danger alert-dismissible fade show mx-3 mt-3 mb-0" role="alert">
                                <strong>{{ $message }}</strong>
                            </div>
                        @endif
                        @include('layout.partials.validation-errors')
                        <!-- /.card-header -->
                        <div class="card-body table-responsive p-0" style="height: 500px;">
                            <table class="table table-head-fixed text-nowrap">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Document Name</th>
                                        <th>Divisi</th>
                                        <th>Sub Divisi</th>
                                        <th>Job Level</th>
                                        <th>Document Type</th>
                                        <th>Versi</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($documents as $document)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td><strong>{{ $document->name }}</strong></td>
                                            <td>{{ $document->divisi->name }}</td>
                                            <td>{{ $document->subdivisi->name ?? '-' }}</td>
                                            <td>{{ $document->joblevel->name }}</td>
                                            <td>{{ $document->dokumentype->name }}</td>
                                            <td>
                                                <button type="button" class="btn btn-xs btn-default font-weight-bold"
                                                    onclick="openDocumentHistory('{{ $document->id }}')" title="Klik untuk lihat riwayat versi">
                                                    v{{ $document->version ?? 1 }}
                                                </button>
                                            </td>
                                            <td>
                                                @if ($document->deleted_at)
                                                    <span class="badge badge-secondary">NONAKTIF</span>
                                                @else
                                                    <span class="badge badge-success">AKTIF</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="d-inline-flex align-items-center" style="gap: 4px;">
                                                    <a href="{{ $document->file_url }}" target='_blank' class="btn btn-default btn-xs" title="Buka File Dokumen">
                                                        Buka
                                                    </a>
                                                    <div class="dropdown">
                                                        <button class="btn btn-default btn-xs dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                            Aksi
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-right shadow-sm border text-sm" style="font-size: 0.85rem;">
                                                            <a class="dropdown-item py-1" href="javascript:void(0)" onclick="openDocumentHistory('{{ $document->id }}')">
                                                                Riwayat Versi
                                                            </a>
                                                            @can('update', $document)
                                                                <a class="dropdown-item py-1" href="{{ route('document.edit', $document) }}">
                                                                    Edit Dokumen
                                                                </a>
                                                                <div class="dropdown-divider my-1"></div>
                                                                @if ($document->deleted_at)
                                                                    <form action="{{ route('document.restore', $document) }}" method="POST" class="m-0" onsubmit="return confirm(@js('Mengaktifkan kembali dokumen '.$document->name.'?'))">
                                                                        @csrf
                                                                        @method('PATCH')
                                                                        <button type="submit" class="dropdown-item py-1 text-success">Aktifkan Dokumen</button>
                                                                    </form>
                                                                @else
                                                                    <form action="{{ route('document.destroy', $document) }}" method="POST" class="m-0" onsubmit="return confirm(@js('Apakah anda yakin menonaktifkan dokumen '.$document->name.'?'))">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button type="submit" class="dropdown-item py-1 text-danger">Nonaktifkan Dokumen</button>
                                                                    </form>
                                                                @endif
                                                            @endcan
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <!-- /.card-body -->
                    </div>
                    <!-- /.card -->
                </div>
            </div>
        </section>
    </section>

    <!-- Modal Riwayat Versi Dokumen -->
    <div class="modal fade" id="documentHistoryModal" tabindex="-1" role="dialog" aria-labelledby="historyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content shadow-sm border-0">
                <div class="modal-header bg-dark text-white py-2">
                    <h5 class="modal-title font-weight-bold text-sm" id="historyModalLabel">
                        <i class="fas fa-history mr-1"></i> Riwayat Dokumen
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-3">
                    <div id="historyLoading" class="text-center py-4">
                        <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                        <p class="small text-muted mt-2 mb-0">Memuat data...</p>
                    </div>

                    <div id="historyContent" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-3 p-2 bg-light rounded border text-sm">
                            <div><strong class="text-muted">Dokumen:</strong> <span id="histDocName" class="font-weight-bold ml-1"></span></div>
                            <div><strong class="text-muted">Divisi:</strong> <span id="histDocDivisi" class="ml-1"></span></div>
                            <div><strong class="text-muted">Versi Aktif:</strong> <span id="histDocVersion" class="badge badge-info ml-1"></span></div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover text-sm mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width: 80px;" class="text-center">Versi</th>
                                        <th>File</th>
                                        <th>Catatan</th>
                                        <th style="width: 130px;">Tanggal</th>
                                        <th style="width: 100px;">Oleh</th>
                                        <th style="width: 80px;" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="historyTableBody">
                                    {{-- Injected via JS --}}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2 justify-content-between">
                    @can('create', App\Models\Document::class)
                    <a href="#" id="histEditLink" class="btn btn-warning btn-sm">
                        <i class="fas fa-edit mr-1"></i> Edit Dokumen
                    </a>
                    @endcan
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>


    <script>
    function openDocumentHistory(id) {
        $('#documentHistoryModal').modal('show');
        $('#historyLoading').show();
        $('#historyContent').hide();

        fetch('/document/' + id + '/history', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('histDocName').innerText = data.document.name;
            document.getElementById('histDocDivisi').innerText = data.document.divisi;
            document.getElementById('histDocVersion').innerText = 'v' + data.document.version;
            const histEditLink = document.getElementById('histEditLink');
            if (histEditLink) histEditLink.href = '/document/' + data.document.id + '/edit';

            var tbody = document.getElementById('historyTableBody');
            tbody.innerHTML = '';

            if (data.versions.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-2">Belum ada riwayat.</td></tr>';
            } else {
                data.versions.forEach(function(ver, index) {
                    var isLatest = (index === 0);
                    var row = document.createElement('tr');
                    if (isLatest) {
                        row.classList.add('table-success');
                    }

                    row.innerHTML = `
                        <td class="text-center font-weight-bold">
                            <span class="badge ${isLatest ? 'badge-success' : 'badge-secondary'}">
                                v${ver.version_number}
                            </span>
                        </td>
                        <td>
                            <i class="fas fa-file-pdf text-danger mr-1"></i>
                            ${ver.file_name}
                        </td>
                        <td>${ver.change_note || '-'}</td>
                        <td>${ver.created_at}</td>
                        <td><span class="badge badge-light border">${ver.created_by || 'Admin'}</span></td>
                        <td class="text-center">
                            <a href="${ver.file_url}" target="_blank" class="btn btn-xs btn-outline-primary" title="Buka File">
                                <i class="fas fa-eye"></i> Buka
                            </a>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            }

            $('#historyLoading').hide();
            $('#historyContent').show();
        })
        .catch(err => {
            console.error('Error fetching history:', err);
            $('#historyLoading').html('<p class="text-danger text-center">Gagal memuat riwayat.</p>');
        });
    }
    </script>
@endsection
