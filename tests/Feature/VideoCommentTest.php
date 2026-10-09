<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use App\Models\VideoComment;
use Tests\TestCase;

class VideoCommentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Video::create([
            'title' => 'Sample Test Video',
            'video_type' => 'link',
            'video_link' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
    }

    public function test_guest_can_view_video_and_sees_login_prompt_for_comments()
    {
        $video = Video::first();
        if (! $video) {
            $video = Video::create([
                'title' => 'Sample Test Video',
                'video_type' => 'link',
                'video_link' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ]);
        }

        $response = $this->get('/video/watch/'.$video->id);
        $response->assertStatus(200);
        $response->assertSee('Diskusi & Tanya Jawab Materi', false);
        $response->assertSee('Masuk / Login untuk Bertanya');
    }

    public function test_guest_cannot_post_comment_and_is_redirected()
    {
        $video = Video::first();
        $response = $this->post('/video/'.$video->id.'/comments', [
            'comment' => 'Pertanyaan dari guest tanpa login',
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_logged_in_user_can_post_comment_and_reply()
    {
        $user = User::first() ?? User::factory()->create();
        $video = Video::first();

        // 1. Post top-level comment
        $commentText = 'Bagaimana alur kerja modul bagian ketiga?';
        $postResponse = $this->actingAs($user)->post('/video/'.$video->id.'/comments', [
            'comment' => $commentText,
        ]);

        $postResponse->assertRedirect('/video/watch/'.$video->id.'#comments');
        $postResponse->assertSessionHas('success');

        $this->assertDatabaseHas('video_comments', [
            'video_id' => $video->id,
            'user_id' => $user->id,
            'parent_id' => null,
            'comment' => $commentText,
        ]);

        $comment = VideoComment::where('video_id', $video->id)->where('comment', $commentText)->first();
        $this->assertNotNull($comment);

        // 2. Reply to the comment
        $replyText = 'Untuk bagian ketiga, silakan ikuti petunjuk slide 12.';
        $replyResponse = $this->actingAs($user)->post('/video/'.$video->id.'/comments', [
            'parent_id' => $comment->id,
            'comment' => $replyText,
        ]);

        $replyResponse->assertRedirect('/video/watch/'.$video->id.'#comments');
        $replyResponse->assertSessionHas('success');

        $this->assertDatabaseHas('video_comments', [
            'video_id' => $video->id,
            'user_id' => $user->id,
            'parent_id' => $comment->id,
            'comment' => $replyText,
        ]);

        // 3. View page and see both comments
        $viewResponse = $this->actingAs($user)->get('/video/watch/'.$video->id);
        $viewResponse->assertStatus(200);
        $viewResponse->assertSee($commentText);
        $viewResponse->assertSee($replyText);

        // 4. Author can delete comment
        $deleteResponse = $this->actingAs($user)->delete('/video/comments/'.$comment->id);
        $deleteResponse->assertRedirect('/video/watch/'.$video->id.'#comments');
        $this->assertDatabaseMissing('video_comments', ['id' => $comment->id]);
    }
}
