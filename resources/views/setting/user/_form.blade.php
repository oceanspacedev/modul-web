@php
    $isEdit = $user->exists;
    $selectedRoleId = (string) old('role_id', $user->jabatan?->id);
    $selectedApprovalId = (string) old('approval_id', $isEdit ? $user->approval_id : ($approvalLocked ? auth()->id() : ''));
    $isGlobalAdminRecord = $isEdit && $user->isAdmin() && $user->data_access !== \App\Models\User::DATA_ACCESS_SELECTED;
    $dataAccess = old('data_access', $isEdit && $user->isAdmin() ? $user->data_access : \App\Models\User::DATA_ACCESS_SELECTED);
    $dataAccessAreaIds = collect(old('data_access_areas', $isEdit ? $user->dataAccessAreas->pluck('id')->all() : []))->map(fn ($id) => (string) $id)->all();
@endphp

@include('layout.partials.validation-errors', ['class' => 'mb-3'])

<div class="card border shadow-sm mb-3">
    <div class="card-header bg-white">
        <h3 class="card-title font-weight-bold">Identitas Pengguna</h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="form-group col-lg-6">
                <label for="employee_id">ID Karyawan</label>
                <input type="text" class="form-control" id="employee_id" name="employee_id" maxlength="255"
                    value="{{ old('employee_id', $user->employee_id) }}" placeholder="Contoh: 25.02.001">
            </div>
            <div class="form-group col-lg-6">
                <label for="nama_lengkap">Nama Lengkap <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" maxlength="255" required
                    value="{{ old('nama_lengkap', $user->nama_lengkap) }}">
            </div>
            <div class="form-group col-lg-6">
                <label for="no_hp">No. HP</label>
                <input type="tel" class="form-control" id="no_hp" name="no_hp" maxlength="20" placeholder="Contoh: 081234567890"
                    value="{{ old('no_hp', $user->no_hp) }}" @disabled(! $canChangePhone)>
                <small class="text-muted">Dipakai sebagai nomor kontak sekaligus login WhatsApp melalui OTP.@unless ($canChangePhone) Hanya admin yang dapat mengubah No. HP.@endunless</small>
            </div>
            <div class="form-group col-lg-6">
                <label for="email">Email</label>
                <input type="email" class="form-control" id="email" name="email" maxlength="255"
                    value="{{ old('email', $user->email) }}" placeholder="contoh@email.com">
            </div>
            <div class="form-group col-lg-6">
                <label for="area_id">Area <span class="text-danger">*</span></label>
                <select class="form-control select2" id="area_id" name="area_id" style="width: 100%;" required>
                    <option value="">-- Pilih Area --</option>
                    @foreach ($areas as $area)
                        <option value="{{ $area->id }}" @selected((string) old('area_id', $user->area_id) === (string) $area->id)>{{ $area->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-lg-6">
                <label for="divisi_id">Divisi <span class="text-danger">*</span></label>
                <select class="form-control select2" id="divisi_id" name="divisi_id" style="width: 100%;" required>
                    <option value="">-- Pilih Divisi --</option>
                    @foreach ($divisis as $divisi)
                        <option value="{{ $divisi->id }}" @selected((string) old('divisi_id', $user->divisi_id) === (string) $divisi->id)>{{ $divisi->name }}</option>
                    @endforeach
                </select>
                <small class="text-muted">Divisi tidak harus sama dengan Area karyawan.</small>
            </div>
            <div class="form-group col-lg-6">
                <label for="role_id">Jabatan <span class="text-danger">*</span></label>
                <select class="form-control" id="role_id" name="role_id" required>
                    <option value="">-- Pilih Jabatan --</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" data-admin="{{ $role->name === 'ADMIN' ? 1 : 0 }}" data-requires-approval="{{ $role->requires_approval ? 1 : 0 }}"
                            @selected($selectedRoleId === (string) $role->id)>{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-lg-6">
                <label for="position_id">Posisi</label>
                <select class="form-control select2" id="position_id" name="position_id" style="width: 100%;">
                    <option value="">-- Pilih Posisi --</option>
                    @foreach ($positions as $position)
                        <option value="{{ $position->id }}" @selected((string) old('position_id', $user->position_id) === (string) $position->id)>{{ $position->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-12">
                <label for="approval_id">Approval Line <span class="text-danger d-none" id="approvalRequired">*</span></label>
                <select class="form-control select2" id="approval_id" name="approval_id" style="width: 100%;" @disabled($approvalLocked)>
                    <option value="">-- Tanpa Approval Line --</option>
                    @foreach ($approverGroups as $group => $options)
                        <optgroup label="{{ $group }}">
                            @foreach ($options as $id => $label)
                                <option value="{{ $id }}" @selected($selectedApprovalId === (string) $id)>{{ $label }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @if ($approvalLocked)
                    <input type="hidden" name="approval_id" value="{{ auth()->id() }}">
                    <small class="text-muted">Karyawan baru yang Anda tambahkan otomatis menjadi anggota tim Anda.</small>
                @else
                    <small class="text-muted" id="approvalHelp">Atasan yang menyetujui karyawan ini. Saran otomatis diambil dari Kepala Divisi.</small>
                @endif
            </div>
        </div>
    </div>
</div>

@if ($canManageDataAccess)
    <div class="card border shadow-sm mb-3 d-none" id="dataAccessCard">
        <div class="card-header bg-white">
            <h3 class="card-title font-weight-bold">Akses Data</h3>
            <small class="text-muted d-block mt-1" style="clear: both;">Tentukan badan usaha yang datanya dapat dilihat dan dikelola admin ini.</small>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label class="d-block">Cakupan Badan Usaha <span class="text-danger">*</span></label>
                <div class="btn-group btn-group-toggle" data-toggle="buttons">
                    <label class="btn btn-outline-primary btn-sm {{ $dataAccess === 'all' ? 'active' : '' }}">
                        <input type="radio" name="data_access" value="all" autocomplete="off" @checked($dataAccess === 'all')> Semua Badan Usaha
                    </label>
                    <label class="btn btn-outline-primary btn-sm {{ $dataAccess === 'selected' ? 'active' : '' }}">
                        <input type="radio" name="data_access" value="selected" autocomplete="off" @checked($dataAccess === 'selected')> Badan Usaha Tertentu
                    </label>
                </div>
            </div>
            @unless ($isGlobalAdminRecord)
                <div class="form-group d-none" id="confirmGlobalAccess">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="confirm_global_access" name="confirm_global_access" value="1" @checked(old('confirm_global_access'))>
                        <label class="custom-control-label" for="confirm_global_access">Saya yakin menjadikan karyawan ini super admin</label>
                    </div>
                    <small class="text-muted">Super admin melihat dan mengelola data karyawan di semua badan usaha, dan dapat memberikan jabatan ADMIN kepada karyawan lain. Pilih Badan Usaha Tertentu bila admin cukup mengelola badan usaha tertentu.</small>
                </div>
            @endunless
            <div class="form-group mb-0" id="dataAccessAreasGroup">
                <label for="data_access_areas">Badan Usaha <span class="text-danger">*</span></label>
                <select class="form-control select2" id="data_access_areas" name="data_access_areas[]" multiple style="width: 100%;">
                    @foreach ($allAreas as $area)
                        <option value="{{ $area->id }}" @selected(in_array((string) $area->id, $dataAccessAreaIds, true))>{{ $area->name }}</option>
                    @endforeach
                </select>
                <small class="text-muted">Admin melihat karyawan di badan usaha ini, ditambah anggota timnya sendiri.</small>
            </div>
        </div>
    </div>
@endif

<div class="row">
    <div class="col-lg-6">
        <div class="card border shadow-sm mb-3">
            <div class="card-header bg-white">
                <h3 class="card-title font-weight-bold">Login</h3>
            </div>
            <div class="card-body">
                @unless ($canChangeCredentials)
                    <div class="alert alert-light border small">Data login karyawan ini hanya dapat diubah admin yang cakupannya meliputi seluruh tim karyawan tersebut.</div>
                @endunless
                <div class="form-group">
                    <label for="username">Username <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="username" name="username" maxlength="255" required
                        value="{{ old('username', $user->username) }}" @disabled(! $canChangeCredentials)>
                    <small class="text-muted">Username tidak boleh mengandung spasi.</small>
                </div>
                <div class="form-group mb-0">
                    <label for="password">Password @unless ($isEdit)<span class="text-danger">*</span>@endunless</label>
                    <input type="password" class="form-control" id="password" name="password" maxlength="255" autocomplete="new-password"
                        placeholder="{{ $isEdit ? 'Kosongkan jika tidak diubah' : 'Masukkan password' }}" @required(! $isEdit) @disabled(! $canChangeCredentials)>
                </div>
            </div>
        </div>
    </div>
    @if ($canManageTrainer)
        <div class="col-lg-6">
            <div class="card border shadow-sm mb-3">
                <div class="card-header bg-white">
                    <h3 class="card-title font-weight-bold">Hak Akses Tambahan</h3>
                </div>
                <div class="card-body">
                    <input type="hidden" name="trainer" value="0">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="trainer" name="trainer" value="1" @checked(old('trainer', $isEdit && $user->isTrainer()))>
                        <label class="custom-control-label" for="trainer">Trainer</label>
                    </div>
                    <small class="text-muted">Memberi akses kelola training, video, dan dokumen di luar hak akses jabatannya.</small>
                </div>
            </div>
        </div>
    @endif
</div>

@section('footer')
<script>
    $(function () {
        const $role = $('#role_id');
        const $approval = $('#approval_id');
        const $area = $('#area_id');
        const isExistingAdmin = @json($isEdit && $user->isAdmin());

        function selectedRole() {
            return $role.find('option:selected');
        }

        function refreshDataAccess(roleChanged) {
            const isAdmin = selectedRole().data('admin') === 1;
            $('#dataAccessCard').toggleClass('d-none', !isAdmin);
            $('#approvalRequired').toggleClass('d-none', selectedRole().data('requires-approval') !== 1);

            // Admin baru: default Badan Usaha Tertentu dengan area karyawan ini (fail closed seperti DnD).
            if (roleChanged && isAdmin && !isExistingAdmin) {
                $('input[name=data_access][value=selected]').prop('checked', true).parent().addClass('active').siblings().removeClass('active');
                if (!$('#data_access_areas').val().length && $area.val()) {
                    $('#data_access_areas').val([$area.val()]).trigger('change');
                }
            }

            const access = $('input[name=data_access]:checked').val();
            $('#dataAccessAreasGroup').toggleClass('d-none', access !== 'selected');
            $('#confirmGlobalAccess').toggleClass('d-none', access !== 'all');
        }

        $role.on('change', () => refreshDataAccess(true));
        $('input[name=data_access]').on('change', () => refreshDataAccess(false));
        refreshDataAccess(false);

        @unless ($approvalLocked)
            $('#divisi_id').on('change', function () {
                const params = { divisi_id: $(this).val() || '' };
                @if ($isEdit)
                    params.user = @json($user->id);
                @endif

                $.getJSON(@json(route('user.approvers')), params, function (data) {
                    const current = $approval.val();
                    $approval.find('optgroup').remove();

                    $.each(data.groups, function (group, options) {
                        const $group = $('<optgroup>').attr('label', group);
                        $.each(options, function (id, label) {
                            $group.append($('<option>').val(id).text(label));
                        });
                        $approval.append($group);
                    });

                    const suggestion = data.suggested ? 'Saran: Kepala Divisi ' + data.suggested.nama_lengkap + '.' : 'Divisi ini belum memiliki Kepala Divisi.';
                    $('#approvalHelp').text(suggestion);
                    $approval.val(current || (data.suggested ? String(data.suggested.id) : '')).trigger('change');
                });
            });
        @endunless
    });
</script>
@endsection
