@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="@auth container-fluid @else container @endauth">
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
                    @if(auth()->user()->can('manage-videos') || auth()->user()->job_level_id == 1)
                    <a href="{{ route('video.edit', $video->id) }}" class="btn btn-default btn-sm mr-1">
                        <i class="fas fa-pencil-alt mr-1"></i> Edit Video
                    </a>
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="confirmDelete('{{ $video->id }}')">
                        <i class="fas fa-trash mr-1"></i> Hapus
                    </button>
                    <form id="deleteForm" action="{{ route('video.destroy', $video->id) }}" method="POST" style="display: none;">
                        @csrf
                    </form>
                    @endif
                @endauth
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="@auth container-fluid @else container @endauth">
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

                {{-- DISKUSI & TANYA JAWAB MATERI (KOMENTAR) --}}
                <div class="card shadow-sm border-0 mt-4" id="comments">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-comments text-danger fa-lg mr-2"></i>
                            <h5 class="card-title font-weight-bold mb-0 text-dark">
                                Diskusi & Tanya Jawab Materi
                            </h5>
                            <span class="badge badge-secondary ml-2 px-2 py-1" style="font-size: 0.85rem;">
                                {{ $video->allComments->count() }} Komentar
                            </span>
                        </div>
                    </div>

                    <div class="card-body p-4">
                        {{-- Flash Messages --}}
                        @if(session('success'))
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        @endif
                        @if(session('error'))
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle mr-1"></i> {{ session('error') }}
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        @endif

                        {{-- BOX TULIS KOMENTAR: AUTH vs GUEST --}}
                        @auth
                            @if(auth()->user()->can('comment-videos') || auth()->user()->job_level_id == 1)
                            <form action="{{ route('video.comments.store', $video->id) }}" method="POST" class="mb-4 comment-submit-form">
                                @csrf
                                <div class="d-flex align-items-start">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-danger text-white font-weight-bold mr-3 flex-shrink-0" style="width: 44px; height: 44px; font-size: 1rem;">
                                        {{ strtoupper(substr(auth()->user()->full_name ?? auth()->user()->username, 0, 2)) }}
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center mb-1">
                                            <strong class="text-dark mr-2">{{ auth()->user()->full_name }}</strong>
                                            @if($video->training && $video->training->trainer_id === auth()->id())
                                                <span class="badge badge-success px-2 py-1"><i class="fas fa-chalkboard-teacher mr-1"></i> Pemateri</span>
                                            @elseif(auth()->user()->job_level_id == 1)
                                                <span class="badge badge-danger px-2 py-1"><i class="fas fa-shield-alt mr-1"></i> Admin</span>
                                            @else
                                                <span class="badge badge-secondary px-2 py-1"><i class="fas fa-user mr-1"></i> Peserta</span>
                                            @endif
                                            @if(auth()->user()->divisi)
                                                <span class="text-muted small ml-2">&bull; {{ auth()->user()->divisi->name }}</span>
                                            @endif
                                        </div>
                                        <textarea name="comment" class="form-control" rows="3" placeholder="Ada materi yang belum dipahami? Tuliskan pertanyaan Anda di sini agar pemateri atau rekan lain dapat membantu menjawab..." required minlength="2" maxlength="2000" style="border-radius: 8px; resize: vertical;"></textarea>
                                        <div class="d-flex justify-content-between align-items-center mt-2">
                                            <small class="text-muted"><i class="fas fa-info-circle mr-1"></i> Peserta dan pemateri dapat saling berdiskusi dan menjawab.</small>
                                            <button type="submit" class="btn btn-danger btn-sm font-weight-bold px-3 shadow-sm btn-submit-comment">
                                                <i class="fas fa-paper-plane mr-1"></i> Kirim Pertanyaan / Komentar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                            @else
                            <div class="alert alert-light border text-muted small p-3 mb-4">
                                <i class="fas fa-info-circle text-info mr-1"></i> Role akun Anda (<strong>{{ auth()->user()->roles->pluck('name')->join(', ') ?: 'Peserta' }}</strong>) saat ini tidak memiliki izin untuk mengirim komentar atau pertanyaan pada video materi.
                            </div>
                            @endif
                        @else
                            <div class="p-4 rounded border text-center mb-4" style="background: #fdfdfd; border-color: #e5e7eb !important;">
                                <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-2" style="width: 50px; height: 50px;">
                                    <i class="fas fa-lock text-muted" style="font-size: 1.3rem;"></i>
                                </div>
                                <h6 class="font-weight-bold text-dark mb-1">Ingin Mengajukan Pertanyaan Seputar Materi Ini?</h6>
                                <p class="text-muted small mb-3" style="max-width: 520px; margin: 0 auto;">
                                    Peserta wajib masuk / login terlebih dahulu untuk dapat mengajukan pertanyaan kepada pemateri atau berdiskusi di kolom komentar materi ini.
                                </p>
                                <a href="{{ route('login', ['redirect' => request()->fullUrl()]) }}" class="btn btn-danger btn-sm font-weight-bold px-4 shadow-sm">
                                    <i class="fas fa-sign-in-alt mr-1"></i> Masuk / Login untuk Bertanya
                                </a>
                            </div>
                        @endauth

                        <hr class="my-4">

                        {{-- LIST KOMENTAR --}}
                        <div class="comments-list">
                            @forelse($video->comments as $comment)
                                <div class="comment-item mb-4 pb-3 border-bottom" id="comment-{{ $comment->id }}">
                                    <div class="d-flex align-items-start">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white font-weight-bold mr-3 flex-shrink-0 {{ $comment->isTrainer() ? 'bg-success' : ($comment->isAdmin() ? 'bg-danger' : 'bg-secondary') }}" style="width: 40px; height: 40px; font-size: 0.9rem;">
                                            {{ strtoupper(substr($comment->user->full_name ?? $comment->user->username ?? 'U', 0, 2)) }}
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <div>
                                                    <strong class="text-dark">{{ $comment->user->full_name ?? 'Pengguna' }}</strong>
                                                    @if($comment->isTrainer())
                                                        <span class="badge badge-success px-2 py-0 ml-1 text-xs"><i class="fas fa-chalkboard-teacher mr-1"></i> Pemateri</span>
                                                    @elseif($comment->isAdmin())
                                                        <span class="badge badge-danger px-2 py-0 ml-1 text-xs"><i class="fas fa-shield-alt mr-1"></i> Admin</span>
                                                    @else
                                                        <span class="badge badge-light border px-2 py-0 ml-1 text-xs text-muted">Peserta</span>
                                                    @endif
                                                    @if($comment->user && $comment->user->divisi)
                                                        <span class="text-muted small ml-1">&bull; {{ $comment->user->divisi->name }}</span>
                                                    @endif
                                                </div>
                                                <small class="text-muted" title="{{ $comment->created_at->format('d M Y H:i') }}">
                                                    {{ $comment->created_at->diffForHumans() }}
                                                </small>
                                            </div>

                                            <div class="text-dark mt-1" style="white-space: pre-line; line-height: 1.6; font-size: 0.95rem;">
                                                {!! nl2br(e($comment->comment)) !!}
                                            </div>

                                            {{-- AKSI KOMENTAR: BALAS & HAPUS --}}
                                            <div class="d-flex align-items-center mt-2" style="gap: 15px;">
                                                @auth
                                                    <button type="button" class="btn btn-link btn-xs text-muted p-0 font-weight-bold" onclick="toggleReplyBox('{{ $comment->id }}')">
                                                        <i class="fas fa-reply mr-1"></i> Balas
                                                    </button>
                                                    @if($comment->user_id === auth()->id() || auth()->user()->job_level_id == 1)
                                                        <button type="button" class="btn btn-link btn-xs text-danger p-0 font-weight-bold" onclick="deleteComment('{{ $comment->id }}')">
                                                            <i class="fas fa-trash-alt mr-1"></i> Hapus
                                                        </button>
                                                    @endif
                                                @else
                                                    <a href="{{ route('login', ['redirect' => request()->fullUrl()]) }}" class="btn btn-link btn-xs text-muted p-0 font-weight-bold">
                                                        <i class="fas fa-reply mr-1"></i> Login untuk membalas
                                                    </a>
                                                @endauth
                                            </div>

                                            {{-- FORM BALASAN (HIDDEN BY DEFAULT) --}}
                                            @auth
                                                <div id="reply-form-{{ $comment->id }}" class="mt-3 p-3 bg-light rounded border reply-form-container" style="display: none;">
                                                    <form action="{{ route('video.comments.store', $video->id) }}" method="POST" class="comment-submit-form">
                                                        @csrf
                                                        <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                                                        <div class="form-group mb-2">
                                                            <label class="small font-weight-bold text-dark mb-1">
                                                                <i class="fas fa-reply text-primary mr-1"></i> Balas ke {{ $comment->user->full_name ?? 'Pengguna' }}:
                                                            </label>
                                                            <textarea name="comment" class="form-control form-control-sm" rows="2" placeholder="Tulis balasan Anda..." required minlength="2" maxlength="2000" style="border-radius: 6px; resize: vertical;"></textarea>
                                                        </div>
                                                        <div class="d-flex justify-content-end" style="gap: 8px;">
                                                            <button type="button" class="btn btn-default btn-xs" onclick="toggleReplyBox('{{ $comment->id }}')">Batal</button>
                                                            <button type="submit" class="btn btn-primary btn-xs font-weight-bold btn-submit-comment">
                                                                <i class="fas fa-paper-plane mr-1"></i> Kirim Balasan
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            @endauth

                                            {{-- LIST REPLIES --}}
                                            @if($comment->replies && $comment->replies->count() > 0)
                                                <div class="replies-container mt-3 pl-3 border-left" style="border-left: 2px solid #e2e8f0 !important; gap: 10px; display: flex; flex-direction: column;">
                                                    @foreach($comment->replies as $reply)
                                                        <div class="p-3 rounded border" style="background: #f8fafc;" id="comment-{{ $reply->id }}">
                                                            <div class="d-flex align-items-start">
                                                                <div class="rounded-circle d-flex align-items-center justify-content-center text-white font-weight-bold mr-2 flex-shrink-0 {{ $reply->isTrainer() ? 'bg-success' : ($reply->isAdmin() ? 'bg-danger' : 'bg-secondary') }}" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                                                    {{ strtoupper(substr($reply->user->full_name ?? $reply->user->username ?? 'U', 0, 2)) }}
                                                                </div>
                                                                <div class="flex-grow-1">
                                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                                        <div>
                                                                            <strong class="text-dark small">{{ $reply->user->full_name ?? 'Pengguna' }}</strong>
                                                                            @if($reply->isTrainer())
                                                                                <span class="badge badge-success px-2 py-0 ml-1 text-xs"><i class="fas fa-chalkboard-teacher mr-1"></i> Pemateri</span>
                                                                            @elseif($reply->isAdmin())
                                                                                <span class="badge badge-danger px-2 py-0 ml-1 text-xs"><i class="fas fa-shield-alt mr-1"></i> Admin</span>
                                                                            @else
                                                                                <span class="badge badge-light border px-2 py-0 ml-1 text-xs text-muted">Peserta</span>
                                                                            @endif
                                                                            @if($reply->user && $reply->user->divisi)
                                                                                <span class="text-muted text-xs ml-1">&bull; {{ $reply->user->divisi->name }}</span>
                                                                            @endif
                                                                        </div>
                                                                        <small class="text-muted text-xs" title="{{ $reply->created_at->format('d M Y H:i') }}">
                                                                            {{ $reply->created_at->diffForHumans() }}
                                                                        </small>
                                                                    </div>
                                                                    <div class="text-dark small" style="white-space: pre-line; line-height: 1.5;">
                                                                        {!! nl2br(e($reply->comment)) !!}
                                                                    </div>
                                                                    @auth
                                                                        @if($reply->user_id === auth()->id() || auth()->user()->job_level_id == 1)
                                                                            <div class="mt-1 text-right">
                                                                                <button type="button" class="btn btn-link btn-xs text-danger p-0" onclick="deleteComment('{{ $reply->id }}')" title="Hapus balasan ini">
                                                                                    <i class="fas fa-trash-alt mr-1"></i> Hapus
                                                                                </button>
                                                                            </div>
                                                                        @endif
                                                                    @endauth
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-5 text-muted">
                                    <div class="mb-2">
                                        <i class="far fa-comment-dots fa-3x text-muted" style="opacity: 0.5;"></i>
                                    </div>
                                    <h6 class="font-weight-bold text-dark mb-1">Belum Ada Pertanyaan / Diskusi</h6>
                                    <p class="small text-muted mb-0">
                                        Ada hal yang masih membingungkan dari video materi ini? Silakan ajukan pertanyaan Anda melalui kolom di atas!
                                    </p>
                                </div>
                            @endforelse
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
<form id="deleteCommentForm" action="" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>

