@extends('layout.main_template')

@php
    $stageNames = array_keys(App\Models\OdDocument::STAGES);
    $last = count($stageNames) - 1;
@endphp

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Lacak Permintaan Dokumen" subtitle="Masukkan kode tiket, mis. IK-20261118-AB12" eyebrow="Permintaan Dokumen">
            <x-slot:actions>
                <a href="{{ route('document-request.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Ajukan baru</a>
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid" style="max-width: 860px;">
        <x-flash />

        @if (session('submitted') && $documentRequest)
            <div class="alert alert-success">
                <strong>Pengajuan terkirim.</strong> Simpan kode <strong>{{ $documentRequest->code }}</strong> untuk melacak.
                <div class="mt-2 d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-light btn-sm" data-copy="{{ route('document-request.track', ['kode' => $documentRequest->code]) }}"><i class="far fa-copy"></i> Salin link lacak</button>
                    @if ($adminWhatsapp)
                        <a class="btn btn-light btn-sm" target="_blank" rel="noopener"
                            href="https://wa.me/{{ $adminWhatsapp }}?text={{ rawurlencode("Halo tim OD, saya {$documentRequest->nama} mengajukan permintaan dokumen {$documentRequest->code}.") }}">
                            <i class="fab fa-whatsapp"></i> Kabari tim OD
                        </a>
                    @endif
                </div>
            </div>
        @endif

        <form action="{{ route('document-request.track') }}" method="GET" class="card">
            <div class="card-body d-flex gap-2">
                <input type="text" name="kode" class="form-control" value="{{ $code }}" placeholder="IK-YYYYMMDD-XXXX" aria-label="Kode tiket" required style="text-transform: uppercase;">
                <button type="submit" class="btn btn-primary">Lacak</button>
            </div>
            <div class="card-footer small" id="myTickets" hidden>Pengajuan di perangkat ini: <span></span></div>
        </form>

        @if ($code !== '' && ! $documentRequest)
            <div class="alert alert-warning">Kode <strong>{{ $code }}</strong> tidak ditemukan. Periksa kembali penulisannya.</div>
        @endif

        @if ($documentRequest)
            @php
                $progress = $documentRequest->progress();
                $priority = $documentRequest->topPriority();
                $released = $documentRequest->releasedCount();
            @endphp
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <div>
                            <div class="text-muted small">{{ $documentRequest->code }}</div>
                            <h3 class="h5 mb-1">{{ $documentRequest->stage() }}</h3>
                            <div class="small text-muted">
                                {{ $documentRequest->nama }} · {{ $documentRequest->departemen }} · WA {{ $documentRequest->maskedWhatsapp() }} · {{ $documentRequest->maskedEmail() }}
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="h4 mb-0">{{ $progress }}%</div>
                            <div class="small text-muted">{{ $released }} dari {{ $documentRequest->processes->count() }} proses Release</div>
                        </div>
                    </div>
                    <div class="progress mt-3" style="height: 8px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: {{ $progress }}%" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="d-flex flex-wrap gap-3 small text-muted mt-2">
                        @if ($priority)<span>Prioritas <span class="badge {{ App\Models\DocumentRequest::priorityBadgeClass($priority) }}">{{ $priority }}</span></span>@endif
                        <span>Diajukan {{ $documentRequest->created_at->format('d/m/Y H:i') }}</span>
                        <span>Diperbarui {{ $documentRequest->updated_at->format('d/m/Y H:i') }}</span>
                        @if ($documentRequest->jadwal_observasi)
                            <span class="text-dark"><i class="far fa-calendar-check"></i> Observasi {{ $documentRequest->jadwal_observasi->locale('id')->translatedFormat('l, d F Y') }}</span>
                        @endif
                    </div>
                </div>
            </div>

            @if ($released && filled($cloud['url']))
                <div class="alert alert-success">
                    <strong>Dokumen sudah rilis.</strong> Unduh di <a href="{{ $cloud['url'] }}" target="_blank" rel="noopener" class="alert-link">{{ $cloud['url'] }}</a>
                    @if ($cloud['user']) · user <code>{{ $cloud['user'] }}</code>@endif
                    @if ($cloud['password']) · password <code>{{ $cloud['password'] }}</code>@endif
                </div>
            @endif

            @foreach ($documentRequest->processes as $process)
                @php($stage = $process->publicStage())
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between gap-2">
                            <strong>{{ $loop->iteration }}. {{ $process->title }}</strong>
                            <span class="badge {{ $stage === App\Models\OdDocument::STAGE_RELEASE ? 'badge-success' : ($stage === App\Models\OdDocument::STAGE_CANCELLED ? 'badge-secondary' : 'badge-info') }}">{{ $stage }}</span>
                        </div>
                        <div class="d-flex gap-1 mt-2" aria-hidden="true">
                            @foreach ($stageNames as $index => $name)
                                <div class="flex-fill rounded {{ $stage !== App\Models\OdDocument::STAGE_CANCELLED && $index <= $process->stageIndex() ? 'bg-success' : 'bg-light' }}" style="height: 6px;" title="{{ $name }}"></div>
                            @endforeach
                        </div>
                        @if ($stage !== App\Models\OdDocument::STAGE_CANCELLED)
                            <div class="small text-muted mt-2">{{ App\Models\OdDocument::STAGES[$process->stage] ?? '' }}</div>
                        @endif
                    </div>
                </div>
            @endforeach

            <div class="card">
                <div class="card-header"><h3 class="card-title">Riwayat</h3></div>
                <div class="card-body p-0">
                    @foreach ($documentRequest->histories as $history)
                        <div class="px-3 py-2 border-bottom small">
                            <div class="d-flex justify-content-between gap-2">
                                <strong>{{ $history->status }}</strong>
                                <span class="text-muted">{{ $history->created_at->format('d/m/Y H:i') }}</span>
                            </div>
                            @if ($history->note)<div>{{ $history->note }}</div>@endif
                        </div>
                    @endforeach
                </div>
            </div>

            <details class="card card-body small mb-4">
                <summary class="font-weight-bold">Arti setiap tahap</summary>
                <ol class="mt-2 mb-0 pl-3">
                    @foreach (App\Models\OdDocument::STAGES as $name => $info)
                        <li class="mb-1"><strong>{{ $name }}</strong>: {{ $info }}</li>
                    @endforeach
                </ol>
            </details>
        @endif
    </div>
</section>
@endsection

@section('footer')
<script>
    (function () {
        const KEY = 'ik_my_tickets';
        let tickets = [];
        try { tickets = JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { tickets = []; }

        @if ($documentRequest)
            tickets = [@json($documentRequest->code), ...tickets.filter(t => t !== @json($documentRequest->code))].slice(0, 20);
            try { localStorage.setItem(KEY, JSON.stringify(tickets)); } catch (e) { /* penyimpanan diblokir */ }
        @endif

        const box = document.getElementById('myTickets');
        if (tickets.length) {
            const base = @json(route('document-request.track'));
            box.querySelector('span').innerHTML = tickets.map(t => `<a href="${base}?kode=${encodeURIComponent(t)}" class="mr-2">${t.replace(/[^A-Z0-9-]/g, '')}</a>`).join('');
            box.hidden = false;
        }

        document.querySelectorAll('[data-copy]').forEach(button => button.addEventListener('click', () => {
            navigator.clipboard.writeText(button.dataset.copy).then(() => { button.innerHTML = '<i class="fas fa-check"></i> Disalin'; });
        }));
    })();
</script>
@endsection
