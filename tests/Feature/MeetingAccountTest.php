<?php

namespace Tests\Feature;

use App\Models\MeetingAccount;
use App\Models\Training;
use App\Models\User;
use Tests\TestCase;

class MeetingAccountTest extends TestCase
{
    public function test_only_the_owner_can_list_or_manage_a_saved_zoom_account(): void
    {
        $owner = User::factory()->staff()->trainer()->create();
        $other = User::factory()->staff()->trainer()->create();
        $list = route('training.meeting-accounts.index');
        $this->actingAs(User::factory()->staff()->create())->getJson($list)->assertForbidden();
        $this->actingAs($owner)->post(route('training.meeting-accounts.store'), [
            'user_id' => $other->id,
            'provider' => 'zoom',
            'name' => 'Zoom OD',
            'credentials' => ['client_id' => 'sdk-id-for-tests', 'client_secret' => 'sdk-secret-for-tests'],
        ])->assertRedirect($list)->assertSessionHasNoErrors();

        $account = MeetingAccount::sole();
        $this->assertSame($owner->id, $account->user_id);
        $this->assertSame('zoom', $account->provider);
        $this->assertSame('sdk-id-for-tests', $account->credential('client_id'));
        $this->assertSame('sdk-secret-for-tests', $account->credential('client_secret'));
        $this->assertStringNotContainsString('sdk-secret-for-tests', $account->getRawOriginal('credentials'));
        $this->assertStringNotContainsString('sdk-id-for-tests', $account->getRawOriginal('credentials'));
        $this->getJson($list)->assertExactJson(['accounts' => [['id' => $account->id, 'name' => 'Zoom OD', 'provider' => 'zoom']]]);

        // Platform tanpa integrasi tidak punya akun penyelenggara.
        $this->actingAs($owner)->post(route('training.meeting-accounts.store'), [
            'provider' => 'other', 'name' => 'Lainnya', 'credentials' => ['client_id' => 'x', 'client_secret' => 'y'],
        ])->assertSessionHasErrors('provider');

        // Kredensial mengikuti platformnya: kolom Zoom tidak berlaku untuk Google Meet.
        $this->post(route('training.meeting-accounts.store'), [
            'provider' => 'google_meet', 'name' => 'Meet', 'credentials' => ['client_id' => 'x', 'client_secret' => 'y'],
        ])->assertSessionHasErrors(['credentials.organizer_email', 'credentials.service_account_json']);
        $this->post(route('training.meeting-accounts.store'), [
            'provider' => 'google_meet', 'name' => 'Meet',
            'credentials' => ['organizer_email' => 'od@example.test', 'service_account_json' => '{"client_email":"x"}'],
        ])->assertSessionHasErrors('credentials.service_account_json');
        $this->post(route('training.meeting-accounts.store'), [
            'provider' => 'teams', 'name' => 'Teams',
            'credentials' => ['tenant_id' => 'contoso/evil', 'client_id' => 'app', 'client_secret' => 'secret', 'organizer_id' => 'dc17674c-81d9-4adb-bfb2-8f6a442e4622'],
        ])->assertSessionHasErrors('credentials.tenant_id');
        $this->assertSame(1, MeetingAccount::count());
        $this->get($list)->assertOk()->assertSee('Zoom OD')->assertDontSee('sdk-secret-for-tests')->assertDontSee('sdk-id-for-tests');

        $this->actingAs($other)->getJson($list)->assertExactJson(['accounts' => []]);
        $this->patchJson(route('training.meeting-accounts.update', $account), ['name' => 'Tidak berwenang'])->assertNotFound();
        $this->deleteJson(route('training.meeting-accounts.destroy', $account))->assertNotFound();
    }

    public function test_credential_rotation_requires_both_values_and_never_flashes_them(): void
    {
        $owner = User::factory()->staff()->trainer()->create();
        $account = $this->account($owner);
        $list = route('training.meeting-accounts.index');
        $update = route('training.meeting-accounts.update', $account);
        $this->actingAs($owner)->from($list)->patch($update, [
            'name' => 'Zoom OD baru',
            'credentials' => ['client_id' => '', 'client_secret' => 'secret-half-rotation'],
        ])->assertRedirect($list)->assertSessionHasErrors('credentials.client_id')
            ->assertSessionMissing('_old_input.credentials');

        $this->patch($update, ['name' => 'Zoom OD baru', 'credentials' => ['client_id' => '', 'client_secret' => '']])
            ->assertRedirect($list);
        $account->refresh();
        $this->assertSame('Zoom OD baru', $account->name);
        $this->assertSame('old-sdk-id', $account->credential('client_id'));
        $this->assertSame('old-sdk-secret', $account->credential('client_secret'));

        $this->patch($update, ['name' => $account->name, 'credentials' => ['client_id' => 'new-sdk-id', 'client_secret' => 'new-sdk-secret']])
            ->assertRedirect($list);
        $account->refresh();
        $this->assertSame('new-sdk-id', $account->credential('client_id'));
        $this->assertSame('new-sdk-secret', $account->credential('client_secret'));
    }

    public function test_an_account_is_kept_while_assigned_to_any_training(): void
    {
        $owner = User::factory()->staff()->trainer()->create();
        $account = $this->account($owner);
        $training = Training::create([
            'title' => 'Pelatihan Zoom',
            'trainer_id' => $owner->id,
            'training_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'meeting_link' => 'https://zoom.us/j/81234567890',
            'meeting_account_id' => $account->id,
        ]);
        $training->delete();
        $destroy = route('training.meeting-accounts.destroy', $account);
        $list = route('training.meeting-accounts.index');

        $this->actingAs($owner)->delete($destroy)->assertRedirect($list)->assertSessionHas('error');
        $this->assertModelExists($account);

        $training->forceDelete();
        $this->delete($destroy)->assertRedirect($list)->assertSessionHas('success');
        $this->assertModelMissing($account);
    }

    private function account(User $owner): MeetingAccount
    {
        return MeetingAccount::create([
            'user_id' => $owner->id,
            'name' => 'Zoom',
            'provider' => 'zoom',
            'credentials' => ['client_id' => 'old-sdk-id', 'client_secret' => 'old-sdk-secret'],
        ]);
    }
}
