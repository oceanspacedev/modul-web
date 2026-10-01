@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <div class="mb-3">
            <h1 class="m-0 font-weight-bold" style="font-size: 1.5rem;">
                Galeri Video Materi
            </h1>
            <p class="text-muted small mt-1 mb-2">Tonton dan pelajari video materi pembelajaran kapan saja.</p>
            @auth
                <div>
                    <a href="{{ route('video.create') }}" class="btn btn-sm btn-danger shadow-sm">
                        <i class="fas fa-plus mr-1"></i> Tambah Video Baru
                    </a>
                </div>
            @endauth
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        {{-- Flash Alert Messages --}}
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        {{-- Search & Filter Bar --}}
        <div class="card card-outline card-danger shadow-sm mb-4">
            <div class="card-body p-3">
                <form action="{{ route('video.index') }}" method="GET" class="row g-2 align-items-center">
                    <div class="col-md-9 col-sm-8 mb-2 mb-sm-0">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-white border-right-0"><i class="fas fa-search text-muted"></i></span>
                            </div>
                            <input type="text" name="search" class="form-control border-left-0"
                                placeholder="Cari judul atau keterangan video..." value="{{ request('search') }}">
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-4 d-flex">
                        <button type="submit" class="btn btn-danger btn-block mr-2">
                            <i class="fas fa-search mr-1"></i> Cari
                        </button>
                        @if(request('search'))
                            <a href="{{ route('video.index') }}" class="btn btn-secondary" title="Reset Pencarian">
                                <i class="fas fa-redo"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        {{-- Video Grid --}}
        @if ($videos->isEmpty())
            <div class="card shadow-sm text-center py-5">
                <div class="card-body">
                    <i class="fas fa-film fa-4x text-muted mb-3" style="opacity: 0.5;"></i>
                    <h4 class="font-weight-bold text-muted">Belum Ada Video</h4>
                    <p class="text-muted">
                        @if(request('search'))
                            Tidak ada video yang cocok dengan kata kunci "{{ request('search') }}".
                        @else
                            Saat ini belum ada video materi yang diunggah.
                        @endif
                    </p>
                    @auth
                        <a href="{{ route('video.create') }}" class="btn btn-danger mt-2">
                            <i class="fas fa-plus mr-1"></i> Tambah Video Pertama
                        </a>
                    @endauth
                </div>
            </div>
        @else
            <div class="row">
                @foreach ($videos as $v)
                    <div class="col-xl-4 col-md-6 col-12 mb-4 d-flex align-items-stretch">
                        <div class="card shadow-sm h-100 w-100 border-0 rounded-lg overflow-hidden video-card" style="transition: transform 0.2s, box-shadow 0.2s;">
                            {{-- Video Poster / Preview Box --}}
                            <div class="position-relative bg-dark video-thumbnail-wrapper" style="height: 200px; cursor: pointer;"
                                onclick="openVideoPlayer('{{ $v->id }}', '{{ addslashes($v->title) }}', '{{ $v->video_type }}', '{{ $v->video_url }}', '{{ $v->embed_url }}', '{{ addslashes($v->description) }}')">
                                
                                @if($v->thumbnail_url)
                                    <img src="{{ $v->thumbnail_url }}" alt="{{ $v->title }}" class="w-100 h-100" style="object-fit: cover;">
                                @elseif($v->isFile())
                                    <video src="{{ $v->video_url }}#t=1" preload="metadata" class="w-100 h-100" style="object-fit: cover; opacity: 0.75;"></video>
                                @else
                                    <div class="w-100 h-100 d-flex justify-content-center align-items-center bg-secondary">
                                        <i class="fas fa-play-circle fa-4x text-white-50"></i>
                                    </div>
                                @endif
                                
                                {{-- Play Button Overlay --}}
                                <div class="position-absolute d-flex justify-content-center align-items-center w-100 h-100" style="top: 0; left: 0; background: rgba(0,0,0,0.3);">
                                    <div class="rounded-circle d-flex justify-content-center align-items-center bg-danger text-white shadow-lg play-btn-badge" style="width: 54px; height: 54px; transition: transform 0.2s;">
                                        <i class="fas fa-play ml-1" style="font-size: 20px;"></i>
                                    </div>
                                </div>

                                {{-- Type / size badge --}}
                                <span class="badge position-absolute" style="bottom: 8px; right: 8px; font-size: 11px; {{ $v->isLink() ? 'background: #17a2b8; color: #fff;' : 'background: rgba(0,0,0,0.75); color: #fff;' }}">
                                    @if($v->isLink())
                                        @if(str_contains(strtolower($v->video_link), 'youtube') || str_contains(strtolower($v->video_link), 'youtu.be'))
                                            <i class="fab fa-youtube mr-1"></i> YouTube
                                        @elseif(str_contains(strtolower($v->video_link), 'drive.google.com'))
                                            <i class="fab fa-google-drive mr-1"></i> Google Drive
                                        @else
                                            <i class="fas fa-link mr-1"></i> Link Video
                                        @endif
                                    @else
                                        <i class="fas fa-file-video mr-1"></i> {{ $v->file_size ?? 'File Video' }}
                                    @endif
                                </span>
                            </div>

                            {{-- Card Body --}}
                            <div class="card-body d-flex flex-column p-3">
                                <h5 class="card-title font-weight-bold text-dark mb-2" title="{{ $v->title }}">
                                    <a href="{{ route('video.show', $v->id) }}" class="text-dark text-decoration-none">
                                        {{ Str::limit($v->title, 55) }}
                                    </a>
                                </h5>

                                <p class="card-text text-muted small flex-grow-1 mb-3">
                                    {{ Str::limit($v->description ?? 'Tidak ada deskripsi.', 90) }}
                                </p>

                                <div class="d-flex justify-content-between align-items-center text-muted small border-top pt-2 mt-auto">
                                    <span>
                                        <i class="far fa-calendar-alt mr-1"></i> {{ $v->created_at->format('d M Y') }}
                                    </span>
                                    <span>
                                        <i class="far fa-eye mr-1"></i> {{ $v->views_count }} ditonton
                                    </span>
                                </div>
                            </div>

                            {{-- Card Footer --}}
                            <div class="card-footer bg-light p-2 d-flex justify-content-between align-items-center">
                                <button type="button" class="btn btn-sm btn-outline-danger font-weight-bold"
                                    onclick="openVideoPlayer('{{ $v->id }}', '{{ addslashes($v->title) }}', '{{ $v->video_type }}', '{{ $v->video_url }}', '{{ $v->embed_url }}', '{{ addslashes($v->description) }}')">
                                    <i class="fas fa-play mr-1"></i> Putar Video
                                </button>
                                
                                <div class="btn-group">
                                    <a href="{{ route('video.show', $v->id) }}" class="btn btn-sm btn-default" title="Halaman Penuh">
                                        <i class="fas fa-expand"></i>
                                    </a>
                                    @auth
                                        <a href="{{ route('video.edit', $v->id) }}" class="btn btn-sm btn-info" title="Edit Video">
                                            <i class="fas fa-pencil-alt"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger" title="Hapus Video" onclick="confirmDelete('{{ $v->id }}', '{{ addslashes($v->title) }}')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    @endauth
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            <div class="d-flex justify-content-center mt-3">
                {{ $videos->links() }}
            </div>
        @endif
    </div>
