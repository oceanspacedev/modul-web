@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <x-page-header title="Dokumen" subtitle="Dokumen per divisi dan jabatan, lengkap dengan riwayat versi dan kuisnya." eyebrow="Dokumen">
                <x-slot:actions>
                    @can('create', App\Models\Document::class)
                        <a href="{{ route('document.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Tambah Dokumen
                        </a>
                    @endcan
                </x-slot:actions>
            </x-page-header>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <x-flash />

            @can('restore', App\Models\Document::class)
                <ul class="nav nav-tabs mb-3">
                    <li class="nav-item">
                        <a class="nav-link {{ $trashed ? '' : 'active' }}" href="{{ route('document.index', request()->except('tab')) }}">Dokumen</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $trashed ? 'active' : '' }}" href="{{ route('document.index', [...request()->except('tab'), 'tab' => 'terhapus']) }}">
                            <i class="fas fa-trash-restore"></i> Terhapus
                        </a>
                    </li>
                </ul>
            @endcan

            <div class="card">
                <!-- Filter & Search Bar -->
                <div class="card-header filter-toolbar">
                    <form action="{{ route('document.index') }}" method="GET" class="d-flex flex-wrap align-items-center justify-content-end gap-2 w-100 m-0">
                        @if ($trashed)
                            <input type="hidden" name="tab" value="terhapus">
                        @endif
                        <div class="filter-group">
                            <div class="filter-group-icon">
                                <i class="fas fa-building"></i>
                            </div>
                            <select class="custom-select select2" name="divisi_id" id="divisi_id" style="width: 100%;" onchange="this.form.submit()">
                                <option value="">Semua Divisi</option>
                                @foreach ($divisis as $divisi)
                                    <option value="{{ $divisi->id }}" {{ request('divisi_id') == $divisi->id ? 'selected' : '' }}>{{ $divisi->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-group">
                            <div class="filter-group-icon">
                                <i class="fas fa-tags"></i>
                            </div>
                            <select class="custom-select select2" name="doctype_id" id="doctype_id" style="width: 100%;" onchange="this.form.submit()">
                                <option value="">Semua Tipe</option>
                                @foreach ($doctypes as $doctype)
                                    <option value="{{ $doctype->id }}" {{ request('doctype_id') == $doctype->id ? 'selected' : '' }}>{{ $doctype->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-search-field placeholder="Cari dokumen..." style="width: 260px; max-width: 100%;"
                            :clear-url="route('document.index', request()->except(['search', 'page']))" />
                        @if (request('divisi_id') || request('doctype_id') || request('search'))
                            <a href="{{ route('document.index', $trashed ? ['tab' => 'terhapus'] : []) }}" class="btn btn-default" title="Reset Filter">
                                Reset
                            </a>
                        @endif
                    </form>
                </div>

                <div class="card-body table-responsive p-0" style="max-height: 560px;">
                    <table class="table table-head-fixed text-nowrap">
                        <thead>
                            <tr>
                                <th style="width: 60px;">No</th>
                                <th>Nama Dokumen</th>
                                <th>Divisi</th>
                                <th>Jabatan</th>
                                <th>Tipe Dokumen</th>
                                <th>Versi</th>
                                <th>Status</th>
                                <th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($documents as $document)
                                <tr>
                                    <td class="text-muted">{{ $loop->iteration }}</td>
                                    @php
                                        $quiz = $document->activeQuiz();
                                        $quizSettings = $document->quizModule && ($document->quizModule->has_pretest || $document->quizModule->has_posttest) ? $document->quizModule : null;
                                    @endphp
                                    <td>
                                        <strong>{{ $document->name }}</strong>
                                        @if($quiz)
                                            <div class="mt-1">
                                                @if($quiz->has_pretest)<span class="badge badge-secondary">Pretest</span>@endif
                                                @if($quiz->has_posttest)<span class="badge badge-secondary">Posttest</span>@endif
                                            </div>
                                        @elseif($quizSettings)
                                            @can('update', $document)
                                                <div class="mt-1"><span class="badge badge-secondary">Kuis belum ada soal</span></div>
                                            @endcan
                                        @endif
                                    </td>
                                    <td>{{ $document->divisi?->name ?? '-' }}</td>
                                    <td>{{ $document->role->name ?? 'SEMUA JABATAN' }}</td>
                                    <td>{{ $document->dokumentype?->name ?? '-' }}</td>
                                    <td>
                                        <button type="button" class="btn btn-xs btn-default"
                                            onclick="openDocumentHistory('{{ $document->id }}')" title="Klik untuk lihat riwayat versi">
                                            v{{ $document->version ?? 1 }}
                                        </button>
                                    </td>
                                    <td>
                                        @if ($document->trashed())
                                            <span class="badge badge-danger">Terhapus</span>
                                            <div class="small text-muted mt-1">{{ $document->deleted_at->format('d M Y, H:i') }}</div>
                                        @elseif ($document->is_active)
                                            <span class="badge badge-success">Aktif</span>
                                        @else
                                            <span class="badge badge-secondary">Nonaktif</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            @if($quiz && $document->is_active)
                                                {{-- Dokumen ber-kuis dibuka lewat halaman pretest -> dokumen -> posttest, juga oleh pengelola (ada tombol pratinjau di sana). --}}
                                                <a href="{{ $quiz->url('show') }}" class="btn btn-default btn-xs" title="Buka dokumen beserta pretest/posttest">
                                                    Buka
                                                </a>
                                            @else
                                                <a href="{{ $document->file_url }}" target='_blank' class="btn btn-default btn-xs" title="Buka File Dokumen">
                                                    Buka
                                                </a>
                                            @endif
                                            @if ($trashed)
                                                <form action="{{ route('document.restore', $document) }}" method="POST" class="m-0" onsubmit="return confirm(@js('Pulihkan dokumen '.$document->name.'? Dokumen kembali ke daftar beserta kuis pretest/posttest-nya.'))">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="btn btn-default btn-xs" title="Kembalikan dokumen ke daftar">
                                                        <i class="fas fa-trash-restore"></i> Pulihkan
                                                    </button>
                                                </form>
                                            @else
                                                <div class="dropdown">
                                                    <button class="btn btn-default btn-xs dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                        Aksi
                                                    </button>
                                                    <div class="dropdown-menu dropdown-menu-right">
                                                        <a class="dropdown-item" href="javascript:void(0)" onclick="openDocumentHistory('{{ $document->id }}')">
                                                            Riwayat Versi
                                                        </a>
                                                        @can('update', $document)
                                                            <a class="dropdown-item" href="{{ route('document.edit', $document) }}">
                                                                Edit Dokumen
                                                            </a>
                                                            @if($quizSettings)
                                                                <a class="dropdown-item" href="{{ $quizSettings->url('questions.index') }}">
                                                                    Soal Pretest/Posttest
                                                                </a>
                                                                <a class="dropdown-item" href="{{ $quizSettings->url('results') }}">
                                                                    Rekap Nilai Kuis
                                                                </a>
                                                            @endif
                                                            <div class="dropdown-divider"></div>
                                                            @if ($document->is_active)
                                                                <form action="{{ route('document.deactivate', $document) }}" method="POST" class="m-0" onsubmit="return confirm(@js('Apakah anda yakin menonaktifkan dokumen '.$document->name.'?'))">
                                                                    @csrf
                                                                    @method('PATCH')
                                                                    <button type="submit" class="dropdown-item">Nonaktifkan Dokumen</button>
                                                                </form>
                                                            @else
                                                                <form action="{{ route('document.activate', $document) }}" method="POST" class="m-0" onsubmit="return confirm(@js('Mengaktifkan kembali dokumen '.$document->name.'?'))">
                                                                    @csrf
                                                                    @method('PATCH')
                                                                    <button type="submit" class="dropdown-item">Aktifkan Dokumen</button>
                                                                </form>
                                                            @endif
                                                        @endcan
                                                        @can('delete', $document)
                                                            <form action="{{ route('document.destroy', $document) }}" method="POST" class="m-0" onsubmit="return confirm(@js('Hapus dokumen '.$document->name.'? Dokumen akan hilang dari daftar beserta kuis pretest/posttest-nya.'))">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="dropdown-item text-danger">Hapus Dokumen</button>
                                                            </form>
                                                        @endcan
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">
                                        @if ($trashed)
                                            <x-empty-state icon="fa-trash-restore" title="Tidak ada dokumen yang dihapus." text="Dokumen yang dihapus akan muncul di sini dan bisa dipulihkan." />
                                        @else
                                            <x-empty-state icon="fa-file-alt" title="Belum ada dokumen" text="Dokumen yang ditambahkan akan muncul di sini.">@can('create', \App\Models\Document::class)<a href="{{ route('document.create') }}" class="btn btn-primary btn-sm">Tambah Dokumen</a>@endcan</x-empty-state>
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <!-- Modal Riwayat Versi Dokumen -->
    <div class="modal fade modal-center" id="documentHistoryModal" tabindex="-1" role="dialog" aria-labelledby="historyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="historyModalLabel">
                        <i class="fas fa-history"></i> Riwayat Dokumen
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">&times;</button>
                </div>
                <div class="modal-body">
                    <div id="historyLoading" class="text-center py-4">
                        <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                        <p class="small text-muted mt-2 mb-0">Memuat data...</p>
                    </div>
                    <p id="historyError" class="text-danger text-center py-3 mb-0" style="display: none;">Gagal memuat riwayat.</p>

                    <div id="historyContent" style="display: none;">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 p-2 bg-light rounded border text-sm">
                            <div><span class="text-muted">Dokumen:</span> <span id="histDocName" class="font-weight-bold ml-1 text-break"></span></div>
                            <div><span class="text-muted">Divisi:</span> <span id="histDocDivisi" class="ml-1"></span></div>
                            <div><span class="text-muted">Versi Aktif:</span> <span id="histDocVersion" class="badge badge-secondary ml-1"></span></div>
                        </div>

                        <div class="table-frame table-responsive">
                            <table class="table table-hover text-sm mb-0">
                                <thead>
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
                <div class="modal-footer flex-wrap justify-content-between gap-2">
                    @can('create', App\Models\Document::class)
                    <a href="#" id="histEditLink" class="btn btn-default">
                        <i class="fas fa-edit"></i> Edit Dokumen
                    </a>
                    @endcan
                    <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('footer')
    <script>
    function openDocumentHistory(id) {
        $('#documentHistoryModal').modal('show');
        $('#historyLoading').show();
        $('#historyError').hide();
        $('#historyContent').hide();

        fetch('/document/' + encodeURIComponent(id) + '/history', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            document.getElementById('histDocName').textContent = data.document.name ?? '';
            document.getElementById('histDocDivisi').textContent = data.document.divisi ?? '-';
            document.getElementById('histDocVersion').textContent = 'v' + (data.document.version ?? 1);
            const histEditLink = document.getElementById('histEditLink');
            if (histEditLink) histEditLink.href = '/document/' + encodeURIComponent(data.document.id) + '/edit';

            var tbody = document.getElementById('historyTableBody');
            tbody.replaceChildren();

            if (!Array.isArray(data.versions) || data.versions.length === 0) {
                var emptyRow = document.createElement('tr');
                var emptyCell = document.createElement('td');
                emptyCell.colSpan = 6;
                emptyCell.className = 'text-center text-muted py-2';
                emptyCell.textContent = 'Belum ada riwayat.';
                emptyRow.appendChild(emptyCell);
                tbody.appendChild(emptyRow);
            } else {
                data.versions.forEach(function(ver, index) {
                    var isLatest = (index === 0);
                    var row = document.createElement('tr');

                    var versionCell = document.createElement('td');
                    versionCell.className = 'text-center';
                    var versionBadge = document.createElement('span');
                    versionBadge.className = 'badge ' + (isLatest ? 'badge-success' : 'badge-secondary');
                    versionBadge.textContent = 'v' + (ver.version_number ?? '');
                    versionCell.appendChild(versionBadge);
                    row.appendChild(versionCell);

                    var fileCell = document.createElement('td');
                    fileCell.className = 'text-break';
                    var fileIcon = document.createElement('i');
                    fileIcon.className = 'fas fa-file-pdf text-muted mr-1';
                    fileCell.appendChild(fileIcon);
                    fileCell.appendChild(document.createTextNode(ver.file_name || ''));
                    row.appendChild(fileCell);

                    var noteCell = document.createElement('td');
                    noteCell.className = 'text-break';
                    noteCell.textContent = ver.change_note || '-';
                    row.appendChild(noteCell);

                    var dateCell = document.createElement('td');
                    dateCell.textContent = ver.created_at || '-';
                    row.appendChild(dateCell);

                    var authorCell = document.createElement('td');
                    authorCell.className = 'text-muted';
                    authorCell.textContent = ver.created_by || 'Admin';
                    row.appendChild(authorCell);

                    var actionCell = document.createElement('td');
                    actionCell.className = 'text-center';
                    var fileUrl = String(ver.file_url || '');
                    if (/^(?:\/(?![\/\\])|https?:\/\/)/i.test(fileUrl)) {
                        var link = document.createElement('a');
                        link.href = fileUrl;
                        link.target = '_blank';
                        link.rel = 'noopener noreferrer';
                        link.className = 'btn btn-xs btn-default';
                        link.title = 'Buka File';
                        var eyeIcon = document.createElement('i');
                        eyeIcon.className = 'fas fa-eye mr-1';
                        link.appendChild(eyeIcon);
                        link.appendChild(document.createTextNode('Buka'));
                        actionCell.appendChild(link);
                    } else {
                        var dash = document.createElement('span');
                        dash.className = 'text-muted';
                        dash.textContent = '-';
                        actionCell.appendChild(dash);
                    }
                    row.appendChild(actionCell);

                    tbody.appendChild(row);
                });
            }

            $('#historyLoading').hide();
            $('#historyContent').show();
        })
        .catch(err => {
            console.error('Error fetching history:', err);
            $('#historyLoading').hide();
            $('#historyError').show();
        });
    }
    </script>
@endsection
