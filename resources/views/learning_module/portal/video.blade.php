@extends('layout.main_template')

@php
    $done = $progress->isItemCompleted($item);
    $youtubeId = $video->youtubeId();
    $items = $module->availableItems();
    $position = $items->search(fn ($i) => $i->id === $item->id);
    $next = $position !== false ? $items->get($position + 1) : null;
@endphp

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <div class="mb-3 d-flex align-items-center">
            <a href="{{ route('learning.show', $module) }}" class="btn btn-default btn-sm mr-3">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="m-0 text-dark font-weight-bold" style="font-size: 1.5rem;">{{ $video->title }}</h1>
                <span class="text-muted small">{{ $module->title }} &middot; materi {{ $position + 1 }} dari {{ $items->count() }}</span>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid" style="max-width: 1000px;">
        <div class="card border-0 rounded-lg overflow-hidden bg-black mb-3">
            <div class="embed-responsive embed-responsive-16by9 bg-black">
                @if($video->isFile())
                    <video id="materialVideo" class="embed-responsive-item" controls playsinline preload="metadata" poster="{{ $video->thumbnail_url ?? '' }}">
                        <source src="{{ $video->video_url }}">
                        Browser Anda tidak mendukung pemutar video HTML5.
                    </video>
                @elseif($youtubeId)
                    <div id="youtubePlayer" class="embed-responsive-item"></div>
                @else
                    <iframe class="embed-responsive-item" src="{{ $video->embed_url }}" frameborder="0" allowfullscreen allow="autoplay; encrypted-media; picture-in-picture"></iframe>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center" style="gap: 12px;">
                <div id="completionStatus">
                    @if($done)
                        <span class="text-success font-weight-bold"><i class="fas fa-check-circle mr-1"></i> Materi ini sudah selesai.</span>
                    @elseif($video->isFile() || $youtubeId)
                        <span class="text-muted"><i class="fas fa-info-circle mr-1"></i> Tonton video sampai selesai agar materi ini tercatat selesai.</span>
                    @else
                        <span class="text-muted"><i class="fas fa-info-circle mr-1"></i> Tonton video, lalu tandai selesai.</span>
                    @endif
                </div>
                <div class="d-flex" style="gap: 8px;">
                    @if(! $done && ! $video->isFile() && ! $youtubeId)
                        <button type="button" class="btn btn-outline-success" id="btnMarkDone" disabled>Saya sudah menonton (<span id="markCountdown">30</span>)</button>
                    @endif
                    @if($next)
                        <a href="{{ $next->isVideo() ? route('learning.items.show', [$module, $next]) : route('learning.show', $module) }}" class="btn btn-default">Materi berikutnya</a>
                    @endif
                    <a href="{{ route('learning.show', $module) }}" class="btn btn-primary {{ $done ? '' : 'd-none' }}" id="btnBack">Kembali ke Modul</a>
                </div>
            </div>
        </div>

        @if($video->description)
            <div class="card mb-5"><div class="card-body text-muted" style="white-space: pre-line;">{{ $video->description }}</div></div>
        @endif
    </div>
</section>
@endsection

@section('footer')
<script>
(function () {
    var completeUrl = '{{ route('learning.items.complete', [$module, $item]) }}';
    var done = @json($done);

    function markComplete() {
        if (done) return;
        done = true;
        fetch(completeUrl, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (!data.success) { done = false; return; }
            var text = data.posttest_open
                ? 'Semua materi selesai! Posttest sudah terbuka.'
                : 'Materi selesai (' + data.completed + '/' + data.total + ').';
            $('#completionStatus').empty().append(
                $('<span class="text-success font-weight-bold"></span>').text(text).prepend('<i class="fas fa-check-circle mr-1"></i>')
            );
            $('#btnMarkDone').remove();
            $('#btnBack').removeClass('d-none');
        })
        .catch(function () { done = false; });
    }

    // File upload: selesai bila ≥90% durasi benar-benar ditonton (lompatan maju lewat seek tidak dihitung).
    var videoEl = document.getElementById('materialVideo');
    if (videoEl) {
        var watchedUntil = 0;
        videoEl.addEventListener('timeupdate', function () {
            if (videoEl.currentTime - watchedUntil < 2) {
                watchedUntil = Math.max(watchedUntil, videoEl.currentTime);
            }
            if (videoEl.duration && watchedUntil >= videoEl.duration * 0.9) {
                markComplete();
            }
        });
    }

    // YouTube: selesai saat pemutar melaporkan ENDED.
    @if($youtubeId && ! $video->isFile())
    var tag = document.createElement('script');
    tag.src = 'https://www.youtube.com/iframe_api';
    document.head.appendChild(tag);
    window.onYouTubeIframeAPIReady = function () {
        new YT.Player('youtubePlayer', {
            videoId: '{{ $youtubeId }}',
            playerVars: { rel: 0, playsinline: 1 },
            events: {
                onStateChange: function (event) {
                    if (event.data === YT.PlayerState.ENDED) markComplete();
                }
            }
        });
    };
    @endif

    // Link Drive/Vimeo tidak bisa dipantau; tombol tandai selesai aktif setelah 30 detik.
    var markBtn = document.getElementById('btnMarkDone');
    if (markBtn) {
        var seconds = 30;
        var timer = setInterval(function () {
            seconds--;
            document.getElementById('markCountdown').textContent = seconds;
            if (seconds <= 0) {
                clearInterval(timer);
                markBtn.disabled = false;
                markBtn.textContent = 'Saya sudah menonton';
            }
        }, 1000);
        markBtn.addEventListener('click', markComplete);
    }
})();
</script>
@endsection
