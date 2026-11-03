<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Facades\Storage;

class StorageUrl
{
    /**
     * Longest validity S3 accepts for a signed URL; for links that leave the app, such as exports.
     */
    public const MAX_TTL_MINUTES = 7 * 24 * 60;

    /**
     * Long enough to watch a full training recording, including seeking near the end.
     */
    private const DEFAULT_TTL_MINUTES = 6 * 60;

    /**
     * Browser-reachable URL for a file on the upload disk. Disks that can sign URLs (S3) get a
     * temporary signed one, so the bucket does not have to allow anonymous reads.
     */
    public static function for(string $path, int|DateTimeInterface $ttl = self::DEFAULT_TTL_MINUTES): string
    {
        $disk = Storage::disk();

        if (! $disk->providesTemporaryUrls()) {
            return $disk->url($path);
        }

        return $disk->temporaryUrl($path, is_int($ttl) ? now()->addMinutes($ttl) : $ttl);
    }
}
