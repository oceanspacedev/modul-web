<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use DatabaseTransactions;
    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_the_application_returns_a_successful_response()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_dashboard_page_loads_with_all_metrics()
    {
        $user = \App\Models\User::first() ?? \App\Models\User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Total Pengguna');
        $response->assertSee('Jadwal Pelatihan');
        $response->assertSee('Video Materi');
        $response->assertSee('Peserta Pelatihan');
        $response->assertSee('Dokumen Modul');
        $response->assertSee('Kuis Modul');
        $response->assertSee('Hasil Evaluasi Kuis');
        $response->assertSee('Presensi Karyawan');
        $response->assertSee('chartPelatihan');
        $response->assertSee('chartQuizScore');
        $response->assertSee('chartVideoViews');
        $response->assertSee('chartContentComposition');
        $response->assertSee('Chart.bundle.min.js');
    }

    public function test_training_video_shortcut_upload_creates_video()
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $user = \App\Models\User::first() ?? \App\Models\User::factory()->create();
        $training = \App\Models\Training::first();

        if (!$training) {
            $training = \App\Models\Training::create([
                'title' => 'Sesi Training Docker',
                'trainer_id' => $user->id,
                'training_date' => now()->toDateString(),
                'start_time' => '09:00:00',
                'end_time' => '11:00:00',
                'zoom_link' => 'https://zoom.us/j/123456789',
                'status' => 'completed',
            ]);
        }

        // 1. Check training detail page has shortcut button and modal
        $showResponse = $this->actingAs($user)->get('/training/' . $training->id);
        $showResponse->assertStatus(200);
        $showResponse->assertSee('Upload Video Materi');
        $showResponse->assertSee('uploadTrainingVideoModal');

        // 2. Submit shortcut video upload
        $videoFile = \Illuminate\Http\UploadedFile::fake()->create('rekaman_pelatihan.mp4', 500, 'video/mp4');
        $uploadResponse = $this->actingAs($user)->post(route('video.store'), [
            'training_id' => $training->id,
            'title' => $training->title,
            'description' => 'Rekaman video materi sesi pelatihan ' . $training->title,
            'video_type' => 'file',
            'video_file' => $videoFile,
            'redirect_to' => '/training/' . $training->id,
        ]);

        $uploadResponse->assertRedirect('/training/' . $training->id);
        $uploadResponse->assertSessionHas('success');

        // 3. Verify video exists in database and is linked to the training
        $this->assertDatabaseHas('videos', [
            'training_id' => $training->id,
            'title' => $training->title,
        ]);

        $training->refresh();
        $this->assertNotNull($training->video);
        $this->assertEquals($training->title, $training->video->title);
    }
}
