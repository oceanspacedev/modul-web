<?php

namespace Tests\Feature;

use App\Models\TrainingParticipant;
use App\Models\Video;
use App\Support\StorageUrl;
use DateTimeInterface;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageUrlTest extends TestCase
{
    public function test_public_disk_files_get_a_plain_url()
    {
        // The real "public" disk, not Storage::fake(), whose disks always sign URLs.
        $video = Video::make(['video_type' => 'file', 'video_file' => 'videos/materi.mp4']);

        $this->assertSame(Storage::url('videos/materi.mp4'), $video->video_url);
        $this->assertStringNotContainsString('signature', $video->video_url);
    }

    public function test_disks_that_sign_urls_get_a_temporary_url_for_uploads()
    {
        $expiries = [];
        Storage::fake('public')->buildTemporaryUrlsUsing(function (string $path, DateTimeInterface $expiration) use (&$expiries) {
            $expiries[] = $expiration;

            return 'https://bucket.test/'.$path.'?signature=abc';
        });

        $video = Video::make([
            'video_type' => 'file',
            'video_file' => 'videos/materi.mp4',
            'thumbnail' => 'videos/thumbnails/cover.jpg',
        ]);
        $participant = TrainingParticipant::make(['attendance_proof' => 'attendance_proofs/bukti.png']);

        $this->assertSame('https://bucket.test/videos/materi.mp4?signature=abc', $video->video_url);
        $this->assertSame('https://bucket.test/videos/thumbnails/cover.jpg?signature=abc', $video->thumbnail_url);
        $this->assertSame('https://bucket.test/attendance_proofs/bukti.png?signature=abc', $participant->attendance_proof_url);

        StorageUrl::for('attendance_proofs/bukti.png', StorageUrl::MAX_TTL_MINUTES);

        $this->assertTrue(end($expiries)->greaterThan(now()->addDays(6)));
        $this->assertTrue($expiries[0]->lessThan(now()->addDay()));
    }
}
