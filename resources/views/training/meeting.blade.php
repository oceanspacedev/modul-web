@extends('layout.main_template')

@section('content')
<section class="content-header training-detail-header">
    <div class="container-fluid">
        <a class="training-back-link" href="{{ route('training.show', $training) }}">
            <i class="fas fa-arrow-left" aria-hidden="true"></i> Detail pelatihan
        </a>
        <h1 class="training-title">Aktivitas {{ $training->meetingPlatformLabel() ?? 'Meeting' }}</h1>
        <p class="training-session-meta">{{ $training->title }} · {{ $training->training_date->translatedFormat('j F Y') }}</p>
    </div>
</section>

<section class="content training-detail">
    <div class="container-fluid">
        <x-flash />

        <section class="card" aria-labelledby="meeting-monitor-title">
            <div class="card-body" data-meeting-monitor
                data-join-url="{{ route('training.meeting.join', $training) }}"
                data-start-url="{{ route('training.meeting.monitors.start', $training) }}"
                data-sync-url="{{ route('training.meeting.monitors.sync', [$training, '__MONITOR__']) }}"
                data-end-url="{{ route('training.meeting.monitors.end', [$training, '__MONITOR__']) }}"
                data-csrf-token="{{ csrf_token() }}"
                data-display-name="{{ auth()->user()->nama_lengkap ?: 'Petugas OD' }}">
                <h2 class="h5" id="meeting-monitor-title">Monitoring {{ $provider?->label() ?? 'meeting' }}</h2>
                @if($meetingAccount && ! $provider->supportsMonitoring())
                    <p class="mb-2">Akun penyelenggara <strong>{{ $meetingAccount->name }}</strong></p>
                    <p class="text-muted">Jam masuk dan keluar peserta ditarik dari catatan {{ $provider->label() }}. Tarik data setelah rapat berakhir; penarikan ulang tidak menggandakan data. Informasikan kepada peserta bahwa kehadirannya dicatat.</p>
                    <form action="{{ route('training.meeting.attendance-sync', $training) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-primary">Tarik Kehadiran dari {{ $provider->label() }}</button>
                    </form>
                @elseif($meetingAccount)
                    <p class="mb-2">Akun penyelenggara <strong>{{ $meetingAccount->name }}</strong></p>
                    <p class="text-muted">Masuk sebelum peserta dan biarkan halaman ini terbuka selama pelatihan. Informasikan kepada peserta bahwa jam masuk, jam keluar, dan status kamera dicatat.</p>
                    <form data-meeting-join method="POST" class="form-inline align-items-end">
                        <div class="form-group d-block mr-sm-2 mb-2">
                            <label for="meeting-passcode" class="d-block font-weight-normal mb-1">Passcode rapat</label>
                            <input id="meeting-passcode" class="form-control" data-meeting-passcode type="password" autocomplete="off" maxlength="255" placeholder="Jika diperlukan">
                        </div>
                        <button type="submit" class="btn btn-primary mb-2">Mulai Monitoring</button>
                    </form>
                    <button type="button" class="btn btn-outline-danger btn-sm mb-2" data-meeting-leave hidden>Selesai Monitoring</button>
                    <p data-meeting-status role="status" class="mb-2">Siap bergabung.</p>
                    <div data-meeting-client></div>
                @elseif($meetingCode !== $currentMeeting)
                    <p class="mb-0 text-muted">Riwayat rapat {{ $meetingCode }}.</p>
                @else
                    <p class="mb-0 text-muted">Monitoring meeting nonaktif. <a href="{{ route('training.edit', $training) }}">Atur meeting pelatihan</a> untuk mengaktifkannya.</p>
                @endif
            </div>
        </section>

        <section class="card" id="meeting-log" aria-labelledby="meeting-log-title">
            <div class="card-body">
                <h2 class="h5" id="meeting-log-title">Riwayat aktivitas</h2>
                <form action="{{ route('training.meeting.show', $training) }}#meeting-log" method="GET" class="form-inline mb-3">
                    @if(count($meetingOptions) > 1)
                        <label class="mr-2" for="meeting-history">Rapat</label>
                        <select id="meeting-history" class="form-control mr-2" name="meeting">
                            @foreach($meetingOptions as $option)
                                <option value="{{ $option }}" @selected($option === $meetingCode)>{{ $option }}{{ $option === $currentMeeting ? ' (tautan aktif)' : '' }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="hidden" name="meeting" value="{{ $meetingCode }}">
                    @endif
                    <label class="sr-only" for="meeting-participant-name">Nama di meeting</label>
                    <input id="meeting-participant-name" class="form-control mr-2" name="nama" value="{{ $participantName }}" maxlength="120" placeholder="Cari nama di meeting">
                    <button type="submit" class="btn btn-default">Cari</button>
                </form>
                <div id="meeting-activity-content" data-url="{{ route('training.meeting.activity', [$training, 'meeting' => $meetingCode, ...request()->only(['nama', 'aktivitas', 'chat'])]) }}">
                    @include('training.detail.meeting-activity')
                </div>
            </div>
        </section>

        <section class="card" id="meeting-chat" aria-labelledby="meeting-chat-title">
            <div class="card-body">
                <h2 class="h5" id="meeting-chat-title">Chat publik</h2>
                @if($meetingAccount && $provider->supportsChatImport())
                    <form action="{{ route('training.meeting.chat', $training) }}" method="POST" enctype="multipart/form-data" class="mb-3">
                        @csrf
                        <input type="hidden" name="account_revision" value="{{ $monitorRevision }}">
                        <label for="meeting-chat-file" class="d-block">File TXT chat dari sesi ini</label>
                        <input type="file" id="meeting-chat-file" name="chat_file" accept=".txt,text/plain" required class="mb-2">
                        <button type="submit" class="btn btn-default btn-sm">Impor Chat</button>
                    </form>
                @endif
                @if($messages->isEmpty())
                    <p class="text-muted mb-0">Belum ada chat publik yang diimpor.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead><tr><th scope="col">Waktu di file</th><th scope="col">Pengirim</th><th scope="col">Pesan</th></tr></thead>
                            <tbody>
                                @foreach($messages as $message)
                                    <tr>
                                        <td class="text-nowrap">{{ $message->sent_time }}</td>
                                        <td>{{ $message->sender }}</td>
                                        <td style="white-space: pre-wrap; overflow-wrap: anywhere">{{ $message->message }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    {{ $messages->links('pagination::bootstrap-4') }}
                @endif
            </div>
        </section>
    </div>
</section>

@if($meetingAccount && $provider->supportsMonitoring())
    {{-- Klien pemantauan milik platformnya: memuat SDK dan mengirim aktivitas ke rute monitors. --}}
    @includeIf('training.meeting-clients.'.$provider->key())
    <script>
        (() => {
            const content = document.querySelector('#meeting-activity-content');
            let update;
            document.querySelector('[data-meeting-monitor]').addEventListener('meeting-monitor:synced', () => {
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
