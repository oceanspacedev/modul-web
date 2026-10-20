<?php

namespace Tests\Feature;

use App\Enums\TrainingStatus;
use App\Jobs\SendTrainingInvitation;
use App\Models\Document;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\User;
use App\Models\Video;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(['job_level_id' => 2, ...$attributes]);
        $user->syncRoles([$role]);

        return $user;
    }

    private function makeDocument(): Document
    {
        return Document::create([
            'name' => 'SOP PENJUALAN',
            'divisi_id' => 1,
            'job_level_id' => 2,
            'document_type' => 1,
            'path' => 'sop-penjualan-v1.pdf',
            'version' => 1,
        ]);
    }

    private function makeTraining(): Training
    {
        return Training::create([
            'title' => 'Training Keamanan',
            'trainer_id' => User::where('username', 'admin')->value('id'),
            'training_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'zoom_link' => 'https://zoom.us/j/1',
            'status' => TrainingStatus::Scheduled,
        ]);
    }

    private function addParticipant(Training $training): TrainingParticipant
    {
        return $training->participants()->create([
            'user_id' => User::factory()->create(['job_level_id' => 2, 'no_wa' => '081234567890'])->id,
            'token' => Str::random(40),
            'attendance_status' => 'pending',
        ]);
    }

    public function test_staff_with_view_permission_can_list_documents_but_not_deactivate_them(): void
    {
        $staff = $this->userWithRole('Staff');
        $document = $this->makeDocument();

        $this->actingAs($staff)->get('/document')->assertOk()->assertDontSee('Nonaktifkan Dokumen');

        $this->actingAs($staff)->from('/document')->delete("/document/{$document->id}")
            ->assertRedirect('/document')
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($document);
    }

    public function test_document_manager_can_deactivate_and_restore_documents(): void
    {
        $trainer = $this->userWithRole('Trainer');
        $document = $this->makeDocument();

        $this->actingAs($trainer)->delete("/document/{$document->id}")->assertRedirect('/document');
        $this->assertSoftDeleted($document);

        $this->actingAs($trainer)->patch("/document/{$document->id}/restore")->assertRedirect('/document');
        $this->assertNotSoftDeleted($document);
    }

    public function test_destructive_actions_are_no_longer_reachable_with_get_requests(): void
    {
        $admin = User::where('username', 'admin')->first();
        $victim = User::factory()->create(['job_level_id' => 2]);
        $training = $this->makeTraining();
        $document = $this->makeDocument();

        $this->actingAs($admin)->get("/user/delete/{$victim->id}")->assertNotFound();
        $this->actingAs($admin)->get("/training/delete/{$training->id}")->assertNotFound();
        $this->actingAs($admin)->get("/document/delete/{$document->id}")->assertNotFound();

        $this->assertNotSoftDeleted($victim);
        $this->assertNotSoftDeleted($training);
        $this->assertNotSoftDeleted($document);
    }

    public function test_login_redirect_parameter_cannot_send_users_to_another_site(): void
    {
        $user = User::factory()->create(['username' => 'redirect_user', 'job_level_id' => 1]);

        $this->get('/?redirect='.urlencode('https://evil.test/phish'))->assertOk();

        $this->post('/login', ['username' => 'redirect_user', 'password' => 'password'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_redirect_parameter_still_returns_to_internal_pages(): void
    {
        User::factory()->create(['username' => 'internal_user', 'job_level_id' => 1]);

        $this->get('/?redirect='.urlencode(url('/video')))->assertOk();

        $this->post('/login', ['username' => 'internal_user', 'password' => 'password'])
            ->assertRedirect(url('/video'));
    }

    public function test_password_login_is_throttled_after_repeated_failures(): void
    {
        User::factory()->create(['username' => 'throttled_user']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => 'throttled_user', 'password' => 'wrong'])
                ->assertSessionHas('error', 'Username / Nomor WhatsApp atau Password salah.');
        }

        $this->post('/login', ['username' => 'throttled_user', 'password' => 'password'])
            ->assertSessionHas('error', 'Terlalu banyak percobaan login. Silakan coba lagi dalam 1 menit.');
        $this->assertGuest();
    }

    public function test_otp_verification_is_throttled_per_account(): void
    {
        User::factory()->create(['username' => 'otp_user', 'no_wa' => '081200000001']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/login/otp/verify', ['username' => 'otp_user', 'otp' => '000000'])->assertUnprocessable();
        }

        $this->postJson('/login/otp/verify', ['username' => 'otp_user', 'otp' => '000000'])->assertTooManyRequests();
    }

    public function test_user_managers_cannot_grant_administrator_access(): void
    {
        $manager = $this->userWithRole('Staff');
        $manager->givePermissionTo('manage-users');

        $payload = [
            'username' => 'calon_admin',
            'full_name' => 'Calon Admin',
            'password' => 'rahasia123',
            'divisi_id' => 1,
            'job_level_id' => 1,
            'role' => 'Admin',
        ];

        $this->actingAs($manager)->from('/user/create')->post('/user', $payload)
            ->assertRedirect('/user/create')
            ->assertSessionHasErrors(['job_level_id', 'role']);
        $this->assertDatabaseMissing('users', ['username' => 'calon_admin']);

        $this->actingAs($manager)->post('/user', [...$payload, 'job_level_id' => 2, 'role' => 'Staff'])
            ->assertRedirect('/user');
        $this->assertTrue(User::where('username', 'calon_admin')->first()->hasRole('Staff'));
    }

    public function test_user_validation_errors_are_returned_to_the_form(): void
    {
        $admin = User::where('username', 'admin')->first();

        $this->actingAs($admin)->from('/user/create')->post('/user', ['username' => 'admin'])
            ->assertRedirect('/user/create')
            ->assertSessionHasErrors(['username', 'full_name', 'password', 'divisi_id', 'job_level_id', 'role']);
    }

    public function test_user_can_be_deactivated_and_restored(): void
    {
        $admin = User::where('username', 'admin')->first();
        $user = User::factory()->create(['job_level_id' => 2]);

        $this->actingAs($admin)->delete("/user/{$user->id}")->assertRedirect('/user');
        $this->assertSoftDeleted($user);

        $this->actingAs($admin)->patch("/user/{$user->id}/restore")->assertRedirect('/user');
        $this->assertNotSoftDeleted($user);
    }

    public function test_uploaded_document_extension_comes_from_its_contents_not_its_name(): void
    {
        Storage::fake('public');
        $admin = User::where('username', 'admin')->first();

        $this->actingAs($admin)->post('/document', [
            'file' => UploadedFile::fake()->create('laporan.html', 10, 'application/pdf'),
            'document_type' => 1,
            'divisi_id' => 1,
            'job_level_id' => [2],
        ])->assertRedirect('/document');

        $document = Document::where('name', 'LAPORAN')->firstOrFail();
        $this->assertStringEndsWith('.pdf', $document->path);
        Storage::disk('public')->assertExists('dokumen/'.$document->path);
        $this->assertSame(1, $document->versions()->count());
    }

    public function test_broadcast_queues_one_invitation_per_participant(): void
    {
        Queue::fake();
        $admin = User::where('username', 'admin')->first();
        $training = $this->makeTraining();
        $this->addParticipant($training);
        $this->addParticipant($training);

        $this->actingAs($admin)->post("/training/{$training->id}/broadcast-wa")->assertSessionHas('success');

        Queue::assertPushed(SendTrainingInvitation::class, 2);
        $this->assertSame(2, $training->participants()->where('wa_status', 'Dalam antrean')->count());
    }

    public function test_participant_routes_are_scoped_to_their_training(): void
    {
        $admin = User::where('username', 'admin')->first();
        $training = $this->makeTraining();
        $otherParticipant = $this->addParticipant($this->makeTraining());

        $this->actingAs($admin)
            ->post("/training/{$training->id}/reset-attendance/{$otherParticipant->id}")
            ->assertNotFound();
    }

    public function test_staff_cannot_manage_trainings(): void
    {
        $staff = $this->userWithRole('Staff');
        $training = $this->makeTraining();

        $this->actingAs($staff)->from('/my-trainings')->delete("/training/{$training->id}")
            ->assertRedirect('/my-trainings')
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($training);
    }

    public function test_comment_can_only_be_deleted_by_its_author_or_an_administrator(): void
    {
        $author = $this->userWithRole('Staff');
        $stranger = $this->userWithRole('Staff');
        $video = Video::create(['title' => 'Video Uji', 'video_type' => 'link', 'video_link' => 'https://youtu.be/abc']);
        $comment = $video->allComments()->create(['user_id' => $author->id, 'comment' => 'Pertanyaan penting']);

        $this->actingAs($stranger)->delete("/video/comments/{$comment->id}")->assertSessionHas('error');
        $this->assertModelExists($comment);

        $this->actingAs($author)->delete("/video/comments/{$comment->id}")->assertSessionHas('success');
        $this->assertModelMissing($comment);
    }
}
