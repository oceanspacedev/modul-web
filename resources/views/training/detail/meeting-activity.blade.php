@if($monitors->isNotEmpty())
    <details class="mb-3">
        <summary>Waktu monitoring</summary>
        <ul class="mt-2 mb-0">
            @foreach($monitors as $monitor)
                <li>
                    @if($monitor->source === \App\Models\TrainingMeetingMonitor::SOURCE_IMPORT)
                        Data platform {{ $monitor->started_at->format('d/m H:i:s') }} – {{ $monitor->ended_at->format('d/m H:i:s') }} WIB
                        · Terakhir ditarik {{ $monitor->last_seen_at->format('d/m H:i:s') }} WIB
                    @else
                    {{ $monitor->started_at->format('d/m H:i:s') }} WIB
                    @if($monitor->ended_at)
                        · Selesai {{ $monitor->ended_at->format('d/m H:i:s') }} WIB
                    @elseif($monitorRevision === null || $monitor->account_revision !== $monitorRevision || ! $monitor->isActiveAt(now()))
                        · Terakhir terhubung {{ $monitor->last_seen_at->format('d/m H:i:s') }} WIB. Data berikutnya belum terekam.
                    @else
                        · Terhubung
                    @endif
                    @endif
                </li>
            @endforeach
        </ul>
    </details>
@endif

@if($events->isEmpty())
    <p class="text-muted mb-0">Belum ada aktivitas meeting{{ $participantName !== '' ? ' untuk nama ini' : '' }}.</p>
@else
    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr><th scope="col">Waktu terekam</th><th scope="col">Nama di meeting</th><th scope="col">Aktivitas</th></tr></thead>
            <tbody>
                @foreach($events as $event)
                    <tr>
                        <td class="text-nowrap">{{ $event->observed_at->format('d/m H:i:s') }} WIB</td>
                        <td>{{ $event->name }}</td>
                        <td>
                            {{ match ($event->type) {
                                'join' => 'Masuk',
                                'leave' => 'Keluar',
                                'camera' => match ($event->camera_on) {
                                    true => 'On Cam',
                                    false => 'Off Cam',
                                    null => 'Status kamera belum diketahui',
                                },
                                'present' => 'Terdeteksi sudah di rapat',
                            } }}
                            @if(in_array($event->type, ['join', 'present'], true) && $event->camera_on !== null)
                                · {{ $event->camera_on ? 'On Cam' : 'Off Cam' }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $events->links('pagination::bootstrap-4') }}
@endif
