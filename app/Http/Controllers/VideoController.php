<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVideoCommentRequest;
use App\Http\Requests\StoreVideoRequest;
use App\Http\Requests\UpdateVideoRequest;
use App\Models\Video;
use App\Models\VideoComment;
use App\Services\MaterialQuizService;
use App\Support\LearningModuleProgress;
use App\Support\SafeRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

#[Authorize('create', Video::class, only: ['create', 'store'])]
#[Authorize('update', 'video', only: ['edit', 'update'])]
#[Authorize('delete', 'video', only: ['destroy'])]
#[Authorize('create', VideoComment::class, only: ['storeComment'])]
#[Authorize('delete', 'comment', only: ['destroyComment'])]
class VideoController extends Controller
{
    public function __construct(private MaterialQuizService $materialQuiz) {}

    /**
     * Display a listing of videos (public).
     */
    public function index(Request $request): View
    {
        $query = Video::with('quizModule')->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return view('video.index', [
            'title' => 'Galeri Video',
            'active' => 'video',
            'videos' => $query->paginate(12)->withQueryString(),
            'search' => $request->search,
        ]);
    }

    /**
     * Show single video player page (public). A video with an active pretest/posttest needs a signed-in user, and
     * the pretest (when enabled) comes before the video; video managers can preview without it.
     */
    public function show(Request $request, Video $video): View|RedirectResponse
    {
        $video->load([
            'training',
            'quizModule.items.materialable',
            'comments' => fn ($q) => $q->with(['user.divisi', 'replies.user.divisi']),
        ]);

        $quiz = $video->activeQuiz();
        $quizProgress = null;

        if ($quiz) {
            if (! $request->user()) {
                return to_route('login', ['redirect' => $request->getRequestUri()]);
            }

            $quizProgress = LearningModuleProgress::for($quiz, $request->user());

            if ($quizProgress->needsPretest() && $request->user()->cannot('update', $video)) {
                return to_route('learning.quiz', [$quiz, 'pretest'])
                    ->with('info', 'Kerjakan pretest terlebih dahulu sebelum menonton video ini.');
            }
        }

        $video->increment('views_count');

        return view('video.show', [
            'title' => $video->title,
            'active' => 'video',
            'video' => $video,
            'quiz' => $quiz,
            'quizProgress' => $quizProgress,
            'otherVideos' => Video::with('quizModule')->whereKeyNot($video->id)->latest()->take(6)->get(),
        ]);
    }

    public function create(): View
    {
        return view('video.create', [
            'title' => 'Tambah Video Baru',
            'active' => 'video',
        ]);
    }

    /**
     * Store an uploaded video or link. Uploading from a training page replaces that training's video.
     */
    public function store(StoreVideoRequest $request): RedirectResponse
    {
        $redirectTo = SafeRedirect::to($request->validated('redirect_to'), route('video.index'));

        // Debounce: prevent duplicate rapid submissions within 10 seconds
        $lockKey = 'video_upload_lock_'.$request->user()->id.'_'.md5($request->title.'_'.$request->training_id);
        if (! cache()->add($lockKey, true, now()->addSeconds(10))) {
            return redirect($redirectTo)->with('info', 'Video Anda sedang diproses atau sudah berhasil disimpan.');
        }

        $isLink = $request->validated('video_type') === 'link';
        $videoFile = $request->file('video_file');
        $videoPath = ! $isLink && $videoFile ? $this->storeUpload($videoFile, 'videos') : null;
        $thumbnailPath = $request->hasFile('thumbnail') ? $this->storeUpload($request->file('thumbnail'), 'videos/thumbnails', 'thumbnail') : null;

        $attributes = [
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'video_type' => $request->validated('video_type'),
            'video_link' => $isLink ? $request->validated('video_link') : null,
        ];

        $existingVideo = $request->filled('training_id')
            ? Video::where('training_id', $request->validated('training_id'))->first()
            : null;

        if ($existingVideo) {
            if ($videoPath && $existingVideo->video_file) {
                Storage::delete($existingVideo->video_file);
            }
            if ($thumbnailPath && $existingVideo->thumbnail) {
                Storage::delete($existingVideo->thumbnail);
            }

            $existingVideo->update([
                ...$attributes,
                'video_file' => $videoPath ?? ($isLink ? null : $existingVideo->video_file),
                'thumbnail' => $thumbnailPath ?? $existingVideo->thumbnail,
                'file_size' => $videoFile ? $this->formatBytes($videoFile->getSize()) : ($isLink ? null : $existingVideo->file_size),
            ]);

            return redirect($redirectTo)->with('success', 'Video materi pelatihan berhasil diperbarui!');
        }

        $video = Video::create([
            ...$attributes,
            'training_id' => $request->validated('training_id'),
            'video_file' => $videoPath,
            'thumbnail' => $thumbnailPath,
            'file_size' => $videoPath ? $this->formatBytes($videoFile->getSize()) : null,
        ]);

        $quiz = $this->materialQuiz->sync($video, $request->validated('quiz'));
        if ($this->materialQuiz->needsQuestions($quiz)) {
            return to_route('learning-module.questions.index', $quiz)
                ->with('success', 'Video berhasil diunggah! Tambahkan soal agar pretest/posttest aktif.');
        }

        return redirect($redirectTo)->with('success', 'Video materi pelatihan berhasil diunggah!');
    }

