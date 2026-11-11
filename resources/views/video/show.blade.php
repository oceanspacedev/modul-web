@extends('layout.main_template')

@section('content')
<style>
.video-mini-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0.5rem;
    border-radius: var(--radius-lg);
    color: inherit;
}
.video-mini-link:hover { background: var(--gray-50); text-decoration: none; color: inherit; }
.video-mini-thumb {
    position: relative;
    flex-shrink: 0;
    width: 110px;
    height: 64px;
    border-radius: var(--radius-lg);
    overflow: hidden;
    background: var(--gray-900);
}
.video-mini-thumb-empty { background: var(--gray-100); }
.video-mini-thumb-play {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 0.75rem;
    background: rgba(9, 9, 11, 0.2);
}
.video-mini-title { color: var(--gray-950); }
.video-mini-link:hover .video-mini-title { color: var(--primary-600); }
.comment-body { white-space: pre-line; }
</style>

<section class="content-header">
    <div class="@auth container-fluid @else container @endauth">
        <x-page-header :title="$video->title" :breadcrumbs="['Video Materi' => route('video.index'), $video->title => null]">
            <x-slot:actions>
                <a href="{{ route('video.index') }}" class="btn btn-default">
                    <i class="fas fa-arrow-left"></i> Kembali ke Galeri
                </a>
                @auth
                    @can('update', $video)
                    <a href="{{ route('video.edit', $video->id) }}" class="btn btn-default">
                        <i class="fas fa-pencil-alt"></i> Edit Video
                    </a>
                    <button type="button" class="btn btn-default text-danger" onclick="confirmDelete('{{ $video->id }}')">
                        <i class="fas fa-trash"></i> Hapus
                    </button>
                    <form id="deleteForm" action="{{ route('video.destroy', $video) }}" method="POST" style="display: none;">
                        @csrf
                        @method('DELETE')
                    </form>
                    @endcan
                @endauth
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="@auth container-fluid @else container @endauth">
        <div class="row">
            {{-- Main Video Player Column --}}
            <div class="col-lg-8 mb-4">
                @if($quiz)
                    {{-- Video ber-kuis: catat selesai ditonton agar posttest terbuka. --}}
                    @php $quizItem = $quiz->items->first(); @endphp
                    @include('learning_module.partials.video-player', [
                        'video' => $video,
                        'completeUrl' => $quiz->url('items.complete', ['item' => $quizItem]),
                        'done' => $quizProgress->isItemCompleted($quizItem),
                    ])
                    @include('learning_module.partials.material-quiz-panel', [
                        'quiz' => $quiz,
                        'progress' => $quizProgress,
                        'canManage' => auth()->user()->can('update', $video),
                    ])
                @else
                <div class="card overflow-hidden">
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
                @endif

                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <h2 class="section-title">{{ $video->title }}</h2>
                            <span class="badge badge-secondary">
                                @if($video->isLink())
                                    <i class="fas fa-link mr-1"></i> Link Eksternal
                                @else
                                    <i class="fas fa-file-video mr-1"></i> File Lokal
                                @endif
                            </span>
                        </div>

                        <div class="d-flex flex-wrap align-items-center text-muted small border-bottom pb-3 mb-3">
                            <span class="mr-3 mb-1">
                                <i class="far fa-calendar-alt mr-1"></i> Diunggah {{ $video->created_at->format('d F Y, H:i') }} WIB
                            </span>
                            <span class="mr-3 mb-1">
                                <i class="far fa-eye mr-1"></i> {{ $video->views_count }} kali ditonton
                            </span>
                            @if($video->file_size)
                                <span class="mr-3 mb-1">
                                    <i class="fas fa-hdd mr-1"></i> Ukuran: {{ $video->file_size }}
                                </span>
                            @endif
                            @if($video->isLink())
                                <span class="mb-1 text-truncate" style="max-width: 300px;">
                                    <i class="fas fa-external-link-alt mr-1"></i>
                                    <a href="{{ $video->video_link }}" target="_blank">Buka Tautan Asli</a>
                                </span>
                            @endif
                        </div>

                        <h3 class="eyebrow mb-2">Deskripsi Video</h3>
                        <div class="comment-body">{{ $video->description ?: 'Tidak ada deskripsi untuk video ini.' }}</div>
                    </div>
                </div>

                {{-- DISKUSI & TANYA JAWAB MATERI (KOMENTAR) --}}
                <div class="card" id="comments">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-comments"></i> Diskusi & Tanya Jawab Materi
                        </h3>
                        <span class="badge badge-secondary">
                            {{ $video->allComments->count() }} Komentar
                        </span>
                    </div>

                    <div class="card-body">
                        <x-flash />

                        {{-- BOX TULIS KOMENTAR: AUTH vs GUEST --}}
                        @auth
                            @can('create', App\Models\VideoComment::class)
                            <form action="{{ route('video.comments.store', $video->id) }}" method="POST" class="mb-4 comment-submit-form">
                                @csrf
                                <div class="d-flex align-items-start gap-3">
                                    <span class="avatar avatar-lg">
                                        {{ strtoupper(substr(auth()->user()->nama_lengkap ?? auth()->user()->username, 0, 2)) }}
                                    </span>
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                            <strong>{{ auth()->user()->nama_lengkap }}</strong>
                                            @if($video->training && $video->training->trainer_id === auth()->id())
                                                <span class="badge badge-success"><i class="fas fa-chalkboard-teacher mr-1"></i> Pemateri</span>
                                            @elseif(auth()->user()->isAdmin())
                                                <span class="badge badge-primary"><i class="fas fa-shield-alt mr-1"></i> Admin</span>
                                            @else
                                                <span class="badge badge-secondary"><i class="fas fa-user mr-1"></i> Peserta</span>
                                            @endif
                                            @if(auth()->user()->divisi)
                                                <span class="text-muted small">&bull; {{ auth()->user()->divisi->name }}</span>
                                            @endif
                                        </div>
                                        <textarea name="comment" class="form-control" rows="3" placeholder="Ada materi yang belum dipahami? Tuliskan pertanyaan Anda di sini agar pemateri atau rekan lain dapat membantu menjawab..." required minlength="2" maxlength="2000" style="resize: vertical;"></textarea>
                                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2">
                                            <small class="text-muted"><i class="fas fa-info-circle mr-1"></i> Peserta dan pemateri dapat saling berdiskusi dan menjawab.</small>
                                            <button type="submit" class="btn btn-primary btn-sm btn-submit-comment">
                                                <i class="fas fa-paper-plane"></i> Kirim Pertanyaan / Komentar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                            @else
                            <div class="alert alert-light mb-4">
                                <i class="fas fa-info-circle mr-1"></i> Role akun Anda (<strong>{{ auth()->user()->roles->pluck('name')->join(', ') ?: 'Peserta' }}</strong>) saat ini tidak memiliki izin untuk mengirim komentar atau pertanyaan pada video materi.
                            </div>
                            @endcan
                        @else
                            <div class="bg-light rounded border mb-4">
                                <x-empty-state icon="fa-lock" title="Ingin mengajukan pertanyaan seputar materi ini?"
                                    text="Peserta wajib masuk / login terlebih dahulu untuk dapat mengajukan pertanyaan kepada pemateri atau berdiskusi di kolom komentar materi ini.">
                                    <a href="{{ route('login', ['redirect' => request()->fullUrl()]) }}" class="btn btn-primary">
                                        <i class="fas fa-sign-in-alt"></i> Masuk / Login untuk Bertanya
                                    </a>
                                </x-empty-state>
                            </div>
                        @endauth

                        {{-- LIST KOMENTAR --}}
                        <div class="comments-list border-top pt-4">
                            @forelse($video->comments as $comment)
                                <div class="comment-item mb-4 pb-3 border-bottom" id="comment-{{ $comment->id }}">
                                    <div class="d-flex align-items-start gap-3">
                                        <span class="avatar avatar-lg">
                                            {{ strtoupper(substr($comment->user->nama_lengkap ?? $comment->user->username ?? 'U', 0, 2)) }}
                                        </span>
                                        <div class="flex-grow-1 min-w-0">
                                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                                                <div class="d-flex flex-wrap align-items-center gap-1">
                                                    <strong>{{ $comment->user->nama_lengkap ?? 'Pengguna' }}</strong>
                                                    @if($comment->isTrainer())
                                                        <span class="badge badge-success"><i class="fas fa-chalkboard-teacher mr-1"></i> Pemateri</span>
                                                    @elseif($comment->isAdmin())
                                                        <span class="badge badge-primary"><i class="fas fa-shield-alt mr-1"></i> Admin</span>
                                                    @else
                                                        <span class="badge badge-secondary">Peserta</span>
                                                    @endif
                                                    @if($comment->user && $comment->user->divisi)
                                                        <span class="text-muted small">&bull; {{ $comment->user->divisi->name }}</span>
                                                    @endif
                                                </div>
                                                <small class="text-muted" title="{{ $comment->created_at->format('d M Y H:i') }}">
                                                    {{ $comment->created_at->diffForHumans() }}
                                                </small>
                                            </div>

                                            <div class="comment-body mt-1">{!! nl2br(e($comment->comment)) !!}</div>

                                            {{-- AKSI KOMENTAR: BALAS & HAPUS --}}
                                            <div class="d-flex align-items-center gap-3 mt-2">
                                                @auth
                                                    <button type="button" class="btn btn-link btn-xs text-muted p-0" onclick="toggleReplyBox('{{ $comment->id }}')">
                                                        <i class="fas fa-reply mr-1"></i> Balas
                                                    </button>
                                                    @can('delete', $comment)
                                                        <button type="button" class="btn btn-link btn-xs text-danger p-0" onclick="deleteComment('{{ $comment->id }}')">
                                                            <i class="fas fa-trash-alt mr-1"></i> Hapus
                                                        </button>
                                                    @endcan
                                                @else
                                                    <a href="{{ route('login', ['redirect' => request()->fullUrl()]) }}" class="btn btn-link btn-xs text-muted p-0">
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
                                                            <label class="small mb-1">
                                                                Balas ke {{ $comment->user->nama_lengkap ?? 'Pengguna' }}:
                                                            </label>
                                                            <textarea name="comment" class="form-control form-control-sm" rows="2" placeholder="Tulis balasan Anda..." required minlength="2" maxlength="2000" style="resize: vertical;"></textarea>
                                                        </div>
                                                        <div class="d-flex justify-content-end gap-2">
                                                            <button type="button" class="btn btn-default btn-xs" onclick="toggleReplyBox('{{ $comment->id }}')">Batal</button>
                                                            <button type="submit" class="btn btn-primary btn-xs btn-submit-comment">
                                                                <i class="fas fa-paper-plane"></i> Kirim Balasan
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            @endauth

                                            {{-- LIST REPLIES --}}
                                            @if($comment->replies && $comment->replies->count() > 0)
                                                <div class="replies-container d-flex flex-column gap-2 mt-3 pl-3 border-left">
                                                    @foreach($comment->replies as $reply)
                                                        <div class="p-3 rounded border bg-light" id="comment-{{ $reply->id }}">
                                                            <div class="d-flex align-items-start gap-2">
                                                                <span class="avatar">
                                                                    {{ strtoupper(substr($reply->user->nama_lengkap ?? $reply->user->username ?? 'U', 0, 2)) }}
                                                                </span>
                                                                <div class="flex-grow-1 min-w-0">
                                                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                                                                        <div class="d-flex flex-wrap align-items-center gap-1">
                                                                            <strong class="small">{{ $reply->user->nama_lengkap ?? 'Pengguna' }}</strong>
                                                                            @if($reply->isTrainer())
                                                                                <span class="badge badge-success"><i class="fas fa-chalkboard-teacher mr-1"></i> Pemateri</span>
                                                                            @elseif($reply->isAdmin())
                                                                                <span class="badge badge-primary"><i class="fas fa-shield-alt mr-1"></i> Admin</span>
                                                                            @else
                                                                                <span class="badge badge-secondary">Peserta</span>
                                                                            @endif
                                                                            @if($reply->user && $reply->user->divisi)
                                                                                <span class="text-muted text-xs">&bull; {{ $reply->user->divisi->name }}</span>
                                                                            @endif
                                                                        </div>
                                                                        <small class="text-muted text-xs" title="{{ $reply->created_at->format('d M Y H:i') }}">
                                                                            {{ $reply->created_at->diffForHumans() }}
                                                                        </small>
                                                                    </div>
                                                                    <div class="comment-body small">{!! nl2br(e($reply->comment)) !!}</div>
                                                                    @auth
                                                                        @can('delete', $reply)
                                                                            <div class="mt-1 text-right">
                                                                                <button type="button" class="btn btn-link btn-xs text-danger p-0" onclick="deleteComment('{{ $reply->id }}')" title="Hapus balasan ini">
                                                                                    <i class="fas fa-trash-alt mr-1"></i> Hapus
                                                                                </button>
                                                                            </div>
                                                                        @endcan
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
                                <x-empty-state icon="fa-comment-dots" title="Belum ada pertanyaan / diskusi"
                                    text="Ada hal yang masih membingungkan dari video materi ini? Silakan ajukan pertanyaan Anda melalui kolom di atas!" />
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            {{-- Other Recommended Videos Column --}}
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-play-circle"></i> Video Lainnya
                        </h3>
                    </div>
                    <div class="card-body p-2">
                        @if($otherVideos->isEmpty())
                            <p class="text-muted text-center py-4 small mb-0">Belum ada video lainnya.</p>
                        @else
                            @foreach($otherVideos as $other)
                                <a href="{{ route('video.show', $other->id) }}" class="video-mini-link">
                                    <div class="video-mini-thumb">
                                        @if($other->thumbnail_url)
                                            <img src="{{ $other->thumbnail_url }}" alt="{{ $other->title }}" class="w-100 h-100" style="object-fit: cover;">
                                        @elseif($other->isFile() && ! $other->activeQuiz())
                                            <video src="{{ $other->video_url }}#t=1" preload="metadata" class="w-100 h-100" style="object-fit: cover;"></video>
                                        @else
                                            <div class="w-100 h-100 video-mini-thumb-empty"></div>
                                        @endif
                                        <div class="video-mini-thumb-play">
                                            <i class="fas fa-play"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="video-mini-title text-sm font-weight-bold text-truncate mb-1" title="{{ $other->title }}">
                                            {{ $other->title }}
                                        </div>
                                        <div class="text-muted text-xs">
                                            <i class="far fa-eye mr-1"></i> {{ $other->views_count }} ditonton
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

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
