<?php

namespace Tests\Feature;

use App\DemoService;
use App\Mail\RecoveryCodeMail;
use App\Models\Booking;
use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
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

    public function test_demo_payment_is_owned_idempotent_and_server_prices_the_deposit(): void
    {
        $booking = Booking::factory()->create(['deposit_amount' => 30000]);
        $this->actingAs($booking->user);
        $attempt = $this->postJson('/api/v1/payments/momo', ['request_key' => (string) Str::uuid(), 'purpose' => 'booking', 'booking_id' => $booking->id, 'deposit_only' => true, 'amount' => 1])->assertOk()->assertJsonPath('data.amount', 30000)->assertJsonPath('data.partner_code', 'DEMO_MOMO')->json('data.id');
        $this->postJson('/api/v1/demo/payments/'.$attempt, ['success' => true])->assertOk();
        $this->postJson('/api/v1/demo/payments/'.$attempt, ['success' => true])->assertOk();
        $this->assertSame(30000, $booking->fresh()->paid_amount);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', ['method' => 'demo_momo', 'amount' => 30000]);
        $this->actingAs(User::factory()->create())->postJson('/api/v1/demo/payments/'.$attempt, ['success' => true])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_demo_wallet_failure_and_expiry_never_credit_and_success_credits_once(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0]);
        $this->actingAs($user);
        $failed = PaymentAttempt::factory()->create(['user_id' => $user->id, 'partner_code' => 'DEMO_MOMO']);
        $this->postJson('/api/v1/demo/payments/'.$failed->id, ['success' => false])->assertOk();
        $this->postJson('/api/v1/demo/payments/'.$failed->id, ['success' => true])->assertConflict();
        $expired = PaymentAttempt::factory()->create(['user_id' => $user->id, 'partner_code' => 'DEMO_MOMO', 'expires_at' => now()->subMinute()]);
        $this->postJson('/api/v1/demo/payments/'.$expired->id, ['success' => true])->assertUnprocessable();
        $this->assertSame(0, $user->fresh()->wallet_balance);
        $success = PaymentAttempt::factory()->create(['user_id' => $user->id, 'partner_code' => 'DEMO_MOMO']);
        $this->postJson('/api/v1/demo/payments/'.$success->id, ['success' => true])->assertOk();
        $this->postJson('/api/v1/demo/payments/'.$success->id, ['success' => true])->assertOk();
        $this->assertSame(100000, $user->fresh()->wallet_balance);
        $this->assertDatabaseCount('wallet_entries', 1);
    }

    public function test_demo_endpoints_cannot_settle_real_attempts_or_run_in_production(): void
    {
        $attempt = PaymentAttempt::factory()->create();
        $this->actingAs($attempt->user)->postJson('/api/v1/demo/payments/'.$attempt->id, ['success' => true])->assertNotFound();
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->app->instance('env', 'production');
        $this->assertFalse(app(DemoService::class)->active());
        $attempt->update(['partner_code' => 'DEMO_MOMO']);
        $this->postJson('/api/v1/demo/payments/'.$attempt->id, ['success' => true])->assertNotFound();
        $this->postJson('/api/v1/me/phone/send', ['phone' => '0901234567'])->assertServiceUnavailable();
        $this->postJson('/api/v1/recovery/send', ['email' => $attempt->user->email])->assertServiceUnavailable();
        Http::assertNothingSent();
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

    public function test_bank_demo_does_not_publish_an_invented_bank_account(): void
    {
        $booking = Booking::factory()->create();
        $this->getJson('/api/v1/hub')->assertJsonPath('data.bank', ['mode' => 'demo']);
        $this->actingAs($booking->user);
        $id = $this->postJson('/api/v1/demo/bookings/'.$booking->id.'/bank', ['request_key' => (string) Str::uuid()])->assertOk()->assertJsonPath('data.partner_code', 'DEMO_BANK')->json('data.id');
        $this->postJson('/api/v1/demo/payments/'.$id, ['success' => true])->assertOk();
        $this->assertDatabaseHas('payments', ['booking_id' => $booking->id, 'method' => 'demo_bank', 'amount' => 100000]);
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

    public function test_configured_momo_uses_real_provider_even_when_demo_is_enabled(): void
    {
        config(['momo.enabled' => true, 'momo.partner_code' => 'LIVE_TEST_PARTNER', 'momo.access_key' => 'access', 'momo.secret_key' => 'secret', 'momo.redirect_url' => 'https://example.test/return', 'momo.ipn_url' => 'https://example.test/ipn', 'momo.endpoint' => 'https://test-payment.momo.vn/v2/gateway/api/create']);
        Http::fake(['https://test-payment.momo.vn/v2/gateway/api/create' => fn ($request) => Http::response(['resultCode' => 0, 'orderId' => $request['orderId'], 'requestId' => $request['requestId'], 'payUrl' => 'https://test-payment.momo.vn/test-checkout'])]);
        $this->actingAs(User::factory()->create())->postJson('/api/v1/payments/momo', ['request_key' => (string) Str::uuid(), 'purpose' => 'wallet', 'amount' => 50000])->assertOk()->assertJsonPath('data.partner_code', 'LIVE_TEST_PARTNER')->assertJsonPath('data.pay_url', 'https://test-payment.momo.vn/test-checkout')->assertJsonPath('data.status', 'pending');
        Http::assertSentCount(1);
        $this->assertDatabaseCount('wallet_entries', 0);
    }

    public function test_late_demo_payment_after_cancellation_is_credited_to_wallet_once(): void
    {
        $booking = Booking::factory()->create(['status' => 'cancelled']);
        $booking->user->forceFill(['wallet_balance' => 0])->save();
        $attempt = PaymentAttempt::factory()->create(['user_id' => $booking->user_id, 'booking_id' => $booking->id, 'purpose' => 'booking', 'partner_code' => 'DEMO_MOMO']);
        $this->actingAs($booking->user)->postJson('/api/v1/demo/payments/'.$attempt->id, ['success' => true])->assertOk()->assertJsonPath('data.status', 'credited');
        $this->postJson('/api/v1/demo/payments/'.$attempt->id, ['success' => true])->assertOk();
        $this->assertSame(0, $booking->fresh()->paid_amount);
        $this->assertSame(100000, $booking->user->fresh()->wallet_balance);
        $this->assertDatabaseCount('wallet_entries', 1);
    }
}
