@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Pengaturan Helpdesk" subtitle="Hubungkan Permintaan Dokumen ke helpdesk" eyebrow="Sistem" />
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />

        @php($connected = $tokenSource !== null)
        <p class="small mb-3 {{ $connected ? 'text-success' : 'text-muted' }}">
            <i class="fas {{ $connected ? 'fa-check-circle' : 'fa-unlink' }}"></i>
            @if ($tokenSource === 'app')
                Kode koneksi aktif sejak {{ $tokenIssuedAt?->timezone(config('app.timezone'))->translatedFormat('d M Y H:i') }}.
            @elseif ($tokenSource === 'env')
                Terhubung lewat HELPDESK_API_TOKEN di .env (cara lama).
            @else
                Belum terhubung ke helpdesk.
            @endif
        </p>

        @if ($previousTokenValidUntil)
            <div class="alert alert-info small">
                <i class="fas fa-history"></i>
                Kode lama masih berlaku sampai <b>{{ $previousTokenValidUntil->timezone(config('app.timezone'))->translatedFormat('d M Y H:i') }}</b>
                atau sampai helpdesk memakai kode baru, jadi helpdesk tidak terputus selama kode baru belum ditempel.
            </div>
        @endif

        <div class="row">
            <div class="col-xl-7">
                @if ($code)
                    <div class="card card-outline card-success">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-key mr-2"></i>Kode koneksi</h3></div>
                        <div class="card-body">
                            <div class="input-group">
                                <input type="text" class="form-control text-monospace" id="helpdesk-code" value="{{ $code }}" readonly aria-label="Kode koneksi">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-success" data-copy-target="helpdesk-code">
                                        <i class="fas fa-copy mr-1"></i><span>Salin</span>
                                    </button>
                                </div>
                            </div>
                            <p class="small text-danger mt-2 mb-0">
                                <i class="fas fa-exclamation-triangle"></i>
                                Kode ini hanya ditampilkan sekali dan berisi kunci akses. Tempel langsung di helpdesk, jangan dibagikan.
                            </p>
                        </div>
                    </div>
                @endif

                <div class="card">
                    <div class="card-header"><h3 class="card-title">Cara menghubungkan</h3></div>
                    <div class="card-body">
                        <ol class="pl-3 mb-3">
                            <li class="mb-1">Klik <b>Buat kode koneksi</b> di bawah, lalu salin kodenya.</li>
                            <li class="mb-1">Di helpdesk buka <b>Pengaturan → Integrasi → Hubungkan sistem</b>.</li>
                            <li class="mb-1">Tempel kode, pilih unit (mis. BUSDEV), lalu klik <b>Hubungkan</b>.</li>
                        </ol>
                        <p class="small text-muted">
                            Helpdesk akan mengimpor kategori dokumen dan mendaftarkan webhook-nya sendiri.
                            Tidak ada yang perlu diubah di .env.
                        </p>

                        <form action="{{ route('helpdesk-settings.issue') }}" method="POST" class="d-inline"
                            @if ($connected) onsubmit="return confirm('Buat kode baru? Kode lama tetap berlaku {{ \App\Support\HelpdeskConnection::GRACE_HOURS }} jam atau sampai kode baru ditempel di helpdesk.')" @endif>
                            @csrf
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-key mr-1"></i>{{ $connected ? 'Buat kode koneksi baru' : 'Buat kode koneksi' }}
                            </button>
                        </form>

                        @if ($tokenSource === 'app')
                            <form action="{{ route('helpdesk-settings.disconnect') }}" method="POST" class="d-inline ml-2"
                                onsubmit="return confirm('Putuskan koneksi helpdesk? Pengajuan baru dari helpdesk akan ditolak sampai kode baru dipakai.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger">
                                    <i class="fas fa-unlink mr-1"></i>Putuskan
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Status</h3></div>
                    <div class="card-body small">
                        <dl class="mb-0">
                            <dt>Alamat API</dt>
                            <dd class="text-monospace text-break">{{ $apiBaseUrl }}</dd>
                            <dd class="text-muted">Diambil dari APP_URL. Pastikan alamat ini bisa dibuka dari server helpdesk.</dd>

                            <dt class="mt-3">Webhook ke helpdesk</dt>
                            @if ($webhookSource === 'app')
                                <dd class="text-success mb-0"><i class="fas fa-check-circle"></i> Didaftarkan helpdesk {{ $webhookRegisteredAt?->timezone(config('app.timezone'))->translatedFormat('d M Y H:i') }}</dd>
                                <dd class="text-monospace text-break">{{ $webhookUrl }}</dd>
                            @elseif ($webhookSource === 'env')
                                <dd class="mb-0">Dari HELPDESK_WEBHOOK_URL di .env (cara lama)</dd>
                                <dd class="text-monospace text-break">{{ $webhookUrl }}</dd>
                            @else
                                <dd class="text-muted">Belum ada. Terisi otomatis saat helpdesk memakai kode koneksi.</dd>
                            @endif

                            @if ($lastDelivery)
                                <dt class="mt-3">Pengiriman webhook terakhir</dt>
                                @if ($lastDelivery['error'] === null)
                                    <dd class="text-success"><i class="fas fa-check-circle"></i> Berhasil {{ $lastDelivery['at']->timezone(config('app.timezone'))->translatedFormat('d M Y H:i') }}</dd>
                                @else
                                    <dd class="text-danger mb-0"><i class="fas fa-exclamation-circle"></i> Gagal {{ $lastDelivery['at']->timezone(config('app.timezone'))->translatedFormat('d M Y H:i') }}</dd>
                                    <dd class="text-danger">{{ $lastDelivery['error'] }} Pembaruan diulang otomatis; bila terus gagal, buat kode koneksi baru dan tempel di helpdesk.</dd>
                                @endif
                            @endif
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    document.querySelectorAll('[data-copy-target]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.dataset.copyTarget);
            input.select();
            var done = function () { button.querySelector('span').textContent = 'Tersalin'; };
            if (navigator.clipboard) {
                navigator.clipboard.writeText(input.value).then(done, function () { document.execCommand('copy'); done(); });
            } else {
                document.execCommand('copy');
                done();
            }
        });
    });
</script>
@endsection
