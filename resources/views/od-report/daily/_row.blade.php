<div class="d-flex align-items-start gap-2 px-3 py-2 border-bottom">
    <div class="flex-grow-1 min-w-0">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <strong class="text-break">{{ $activity->name }}</strong>
            <span class="badge {{ $activity->badgeClass() }}">{{ $activity->result }}</span>
            @if ($activity->document)
                <span class="badge badge-light badge-wrap text-left"><i class="far fa-file-alt"></i> {{ $activity->document->title }}</span>
            @endif
        </div>
        @if ($activity->kendala || $activity->solusi || $activity->keterangan)
            <div class="small text-muted text-break">
                @if ($activity->kendala) Kendala: {{ $activity->kendala }}. @endif
                @if ($activity->solusi) Solusi: {{ $activity->solusi }}. @endif
                @if ($activity->keterangan) {{ $activity->keterangan }} @endif
            </div>
        @endif
    </div>
    <div class="text-nowrap flex-shrink-0">
        @if ($movable)
            @foreach (['up' => ['fa-arrow-up', $first], 'down' => ['fa-arrow-down', $last]] as $direction => [$icon, $disabled])
                <form action="{{ route('od-report.daily.move', $activity) }}" method="POST" class="d-inline">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="direction" value="{{ $direction }}">
                    <button type="submit" class="btn btn-default btn-xs" @disabled($disabled) aria-label="Geser {{ $direction === 'up' ? 'ke atas' : 'ke bawah' }}"><i class="fas {{ $icon }}"></i></button>
                </form>
            @endforeach
        @endif
        <a href="{{ route('od-report.daily.edit', $activity) }}" class="btn btn-default btn-xs" title="Ubah"><i class="fas fa-pen"></i></a>
        <form action="{{ route('od-report.daily.destroy', $activity) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus aktivitas ini?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-default btn-xs text-danger" title="Hapus"><i class="fas fa-trash"></i></button>
        </form>
    </div>
</div>
