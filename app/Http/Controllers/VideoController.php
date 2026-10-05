<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Models\VideoComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VideoController extends Controller
{
    /**
     * Display a listing of videos (public).
     */
    public function index(Request $request)
    {
        $query = Video::query()->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $videos = $query->paginate(12)->withQueryString();

        return view('video.index', [
            'title' => 'Galeri Video',
            'active' => 'video',
            'videos' => $videos,
            'search' => $request->search,
        ]);
    }

    /**
     * Show single video player page or details.
     */
    public function show($id)
    {
        $video = Video::with([
            'training',
            'comments' => function ($q) {
                $q->with([
                    'user.divisi',
                    'replies.user.divisi',
                ]);
            }
        ])->findOrFail($id);

        // Increment view count
        $video->increment('views_count');

        // Other recommended videos
        $otherVideos = Video::where('id', '!=', $id)->latest()->take(6)->get();

        return view('video.show', [
            'title' => $video->title,
            'active' => 'video',
            'video' => $video,
            'otherVideos' => $otherVideos,
        ]);
    }

    /**
     * Show form to upload new video (admin).
     */
    public function create()
    {
        return view('video.create', [
            'title' => 'Tambah Video Baru',
            'active' => 'video',
        ]);
    }

    /**
     * Store uploaded video or link (admin).
     */
    public function store(Request $request)
    {
        $videoType = $request->input('video_type', 'file');

        $rules = [
            'training_id' => 'nullable|exists:trainings,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'video_type' => 'required|in:file,link',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ];

        if ($videoType === 'link') {
            $rules['video_link'] = 'required|url|max:1000';
        } else {
            $rules['video_file'] = 'required|file|mimes:mp4,webm,ogg,mov,mkv|max:2097152'; // max 2GB
        }

        $request->validate($rules);

        $videoPath = null;
        $fileSize = null;

        if ($videoType === 'file' && $request->hasFile('video_file')) {
            $videoFile = $request->file('video_file');
            $fileSize = $this->formatBytes($videoFile->getSize());
            $videoFileName = Str::random(20) . '.' . $videoFile->getClientOriginalExtension();
            $videoPath = $videoFile->storeAs('videos', $videoFileName, 'public');
        }

        // Store thumbnail if uploaded
        $thumbnailPath = null;
        if ($request->hasFile('thumbnail')) {
            $thumbFile = $request->file('thumbnail');
            $thumbName = Str::random(20) . '.' . $thumbFile->getClientOriginalExtension();
            $thumbnailPath = $thumbFile->storeAs('videos/thumbnails', $thumbName, 'public');
        }

        // Debounce: prevent duplicate rapid submissions within 10 seconds
        $userId = auth()->id() ?? 'guest';
        $lockKey = 'video_upload_lock_' . $userId . '_' . md5($request->title . '_' . $request->training_id);
        if (cache()->has($lockKey)) {
            $redirectTo = $request->input('redirect_to', '/video');
            return redirect($redirectTo)->with('info', 'Video Anda sedang diproses atau sudah berhasil disimpan.');
        }
        cache()->put($lockKey, true, now()->addSeconds(10));

        // If uploaded via training shortcut, check if a video already exists for this training
        if ($request->filled('training_id')) {
            $existingVideo = Video::where('training_id', $request->training_id)->first();
            if ($existingVideo) {
                // Delete old storage files if replaced
                if ($videoPath && $existingVideo->video_file) {
                    Storage::disk('public')->delete($existingVideo->video_file);
                }
                if ($thumbnailPath && $existingVideo->thumbnail) {
                    Storage::disk('public')->delete($existingVideo->thumbnail);
                }

                $existingVideo->update([
                    'title' => $request->title,
                    'description' => $request->description,
                    'video_type' => $videoType,
                    'video_file' => $videoPath ?? ($videoType === 'file' ? $existingVideo->video_file : null),
                    'video_link' => $videoType === 'link' ? trim($request->video_link) : null,
                    'thumbnail' => $thumbnailPath ?? $existingVideo->thumbnail,
                    'file_size' => $fileSize ?? ($videoType === 'file' ? $existingVideo->file_size : null),
                ]);

                $redirectTo = $request->input('redirect_to', '/video');
                return redirect($redirectTo)->with('success', 'Video materi pelatihan berhasil diperbarui!');
            }
        }

        Video::create([
            'training_id' => $request->training_id,
            'title' => $request->title,
            'description' => $request->description,
            'video_type' => $videoType,
            'video_file' => $videoPath,
            'video_link' => $videoType === 'link' ? trim($request->video_link) : null,
            'thumbnail' => $thumbnailPath,
            'file_size' => $fileSize,
        ]);

        $redirectTo = $request->input('redirect_to', '/video');
        return redirect($redirectTo)->with('success', 'Video materi pelatihan berhasil diunggah!');
    }

    /**
     * Show edit form for video (admin).
     */
    public function edit($id)
    {
        $video = Video::findOrFail($id);

        return view('video.edit', [
            'title' => 'Edit Video: ' . $video->title,
            'active' => 'video',
            'video' => $video,
        ]);
    }

    /**
     * Update video info (admin).
     */
    public function update(Request $request, $id)
    {
        $video = Video::findOrFail($id);
        $videoType = $request->input('video_type', $video->video_type);

        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'video_type' => 'required|in:file,link',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ];

        if ($videoType === 'link') {
            $rules['video_link'] = 'required|url|max:1000';
        } else {
            $rules['video_file'] = 'nullable|file|mimes:mp4,webm,ogg,mov,mkv|max:2097152';
        }

        $request->validate($rules);

        $video->title = $request->title;
        $video->description = $request->description;
        $video->video_type = $videoType;

        if ($videoType === 'link') {
            // Remove previous local file if it was a file before
            if ($video->video_file && Storage::disk('public')->exists($video->video_file)) {
                Storage::disk('public')->delete($video->video_file);
            }
            $video->video_file = null;
            $video->file_size = null;
            $video->video_link = trim($request->video_link);
        } else {
            $video->video_link = null;
            // If new video file uploaded
            if ($request->hasFile('video_file')) {
                if ($video->video_file && Storage::disk('public')->exists($video->video_file)) {
                    Storage::disk('public')->delete($video->video_file);
                }

                $newVideo = $request->file('video_file');
                $video->file_size = $this->formatBytes($newVideo->getSize());
                $videoFileName = Str::random(20) . '.' . $newVideo->getClientOriginalExtension();
                $video->video_file = $newVideo->storeAs('videos', $videoFileName, 'public');
            }
        }

        // If new thumbnail uploaded
        if ($request->hasFile('thumbnail')) {
            if ($video->thumbnail && Storage::disk('public')->exists($video->thumbnail)) {
                Storage::disk('public')->delete($video->thumbnail);
            }

            $thumbFile = $request->file('thumbnail');
            $thumbName = Str::random(20) . '.' . $thumbFile->getClientOriginalExtension();
            $video->thumbnail = $thumbFile->storeAs('videos/thumbnails', $thumbName, 'public');
        }

        $video->save();

        return redirect('/video')->with('success', 'Video berhasil diperbarui!');
    }

    /**
     * Delete video (admin).
     */
    public function destroy($id)
    {
        $video = Video::findOrFail($id);

        if ($video->video_file && Storage::disk('public')->exists($video->video_file)) {
            Storage::disk('public')->delete($video->video_file);
        }

        if ($video->thumbnail && Storage::disk('public')->exists($video->thumbnail)) {
            Storage::disk('public')->delete($video->thumbnail);
        }

        $video->delete();

        return redirect('/video')->with('success', 'Video berhasil dihapus!');
    }

    /**
     * Helper to format bytes to human readable string.
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Store comment on video (participants must be logged in).
     */
    public function storeComment(Request $request, $id)
    {
        if (!auth()->check()) {
            return redirect()->route('login', ['redirect' => route('video.show', $id)])
                ->with('error', 'Silakan login terlebih dahulu untuk menyampaikan pertanyaan atau komentar.');
        }

        if (!auth()->user()->can('comment-videos') && auth()->user()->job_level_id != 1) {
            return back()->with('error', 'Role akun Anda tidak memiliki izin untuk mengirim komentar.');
        }

        $request->validate([
            'comment' => 'required|string|min:2|max:2000',
            'parent_id' => 'nullable|exists:video_comments,id',
        ], [
            'comment.required' => 'Isi komentar / pertanyaan tidak boleh kosong.',
            'comment.min' => 'Komentar minimal 2 karakter.',
            'comment.max' => 'Komentar maksimal 2000 karakter.',
        ]);

        $video = Video::findOrFail($id);

        $parentId = null;
        if ($request->filled('parent_id')) {
            $parent = VideoComment::where('id', $request->parent_id)
                ->where('video_id', $video->id)
                ->firstOrFail();
            // Nested limit to 1 level: if parent itself has a parent, attach to top-level parent
            $parentId = $parent->parent_id ?: $parent->id;
        }

        VideoComment::create([
            'video_id' => $video->id,
            'user_id' => auth()->id(),
            'parent_id' => $parentId,
            'comment' => trim($request->comment),
        ]);

        return redirect(route('video.show', $video->id) . '#comments')
            ->with('success', $parentId ? 'Balasan berhasil dikirim!' : 'Pertanyaan / komentar berhasil dikirim!');
    }

    /**
     * Delete a comment (by author or admin).
     */
    public function destroyComment($id)
    {
        if (!auth()->check()) {
            return back()->with('error', 'Akses ditolak.');
        }

        $comment = VideoComment::findOrFail($id);

        // Allow author or admin (job_level_id == 1) to delete
        if ($comment->user_id !== auth()->id() && auth()->user()->job_level_id != 1) {
            return back()->with('error', 'Anda tidak memiliki izin untuk menghapus komentar ini.');
        }

        $videoId = $comment->video_id;
        $comment->delete();

        return redirect(route('video.show', $videoId) . '#comments')
            ->with('success', 'Komentar berhasil dihapus.');
    }
}