</section>

{{-- MODAL VIDEO PLAYER --}}
<div class="modal fade" id="videoPlayerModal" tabindex="-1" role="dialog" aria-labelledby="videoModalLabel" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0 bg-dark text-white">
            <div class="modal-header border-secondary py-2">
                <h5 class="modal-title font-weight-bold text-truncate pr-3" id="modalVideoTitle">Putar Video</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" onclick="stopModalVideo()">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0 bg-black">
                {{-- HTML5 Video Player Container --}}
                <div id="modalHtmlVideoContainer" class="embed-responsive embed-responsive-16by9 bg-black" style="display: none;">
                    <video id="activeModalVideo" class="embed-responsive-item" controls preload="auto" playsinline>
                        <source id="modalVideoSource" src="" type="video/mp4">
                        Browser Anda tidak mendukung tag video HTML5.
                    </video>
                </div>

                {{-- Iframe Embed Container (YouTube, Google Drive, Vimeo) --}}
                <div id="modalIframeContainer" class="embed-responsive embed-responsive-16by9 bg-black" style="display: none;">
                    <iframe id="modalVideoIframe" class="embed-responsive-item" src="" frameborder="0" allowfullscreen allow="autoplay; encrypted-media"></iframe>
                </div>

                <div class="p-3 bg-dark">
                    <p class="text-white-50 small mb-0" id="modalVideoDesc"></p>
                </div>
            </div>
            <div class="modal-footer border-secondary py-2 justify-content-between">
                <a href="#" id="modalWatchFullLink" class="btn btn-sm btn-outline-light">
                    <i class="fas fa-external-link-alt mr-1"></i> Buka di Halaman Penuh
                </a>
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal" onclick="stopModalVideo()">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>

{{-- MODAL DELETE CONFIRMATION --}}
@auth
<form id="deleteVideoForm" action="" method="POST" style="display: none;">
    @csrf
</form>
@endauth

<style>
.video-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.12) !important;
}
.video-thumbnail-wrapper:hover .play-btn-badge {
    transform: scale(1.15);
    background-color: #e02424 !important;
}
</style>

<script>
function openVideoPlayer(id, title, type, url, embedUrl, desc) {
    document.getElementById('modalVideoTitle').innerText = title;
    document.getElementById('modalVideoDesc').innerText = desc || 'Tidak ada deskripsi tambahan.';
    document.getElementById('modalWatchFullLink').href = '/video/watch/' + id;

    var htmlVideoContainer = document.getElementById('modalHtmlVideoContainer');
    var iframeContainer = document.getElementById('modalIframeContainer');
    var video = document.getElementById('activeModalVideo');
    var source = document.getElementById('modalVideoSource');
    var iframe = document.getElementById('modalVideoIframe');

    if (type === 'link' && embedUrl) {
        htmlVideoContainer.style.display = 'none';
        iframeContainer.style.display = 'block';
        iframe.src = embedUrl;
    } else {
        iframeContainer.style.display = 'none';
        htmlVideoContainer.style.display = 'block';
        source.src = url;
        video.load();
        video.play().catch(function(e) {
            console.log('Autoplay prevented:', e);
        });
    }

    $('#videoPlayerModal').modal('show');

    // Send view increment
    fetch('/video/watch/' + id, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
}

function stopModalVideo() {
    var video = document.getElementById('activeModalVideo');
    video.pause();
    video.currentTime = 0;
    document.getElementById('modalVideoIframe').src = '';
}

$('#videoPlayerModal').on('hidden.bs.modal', function () {
    stopModalVideo();
});

@auth
function confirmDelete(id, title) {
    if (confirm('Apakah Anda yakin ingin menghapus video "' + title + '"? Data dan file akan dihapus permanen.')) {
        var form = document.getElementById('deleteVideoForm');
        form.action = '/video-manage/delete/' + id;
        form.submit();
    }
}
@endauth
</script>
@endsection
