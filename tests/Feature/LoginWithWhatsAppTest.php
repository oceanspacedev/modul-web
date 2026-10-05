<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginWithWhatsAppTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_user_can_login_with_username(): void
    {
        $user = User::factory()->create([
            'username' => 'test_user_unique',
            'no_wa' => '081299990001',
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
        $user = User::factory()->create([
            'username' => 'test_wa_user',
            'no_wa' => '081299990002',
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
        $user = User::factory()->create([
            'username' => 'test_wa_user_62',
            'no_wa' => '081299990003',
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
        $user = User::factory()->create([
            'username' => 'test_wa_user_formatted',
            'no_wa' => '081299990004',
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
        User::factory()->create([
            'username' => 'test_fail_user',
            'no_wa' => '081299990005',
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
        $user = User::factory()->create([
            'username' => 'test_otp_user',
            'no_wa' => '081299990010',
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
        $user = User::factory()->create([
            'username' => 'test_otp_verify_user',
            'no_wa' => '081299990011',
            'password' => Hash::make('password123'),
        ]);

        // Request OTP
        $this->postJson('/login/otp/request', [
            'username' => '081299990011',
        ]);

        $otpRecord = \App\Models\LoginOtp::where('user_id', $user->id)->latest()->first();
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

    public function test_otp_request_fails_if_user_has_no_whatsapp(): void
    {
        User::factory()->create([
            'username' => 'no_wa_user',
            'no_wa' => null,
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
        $staff = User::factory()->create(['job_level_id' => 2]);
        $staff->syncRoles(['Staff']);

        $response = $this->actingAs($staff)->post('/logout');
        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_user_can_logout_via_get(): void
    {
        $trainer = User::factory()->create(['job_level_id' => 2]);
        $trainer->syncRoles(['Trainer']);

        $response = $this->actingAs($trainer)->get('/logout');
        $response->assertRedirect('/');
        $this->assertGuest();
    }
}
