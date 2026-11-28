@extends('layout.main_template')

@section('content')
<section class="content-header training-detail-header">
    <div class="container-fluid">
        <a class="training-back-link" href="{{ route('training.show', $training) }}">
            <i class="fas fa-arrow-left" aria-hidden="true"></i> Detail pelatihan
        </a>
        <h1 class="training-title text-break">Aktivitas {{ $training->meetingPlatformLabel() ?? 'Meeting' }}</h1>
        <p class="training-session-meta text-break"><span class="min-w-0 text-break">{{ $training->title }} · {{ $training->training_date->translatedFormat('j F Y') }}</span></p>
    </div>
</section>

<section class="content training-detail">
    <div class="container-fluid">
        <div data-meeting-refresh="flash"><x-flash /></div>

        <section class="card" aria-labelledby="meeting-monitor-title">
            <div class="card-header">
                <h2 class="card-title" id="meeting-monitor-title">Monitoring {{ $provider?->label() ?? 'meeting' }}</h2>
            </div>
            <div class="card-body" data-meeting-monitor
                data-meeting-url="{{ route('training.meeting.show', $training) }}"
                data-join-url="{{ route('training.meeting.join', $training) }}"
                data-start-url="{{ route('training.meeting.monitors.start', $training) }}"
                data-sync-url="{{ route('training.meeting.monitors.sync', [$training, '__MONITOR__']) }}"
                data-end-url="{{ route('training.meeting.monitors.end', [$training, '__MONITOR__']) }}"
                data-csrf-token="{{ csrf_token() }}"
                data-display-name="{{ auth()->user()->nama_lengkap ?: 'Petugas OD' }}">
                @if($meetingAccount && ! $provider->supportsMonitoring())
                    <p class="mb-2">Akun penyelenggara <strong>{{ $meetingAccount->name }}</strong></p>
                    <p class="text-muted">Jam masuk dan keluar peserta ditarik dari catatan {{ $provider->label() }}. Tarik data setelah rapat berakhir; penarikan ulang tidak menggandakan data. Informasikan kepada peserta bahwa kehadirannya dicatat.</p>
                    <form action="{{ route('training.meeting.attendance-sync', $training) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-primary">Tarik Kehadiran dari {{ $provider->label() }}</button>
                    </form>
                @elseif($meetingAccount)
                    <p class="mb-2">Akun penyelenggara <strong>{{ $meetingAccount->name }}</strong></p>
                    <p class="text-muted">Masuk sebelum peserta dan biarkan halaman ini terbuka selama pelatihan. Informasikan kepada peserta bahwa jam masuk, jam keluar, status kamera, dan chat ke semua peserta dicatat.</p>
                    <form data-meeting-join method="POST" class="form-inline align-items-end flex-wrap gap-2 mb-2">
                        <div class="form-group d-block mb-0">
                            <label for="meeting-passcode" class="d-block font-weight-normal mb-1">Passcode rapat</label>
                            <input id="meeting-passcode" class="form-control" data-meeting-passcode type="password" autocomplete="off" maxlength="255" placeholder="Jika diperlukan">
                        </div>
                        <button type="submit" class="btn btn-primary mb-0">Mulai Monitoring</button>
                    </form>
                    <button type="button" class="btn btn-outline-danger btn-sm mb-2" data-meeting-leave hidden>Selesai Monitoring</button>
                    <p data-meeting-status role="status" class="mb-2">Siap bergabung.</p>
                    {{-- Selama monitoring, pindah halaman membuka halaman tujuan di atas halaman ini dan klien meeting
                         tetap berjalan di pemutar mini (public/meeting-player.js). --}}
                    <div class="meeting-player" data-meeting-player aria-label="Pemutar meeting">
                        <div class="meeting-player-bar" data-player-handle>
                            <span class="meeting-player-title text-truncate">
                                <i class="fas fa-video" aria-hidden="true"></i>
                                <span class="text-truncate">{{ $training->title }}</span>
                            </span>
                            <span class="meeting-player-actions">
                                <button type="button" class="meeting-player-btn" data-player-size aria-label="Perbesar pemutar" title="Perbesar pemutar"><i class="fas fa-expand-alt" aria-hidden="true"></i></button>
                                <button type="button" class="meeting-player-btn" data-player-expand aria-label="Kembali ke halaman monitoring" title="Kembali ke halaman monitoring"><i class="fas fa-external-link-alt" aria-hidden="true"></i></button>
                                <button type="button" class="meeting-player-btn is-danger" data-player-leave aria-label="Selesai monitoring" title="Selesai monitoring"><i class="fas fa-phone-slash" aria-hidden="true"></i></button>
                                <button type="button" class="meeting-player-btn" data-player-close aria-label="Tutup pemutar" title="Tutup pemutar"><i class="fas fa-times" aria-hidden="true"></i></button>
                            </span>
                        </div>
                        <div class="meeting-player-body">
                            <div data-meeting-client></div>
                        </div>
                        <p class="meeting-player-status text-truncate" data-player-status role="status" aria-live="off"></p>
                    </div>
                @elseif($meetingCode !== $currentMeeting)
                    <p class="mb-0 text-muted">Riwayat rapat {{ $meetingCode }}.</p>
                @else
                    <p class="mb-0 text-muted">Monitoring meeting nonaktif. <a href="{{ route('training.edit', $training) }}">Atur meeting pelatihan</a> untuk mengaktifkannya.</p>
                @endif
            </div>
        </section>

        <section class="card" id="meeting-log" aria-labelledby="meeting-log-title">
            <div class="card-header">
                <h2 class="card-title" id="meeting-log-title">Riwayat aktivitas</h2>
            </div>
            <div class="card-body">
                <form action="{{ route('training.meeting.show', $training) }}#meeting-log" method="GET" class="form-inline flex-wrap gap-2 mb-3">
                    @if(count($meetingOptions) > 1)
                        <label class="mb-0" for="meeting-history">Rapat</label>
                        <select id="meeting-history" data-native-select class="form-control" name="meeting">
                            @foreach($meetingOptions as $option)
                                <option value="{{ $option }}" @selected($option === $meetingCode)>{{ $option }}{{ $option === $currentMeeting ? ' (tautan aktif)' : '' }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="hidden" name="meeting" value="{{ $meetingCode }}">
                    @endif
                    <label class="sr-only" for="meeting-participant-name">Nama di meeting</label>
                    <input id="meeting-participant-name" class="form-control" name="nama" value="{{ $participantName }}" maxlength="120" placeholder="Cari nama di meeting">
                    <button type="submit" class="btn btn-default">Cari</button>
                </form>
                <div id="meeting-activity-content" data-meeting-refresh="activity" data-url="{{ route('training.meeting.activity', [$training, 'meeting' => $meetingCode, ...request()->only(['nama', 'aktivitas', 'chat'])]) }}">
                    @include('training.detail.meeting-activity')
                </div>
            </div>
        </section>

        <section class="card" id="meeting-chat" aria-labelledby="meeting-chat-title">
            <div class="card-header">
                <h2 class="card-title" id="meeting-chat-title">Chat publik</h2>
            </div>
            <div class="card-body">
                @if($meetingAccount && $provider->supportsMonitoring())
                    <p class="text-muted">Chat ke semua peserta tercatat otomatis selama monitoring berjalan. Impor file TXT hanya untuk sesi yang tidak dimonitor, karena chat yang sudah tercatat akan muncul dua kali.</p>
                @endif
                @if($meetingAccount && $provider->supportsChatImport())
                    <form action="{{ route('training.meeting.chat', $training) }}" method="POST" enctype="multipart/form-data" class="mb-3">
                        @csrf
                        <input type="hidden" name="account_revision" value="{{ $monitorRevision }}">
                        <label for="meeting-chat-file" class="d-block">File TXT chat dari sesi ini</label>
                        <x-file-dropzone name="chat_file" id="meeting-chat-file" :extensions="['txt']" accept=".txt,text/plain"
                            :max-mb="1" compact required />
                        <button type="submit" class="btn btn-default btn-sm mt-2">Impor Chat</button>
                    </form>
                @endif
                <div id="meeting-chat-content" data-meeting-refresh="chat" data-url="{{ route('training.meeting.chat.index', [$training, 'meeting' => $meetingCode, ...request()->only(['chat'])]) }}">
                    @include('training.detail.meeting-chat')
                </div>
            </div>
        </section>
    </div>
</section>

@if($meetingAccount && $provider->supportsMonitoring())
    {{-- Klien pemantauan milik platformnya: memuat SDK dan mengirim aktivitas ke rute monitors. --}}
    <script src="{{ asset('meeting-player.js') }}?v={{ filemtime(public_path('meeting-player.js')) }}"></script>
    @includeIf('training.meeting-clients.'.$provider->key())
    <script>
        (() => {
            const content = document.querySelector('#meeting-activity-content');
            const chat = document.querySelector('#meeting-chat-content');
            let update;
            let chatUpdate;
            document.querySelector('[data-meeting-monitor]').addEventListener('meeting-monitor:synced', (event) => {
                if (event.detail?.chats) {
                    clearTimeout(chatUpdate);
                    chatUpdate = setTimeout(async () => {
                        try {
                            const response = await fetch(chat.dataset.url);
                            if (response.ok && !response.redirected) chat.innerHTML = await response.text();
                        } catch {
                            // Chat akan muncul saat halaman dimuat ulang.
                        }
                    }, 300);
                }
                clearTimeout(update);
                update = setTimeout(async () => {
                    try {
                        const response = await fetch(content.dataset.url);
                        if (response.ok && !response.redirected) {
                            const timing = content.querySelector('details')?.open;
                            content.innerHTML = await response.text();
                            if (timing) content.querySelector('details')?.setAttribute('open', '');
                        }
                    } catch {
                        // Data akan muncul saat riwayat dimuat lagi.
                    }
                }, 300);
            });
        })();
    </script>
@endif
@endsection
