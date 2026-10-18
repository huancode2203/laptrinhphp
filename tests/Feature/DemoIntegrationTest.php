<?php

namespace Tests\Feature;

use App\Mail\RecoveryCodeMail;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class DemoIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true, 'momo.enabled' => false, 'integrations.sms.enabled' => false, 'mail.default' => 'log', 'integrations.google.client_id' => '', 'integrations.bank.account' => '', 'integrations.bank.key' => '', 'ai.search.provider' => 'local', 'ai.moderation.provider' => 'local']);
        Http::preventStrayRequests();
        Mail::fake();
    }

    public function test_personal_bank_works_without_sepay_and_only_admin_can_configure_it(): void
    {
        $bank = ['account' => '1234567890', 'name' => 'TAI KHOAN KIEM THU', 'bank_bin' => '970422'];
        $this->actingAs(User::factory()->create())->putJson('/api/v1/admin/integrations/bank', $bank)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->putJson('/api/v1/admin/integrations/bank', $bank)->assertOk();
        $this->getJson('/api/v1/hub')->assertOk()->assertJsonPath('data.bank.mode', 'manual')->assertJsonPath('data.bank.account', $bank['account']);
        $booking = Booking::factory()->create();
        $this->actingAs($booking->user)->postJson('/api/v1/demo/bookings/'.$booking->id.'/bank', ['request_key' => (string) Str::uuid()])->assertNotFound();
        $this->assertSame(0, $booking->fresh()->paid_amount);
        Http::assertNothingSent();
    }

    public function test_email_demo_requires_same_browser_and_never_marks_email_verified(): void
    {
        $user = User::factory()->create(['email' => 'otp@badminton.test', 'email_verified_at' => null]);
        $code = $this->postJson('/api/v1/recovery/send', ['email' => $user->email])->assertOk()->json('demo_code');
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $code);
        $this->postJson('/api/v1/recovery/send', ['email' => $user->email])->assertStatus(429);
        $token = $this->postJson('/api/v1/recovery/verify', ['email' => $user->email, 'code' => $code])->assertOk()->json('data.reset_token');
        $this->assertNull($user->fresh()->email_verified_at);
        $this->postJson('/api/v1/recovery/reset', ['email' => $user->email, 'reset_token' => $token, 'password' => 'NewDemo@123', 'password_confirmation' => 'NewDemo@123'])->assertOk();
        $this->postJson('/api/v1/recovery/reset', ['email' => $user->email, 'reset_token' => $token, 'password' => 'NewDemo@123', 'password_confirmation' => 'NewDemo@123'])->assertUnprocessable();
        Mail::assertNothingSent();
    }

    public function test_email_demo_cannot_reset_a_real_address_as_guest_or_after_demo_is_disabled(): void
    {
        $real = User::factory()->create(['email' => 'owner@example.com']);
        $this->postJson('/api/v1/recovery/send', ['email' => $real->email])->assertServiceUnavailable();
        $user = User::factory()->create(['email' => 'test@badminton.test']);
        $code = $this->postJson('/api/v1/recovery/send', ['email' => $user->email])->assertOk()->json('demo_code');
        $this->withSession(['demo_recovery_id' => null])->postJson('/api/v1/recovery/verify', ['email' => $user->email, 'code' => $code])->assertUnprocessable();
        config(['demo.enabled' => false]);
        $this->postJson('/api/v1/recovery/verify', ['email' => $user->email, 'code' => $code])->assertUnprocessable();
    }

    public function test_sms_demo_is_one_use_and_does_not_mark_a_phone_verified(): void
    {
        $user = User::factory()->create(['phone_verified_at' => null]);
        $this->actingAs($user);
        $code = $this->postJson('/api/v1/me/phone/send', ['phone' => '0901234567'])->assertOk()->json('demo_code');
        $this->postJson('/api/v1/me/phone/send', ['phone' => '0901234567'])->assertStatus(429);
        $this->postJson('/api/v1/me/phone/verify', ['code' => '000000'])->assertUnprocessable();
        $this->postJson('/api/v1/me/phone/verify', ['code' => $code])->assertOk();
        $this->assertNull($user->fresh()->phone_verified_at);
        $this->postJson('/api/v1/me/phone/verify', ['code' => $code])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_google_demo_can_only_login_reserved_customer_and_real_config_takes_priority(): void
    {
        $this->postJson('/api/v1/demo/google')->assertOk();
        $this->assertAuthenticated();
        $this->assertSame('customer', auth()->user()->role);
        $this->assertSame('google-demo@badminton.test', auth()->user()->email);
        $this->assertNull(auth()->user()->email_verified_at);
        $this->postJson('/api/v1/logout')->assertOk();
        config(['integrations.google.client_id' => 'client', 'integrations.google.client_secret' => 'secret', 'integrations.google.redirect_uri' => 'http://127.0.0.1:8088/auth/google/callback']);
        $this->postJson('/api/v1/demo/google')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_real_smtp_sends_mail_without_returning_demo_code(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.test', 'mail.mailers.smtp.port' => 587, 'mail.from.address' => 'sender@example.test']);
        $user = User::factory()->create();
        $response = $this->postJson('/api/v1/recovery/send', ['email' => $user->email])->assertOk();
        $this->assertArrayNotHasKey('demo_code', $response->json());
        Mail::assertSent(RecoveryCodeMail::class);
    }
}
