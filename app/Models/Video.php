<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_id',
        'title',
        'description',
        'video_type',
        'video_file',
        'video_link',
        'thumbnail',
        'duration',
        'file_size',
        'views_count',
    ];

    /**
     * Associated training session (optional).
     */
    public function training()
    {
        return $this->belongsTo(Training::class, 'training_id');
    }

    /**
     * Top-level comments on this video.
     */
    public function comments()
    {
        return $this->hasMany(VideoComment::class, 'video_id')->whereNull('parent_id')->latest();
    }

    /**
     * All comments including replies.
     */
    public function allComments()
    {
        return $this->hasMany(VideoComment::class, 'video_id');
    }

    /**
     * Check if video is from external link.
     */
    public function isLink()
    {
        return $this->video_type === 'link';
    }

    /**
     * Check if video is uploaded file.
     */
    public function isFile()
    {
        return $this->video_type === 'file';
    }

    /**
     * Get playback URL (storage asset or raw link).
     */
    public function getVideoUrlAttribute()
    {
        if ($this->video_type === 'file') {
            return $this->video_file ? asset('storage/' . $this->video_file) : '';
        }
        return $this->video_link ?: '';
    }

    /**
     * Get Embed URL for iframes (YouTube, Google Drive, Vimeo).
     */
    public function getEmbedUrlAttribute()
    {
        if ($this->video_type !== 'link' || empty($this->video_link)) {
            return null;
        }

        $url = trim($this->video_link);

        // 1. YouTube (Standard, Short, Embed, Shorts)
        if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/|youtube\.com\/shorts\/)([^"&?\/ ]{11})/i', $url, $matches)) {
            $youtubeId = $matches[1];
            return "https://www.youtube.com/embed/{$youtubeId}?autoplay=1&rel=0";
        }

        // 2. Google Drive
        if (preg_match('/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/i', $url, $matches)) {
            $driveId = $matches[1];
            return "https://drive.google.com/file/d/{$driveId}/preview";
        }

        // 3. Vimeo
        if (preg_match('/vimeo\.com\/(?:channels\/(?:\w+\/)?|groups\/([^\/]*)\/videos\/|album\/(\d+)\/video\/|video\/|)(\d+)/i', $url, $matches)) {
            $vimeoId = end($matches);
            return "https://player.vimeo.com/video/{$vimeoId}?autoplay=1";
        }

        return $url;
    }

    /**
     * Check if link can be embedded via iframe.
     */
    public function isEmbeddable()
    {
        if ($this->video_type !== 'link' || empty($this->video_link)) {
            return false;
        }

        $url = strtolower($this->video_link);
        return str_contains($url, 'youtube.com') ||
               str_contains($url, 'youtu.be') ||
               str_contains($url, 'drive.google.com') ||
               str_contains($url, 'vimeo.com');
    }

    /**
     * Get thumbnail URL (custom image or auto YouTube thumbnail).
     */
    public function getThumbnailUrlAttribute()
    {
        // Custom uploaded thumbnail
        if (!empty($this->thumbnail)) {
            return asset('storage/' . $this->thumbnail);
        }

        // Auto YouTube thumbnail
        if ($this->video_type === 'link' && !empty($this->video_link)) {
            if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/|youtube\.com\/shorts\/)([^"&?\/ ]{11})/i', $this->video_link, $matches)) {
                $youtubeId = $matches[1];
                return "https://img.youtube.com/vi/{$youtubeId}/hqdefault.jpg";
            }
        }

        return null;
    }
}
