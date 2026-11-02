@extends('layout.main_template')

@section('content')
    <section class="content-header">
        <section class="content">
            <div class="container-fluid">
                <div class="card">
                    <div class="card-header bg-white py-3 d-block">
                        <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap: 12px;">
                            <h3 class="card-title font-weight-bold text-dark m-0" style="font-size: 1.2rem;">Data User</h3>
                            <div class="d-flex flex-wrap" style="gap: 8px;">
                                <a href="{{ route('user.create') }}" class="btn btn-sm btn-primary">Tambah User</a>
                                @can('import', App\Models\User::class)
                                    <form action="{{ route('user.dnd.preview') }}" method="POST" class="m-0">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success" @disabled(! $dndConfigured)
                                            title="{{ $dndConfigured ? 'Ambil data karyawan terbaru dari DnD' : 'Isi DND_API_URL dan DND_API_TOKEN di .env untuk mengaktifkan' }}">
                                            <i class="fas fa-sync-alt mr-1"></i> Sinkronisasi DnD
                                        </button>
                                    </form>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-default dropdown-toggle" type="button" data-toggle="dropdown">Import/Export</button>
                                        <div class="dropdown-menu dropdown-menu-right">
                                            <a class="dropdown-item" href="{{ route('user.export') }}">Export User</a>
                                            <a class="dropdown-item" href="{{ route('user.template') }}">Download Template</a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#importExcel">Import User (Excel)</a>
                                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#importJson">Import JSON (Talenta)</a>
                                        </div>
                                    </div>
                                @endcan
                            </div>
                        </div>

                        <form action="{{ route('user.index') }}" method="GET" class="mt-3">
                            <input type="hidden" name="tab" value="{{ request('tab') }}">
                            <div class="form-row">
                                <div class="col-lg-3 col-md-6 mb-2">
                                    <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Cari nama / username / ID / No. HP...">
                                </div>
                                <div class="col-lg-2 col-md-6 mb-2">
                                    <select name="area_id" class="form-control form-control-sm">
                                        <option value="">Semua Area</option>
                                        @foreach ($areas as $area)
                                            <option value="{{ $area->id }}" @selected(request('area_id') == $area->id)>{{ $area->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-2 col-md-6 mb-2">
                                    <select name="divisi_id" class="form-control form-control-sm">
                                        <option value="">Semua Divisi</option>
                                        @foreach ($divisis as $divisi)
                                            <option value="{{ $divisi->id }}" @selected(request('divisi_id') == $divisi->id)>{{ $divisi->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-1 col-md-6 mb-2">
                                    <select name="role_id" class="form-control form-control-sm">
                                        <option value="">Jabatan</option>
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->id }}" @selected(request('role_id') == $role->id)>{{ $role->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-2 col-md-6 mb-2">
                                    <select name="position_id" class="form-control form-control-sm select2" style="width: 100%;">
                                        <option value="">Semua Posisi</option>
                                        @foreach ($positions as $position)
                                            <option value="{{ $position->id }}" @selected(request('position_id') == $position->id)>{{ $position->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-2 col-md-6 mb-2">
                                    <select name="approval_id" class="form-control form-control-sm select2" style="width: 100%;">
                                        <option value="">Semua Atasan</option>
                                        @foreach ($approvers as $approver)
                                            <option value="{{ $approver->id }}" @selected(request('approval_id') == $approver->id)>{{ $approver->nama_lengkap }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-sm btn-default">Filter</button>
                            <a href="{{ route('user.index', ['tab' => request('tab')]) }}" class="btn btn-sm btn-link">Reset</a>
                        </form>
                    </div>

                    @if ($message = Session::get('success'))
                        <div class="alert alert-success mx-3 mt-3 mb-0"><strong>{{ $message }}</strong></div>
                    @endif
                    @if ($message = Session::get('error'))
                        <div class="alert alert-danger mx-3 mt-3 mb-0"><strong>{{ $message }}</strong></div>
                    @endif
                    @if ($importErrors = Session::get('importErrors'))
                        <div class="alert alert-light border mx-3 mt-2 mb-0 small">
                            <strong>Catatan:</strong>
                            <ul class="mb-0 pl-3">
                                @foreach ($importErrors as $line)
                                    <li>{{ $line }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @include('layout.partials.validation-errors')

                    <div class="card-body p-0">
                        <ul class="nav nav-tabs px-3 pt-3">
                            <li class="nav-item">
                                <a class="nav-link {{ $archived ? '' : 'active' }}" href="{{ route('user.index', request()->except(['tab', 'page'])) }}">Semua</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ $archived ? 'active' : '' }}" href="{{ route('user.index', [...request()->except('page'), 'tab' => 'arsip']) }}">Arsip</a>
                            </li>
                        </ul>

                        @unless ($archived)
                            <div class="d-flex align-items-center flex-wrap px-3 py-2 border-bottom" style="gap: 8px;">
                                <span class="text-muted small"><span id="selectedCount">0</span> dipilih</span>
                                <button type="button" class="btn btn-xs btn-default bulk-action" data-toggle="modal" data-target="#bulkPosition" disabled>Ubah Posisi Massal</button>
                                <button type="button" class="btn btn-xs btn-default bulk-action" data-toggle="modal" data-target="#bulkApproval" disabled>Ubah Approval Line Massal</button>
                            </div>
                        @endunless

                        <div class="table-responsive">
                            <table class="table table-hover text-nowrap mb-0" style="font-size: 0.92rem;">
                                <thead>
                                    <tr>
                                        @unless ($archived)
                                            <th style="width: 30px;"><input type="checkbox" id="checkAll"></th>
                                        @endunless
                                        <th>Nama Lengkap</th>
                                        <th>ID Karyawan</th>
                                        <th>No. HP</th>
                                        <th>Email</th>
                                        <th>Area</th>
                                        <th>Divisi</th>
                                        <th>Posisi</th>
                                        <th>Jabatan</th>
                                        <th>Approval Line</th>
                                        <th>Akses Data</th>
                                        <th>Last Seen</th>
                                        <th style="width: 100px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($users as $user)
                                        <tr>
                                            @unless ($archived)
                                                <td><input type="checkbox" class="row-check" value="{{ $user->id }}"></td>
                                            @endunless
                                            <td>
                                                <strong>{{ $user->nama_lengkap }}</strong>
                                                <span class="d-block text-muted small">{{ $user->username }}</span>
                                            </td>
                                            <td>{{ $user->employee_id ?? '-' }}</td>
                                            <td>{{ $user->no_hp ?? '-' }}</td>
                                            <td>{{ $user->email ?? '-' }}</td>
                                            <td>{{ $user->area->name ?? '-' }}</td>
                                            <td>{{ $user->divisi->name ?? '-' }}</td>
                                            <td>{{ $user->position->name ?? '-' }}</td>
                                            <td>
                                                @if ($user->jabatan)
                                                    <span class="badge {{ $user->jabatan->name === 'ADMIN' ? 'badge-danger' : 'badge-info' }}">{{ $user->jabatan->name }}</span>
                                                @else
                                                    <span class="badge badge-secondary">-</span>
                                                @endif
                                            </td>
                                            <td>{{ $user->approval->nama_lengkap ?? '-' }}</td>
                                            <td>
                                                @if ($user->isAdmin())
                                                    {{ $user->data_access === 'selected' ? 'Badan Usaha Tertentu' : 'Semua Badan Usaha' }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                @if ($user->last_seen)
                                                    <span class="text-sm">{{ $user->last_seen->format('d M Y H:i') }}</span>
                                                @else
                                                    <span class="text-muted text-sm">Inactive</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center" style="gap: 4px;">
                                                    @if ($user->trashed())
                                                        @can('restore', $user)
                                                            <form action="{{ route('user.restore', $user) }}" method="POST" class="d-inline m-0"
                                                                onsubmit="return confirm(@js('Mengaktifkan kembali user '.$user->nama_lengkap.'?'))">
                                                                @csrf
                                                                @method('PATCH')
                                                                <button type="submit" class="btn btn-default btn-xs text-success">Aktifkan</button>
                                                            </form>
                                                        @endcan
                                                    @else
                                                        @can('update', $user)
                                                            <a href="{{ route('user.edit', $user) }}" class="btn btn-default btn-xs">Edit</a>
                                                        @endcan
                                                        @if (auth()->user()->isNot($user) && auth()->user()->can('delete', $user))
                                                            <form action="{{ route('user.destroy', $user) }}" method="POST" class="d-inline m-0"
                                                                onsubmit="return confirm(@js('Arsipkan user '.$user->nama_lengkap.'?'))">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-default btn-xs text-danger">Arsipkan</button>
                                                            </form>
                                                        @endif
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="13" class="text-center text-muted py-4">Tidak ada data user.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap">
                        <span class="text-muted small">Total {{ $users->total() }} user</span>
                        {{ $users->links('pagination::bootstrap-4') }}
                    </div>
                </div>
            </div>
        </section>
    </section>

    {{-- Aksi massal --}}
    <form action="{{ route('user.bulk') }}" method="POST" id="bulkPositionForm">
        @csrf
        <input type="hidden" name="action" value="position">
        <div class="modal fade" id="bulkPosition" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Ubah Posisi Massal</h5></div>
                    <div class="modal-body">
                        <p class="text-muted small">Posisi baru diterapkan ke <strong class="selected-count">0</strong> user yang dipilih.</p>
                        <label for="bulk_position_id">Posisi</label>
                        <select class="form-control select2" id="bulk_position_id" name="position_id" style="width: 100%;" required>
                            <option value="">-- Pilih Posisi --</option>
                            @foreach ($positions as $position)
                                <option value="{{ $position->id }}">{{ $position->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Terapkan</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <form action="{{ route('user.bulk') }}" method="POST" id="bulkApprovalForm">
        @csrf
        <input type="hidden" name="action" value="approval">
        <div class="modal fade" id="bulkApproval" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Ubah Approval Line Massal</h5></div>
                    <div class="modal-body">
                        <p class="text-muted small">Atasan baru diterapkan ke <strong class="selected-count">0</strong> user yang dipilih. User yang akan membentuk siklus approval dilewati.</p>
                        <label for="bulk_approval_id">Approval Line</label>
                        <select class="form-control select2" id="bulk_approval_id" name="approval_id" style="width: 100%;" required>
                            <option value="">-- Pilih Atasan --</option>
                            @foreach ($bulkApprovers as $approver)
                                <option value="{{ $approver->id }}">{{ $approver->nama_lengkap }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Terapkan</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @can('import', App\Models\User::class)
        <form action="{{ route('user.import') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal fade" id="importExcel" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header"><h5 class="modal-title">Import User (Excel)</h5></div>
                        <div class="modal-body">
                            <input class="form-control" type="file" name="file" accept=".xlsx,.xls,.csv" required>
                            <small class="text-muted d-block mt-2">Gunakan kolom dari Download Template. User dicocokkan lewat id_karyawan lalu username; area, divisi, dan posisi baru dibuat otomatis. Password kosong untuk user baru memakai password default.</small>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">Import</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <form action="{{ route('user.import-json') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal fade" id="importJson" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header"><h5 class="modal-title">Import User dari File JSON (Talenta)</h5></div>
                        <div class="modal-body">
                            <input class="form-control" type="file" name="file" accept=".json,.txt" required>
                            <small class="text-muted d-block mt-2">User yang sudah ada hanya diperbarui email dan No. HP-nya. User baru mengikuti pola karyawan dengan posisi yang sama; bila belum ada, organization menjadi Divisi, title menjadi Posisi, dan jabatannya STAFF. User baru mendapat password acak dan login lewat OTP WhatsApp.</small>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">Import JSON</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    @endcan
@endsection

@section('footer')
<script>
    $(function () {
        function selectedIds() {
            return $('.row-check:checked').map(function () { return this.value; }).get();
        }

        function refreshSelection() {
            const count = selectedIds().length;
            $('#selectedCount, .selected-count').text(count);
            $('.bulk-action').prop('disabled', count === 0);
        }

        $('#checkAll').on('change', function () {
            $('.row-check').prop('checked', this.checked);
            refreshSelection();
        });
        $('.row-check').on('change', refreshSelection);

        $('#bulkPositionForm, #bulkApprovalForm').on('submit', function () {
            const $form = $(this);
            $form.find('input[name="ids[]"]').remove();
            selectedIds().forEach(function (id) {
                $form.append($('<input type="hidden" name="ids[]">').val(id));
            });
        });
    });
</script>
@endsection
