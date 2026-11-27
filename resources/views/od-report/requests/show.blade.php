@extends('layout.main_template')

{{-- Detail Permintaan Masuk ala helpdesk: isi pengajuan dan status tiap dokumen di kiri, data tiket dan pemohon di kanan,
     komentar untuk pemohon di bawah. Komentar memakai form terpisah (#commentForm) supaya tidak ikut menyimpan perubahan. --}}
@php
    $user = auth()->user();
    $isLeader = $user->can('manage-od-report');
    $r = $documentRequest;
    $stageNames = App\Models\OdDocument::REQUEST_STAGES;
    $finalStage = App\Models\OdDocument::STAGE_DONE;
    $stage = $r->stage();
    $priority = $r->topPriority();
    $canStart = $r->processes->contains(fn ($p) => $p->stage === App\Models\OdDocument::STAGE_RECEIVED && ! $p->status->isCancel() && $p->canBeEditedBy($user));
    $stageBadge = fn (string $name) => match ($name) {
        App\Models\OdDocument::STAGE_DONE => 'badge-success',
        App\Models\OdDocument::STAGE_PROGRESS => 'badge-info',
        App\Models\OdDocument::STAGE_CANCELLED => 'badge-secondary',
        default => 'badge-warning',
    };
@endphp

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header :title="$r->processes->count() === 1 ? $r->processes->first()->title : $r->code" :subtitle="$r->code.' · '.$r->category->label()"
            :breadcrumbs="['Permintaan Masuk' => route('od-report.requests.index'), $r->code => null]">
            <x-slot:actions>
                @if ($canStart)
                    <button type="submit" form="requestForm" name="aksi" value="proses" class="btn btn-primary"><i class="fas fa-play"></i> Proses</button>
                @endif
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

        <form action="{{ route('od-report.requests.comment', $r) }}" method="POST" id="commentForm">@csrf</form>

        <form action="{{ route('od-report.requests.update', $r) }}" method="POST" enctype="multipart/form-data" id="requestForm">
            @csrf
            @method('PUT')
            <input type="hidden" name="updated_at" value="{{ $r->updated_at->toIso8601String() }}">

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-body">
                            <div class="form-row">
                                <div class="col-md-6 mb-3">
                                    <div class="ticket-field-label">Kategori</div>
                                    <div class="ticket-field-value">{{ $r->category->label() }}</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="ticket-field-label">Badan usaha</div>
                                    <div class="ticket-field-value">{{ $r->badan_usaha ?: '-' }}</div>
                                </div>
                            </div>

                            @if ($r->processes->count() > 1)
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                    <strong>Dokumen ({{ $r->processes->count() }})</strong>
                                    <select class="custom-select custom-select-sm ml-sm-auto" id="applyStage" aria-label="Status untuk semua dokumen" style="width: 210px; max-width: 100%;">
                                        <option value="">Terapkan status ke semua…</option>
                                        @foreach ($stageNames as $name)
                                            <option value="{{ $name }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            @foreach ($r->processes as $process)
                                @php($editable = $process->canBeEditedBy($user))
                                @php($template = $process->attachments->firstWhere('kind', App\Models\OdDocumentAttachment::KIND_TEMPLATE))
                                @php($lampiran = $process->attachments->where('kind', App\Models\OdDocumentAttachment::KIND_LAMPIRAN))
                                <div class="ticket-doc" data-process>
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                        <strong class="min-w-0 text-break">@if ($r->processes->count() > 1){{ $loop->iteration }}. @endif{{ $process->title }}</strong>
                                        <span class="badge flex-shrink-0 {{ $stageBadge($process->publicStage()) }}">{{ $process->publicStage() }}</span>
                                    </div>

                                    @if ($process->jenis_pengajuan)
                                        <div class="ticket-field-label">Keterangan</div>
                                        <div class="ticket-field-value mb-3">{{ $r->category->name }} {{ strtolower($process->jenis_pengajuan) }}</div>
                                    @endif
                                    @if ($process->tujuan)
                                        <div class="ticket-field-label">Uraian</div>
                                        <div class="ticket-field-value text-break mb-3" style="white-space: pre-line;">{{ $process->tujuan }}</div>
                                    @endif
                                    @if ($process->sistem)
                                        <p class="small text-muted mb-3 text-break">Sistem/alat: {{ $process->sistem }}</p>
                                    @endif

                                    @if ($template || $lampiran->isNotEmpty())
                                        <div class="ticket-field-label">{{ $template ? 'Template terisi' : 'Lampiran' }}</div>
                                        <div class="d-flex flex-wrap gap-2 mb-3">
                                            @foreach (array_filter([$template, ...$lampiran]) as $file)
                                                <a href="{{ route('od-report.requests.attachment', [$r, $file]) }}" class="ticket-file mw-100 text-break flex-wrap">
                                                    <i class="fas {{ $file->icon() }}"></i> {{ $file->name }} <span class="text-muted">{{ $file->sizeLabel() }}</span>
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if ($process->builderDraft && $process->builderDraft->canBeManagedBy($user))
                                        <a href="{{ route('document-builder.edit', $process->builderDraft) }}" class="btn btn-default btn-xs mb-3"><i class="fas fa-file-alt"></i> Lihat draf builder</a>
                                    @elseif ($editable && $process->category?->is_active && $process->status->isOpen())
                                        <a href="{{ route('document-builder.form', ['category' => $process->category, 'permintaan' => $process->id]) }}" class="btn btn-default btn-xs mb-3"><i class="fas fa-magic"></i> Susun di Builder</a>
                                    @endif

                                    <div class="form-row">
                                        <div class="col-md-4 mb-2">
                                            <label class="ticket-field-label" for="stage-{{ $process->id }}">Status</label>
                                            <select class="custom-select custom-select-sm" id="stage-{{ $process->id }}" name="proses[{{ $process->id }}][stage]" data-stage data-current="{{ $process->stage }}" @disabled(! $editable)>
                                                @foreach ($stageNames as $name)
                                                    <option value="{{ $name }}" @selected(old("proses.{$process->id}.stage", $process->stage) === $name)>{{ $name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <label class="ticket-field-label" for="prioritas-{{ $process->id }}">Prioritas</label>
                                            <select class="custom-select custom-select-sm" id="prioritas-{{ $process->id }}" name="proses[{{ $process->id }}][prioritas]" @disabled(! $editable)>
                                                @foreach (App\Models\OdDocument::PRIORITAS as $option)
                                                    <option value="{{ $option }}" @selected($process->prioritas === $option)>{{ $option }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-5 mb-2">
                                            <label class="ticket-field-label" for="pic-{{ $process->id }}">PIC</label>
                                            @if ($isLeader)
                                                <select class="custom-select custom-select-sm" id="pic-{{ $process->id }}" name="proses[{{ $process->id }}][pic_user_id]">
                                                    <option value="">Belum ada PIC</option>
                                                    @foreach ($team as $member)
                                                        <option value="{{ $member->id }}" @selected($process->pic_user_id === $member->id)>{{ $member->nama_lengkap }}</option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <div class="small pt-1">{{ $process->pic->nama_lengkap ?? '-' }}</div>
                                            @endif
                                        </div>
                                    </div>

                                    @if ($process->hasFile())
                                        <div class="small">
                                            <a href="{{ route('od-report.documents.file', $process) }}" target="_blank" class="text-break"><i class="fas fa-paperclip"></i> Dokumen rilis: {{ $process->file_name }}</a>
                                        </div>
                                    @endif
                                    @if ($editable)
                                        {{-- Muncul saat status Selesai dipilih: file dokumen yang dikirim ke WhatsApp pemohon. --}}
                                        <div data-release-file class="mt-2" hidden>
                                            <label class="ticket-field-label" for="release-file-{{ $process->id }}">{{ $process->hasFile() ? 'Ganti dokumen rilis (opsional)' : 'Dokumen rilis' }}</label>
                                            {{-- Pesan error di bawah tetap tampil walau kotak ini tersembunyi, jadi :error-key="false". --}}
                                            <x-file-dropzone name="proses[{{ $process->id }}][file]" id="release-file-{{ $process->id }}" compact :max-mb="50"
                                                :extensions="['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx']" types="PDF, Word, Excel, atau PowerPoint"
                                                :error-key="false" :class="$errors->has('proses.'.$process->id.'.file') ? 'is-invalid' : ''"
                                                aria-label="Dokumen rilis {{ $process->title }}" />
                                        </div>
                                    @endif
                                    @error("proses.{$process->id}.file")<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Komentar</h3></div>
                        <div class="card-body">
                            <label for="commentBody" class="ticket-field-label">Tambah komentar <span class="text-muted font-weight-normal">(tampil di halaman lacak & dikirim ke WhatsApp pemohon)</span></label>
                            <textarea class="form-control @error('body') is-invalid @enderror" id="commentBody" name="body" rows="2" maxlength="{{ App\Models\DocumentRequestComment::MAX_LENGTH }}" form="commentForm" required>{{ old('body') }}</textarea>
                            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="text-right mt-2">
                                <button type="submit" form="commentForm" class="btn btn-default btn-sm"><i class="far fa-comment-dots"></i> Kirim komentar</button>
                            </div>
                        </div>
                        @if ($r->comments->isNotEmpty())
                            <div class="card-body p-0 border-top">
                                @foreach ($r->comments as $comment)
                                    <div class="px-3 py-2 border-bottom small">
                                        <div class="d-flex flex-wrap justify-content-between gap-2">
                                            <strong>{{ $comment->user?->nama_lengkap ?? 'Tim OD' }}</strong>
                                            <span class="text-muted">{{ $comment->created_at->format('d/m/Y H:i') }}</span>
                                        </div>
                                        <div class="text-break" style="white-space: pre-line;">{{ $comment->body }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-body">
                            <dl class="ticket-meta mb-0">
                                <dt>Status</dt>
                                <dd><span class="badge {{ $stageBadge($stage) }}">{{ $stage }}</span> <span class="small text-muted">{{ $r->progress() }}%</span></dd>
                                <dt>Prioritas</dt>
                                <dd>@if ($priority)<span class="badge {{ App\Models\DocumentRequest::priorityBadgeClass($priority) }}">{{ $priority }}</span>@else - @endif</dd>
                                <dt>Dari</dt>
                                <dd class="text-break">
                                    {{ $r->nama }} @if ($r->user)<span class="badge badge-light">akun</span>@endif
                                    <div class="small text-muted">{{ collect([$r->jabatan, $r->divisi, $r->departemen, $r->badan_usaha])->filter()->implode(' · ') }}</div>
                                </dd>
                                @if ($r->helpdesk_ticket_number)
                                    <dt>Tiket Helpdesk</dt>
                                    <dd>{{ $r->helpdesk_ticket_number }}</dd>
                                @endif
                                @if ($r->atasan)
                                    <dt>Atasan</dt>
                                    <dd>{{ $r->atasan }}</dd>
                                @endif
                                <dt>Nomor WhatsApp pemohon</dt>
                                <dd><a href="{{ $r->whatsappLink() }}" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i> {{ $r->whatsapp }}</a></dd>
                                <dt>Email</dt>
                                <dd class="text-break"><a href="mailto:{{ $r->email }}">{{ $r->email }}</a></dd>
                                <dt>Dibuat pada</dt>
                                <dd>{{ $r->created_at->format('d/m/Y H:i') }}</dd>
                                <dt>Diperbarui pada</dt>
                                <dd>{{ $r->updated_at->format('d/m/Y H:i') }}</dd>
                            </dl>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            @if ($r->obs_tanggal_1)
                                <div class="form-group">
                                    <label for="jadwal_observasi">Jadwal observasi final</label>
                                    <input type="date" class="form-control" id="jadwal_observasi" name="jadwal_observasi"
                                        value="{{ old('jadwal_observasi', $r->jadwal_observasi?->toDateString()) }}">
                                    <div class="d-flex flex-wrap gap-2 mt-2">
                                        <button type="button" class="btn btn-default btn-xs" data-use-date="{{ $r->obs_tanggal_1->toDateString() }}">Pilihan 1 ({{ $r->obs_tanggal_1->format('d/m') }})</button>
                                        @if ($r->obs_tanggal_2)
                                            <button type="button" class="btn btn-default btn-xs" data-use-date="{{ $r->obs_tanggal_2->toDateString() }}">Pilihan 2 ({{ $r->obs_tanggal_2->format('d/m') }})</button>
                                        @endif
                                    </div>
                                    @if ($r->obs_catatan)<small class="form-text text-muted text-break">Catatan pemohon: {{ $r->obs_catatan }}</small>@endif
                                </div>
                            @else
                                <input type="hidden" name="jadwal_observasi" value="{{ $r->jadwal_observasi?->toDateString() }}">
                            @endif
                            <div class="form-group">
                                <label for="catatan_admin">Catatan internal <span class="text-muted font-weight-normal">(tidak terlihat pemohon)</span></label>
                                <textarea class="form-control" id="catatan_admin" name="catatan_admin" rows="3" maxlength="2000">{{ old('catatan_admin', $r->catatan_admin) }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">Simpan perubahan</button>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Riwayat</h3></div>
                        <div class="card-body p-0">
                            @foreach ($r->histories as $history)
                                <div class="px-3 py-2 border-bottom small">
                                    <div class="d-flex flex-wrap justify-content-between gap-2">
                                        <strong>{{ $history->status }}</strong>
                                        <span class="text-muted">{{ $history->created_at->format('d/m H:i') }}</span>
                                    </div>
                                    @if ($history->process && $r->processes->count() > 1)<div class="text-muted text-break">{{ $history->process->title }}</div>@endif
                                    @if ($history->note)<div class="text-break">{{ $history->note }}</div>@endif
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
    // Input dokumen rilis hanya muncul saat status Selesai dipilih.
    const finalStage = @js($finalStage);
    function syncFile(select) {
        const field = select.closest('[data-process]').querySelector('[data-release-file]');
        if (field) {
            field.hidden = !(select.value === finalStage && select.dataset.current !== finalStage);
        }
    }
    document.querySelectorAll('[data-stage]').forEach(select => {
        select.addEventListener('change', () => syncFile(select));
        syncFile(select);
    });

    document.getElementById('applyStage')?.addEventListener('change', function () {
        if (!this.value) return;
        document.querySelectorAll('[data-stage]:not([disabled])').forEach(select => {
            select.value = this.value;
            if (window.jQuery) { $(select).trigger('change.select2'); }
            syncFile(select);
        });
    });
    document.querySelectorAll('[data-use-date]').forEach(button => button.addEventListener('click', () => {
        document.getElementById('jadwal_observasi').value = button.dataset.useDate;
    }));
</script>
@endsection
