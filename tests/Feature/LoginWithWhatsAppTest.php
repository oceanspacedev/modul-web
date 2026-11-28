<?php

namespace Tests\Feature;

use App\Models\LoginOtp;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LoginWithWhatsAppTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_user_can_login_with_username(): void
    {
        $user = User::factory()->staff()->create([
            'username' => 'test_user_unique',
            'no_hp' => '081299990001',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post('/login', [
            'username' => 'test_user_unique',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_login_with_whatsapp_number(): void
    {
        $user = User::factory()->staff()->create([
            'username' => 'test_wa_user',
            'no_hp' => '081299990002',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post('/login', [
            'username' => '081299990002',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_login_with_whatsapp_number_prefix_62(): void
    {
        $user = User::factory()->staff()->create([
            'username' => 'test_wa_user_62',
            'no_hp' => '081299990003',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post('/login', [
            'username' => '6281299990003',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_login_with_whatsapp_number_prefix_plus_62_and_dashes(): void
    {
        $user = User::factory()->staff()->create([
            'username' => 'test_wa_user_formatted',
            'no_hp' => '081299990004',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post('/login', [
            'username' => '+62 812-9999-0004',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->staff()->create([
            'username' => 'test_fail_user',
            'no_hp' => '081299990005',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post('/login', [
            'username' => '081299990005',
            'password' => 'wrong_password',
        ]);

        $response->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_user_can_request_whatsapp_otp(): void
    {
        $user = User::factory()->staff()->create([
            'username' => 'test_otp_user',
            'no_hp' => '081299990010',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/login/otp/request', [
            'username' => '081299990010',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
        ]);

        $this->assertDatabaseHas('login_otps', [
            'user_id' => $user->id,
            'is_used' => false,
        ]);
    }

    public function test_user_can_verify_whatsapp_otp_and_login(): void
    {
        $user = User::factory()->staff()->create([
            'username' => 'test_otp_verify_user',
            'no_hp' => '081299990011',
            'password' => Hash::make('password123'),
        ]);

        // Request OTP
        $this->postJson('/login/otp/request', [
            'username' => '081299990011',
        ]);

        $otpRecord = LoginOtp::where('user_id', $user->id)->latest()->first();
        $this->assertNotNull($otpRecord);

        // Verify with wrong OTP
        $wrongResponse = $this->postJson('/login/otp/verify', [
            'username' => '081299990011',
            'otp' => '000000',
        ]);
        $wrongResponse->assertStatus(422);
        $this->assertGuest();

        // Verify with correct OTP
        $correctResponse = $this->postJson('/login/otp/verify', [
            'username' => '081299990011',
            'otp' => $otpRecord->otp_code,
        ]);
        $correctResponse->assertStatus(200);
        $correctResponse->assertJson([
            'status' => true,
            'redirect' => url('/dashboard'),
        ]);

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($otpRecord->fresh()->is_used);
    }

    public function test_otp_login_remembers_the_user_when_ingat_saya_is_checked(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertSee('<input type="checkbox" name="remember" checked>', false)
            ->assertSee('<input type="checkbox" name="remember" id="otpRememberInput" checked>', false);

        $remembered = User::factory()->staff()->create(['username' => 'otp_remember', 'no_hp' => '081299990021']);
        $forgotten = User::factory()->staff()->create(['username' => 'otp_forget', 'no_hp' => '081299990022']);
        $rememberCookie = Auth::guard('web')->getRecallerName();

        foreach ([[$remembered, true], [$forgotten, false]] as [$user, $remember]) {
            $this->postJson('/login/otp/request', ['username' => $user->no_hp]);
            $otp = LoginOtp::where('user_id', $user->id)->latest()->first()->otp_code;

            $response = $this->postJson('/login/otp/verify', ['username' => $user->no_hp, 'otp' => $otp, 'remember' => $remember])->assertOk();

            $remember ? $response->assertCookie($rememberCookie) : $response->assertCookieMissing($rememberCookie);
            Auth::logout();
        }
    }

    public function test_password_login_with_ingat_saya_survives_a_browser_restart(): void
    {
        $user = User::factory()->staff()->create([
            'username' => 'pwd_remember',
            'no_hp' => '081299990031',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post('/login', ['username' => 'pwd_remember', 'password' => 'password123', 'remember' => 'on'])
            ->assertRedirect($user->homeUrl());

        $this->assertStaysSignedInAfterRestart($response, $user);
    }

    public function test_password_login_without_ingat_saya_ends_with_the_session(): void
    {
        $user = User::factory()->staff()->create([
            'username' => 'pwd_forget',
            'no_hp' => '081299990032',
            'password' => Hash::make('password123'),
        ]);

        $this->post('/login', ['username' => 'pwd_forget', 'password' => 'password123'])
            ->assertRedirect($user->homeUrl())
            ->assertCookieMissing(Auth::guard('web')->getRecallerName());

        $this->restartBrowser();
        $this->get($user->homeUrl())->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_otp_login_with_ingat_saya_survives_a_browser_restart(): void
    {
        $user = User::factory()->staff()->create(['username' => 'otp_restart', 'no_hp' => '081299990033']);

        $this->postJson('/login/otp/request', ['username' => $user->no_hp])->assertOk();
        $otp = LoginOtp::where('user_id', $user->id)->latest()->first()->otp_code;

        $response = $this->postJson('/login/otp/verify', ['username' => $user->no_hp, 'otp' => $otp, 'remember' => true])->assertOk();

        $this->assertStaysSignedInAfterRestart($response, $user);
    }

    /**
     * Laptop dimatikan lalu dinyalakan: cookie sesi (dan sesi di server) sudah hilang, yang tersisa hanya cookie
     * "Ingat saya". Pengguna harus tetap masuk tanpa diminta login lagi.
     */
    private function assertStaysSignedInAfterRestart(TestResponse $loginResponse, User $user): void
    {
        $recaller = Auth::guard('web')->getRecallerName();
        $this->assertStringStartsWith('remember_web_', $recaller);
        $loginResponse->assertCookie($recaller);
        $cookie = $loginResponse->getCookie($recaller);
        $this->assertGreaterThan(now()->addYear()->getTimestamp(), $cookie->getExpiresTime());

        $this->restartBrowser();

        $this->withCookie($recaller, $loginResponse->getCookie($recaller)->getValue())
            ->get($user->homeUrl())
            ->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    private function restartBrowser(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['session']->driver()->flush();
        $this->flushSession();
        $this->defaultCookies = [];
    }

    public function test_otp_request_fails_if_user_has_no_whatsapp(): void
    {
        User::factory()->staff()->create([
            'username' => 'no_wa_user',
            'no_hp' => null,
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/login/otp/request', [
            'username' => 'no_wa_user',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'status' => false,
        ]);
    }

    public function test_user_with_any_role_can_logout_via_post(): void
    {
        $staff = User::factory()->staff()->create();

        $response = $this->actingAs($staff)->post('/logout');
        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_logout_via_get_is_rejected_so_links_cannot_sign_users_out(): void
    {
        $trainer = User::factory()->staff()->trainer()->create();

        $this->actingAs($trainer)->get('/logout')->assertMethodNotAllowed();
        $this->assertAuthenticatedAs($trainer);
    }
}
