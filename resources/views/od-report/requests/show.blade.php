@extends('layout.main_template')

@php
    $user = auth()->user();
    $isLeader = $user->can('manage-od-report');
    $r = $documentRequest;
    $stageNames = $r->category->stageNames();
@endphp

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header :title="$r->code" :subtitle="$r->category->label().' · '.$r->stage().' · '.$r->progress().'%'"
            :breadcrumbs="['Permintaan Masuk' => route('od-report.requests.index'), $r->code => null]">
            <x-slot:actions>
                <a href="{{ route('document-request.track', ['kode' => $r->code]) }}" class="btn btn-default" target="_blank"><i class="fas fa-eye"></i> Tampilan pemohon</a>
                @if ($isLeader)
                    <form action="{{ route('od-report.requests.destroy', $r) }}" method="POST" class="m-0" onsubmit="return confirm('Hapus pengajuan {{ $r->code }} beserta prosesnya?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-default text-danger"><i class="fas fa-trash"></i> Hapus</button>
                    </form>
                @endif
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />

        <form action="{{ route('od-report.requests.update', $r) }}" method="POST">
            @csrf
            @method('PUT')
            <input type="hidden" name="updated_at" value="{{ $r->updated_at->toIso8601String() }}">

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex align-items-center">
                            <h3 class="card-title">Proses ({{ $r->processes->count() }})</h3>
                            <div class="ml-auto d-flex gap-2 align-items-center">
                                <select class="custom-select custom-select-sm" id="applyStage" aria-label="Tahap untuk semua proses" style="width: 210px;">
                                    <option value="">Terapkan tahap ke semua…</option>
                                    @foreach ($stageNames as $stage)
                                        <option value="{{ $stage }}">{{ $stage }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            @foreach ($r->processes as $process)
                                @php($editable = $process->canBeEditedBy($user))
                                <div class="p-3 border-bottom">
                                    <div class="d-flex justify-content-between gap-2">
                                        <strong>{{ $loop->iteration }}. {{ $process->title }}</strong>
                                        <span class="badge {{ $process->status->badgeClass() }}">{{ $process->status->name }}</span>
                                    </div>
                                    <p class="small mb-2" style="white-space: pre-line;">{{ $process->tujuan }}</p>
                                    @if ($process->sistem)<p class="small text-muted mb-2">Sistem/alat: {{ $process->sistem }}</p>@endif
                                    <div class="form-row">
                                        <div class="col-md-5 mb-2">
                                            <select class="custom-select custom-select-sm" name="proses[{{ $process->id }}][stage]" data-stage @disabled(! $editable) aria-label="Tahap {{ $process->title }}">
                                                @foreach ($stageNames as $stage)
                                                    <option value="{{ $stage }}" @selected($process->stage === $stage)>{{ $loop->iteration }}. {{ $stage }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <select class="custom-select custom-select-sm" name="proses[{{ $process->id }}][prioritas]" @disabled(! $editable) aria-label="Prioritas {{ $process->title }}">
                                                @foreach (App\Models\OdDocument::PRIORITAS as $priority)
                                                    <option value="{{ $priority }}" @selected($process->prioritas === $priority)>{{ $priority }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            @if ($isLeader)
                                                <select class="custom-select custom-select-sm" name="proses[{{ $process->id }}][pic_user_id]" aria-label="PIC {{ $process->title }}">
                                                    <option value="">Belum ada PIC</option>
                                                    @foreach ($team as $member)
                                                        <option value="{{ $member->id }}" @selected($process->pic_user_id === $member->id)>{{ $member->nama_lengkap }}</option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <span class="small text-muted">PIC: {{ $process->pic->nama_lengkap ?? '-' }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><h3 class="card-title">{{ $r->obs_tanggal_1 ? 'Observasi & catatan' : 'Catatan' }}</h3></div>
                        <div class="card-body">
                            @if ($r->obs_tanggal_1)
                            <div class="form-group">
                                <label for="jadwal_observasi">Jadwal observasi final</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <input type="date" class="form-control" id="jadwal_observasi" name="jadwal_observasi" style="max-width: 200px;"
                                        value="{{ old('jadwal_observasi', $r->jadwal_observasi?->toDateString()) }}">
                                    <button type="button" class="btn btn-default btn-sm" data-use-date="{{ $r->obs_tanggal_1->toDateString() }}">Pakai pilihan 1 ({{ $r->obs_tanggal_1->format('d/m') }})</button>
                                    @if ($r->obs_tanggal_2)
                                        <button type="button" class="btn btn-default btn-sm" data-use-date="{{ $r->obs_tanggal_2->toDateString() }}">Pakai pilihan 2 ({{ $r->obs_tanggal_2->format('d/m') }})</button>
                                    @endif
                                </div>
                                @if ($r->obs_catatan)<small class="form-text text-muted">Catatan pemohon: {{ $r->obs_catatan }}</small>@endif
                            </div>
                            @else
                                <input type="hidden" name="jadwal_observasi" value="{{ $r->jadwal_observasi?->toDateString() }}">
                            @endif
                            <div class="form-group">
                                <label for="catatan_publik">Catatan untuk pemohon <span class="text-muted font-weight-normal">(masuk riwayat & WA)</span></label>
                                <input type="text" class="form-control" id="catatan_publik" name="catatan_publik" maxlength="300" value="{{ old('catatan_publik') }}">
                            </div>
                            <div class="form-group mb-0">
                                <label for="catatan_admin">Catatan internal</label>
                                <textarea class="form-control" id="catatan_admin" name="catatan_admin" rows="3" maxlength="2000">{{ old('catatan_admin', $r->catatan_admin) }}</textarea>
                            </div>
                        </div>
                        <div class="card-footer text-right">
                            <button type="submit" class="btn btn-primary">Simpan perubahan</button>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Pemohon</h3></div>
                        <div class="card-body small">
                            <p class="mb-1"><strong>{{ $r->nama }}</strong> @if ($r->user)<span class="badge badge-light">akun</span>@endif</p>
                            <p class="mb-1">{{ $r->jabatan }} · {{ $r->divisi }}</p>
                            <p class="mb-1">{{ $r->departemen }}</p>
                            @if ($r->atasan)<p class="mb-1">Atasan: {{ $r->atasan }}</p>@endif
                            <p class="mb-2">Diajukan {{ $r->created_at->format('d/m/Y H:i') }}</p>
                            <a href="{{ $r->whatsappLink() }}" target="_blank" rel="noopener" class="btn btn-default btn-sm"><i class="fab fa-whatsapp"></i> {{ $r->whatsapp }}</a>
                            <a href="mailto:{{ $r->email }}" class="btn btn-default btn-sm"><i class="far fa-envelope"></i> Email</a>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Riwayat</h3></div>
                        <div class="card-body p-0">
                            @foreach ($r->histories as $history)
                                <div class="px-3 py-2 border-bottom small">
                                    <div class="d-flex justify-content-between gap-2">
                                        <strong>{{ $history->status }}</strong>
                                        <span class="text-muted">{{ $history->created_at->format('d/m H:i') }}</span>
                                    </div>
                                    @if ($history->note)<div>{{ $history->note }}</div>@endif
                                    @if ($history->user)<div class="text-muted">{{ $history->user->nama_lengkap }}</div>@endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>
@endsection

@section('footer')
<script>
    document.getElementById('applyStage').addEventListener('change', function () {
        if (!this.value) return;
        document.querySelectorAll('[data-stage]:not([disabled])').forEach(select => select.value = this.value);
    });
    document.querySelectorAll('[data-use-date]').forEach(button => button.addEventListener('click', () => {
        document.getElementById('jadwal_observasi').value = button.dataset.useDate;
    }));
</script>
@endsection
