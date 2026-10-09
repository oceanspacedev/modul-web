<?php

namespace Tests\Feature;

use App\Http\Middleware\LastUserActivity;
use App\Models\LoginOtp;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Cache\CacheManager;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FrameworkUpgradeTest extends TestCase
{
    public function test_health_endpoint_reports_the_application_is_up(): void
    {
        $this->getJson('/up')->assertOk()->assertJsonPath('status', 'up');
    }

    public function test_api_routes_are_rate_limited_and_render_json_for_guests(): void
    {
        // Guests get JSON (not a login redirect) even without an Accept header.
        $this->get('/api/user')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json');

        // The "api" group throttles public endpoints at 60 requests per minute.
        $this->postJson('/api/login', [])
            ->assertHeader('X-RateLimit-Limit', '60');
    }

    public function test_web_group_tracks_user_activity_and_protects_against_forgery(): void
    {
        $groups = $this->app['router']->getMiddlewareGroups();

        $this->assertContains(LastUserActivity::class, $groups['web']);
        $this->assertContains(PreventRequestForgery::class, $groups['web']);
        $this->assertContains('throttle:api', $groups['api']);
        $this->assertArrayHasKey('isAdmin', $this->app['router']->getMiddleware());
    }

    public function test_admins_implicitly_pass_every_gate_check(): void
    {
        $admin = User::factory()->create(['job_level_id' => 1]);
        $staff = User::factory()->create(['job_level_id' => 2]);

        $this->assertTrue($admin->can('something-undefined'));
        $this->assertFalse($staff->can('something-undefined'));
    }

    public function test_api_login_issues_a_token_that_can_fetch_the_user(): void
    {
        $user = User::factory()->create(['password' => Hash::make('test-password')]);

        $response = $this->postJson('/api/login', [
            'username' => $user->username,
            'password' => 'test-password',
        ])->assertOk()->assertJsonPath('data.token_type', 'Bearer');

        $token = $response->json('data.access_token');
        $this->assertNotNull(PersonalAccessToken::findToken($token));

        // A new API request must authenticate through Sanctum, not the session
        // guard cached by the preceding login request inside this PHP process.
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonMissingPath('data.password');
    }

    public function test_api_logout_revokes_only_the_current_bearer_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('current-device')->plainTextToken;
        $otherToken = $user->createToken('other-device')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->assertNull(PersonalAccessToken::findToken($token));
        $this->assertNotNull(PersonalAccessToken::findToken($otherToken));

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_missing_invalid_and_expired_api_tokens_are_rejected(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken('invalid-token')->getJson('/api/user')->assertUnauthorized();

        $token = User::factory()->create()->createToken(
            'expired-device', ['*'], now()->subMinute()
        )->plainTextToken;

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_expired_and_used_whatsapp_otps_cannot_authenticate(): void
    {
        $user = User::factory()->create(['no_wa' => '081299990020']);
        $otp = LoginOtp::create([
            'user_id' => $user->id,
            'phone' => $user->no_wa,
            'otp_code' => '123456',
            'expires_at' => now()->subMinute(),
            'is_used' => false,
        ]);

        $this->postJson('/login/otp/verify', [
            'username' => $user->username, 'otp' => '123456',
        ])->assertUnprocessable();
        $this->assertGuest();

        $otp->update(['expires_at' => now()->addMinutes(5), 'is_used' => true]);

        $this->postJson('/login/otp/verify', [
            'username' => $user->username, 'otp' => '123456',
        ])->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_otp_cooldown_counts_down_with_carbon_three(): void
    {
        $this->freezeSecond();
        $user = User::factory()->create(['no_wa' => '081299990021']);
        $otp = LoginOtp::create([
            'user_id' => $user->id,
            'phone' => $user->no_wa,
            'otp_code' => '123456',
            'expires_at' => now()->addMinutes(5),
            'is_used' => false,
        ]);
        $otp->forceFill(['created_at' => now()->subSeconds(30)])->save();

        $this->postJson('/login/otp/request', ['username' => $user->username])
            ->assertStatus(429)
            ->assertJsonPath('cooldown', 30);
        Http::assertNothingSent();
    }

    public function test_web_login_rejects_missing_csrf_tokens_outside_the_test_bypass(): void
    {
        $this->app->instance('env', 'production');

        try {
            $this->post('/login', ['username' => 'admin', 'password' => 'password'])
                ->assertStatus(419);
            $this->assertGuest();
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_production_otp_fails_when_gateway_credentials_are_missing(): void
    {
        config(['services.whatsapp.token' => null]);
        $this->app->instance('env', 'production');

        try {
            $result = WhatsAppService::sendOtp('081299990022', '123456');

            $this->assertFalse($result['status']);
            $this->assertArrayNotHasKey('otp', $result);
            $this->assertArrayNotHasKey('dev_otp', $result);
            Http::assertNothingSent();
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_whatsapp_gateway_uses_configuration_after_config_caching(): void
    {
        config([
            'services.whatsapp.url' => 'https://configured-gateway.test',
            'services.whatsapp.token' => 'configured-token',
        ]);
        Http::fake(['configured-gateway.test/*' => Http::response(['status' => true])]);

        $result = WhatsAppService::sendOtp('6281299990023', '123456');

        $this->assertTrue($result['status']);
        Http::assertSent(fn ($request) => $request->url() === 'https://configured-gateway.test/api/v1/messages'
            && $request->hasHeader('Authorization', 'Bearer configured-token')
            && $request['recipient']['value'] === '081299990023');
    }

    public function test_web_login_accepts_a_matching_csrf_token(): void
    {
        $user = User::factory()->create(['password' => Hash::make('test-password')]);
        $token = Str::random(40);
        $this->app->instance('env', 'production');

        try {
            $this->withSession(['_token' => $token])->post('/login', [
                '_token' => $token,
                'username' => $user->username,
                'password' => 'test-password',
            ])->assertRedirect('/dashboard');
            $this->assertAuthenticatedAs($user);
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_json_sessions_preserve_authentication_and_validation_errors(): void
    {
        $user = User::factory()->create();
        $session = $this->app['session']->driver();
        $session->start();
        $authKey = $this->app['auth']->guard()->getName();
        $session->put($authKey, $user->id);
        $session->flash('errors', (new ViewErrorBag)->put(
            'default', new MessageBag(['username' => ['Username wajib diisi.']])
        ));
        $session->save();

        $rawSession = $session->getHandler()->read($session->getId());
        $this->assertSame($user->id, json_decode($rawSession, true, 512, JSON_THROW_ON_ERROR)[$authKey]);

        $restored = new Store(
            $session->getName(), $session->getHandler(), $session->getId(), config('session.serialization')
        );
        $restored->start();

        $this->assertSame($user->id, $restored->get($authKey));
        $this->assertInstanceOf(ViewErrorBag::class, $restored->get('errors'));
        $this->assertSame('Username wajib diisi.', $restored->get('errors')->first('username'));
    }

    public function test_permissions_reload_from_file_cache_without_unserializing_objects(): void
    {
        $cachePath = sys_get_temp_dir().'/modul-permission-test-'.Str::uuid();
        config([
            'permission.cache.store' => 'permission_test',
            'cache.stores.permission_test' => ['driver' => 'file', 'path' => $cachePath],
        ]);

        try {
            $registrar = new PermissionRegistrar($this->app->make(CacheManager::class));
            $expectedPermissions = $registrar->getPermissions()->pluck('name')->sort()->values()->all();
            $this->assertContains('manage-trainings', $expectedPermissions);
            $this->assertIsArray($registrar->getCacheRepository()->get(config('permission.cache.key')));

            $restored = new PermissionRegistrar($this->app->make(CacheManager::class));
            $this->assertSame(
                $expectedPermissions,
                $restored->getPermissions()->pluck('name')->sort()->values()->all()
            );
            $this->assertTrue($restored->getPermissions(['name' => 'manage-trainings'])->first()->roles->contains('name', 'Trainer'));
        } finally {
            File::deleteDirectory($cachePath);
        }
    }
}
