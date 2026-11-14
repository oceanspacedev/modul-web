{{--
    Pemutar video yang mencatat "selesai ditonton" ke $completeUrl (POST learning.items.complete):
    file upload saat ≥90% durasi benar-benar ditonton, YouTube saat ENDED, link lain lewat tombol setelah 30 detik.
    Setelah tercatat, event "material-completed" (detail = respons JSON) dikirim ke document.
    Parameter: $video, $completeUrl, $done (sudah selesai sebelumnya).
--}}
@php $youtubeId = $video->youtubeId(); @endphp

<div class="card overflow-hidden mb-3 material-player">
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

<div class="card mb-3">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3 py-3">
        <div id="completionStatus">
            @if($done)
                <span class="text-success font-weight-bold"><i class="fas fa-check-circle mr-1"></i> Video ini sudah selesai ditonton.</span>
            @elseif($video->isFile() || $youtubeId)
                <span class="text-muted"><i class="fas fa-info-circle mr-1"></i> Tonton video sampai selesai agar tercatat selesai.</span>
            @else
                <span class="text-muted"><i class="fas fa-info-circle mr-1"></i> Tonton video, lalu tandai selesai.</span>
            @endif
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if(! $done && ! $video->isFile() && ! $youtubeId)
                <button type="button" class="btn btn-primary" id="btnMarkDone" disabled>Saya sudah menonton (<span id="markCountdown">30</span>)</button>
            @endif
        </div>
    </div>
</div>

<script>
(function () {
    var done = @json($done);

    function markComplete() {
        if (done) return;
        done = true;
        fetch(@json($completeUrl), {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) }
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            // Ditolak server (mis. pretest belum dikerjakan): jangan kirim ulang di setiap timeupdate.
            if (!data.success) return;
            var status = document.getElementById('completionStatus');
            status.innerHTML = '<span class="text-success font-weight-bold"><i class="fas fa-check-circle mr-1"></i> </span>';
            status.firstChild.appendChild(document.createTextNode('Video ini sudah selesai ditonton.'));
            var markBtn = document.getElementById('btnMarkDone');
            if (markBtn) markBtn.remove();
            document.dispatchEvent(new CustomEvent('material-completed', { detail: data }));
        })
        .catch(function () { done = false; });
    }

    var videoEl = document.getElementById('materialVideo');
    if (videoEl) {
        // Lompatan maju lewat seek tidak dihitung sebagai bagian yang ditonton.
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

    @if($youtubeId && ! $video->isFile())
    var tag = document.createElement('script');
    tag.src = 'https://www.youtube.com/iframe_api';
    document.head.appendChild(tag);
    window.onYouTubeIframeAPIReady = function () {
        new YT.Player('youtubePlayer', {
            videoId: @json($youtubeId),
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