<script>
function confirmDelete(id) {
    if (confirm('Yakin ingin menghapus video ini secara permanen?')) {
        document.getElementById('deleteForm').submit();
    }
}

function toggleReplyBox(id) {
    var box = document.getElementById('reply-form-' + id);
    if (box) {
        if (box.style.display === 'none' || box.style.display === '') {
            box.style.display = 'block';
            var textarea = box.querySelector('textarea');
            if (textarea) textarea.focus();
        } else {
            box.style.display = 'none';
        }
    }
}

function deleteComment(id) {
    if (confirm('Yakin ingin menghapus komentar ini?')) {
        var form = document.getElementById('deleteCommentForm');
        form.action = '{{ url("video/comments") }}/' + id;
        form.submit();
    }
}

document.querySelectorAll('.comment-submit-form').forEach(function(f) {
    var isCommentSubmitting = false;
    f.addEventListener('submit', function(e) {
        if (isCommentSubmitting) {
            e.preventDefault();
            return false;
        }
        isCommentSubmitting = true;
        var btn = f.querySelector('.btn-submit-comment');
        if (btn) {
            btn.style.pointerEvents = 'none';
            btn.innerHTML = '<span class="spinner-border spinner-border-sm mr-1"></span> Mengirim...';
            setTimeout(function() { btn.disabled = true; }, 50);
        }
    });
});
</script>
@endauth
@endsection
