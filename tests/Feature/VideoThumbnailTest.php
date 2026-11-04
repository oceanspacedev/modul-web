<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Cover otomatis dari frame video: form tambah/edit mengambil frame di browser, form edit membaca file lewat
 * rute video.source (satu domain dengan aplikasi).
 */
class VideoThumbnailTest extends TestCase
{
    public function test_video_forms_offer_automatic_cover(): void
    {
        Storage::fake('public')->put('videos/k3.mp4', 'fake-video-bytes');
        $admin = User::where('username', 'admin')->sole();
        $video = Video::create(['title' => 'K3', 'video_type' => 'file', 'video_file' => 'videos/k3.mp4']);

        $this->actingAs($admin)->get(route('video.create'))->assertOk()->assertSee('videoThumbnail.fromSelectedFile', false);
        $this->actingAs($admin)->get(route('video.edit', $video))->assertOk()
            ->assertSee('Ambil cover dari video')
            ->assertSee(json_encode(route('video.source', $video)), false);
    }

    public function test_video_source_is_streamed_to_managers_only(): void
    {
        Storage::fake('public')->put('videos/k3.mp4', 'fake-video-bytes');
        $video = Video::create(['title' => 'K3', 'video_type' => 'file', 'video_file' => 'videos/k3.mp4']);
        $admin = User::where('username', 'admin')->sole();

        $response = $this->actingAs($admin)->get(route('video.source', $video))->assertOk();
        $this->assertSame('fake-video-bytes', $response->streamedContent());

        $this->actingAs(User::factory()->staff()->create())->get(route('video.source', $video))->assertSessionHas('error');

        $link = Video::create(['title' => 'YT', 'video_type' => 'link', 'video_link' => 'https://youtu.be/dQw4w9WgXcQ']);
        $this->actingAs($admin)->get(route('video.source', $link))->assertNotFound();
    }
}
