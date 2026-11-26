@extends('layout.main_template')

@section('content')
<style>
.video-card { transition: border-color 0.15s ease; }
.video-card:hover { border-color: var(--gray-300) !important; }
.video-thumb {
    position: relative;
    display: block;
    height: 190px;
    background: var(--gray-900);
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
    overflow: hidden;
}
/* Letterbox behind a thumbnail stays dark in both themes (--gray-900 inverts to near-white). */
[data-theme="dark"] .video-thumb { background: var(--black); }
.video-thumb-empty {
    background: var(--gray-100);
    color: var(--gray-500);
}
.video-thumb-play {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.video-thumb-play span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border-radius: 9999px;
    background: rgba(255, 255, 255, 0.92);
    color: var(--black);
    box-shadow: var(--shadow-xs);
    transition: transform 0.15s ease;
}
.video-thumb:hover .video-thumb-play span { transform: scale(1.08); }
.video-thumb-tag {
    position: absolute;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 0.125rem 0.5rem;
    border-radius: var(--radius-md);
    font-size: 0.6875rem;
    font-weight: 500;
    background: rgba(9, 9, 11, 0.7);
    color: var(--white);
}
.video-thumb-tag.is-top { top: 8px; left: 8px; background: var(--surface-raised); color: var(--gray-950); box-shadow: 0 0 0 1px var(--ring); }
.video-thumb-tag.is-bottom { bottom: 8px; right: 8px; }
.video-card-title a { color: var(--gray-950); }
.video-card-title a:hover { color: var(--primary-700); text-decoration: none; }
.video-search { width: 260px; margin: 0; }
@media (max-width: 575.98px) {
    .video-search { width: 100%; }
}
</style>

