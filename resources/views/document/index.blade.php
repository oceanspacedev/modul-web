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
                                    <a href="/document/create" class="btn btn-sm btn-success">
                                        <i class="fas fa-plus mr-1"></i> Tambah Dokumen
                                    </a>
                                    <a href="/document/export" class="btn btn-sm btn-primary">
                                        <i class="fas fa-file-export mr-1"></i> Export All
                                    </a>
                                </div>
                            </div>

                            <!-- Dedicated Filter & Search Bar -->
                            <div class="filter-toolbar">
                                <form action="/document" method="GET" class="d-flex flex-wrap align-items-center justify-content-end" style="gap: 12px;">
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
                                            <a href="/document" class="btn btn-sm btn-outline-secondary" title="Reset Filter" style="height: 38px; display: inline-flex; align-items: center; border-radius: 10px;">
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
                                                <button type="button" class="btn btn-xs btn-outline-info font-weight-bold"
                                                    onclick="openDocumentHistory('{{ $document->id }}')" title="Klik untuk lihat riwayat versi">
                                                    <i class="fas fa-code-branch mr-1"></i>v{{ $document->version ?? 1 }}
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
                                                <a href="{{ asset('storage/dokumen/'.$document->path) }}" target='_blank' data-toggle="tooltip" title="Buka File" class="badge bg-primary p-2">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <button type="button" class="badge bg-purple border-0"
                                                    data-toggle="tooltip" title="Riwayat Versi" onclick="openDocumentHistory('{{ $document->id }}')">
                                                    <i class="fas fa-history" style="color: #6b21a8 !important;"></i>
                                                </button>
                                                <a href="/document/{{ $document->id }}" data-toggle="tooltip" title="Edit Dokumen" class="badge bg-warning p-2">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                @if ($document->deleted_at)
                                                    <a href="/document/active/{{ $document->id }}"
                                                        class="badge bg-success p-2" data-toggle="tooltip" title="Aktifkan"
                                                        onclick="return confirm('Mengaktifkan kembali dokumen {{ $document->name }}?')">
                                                        <i class="far fa-check-circle"></i>
                                                    </a>
                                                @else
                                                    <a href="/document/delete/{{ $document->id }}"
                                                        class="badge bg-danger p-2" data-toggle="tooltip" title="Nonaktifkan"
                                                        onclick="return confirm('Apakah anda yakin menonaktifkan dokumen {{ $document->name }}?')">
                                                        <i class="far fa-times-circle"></i>
                                                    </a>
                                                @endif
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
                    <a href="#" id="histEditLink" class="btn btn-warning btn-sm">
                        <i class="fas fa-edit mr-1"></i> Edit Dokumen
                    </a>
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Import -->
    <form action="/document/import" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="modal fade" id="impordocument" tabindex="-1" aria-labelledby="impordocumentLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="impordocumentLabel">Import document</h5>
                    </div>
                    <div class="modal-body">
                        <div class="col-12 mt-3">
                            <label for="formFile" class="form-label">Pilih File</label>
                            <input class="form-control" type="file" id="formFile" name="file">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Import</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <script>
    function openDocumentHistory(id) {
        $('#documentHistoryModal').modal('show');
        $('#historyLoading').show();
        $('#historyContent').hide();

        fetch('/document/history/' + id, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('histDocName').innerText = data.document.name;
            document.getElementById('histDocDivisi').innerText = data.document.divisi;
            document.getElementById('histDocVersion').innerText = 'v' + data.document.version;
            document.getElementById('histEditLink').href = '/document/' + data.document.id;

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
