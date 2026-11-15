<?php

namespace Tests\Feature;

use App\Models\MeetingAccount;
use App\Models\Training;
use App\Models\User;
use Tests\TestCase;

class TrainingMeetingSettingsTest extends TestCase
{
    public function test_creator_can_select_a_zoom_account_per_training_and_switch_to_another_platform(): void
    {
        $creator = User::where('username', 'admin')->firstOrFail();
        $firstAccount = $this->account($creator, 'Akun Zoom Kantor');
        $secondAccount = $this->account($creator, 'Akun Zoom Pelatih');
        $payload = $this->payload($creator);
        $zoom = [
            'meeting_platform' => 'zoom',
            'meeting_link' => 'https://kantor.zoom.us/j/123456789',
            'meeting_monitoring_enabled' => '1',
        ];

        $this->actingAs($creator)->get(route('training.create'))
            ->assertOk()->assertSee('Akun Zoom Kantor')->assertSee('Akun Zoom Pelatih')
            ->assertDontSee('sdk-secret');
        $this->post(route('training.store'), [...$payload, ...$zoom, 'meeting_account_id' => $firstAccount->id])
            ->assertSessionHasNoErrors();
        $this->post(route('training.store'), [
            ...$payload, ...$zoom, 'title' => 'Pelatihan Kedua', 'meeting_account_id' => $secondAccount->id,
        ])->assertSessionHasNoErrors();

        $first = Training::where('title', $payload['title'])->sole();
        $second = Training::where('title', 'Pelatihan Kedua')->sole();
        $this->assertTrue($first->hasMeetingMonitoring());
        $this->assertTrue($second->hasMeetingMonitoring());
        $this->assertSame($firstAccount->id, $first->meetingAccount->id);
        $this->assertSame($secondAccount->id, $second->meetingAccount->id);
        $previousRevision = $first->meeting_monitoring_revision;

        // Akun Zoom tidak bisa dipakai untuk platform lain.
        $meet = [...$payload, 'meeting_platform' => 'google_meet', 'meeting_link' => 'https://meet.google.com/abc-defg-hij', 'status' => 'scheduled'];
        $this->put(route('training.update', $first), [
            ...$meet, 'meeting_monitoring_enabled' => '1', 'meeting_account_id' => $firstAccount->id,
        ])->assertSessionHasErrors('meeting_account_id');
        $this->put(route('training.update', $first), [...$meet, 'meeting_monitoring_enabled' => '0', 'meeting_account_id' => ''])
            ->assertSessionHasNoErrors();
        $first->refresh();
        $this->assertFalse($first->hasMeetingMonitoring());
        $this->assertFalse($first->meeting_monitoring_enabled);
        $this->assertNull($first->meeting_account_id);
        $this->assertNotSame($previousRevision, $first->meeting_monitoring_revision);
        $this->assertSame('Google Meet', $first->meetingPlatformLabel());
    }

    public function test_editing_a_training_does_not_allow_reusing_another_creators_zoom_account_for_a_different_link(): void
    {
        $creator = User::factory()->staff()->trainer()->create();
        $editor = User::factory()->staff()->trainer()->create();
        $account = $this->account($creator, 'Akun Panitia');
        $payload = $this->payload($creator);
        $zoom = [
            'meeting_platform' => 'zoom',
            'meeting_link' => 'https://zoom.us/j/123456789',
            'meeting_monitoring_enabled' => '1',
            'meeting_account_id' => $account->id,
        ];

        $this->actingAs($editor)->post(route('training.store'), [...$payload, ...$zoom])->assertSessionHasErrors('meeting_account_id');
        $this->actingAs($creator)->post(route('training.store'), [...$payload, ...$zoom])->assertSessionHasNoErrors();
        $training = Training::where('title', $payload['title'])->sole();
        $previousRevision = $training->meeting_monitoring_revision;

        $this->actingAs($editor)->put(route('training.update', $training), [
            ...$payload, 'title' => 'Ganti Judul', 'status' => 'scheduled',
        ])->assertSessionHasNoErrors();
        $training->refresh();
        $this->assertTrue($training->hasMeetingMonitoring());
        $this->assertSame($previousRevision, $training->meeting_monitoring_revision);

        $this->put(route('training.update', $training), [
            ...$payload, ...$zoom, 'meeting_link' => 'https://zoom.us/j/987654321', 'status' => 'scheduled',
        ])->assertSessionHasErrors('meeting_account_id');
        $this->assertSame($zoom['meeting_link'], $training->refresh()->meeting_link);
    }

    public function test_a_meeting_link_can_be_used_with_monitoring_off_and_without_an_account(): void
    {
        $creator = User::where('username', 'admin')->firstOrFail();
        $payload = $this->payload($creator);
        $settings = ['meeting_platform' => 'zoom', 'meeting_link' => 'https://zoom.us/j/123456789'];

        $this->actingAs($creator)->post(route('training.store'), [
            ...$payload, ...$settings, 'meeting_monitoring_enabled' => '1',
        ])->assertSessionHasErrors('meeting_account_id');
        $this->post(route('training.store'), [
            ...$payload, ...$settings, 'meeting_monitoring_enabled' => '0',
        ])->assertSessionHasNoErrors();
        $training = Training::where('title', $payload['title'])->sole();
        $this->assertSame('Zoom', $training->meetingPlatformLabel());
        $this->assertFalse($training->hasMeetingMonitoring());
        $this->assertNull($training->meeting_account_id);

        $this->put(route('training.update', $training), [
            ...$payload, ...$settings, 'meeting_platform' => '', 'status' => 'scheduled',
        ])->assertSessionHasNoErrors();
        $this->assertFalse($training->refresh()->isOnline());
    }

    private function account(User $owner, string $name): MeetingAccount
    {
        return MeetingAccount::create([
            'user_id' => $owner->id, 'name' => $name, 'provider' => 'zoom', 'credentials' => ['client_id' => 'sdk-id', 'client_secret' => 'sdk-secret'],
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(User $trainer): array
    {
        return [
            'title' => 'Pelatihan Pertama',
            'trainer_id' => $trainer->id,
            'training_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'participants' => [User::factory()->staff()->create()->id],
        ];
    }
}
