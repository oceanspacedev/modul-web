<?php

namespace Tests\Feature;

use App\Models\MeetingAccount;
use App\Models\Training;
use App\Models\TrainingMeetingChatMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TrainingMeetingIntegrationTest extends TestCase
{
    public function test_zoom_signature_is_only_issued_to_training_managers_for_the_configured_meeting(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $training = $this->training($admin, $this->account($admin, 'first'));
        $otherTraining = $this->training($admin, $this->account($admin, 'second'));
        $route = route('training.meeting.join', $training);

        $this->actingAs(User::factory()->staff()->create())->postJson($route)->assertForbidden();
        $this->actingAs($admin)->get(route('training.meeting.show', $training))
            ->assertOk()->assertSee('Mulai Monitoring')->assertDontSee('join-token')->assertDontSee('sdk-secret-first');
        $response = $this->postJson($route, ['role' => 1, 'meetingNumber' => '92222222222'])
            ->assertOk()
            ->assertJsonPath('meetingCode', '81234567890')
            ->assertDontSee('sdk-secret-first');
        $otherResponse = $this->postJson(route('training.meeting.join', $otherTraining))
            ->assertOk()->assertDontSee('sdk-secret-second');

        $claims = $this->claimsFor($response->json('signature'), 'sdk-secret-first');
        $this->assertSame('81234567890', $claims['mn']);
        $this->assertSame(0, $claims['role']);
        $this->assertSame('sdk-id-first', $claims['appKey']);
        $this->assertSame('sdk-id-second', $this->claimsFor($otherResponse->json('signature'), 'sdk-secret-second')['appKey']);
        $this->assertNotSame($response->json('accountRevision'), $otherResponse->json('accountRevision'));

        $training->update(['meeting_platform' => 'teams']);
        $this->postJson($route)->assertConflict();

        $training->update(['meeting_platform' => 'zoom', 'meeting_link' => 'https://zoom.us.example.org/j/81234567890']);
        $this->postJson($route)->assertNotFound();
    }

    public function test_chat_import_uses_opt_in_and_preserves_read_access_to_public_messages(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $training = $this->training($admin);
        $page = route('training.meeting.show', $training);
        $upload = route('training.meeting.chat', $training);
        $contents = "09:00:00 From Ana to Everyone: Selamat pagi\n09:00:01 From Ana to Dani: Pesan privat";

        $this->actingAs($admin)->get($page)
            ->assertOk()
            ->assertSee('Monitoring meeting nonaktif')
            ->assertDontSee('Impor Chat')
            ->assertDontSee('https://source.zoom.us/');
        $this->postJson(route('training.meeting.join', $training))->assertConflict();
        $this->post($upload, [
            'chat_file' => UploadedFile::fake()->createWithContent('meeting_saved_chat.txt', $contents),
        ])->assertConflict();

        $training->update(['meeting_monitoring_enabled' => true, 'meeting_account_id' => $this->account($admin, 'chat')->id]);
        $revision = $this->get($page)->assertOk()->viewData('monitorRevision');

        foreach (range(1, 2) as $attempt) {
            $this->post($upload, [
                'account_revision' => $revision,
                'chat_file' => UploadedFile::fake()->createWithContent('meeting_saved_chat.txt', $contents),
            ])->assertRedirect($page)->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount(TrainingMeetingChatMessage::class, 1);
        $training->update(['meeting_monitoring_enabled' => false]);
        $this->get($page)->assertOk()->assertSee('Selamat pagi')->assertDontSee('Impor Chat')->assertDontSee('Pesan privat');
    }

    public function test_stale_chat_forms_cannot_upload_after_link_or_credentials_change(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $account = $this->account($admin, 'chat');
        $training = $this->training($admin, $account);
        $page = route('training.meeting.show', $training);
        $upload = route('training.meeting.chat', $training);
        $this->actingAs($admin);

        $oldPage = $this->get($page)->assertOk();
        $oldRevision = $oldPage->viewData('monitorRevision');
        $oldPage->assertSee('name="account_revision" value="'.$oldRevision.'"', false);
        $training->update(['meeting_link' => 'https://zoom.us/j/91111111111']);

        $this->from($page)->post($upload, [
            'account_revision' => $oldRevision,
            'chat_file' => UploadedFile::fake()->createWithContent('meeting_saved_chat.txt', '09:00:00 From Ana to Everyone: Chat sesi pertama'),
        ])->assertRedirect($page)->assertSessionHasErrors([
            'chat_file' => 'Pengaturan meeting telah berubah. Muat ulang halaman sebelum mengimpor chat.',
        ]);

        $beforeRotation = $this->get($page)->assertOk()->viewData('monitorRevision');
        $account->update(['credentials' => ['client_id' => 'changed-sdk-id', 'client_secret' => 'changed-sdk-secret']]);

        $this->from($page)->post($upload, [
            'account_revision' => $beforeRotation,
            'chat_file' => UploadedFile::fake()->createWithContent('meeting_saved_chat.txt', 'File ini tidak berformat chat Zoom'),
        ])->assertRedirect($page)->assertSessionHasErrors([
            'chat_file' => 'Pengaturan meeting telah berubah. Muat ulang halaman sebelum mengimpor chat.',
        ]);

        $this->assertDatabaseCount(TrainingMeetingChatMessage::class, 0);
    }

    private function claimsFor(string $jwt, string $secret): array
    {
        [$header, $body, $signature] = explode('.', $jwt);
        $expected = hash_hmac('sha256', "$header.$body", $secret, true);
        $this->assertSame($expected, base64_decode(strtr($signature, '-_', '+/'), true));

        return json_decode(base64_decode(strtr($body, '-_', '+/'), true), true, flags: JSON_THROW_ON_ERROR);
    }

    private function account(User $owner, string $name): MeetingAccount
    {
        return MeetingAccount::create([
            'user_id' => $owner->id,
            'name' => $name,
            'provider' => 'zoom',
            'credentials' => ['client_id' => 'sdk-id-'.$name, 'client_secret' => 'sdk-secret-'.$name],
        ]);
    }

    private function training(User $trainer, ?MeetingAccount $account = null): Training
    {
        return Training::create([
            'title' => 'Pelatihan Zoom',
            'trainer_id' => $trainer->id,
            'training_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'ongoing',
            'meeting_link' => 'https://company.zoom.us/j/81234567890?pwd=join-token',
            'meeting_platform' => 'zoom',
            'meeting_monitoring_enabled' => $account !== null,
            'meeting_account_id' => $account?->id,
        ]);
    }
}