<section class="content-header">
    <div class="@auth container-fluid @else container @endauth">
        <x-page-header title="Galeri Video Materi" subtitle="Tonton dan pelajari video materi pembelajaran kapan saja." eyebrow="Video Materi">
            @auth
                @can('create', App\Models\Video::class)
                    <x-slot:actions>
                        <a href="{{ route('video.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Tambah Video
                        </a>
                    </x-slot:actions>
                @endcan
            @endauth
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="@auth container-fluid @else container @endauth">
        <x-flash />

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    Semua Video <span class="badge badge-secondary ml-1">{{ $videos->total() }}</span>
                </h3>
                <form action="{{ route('video.index') }}" method="GET" class="card-tools video-search" role="search">
                    <x-search-field placeholder="Cari video..." :clear-url="route('video.index')" />
                </form>
            </div>

        {{-- Video Grid --}}
        @if ($videos->isEmpty())
                <x-empty-state icon="fa-film" title="Belum ada video"
                    :text="request('search') ? 'Tidak ada video yang cocok dengan kata kunci “'.request('search').'”.' : 'Saat ini belum ada video materi yang diunggah.'">
                    @can('create', App\Models\Video::class)
                        <a href="{{ route('video.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Tambah Video Pertama
                        </a>
                    @endcan
                </x-empty-state>
        @else
            <div class="card-body pb-0">
            <div class="row">
                @foreach ($videos as $v)
                    <div class="col-xl-4 col-md-6 col-12 mb-4 d-flex align-items-stretch">
                        <div class="card h-100 w-100 mb-0 video-card">
                            {{-- Video Poster / Preview Box --}}
                            <a href="{{ route('video.show', $v->id) }}" class="video-thumb">
                                @if($v->thumbnail_url)
                                    <img src="{{ $v->thumbnail_url }}" alt="{{ $v->title }}" class="w-100 h-100" style="object-fit: cover;">
                                @elseif($v->isFile() && ! $v->activeQuiz())
                                    <video src="{{ $v->video_url }}#t=1" preload="metadata" class="w-100 h-100" style="object-fit: cover;"></video>
                                @else
                                    <div class="w-100 h-100 video-thumb-empty"></div>
                                @endif

                                {{-- Play Button Overlay --}}
                                <div class="video-thumb-play">
                                    <span><i class="fas fa-play ml-1"></i></span>
                                </div>

                                @if($quiz = $v->activeQuiz())
                                    <span class="video-thumb-tag is-top">
                                        <i class="fas fa-clipboard-check"></i>
                                        {{ collect([$quiz->has_pretest ? 'Pretest' : null, $quiz->has_posttest ? 'Posttest' : null])->filter()->join(' & ') }}
                                    </span>
                                @endif

                                {{-- Type / size badge --}}
                                <span class="video-thumb-tag is-bottom">
                                    @if($v->isLink())
                                        @if(str_contains(strtolower($v->video_link), 'youtube') || str_contains(strtolower($v->video_link), 'youtu.be'))
                                            <i class="fab fa-youtube"></i> YouTube
                                        @elseif(str_contains(strtolower($v->video_link), 'drive.google.com'))
                                            <i class="fab fa-google-drive"></i> Google Drive
                                        @else
                                            <i class="fas fa-link"></i> Link Video
                                        @endif
                                    @else
                                        <i class="fas fa-file-video"></i> {{ $v->file_size ?? 'File Video' }}
                                    @endif
                                </span>
                            </a>

                            {{-- Card Body --}}
                            <div class="card-body d-flex flex-column p-3">
                                <h3 class="video-card-title text-sm font-weight-bold text-break mb-1" title="{{ $v->title }}">
                                    <a href="{{ route('video.show', $v->id) }}">
                                        {{ Str::limit($v->title, 55) }}
                                    </a>
                                </h3>

                                <p class="text-muted small text-break flex-grow-1 mb-3">
                                    {{ Str::limit($v->description ?? 'Tidak ada deskripsi.', 90) }}
                                </p>

                                <div class="d-flex justify-content-between align-items-center text-muted text-xs mt-auto">
                                    <span>
                                        <i class="far fa-calendar-alt mr-1"></i> {{ $v->created_at->format('d M Y') }}
                                    </span>
                                    <span>
                                        <i class="far fa-eye mr-1"></i> {{ $v->views_count }} ditonton
                                    </span>
                                </div>
                            </div>

                            {{-- Card Footer --}}
                            @can('update', $v)
                            <div class="card-footer d-flex flex-wrap justify-content-end align-items-center gap-1 px-3 py-2">
                                @if($v->quizModule && ($v->quizModule->has_pretest || $v->quizModule->has_posttest))
                                    <a href="{{ $v->quizModule->url('questions.index') }}" class="btn btn-default btn-xs" title="Soal pretest/posttest">
                                        Soal Kuis
                                    </a>
                                @endif
                                <a href="{{ route('video.edit', $v->id) }}" class="btn btn-default btn-xs" title="Edit Video">
                                    Edit
                                </a>
                                <button type="button" class="btn btn-default btn-xs text-danger" title="Hapus Video"
                                    data-destroy-url="{{ route('video.destroy', $v->id) }}"
                                    data-video-title="{{ $v->title }}"
                                    onclick="confirmDelete(this.dataset.destroyUrl, this.dataset.videoTitle)">
                                    Hapus
                                </button>
                            </div>
                            @endcan
                        </div>
                    </div>
                @endforeach
            </div>

            </div>

            @if($videos->hasPages())
                <div class="card-footer d-flex justify-content-center">
                    {{ $videos->links('pagination::bootstrap-4') }}
                </div>
            @endif
        @endif
        </div>
    </div>
</section>

{{-- MODAL DELETE CONFIRMATION --}}
@auth
<form id="deleteVideoForm" action="" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>
@endauth
@endsection

@auth
@section('footer')
<script>
function confirmDelete(destroyUrl, title) {
    if (confirm('Apakah Anda yakin ingin menghapus video "' + title + '"? Data dan file akan dihapus permanen.')) {
        var form = document.getElementById('deleteVideoForm');
        form.action = destroyUrl;
        form.submit();
    }
}
</script>
@endsection
@endauth
