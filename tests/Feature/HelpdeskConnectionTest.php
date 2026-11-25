<?php

namespace Tests\Feature;

use App\Jobs\SendHelpdeskWebhook;
use App\Models\DocumentCategory;
use App\Models\DocumentRequest;
use App\Models\IntegrationSetting;
use App\Models\User;
use App\Support\HelpdeskConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pengaturan Helpdesk: kode koneksi menggantikan HELPDESK_* di .env, dan helpdesk mendaftarkan webhook-nya sendiri.
 */
class HelpdeskConnectionTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::where('username', 'admin')->sole();
        config([
            'app.url' => 'https://modul.test',
            'services.helpdesk.api_token' => 'token-env-lama',
            'services.helpdesk.webhook_url' => 'https://helpdesk-lama.test/api/integrations/1/events',
            'services.helpdesk.webhook_secret' => 'rahasia-env-lama',
        ]);
    }

    /** @return array{v: int, name: string, url: string, token: string} */
    private function issueCode(): array
    {
        $response = $this->actingAs($this->admin)->post(route('helpdesk-settings.issue'))
            ->assertRedirect(route('helpdesk-settings.index'))
            ->assertSessionHas('helpdesk_connection_code');

        $code = (string) $response->getSession()->get('helpdesk_connection_code');
        $this->assertStringStartsWith(HelpdeskConnection::CODE_PREFIX, $code);

        return json_decode(base64_decode(strtr(substr($code, strlen(HelpdeskConnection::CODE_PREFIX)), '-_', '+/')), true);
    }

    public function test_connection_code_carries_the_api_url_and_a_token_that_replaces_the_env_token(): void
    {
        $this->withToken('token-env-lama')->getJson('/api/helpdesk/v1/categories')->assertOk();

        $payload = $this->issueCode();

        $this->assertSame(1, $payload['v']);
        $this->assertNotSame('Laravel', $payload['name']);
        $this->assertSame('https://modul.test/api/helpdesk/v1', $payload['url']);
        $this->assertSame(48, strlen($payload['token']));
        $this->assertSame(hash('sha256', $payload['token']), DB::table('integration_settings')->where('key', 'helpdesk.token_hash')->value('value'), 'Hanya hash token yang disimpan.');

        $this->withToken($payload['token'])->getJson('/api/helpdesk/v1/categories')->assertOk();
        $this->withToken('token-env-lama')->getJson('/api/helpdesk/v1/categories')->assertUnauthorized();
        $this->assertSame('app', HelpdeskConnection::tokenSource());
    }

    public function test_the_code_is_shown_once_on_the_settings_page(): void
    {
        $this->actingAs($this->admin)->get(route('helpdesk-settings.index'))
            ->assertOk()
            ->assertSee('Terhubung lewat HELPDESK_API_TOKEN di .env')
            ->assertSee('https://modul.test/api/helpdesk/v1')
            ->assertSee('Buat kode koneksi');

        $this->actingAs($this->admin)->followingRedirects()->post(route('helpdesk-settings.issue'))
            ->assertSee(HelpdeskConnection::CODE_PREFIX)
            ->assertSee('hanya ditampilkan sekali');

        $this->actingAs($this->admin)->get(route('helpdesk-settings.index'))
            ->assertDontSee('id="helpdesk-code"', false)
            ->assertSee('Kode koneksi aktif sejak');
    }

    public function test_helpdesk_registers_its_webhook_which_signs_every_following_webhook(): void
    {
        $token = $this->issueCode()['token'];
        $this->assertSame('env', HelpdeskConnection::webhookSource(), 'Selama masa tenggang webhook lama tetap jalan.');

        $this->withToken($token)->putJson('/api/helpdesk/v1/webhook', [
            'url' => 'https://helpdesk.test/api/integrations/7/events',
            'secret' => 'rahasia-baru-dari-helpdesk-123',
        ])->assertOk()->assertJsonPath('data.registered', true);

        $this->assertTrue(SendHelpdeskWebhook::configured());
        $this->assertSame('app', HelpdeskConnection::webhookSource());
        $this->assertSame('https://helpdesk.test/api/integrations/7/events', HelpdeskConnection::webhookUrl());
        $this->assertNotSame('rahasia-baru-dari-helpdesk-123', DB::table('integration_settings')->where('key', 'helpdesk.webhook_secret')->value('value'), 'Rahasia webhook harus terenkripsi.');
        $this->assertSame(
            'sha256='.hash_hmac('sha256', '1700000000.{}', 'rahasia-baru-dari-helpdesk-123'),
            SendHelpdeskWebhook::signature('1700000000', '{}'),
        );

        $this->actingAs($this->admin)->get(route('helpdesk-settings.index'))
            ->assertSee('Didaftarkan helpdesk')
            ->assertSee('https://helpdesk.test/api/integrations/7/events');
    }

    public function test_a_new_code_keeps_the_old_token_working_until_the_helpdesk_uses_the_new_one(): void
    {
        $first = $this->issueCode()['token'];
        $this->withToken($first)->putJson('/api/helpdesk/v1/webhook', ['url' => 'https://helpdesk.test/lama', 'secret' => str_repeat('a', 20)])->assertOk();

        $second = $this->issueCode()['token'];

        // Helpdesk belum memakai kode baru: token dan webhook lama tetap jalan.
        $this->withToken($first)->getJson('/api/helpdesk/v1/categories')->assertOk();
        $this->assertSame('https://helpdesk.test/lama', HelpdeskConnection::webhookUrl());
        $this->assertNotNull(HelpdeskConnection::previousTokenValidUntil());
        $this->actingAs($this->admin)->get(route('helpdesk-settings.index'))->assertSee('Kode lama masih berlaku sampai');

        // Begitu helpdesk memakai kode baru, token lama langsung ditolak.
        $this->withToken($second)->getJson('/api/helpdesk/v1/categories')->assertOk();
        $this->withToken($first)->getJson('/api/helpdesk/v1/categories')->assertUnauthorized();
        $this->assertNull(HelpdeskConnection::previousTokenValidUntil());
    }

    public function test_the_old_token_expires_after_the_grace_period(): void
    {
        $this->issueCode();

        $this->withToken('token-env-lama')->getJson('/api/helpdesk/v1/categories')->assertOk();
        $this->assertSame('env', HelpdeskConnection::webhookSource());

        $this->travel(HelpdeskConnection::GRACE_HOURS)->hours();
        $this->travel(1)->minutes();

        $this->withToken('token-env-lama')->getJson('/api/helpdesk/v1/categories')->assertUnauthorized();
        $this->assertNull(HelpdeskConnection::webhookSource(), 'Setelah masa tenggang, webhook .env tidak dipakai lagi.');
    }

    public function test_webhook_registration_needs_the_token_and_a_valid_url_and_secret(): void
    {
        $token = $this->issueCode()['token'];

        $this->putJson('/api/helpdesk/v1/webhook', ['url' => 'https://helpdesk.test/x', 'secret' => str_repeat('a', 20)])->assertUnauthorized();
        $this->withToken($token)->putJson('/api/helpdesk/v1/webhook', ['url' => 'ftp://helpdesk.test/x', 'secret' => str_repeat('a', 20)])
            ->assertUnprocessable()->assertJsonValidationErrors('url');
        $this->withToken($token)->putJson('/api/helpdesk/v1/webhook', ['url' => 'https://helpdesk.test/x', 'secret' => 'pendek'])
            ->assertUnprocessable()->assertJsonValidationErrors('secret');
        $this->assertNull(IntegrationSetting::value(HelpdeskConnection::WEBHOOK_URL));
    }

    public function test_webhook_registration_rejects_metadata_and_credential_urls(): void
    {
        $token = $this->issueCode()['token'];

        foreach (['http://169.254.169.254/latest/meta-data/', 'http://user:pass@helpdesk.test/e', 'http://[fe80::1]/e', 'http://metadata.google.internal/x'] as $url) {
            $this->withToken($token)->putJson('/api/helpdesk/v1/webhook', ['url' => $url, 'secret' => str_repeat('a', 20)])
                ->assertUnprocessable()->assertJsonValidationErrors('url');
        }
        // Jaringan internal biasa tetap boleh (helpdesk sering satu jaringan dengan Modul).
        $this->withToken($token)->putJson('/api/helpdesk/v1/webhook', ['url' => 'http://10.0.0.5/api/integrations/1/events', 'secret' => str_repeat('a', 20)])->assertOk();
    }

    public function test_webhook_delivery_result_is_recorded_and_a_helpdesk_outage_never_breaks_the_od_action(): void
    {
        $token = $this->issueCode()['token'];
        $this->withToken($token)->putJson('/api/helpdesk/v1/webhook', ['url' => 'https://helpdesk.test/api/integrations/1/events', 'secret' => str_repeat('a', 20)])->assertOk();
        $helpdeskUp = false;
        Http::fake(function ($request) use (&$helpdeskUp) {
            if (str_contains($request->url(), 'helpdesk.test')) {
                return $helpdeskUp ? Http::response(['ok' => true]) : Http::response(['status' => 'invalid_signature'], 401);
            }

            return Http::response(['status' => true]);
        });

        $request = DocumentRequest::query()->create([
            'code' => 'IK-20261126-TEST', 'nama' => 'Budi', 'divisi' => 'Gudang', 'departemen' => 'Warehouse', 'jabatan' => 'Staff', 'whatsapp' => '081234567890', 'email' => 'budi@example.com',
            'tanggal' => now(), 'document_category_id' => DocumentCategory::where('code', 'IK')->value('id'),
            'source' => DocumentRequest::SOURCE_HELPDESK, 'helpdesk_ticket_number' => 'HD-2026-0001',
        ]);

        // Antrean sync: helpdesk menolak, tetapi komentar tim OD tetap tersimpan tanpa error.
        $this->actingAs($this->admin)->post(route('od-report.requests.comment', $request), ['body' => 'Halo'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, $request->comments()->count());
        $this->assertStringContainsString('HTTP 401', (string) HelpdeskConnection::lastDelivery()['error']);
        $this->actingAs($this->admin)->get(route('helpdesk-settings.index'))->assertSee('rahasia webhook tidak cocok');

        $helpdeskUp = true;
        (new SendHelpdeskWebhook($request->id))->handle();
        $this->assertNull(HelpdeskConnection::lastDelivery()['error']);
    }

    public function test_webhook_test_sends_a_signed_ping_and_reports_the_outcome(): void
    {
        $token = $this->issueCode()['token'];
        config(['services.helpdesk.webhook_url' => null]);
        $this->travel(HelpdeskConnection::GRACE_HOURS + 1)->hours();
        $this->withToken($token)->postJson('/api/helpdesk/v1/webhook/test')
            ->assertOk()->assertJsonPath('data.delivered', false)->assertJsonPath('data.error', 'Webhook helpdesk belum terdaftar.');

        $this->withToken($token)->putJson('/api/helpdesk/v1/webhook', ['url' => 'https://helpdesk.test/api/integrations/1/events', 'secret' => str_repeat('a', 20)])->assertOk();
        $status = 200;
        Http::fake(function () use (&$status) {
            return Http::response(['status' => 'ignored'], $status);
        });

        $eventId = $this->withToken($token)->postJson('/api/helpdesk/v1/webhook/test')
            ->assertOk()->assertJsonPath('data.delivered', true)->json('data.event_id');
        $ping = Http::recorded()->last()[0];
        $this->assertSame('ping', $ping['type']);
        $this->assertSame($eventId, $ping['event_id']);
        $this->assertSame(
            'sha256='.hash_hmac('sha256', $ping->header('X-Helpdesk-Timestamp')[0].'.'.$ping->body(), str_repeat('a', 20)),
            $ping->header('X-Helpdesk-Signature')[0],
        );

        $status = 401;
        $this->withToken($token)->postJson('/api/helpdesk/v1/webhook/test')
            ->assertOk()->assertJsonPath('data.delivered', false)->assertJsonPath('data.status', 401);
        $this->assertStringContainsString('rahasia webhook tidak cocok', (string) HelpdeskConnection::lastDelivery()['error']);
        $this->flushHeaders()->postJson('/api/helpdesk/v1/webhook/test')->assertUnauthorized();
    }

    public function test_disconnect_rejects_the_token_and_falls_back_to_the_env_values(): void
    {
        $token = $this->issueCode()['token'];
        $this->withToken($token)->putJson('/api/helpdesk/v1/webhook', ['url' => 'https://helpdesk.test/e', 'secret' => str_repeat('s', 20)])->assertOk();

        $this->actingAs($this->admin)->delete(route('helpdesk-settings.disconnect'))->assertRedirect()->assertSessionHas('success');

        $this->withToken($token)->getJson('/api/helpdesk/v1/categories')->assertUnauthorized();
        $this->assertSame('env', HelpdeskConnection::tokenSource());
        $this->assertSame('https://helpdesk-lama.test/api/integrations/1/events', HelpdeskConnection::webhookUrl());

        config(['services.helpdesk.api_token' => null, 'services.helpdesk.webhook_url' => null]);
        $this->assertNull(HelpdeskConnection::tokenSource());
        $this->assertFalse(SendHelpdeskWebhook::configured());
    }

    public function test_non_admin_cannot_open_or_use_helpdesk_settings(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get(route('helpdesk-settings.index'))->assertRedirect()->assertSessionHas('error');
        $this->actingAs($staff)->post(route('helpdesk-settings.issue'))->assertRedirect();
        $this->assertNull(IntegrationSetting::value(HelpdeskConnection::TOKEN_HASH));
    }
}
