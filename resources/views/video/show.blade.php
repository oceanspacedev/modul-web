@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <a href="{{ route('video.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali ke Galeri
                </a>
                <h1 class="m-0 font-weight-bold text-truncate" title="{{ $video->title }}">
                    {{ $video->title }}
                </h1>
            </div>
            <div class="col-sm-6 text-sm-right mt-2 mt-sm-0">
                @auth
                    <a href="{{ route('video.edit', $video->id) }}" class="btn btn-info btn-sm mr-1">
                        <i class="fas fa-pencil-alt mr-1"></i> Edit Video
                    </a>
                    <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete('{{ $video->id }}')">
                        <i class="fas fa-trash mr-1"></i> Hapus
                    </button>
                    <form id="deleteForm" action="{{ route('video.destroy', $video->id) }}" method="POST" style="display: none;">
                        @csrf
                    </form>
                @endauth
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            {{-- Main Video Player Column --}}
            <div class="col-lg-8 mb-4">
                <div class="card shadow-sm border-0 rounded-lg overflow-hidden bg-black mb-3">
                    <div class="embed-responsive embed-responsive-16by9 bg-black">
                        @if($video->isLink() && $video->embed_url)
                            <iframe class="embed-responsive-item" src="{{ $video->embed_url }}" frameborder="0" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
                        @else
                            <video class="embed-responsive-item" controls autoplay playsinline preload="auto" poster="{{ $video->thumbnail_url ?? '' }}">
                                <source src="{{ $video->video_url }}" type="video/mp4">
                                Browser Anda tidak mendukung pemutar video HTML5.
                            </video>
                        @endif
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h3 class="font-weight-bold mb-0">{{ $video->title }}</h3>
                            <span class="badge {{ $video->isLink() ? 'badge-info' : 'badge-danger' }} px-2 py-1">
                                @if($video->isLink())
                                    <i class="fas fa-link mr-1"></i> Link Eksternal
                                @else
                                    <i class="fas fa-file-video mr-1"></i> File Lokal
                                @endif
                            </span>
                        </div>
                        
                        <div class="d-flex flex-wrap align-items-center text-muted small border-bottom pb-3 mb-3">
                            <span class="mr-3 mb-1">
                                <i class="far fa-calendar-alt mr-1 text-danger"></i> Diunggah {{ $video->created_at->format('d F Y, H:i') }} WIB
                            </span>
                            <span class="mr-3 mb-1">
                                <i class="far fa-eye mr-1 text-danger"></i> {{ $video->views_count }} kali ditonton
                            </span>
                            @if($video->file_size)
                                <span class="mr-3 mb-1">
                                    <i class="fas fa-hdd mr-1 text-danger"></i> Ukuran: {{ $video->file_size }}
                                </span>
                            @endif
                            @if($video->isLink())
                                <span class="mb-1 text-truncate" style="max-width: 300px;">
                                    <i class="fas fa-external-link-alt mr-1 text-danger"></i>
                                    <a href="{{ $video->video_link }}" target="_blank" class="text-muted">Buka Tautan Asli</a>
                                </span>
                            @endif
                        </div>

                        <h5 class="font-weight-bold text-secondary mb-2">Deskripsi Video</h5>
                        <div class="text-dark" style="white-space: pre-line; line-height: 1.7;">
                            {{ $video->description ?: 'Tidak ada deskripsi untuk video ini.' }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Other Recommended Videos Column --}}
            <div class="col-lg-4">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="card-title font-weight-bold m-0 text-danger">
                            <i class="fas fa-play-circle mr-1"></i> Video Lainnya
                        </h5>
                    </div>
                    <div class="card-body p-2">
                        @if($otherVideos->isEmpty())
                            <p class="text-muted text-center py-4 small mb-0">Belum ada video lainnya.</p>
                        @else
                            @foreach($otherVideos as $other)
                                <div class="d-flex align-items-center p-2 mb-2 rounded hover-bg-light" style="cursor: pointer;" onclick="window.location='{{ route('video.show', $other->id) }}'">
                                    <div class="position-relative flex-shrink-0 bg-dark rounded overflow-hidden mr-3" style="width: 110px; height: 68px;">
                                        @if($other->thumbnail_url)
                                            <img src="{{ $other->thumbnail_url }}" alt="{{ $other->title }}" class="w-100 h-100" style="object-fit: cover;">
                                        @elseif($other->isFile())
                                            <video src="{{ $other->video_url }}#t=1" preload="metadata" class="w-100 h-100" style="object-fit: cover; opacity: 0.8;"></video>
                                        @else
                                            <div class="w-100 h-100 d-flex justify-content-center align-items-center bg-secondary">
                                                <i class="fas fa-play text-white-50" style="font-size: 14px;"></i>
                                            </div>
                                        @endif
                                        <div class="position-absolute d-flex justify-content-center align-items-center w-100 h-100" style="top:0; left:0; background: rgba(0,0,0,0.25);">
                                            <i class="fas fa-play text-white" style="font-size: 13px;"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 overflow-hidden">
                                        <h6 class="font-weight-bold mb-1 text-truncate" title="{{ $other->title }}" style="font-size: 14px;">
                                            <a href="{{ route('video.show', $other->id) }}" class="text-dark text-decoration-none">
                                                {{ $other->title }}
                                            </a>
                                        </h6>
                                        <div class="text-muted small">
                                            <i class="far fa-eye mr-1"></i> {{ $other->views_count }} ditonton
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.hover-bg-light:hover {
    background-color: #f8f9fa;
}
</style>

@auth
<script>
function confirmDelete(id) {
    if (confirm('Yakin ingin menghapus video ini secara permanen?')) {
        document.getElementById('deleteForm').submit();
    }
}
</script>
@endauth
@endsection