    public function edit(Video $video): View
    {
        return view('video.edit', [
            'title' => 'Edit Video: '.$video->title,
            'active' => 'video',
            'video' => $video,
            'quiz' => $video->quizModule,
        ]);
    }

    public function update(UpdateVideoRequest $request, Video $video): RedirectResponse
    {
        $video->fill($request->safe()->only(['title', 'description', 'video_type']));

        if ($request->validated('video_type') === 'link') {
            $this->deleteStoredFile($video->video_file);
            $video->video_file = null;
            $video->file_size = null;
            $video->video_link = $request->validated('video_link');
        } else {
            $video->video_link = null;

            if ($request->hasFile('video_file')) {
                $newPath = $this->storeUpload($request->file('video_file'), 'videos');
                $this->deleteStoredFile($video->video_file);
                $video->file_size = $this->formatBytes($request->file('video_file')->getSize());
                $video->video_file = $newPath;
            }
        }

        if ($request->hasFile('thumbnail')) {
            $newPath = $this->storeUpload($request->file('thumbnail'), 'videos/thumbnails', 'thumbnail');
            $this->deleteStoredFile($video->thumbnail);
            $video->thumbnail = $newPath;
        }

        $video->save();

        $quiz = $this->materialQuiz->sync($video, $request->validated('quiz'));
        if ($this->materialQuiz->needsQuestions($quiz)) {
            return to_route('learning-module.questions.index', $quiz)
                ->with('success', 'Video berhasil diperbarui! Tambahkan soal agar pretest/posttest aktif.');
        }

        return to_route('video.index')->with('success', 'Video berhasil diperbarui!');
    }

    public function destroy(Video $video): RedirectResponse
    {
        $this->deleteStoredFile($video->video_file);
        $this->deleteStoredFile($video->thumbnail);

        // Riwayat nilai kuis video tetap tersimpan (soft delete).
        $video->quizModule()->first()?->delete();
        $video->delete();

        return to_route('video.index')->with('success', 'Video berhasil dihapus!');
    }

    /**
     * Store a comment or a reply on a video. Replies are kept one level deep.
     */
    public function storeComment(StoreVideoCommentRequest $request, Video $video): RedirectResponse
    {
        $parentId = null;
        if ($request->filled('parent_id')) {
            $parent = $video->allComments()->findOrFail($request->validated('parent_id'));
            $parentId = $parent->parent_id ?: $parent->id;
        }

        $video->allComments()->create([
            'user_id' => $request->user()->id,
            'parent_id' => $parentId,
            'comment' => $request->validated('comment'),
        ]);

        return redirect(route('video.show', $video).'#comments')
            ->with('success', $parentId ? 'Balasan berhasil dikirim!' : 'Pertanyaan / komentar berhasil dikirim!');
    }

    /**
     * Delete a comment (by its author or an administrator).
     */
    public function destroyComment(VideoComment $comment): RedirectResponse
    {
        $comment->delete();

        return redirect(route('video.show', $comment->video_id).'#comments')
            ->with('success', 'Komentar berhasil dihapus.');
    }

    /**
     * Store with a random name and an extension derived from the file contents, not the client name.
     * The disk does not throw on write failures, so a false result is turned into a form error
     * instead of saving a video record without a file.
     */
    private function storeUpload(UploadedFile $file, string $directory, string $field = 'video_file'): string
    {
        $path = $file->storeAs($directory, Str::random(20).'.'.$file->extension());

        if ($path === false) {
            throw ValidationException::withMessages([
                $field => 'File gagal disimpan ke storage server. Silakan coba lagi atau hubungi administrator.',
            ]);
        }

        return $path;
    }

    private function deleteStoredFile(?string $path): void
    {
        if ($path && Storage::exists($path)) {
            Storage::delete($path);
        }
    }

    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision).' '.$units[$pow];
    }
}
