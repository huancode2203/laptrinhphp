<?php

namespace Tests\Feature;

use App\IntegrationStatus;
use App\Mail\RecoveryCodeMail;
use App\Models\AuthCode;
use App\Models\Booking;
use App\Models\User;
use App\ModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LiveIntegrationSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.search.provider' => 'local', 'ai.moderation.provider' => 'local']);
        Http::preventStrayRequests();
    }

    private function google(array $overrides = []): void
    {
        config(array_replace(['integrations.google.client_id' => 'client', 'integrations.google.client_secret' => 'private-google-secret', 'integrations.google.redirect_uri' => 'http://127.0.0.1:8088/auth/google/callback'], $overrides));
    }

    private function phone(): void
    {
        config(['integrations.sms.enabled' => true, 'integrations.sms.sid' => 'account', 'integrations.sms.token' => 'private-sms-token', 'integrations.sms.service' => 'verify-service']);
    }

    public function test_only_admin_can_read_service_status_and_no_secret_is_returned(): void
    {
        $this->google();
        $this->phone();
        config(['momo.secret_key' => 'private-momo-secret', 'ai.openai_key' => 'private-ai-secret', 'integrations.bank.key' => 'private-bank-secret']);
        $this->getJson('/api/v1/admin/integrations')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson('/api/v1/admin/integrations')->assertForbidden();
        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson('/api/v1/admin/integrations')->assertOk()->assertJsonCount(8, 'data');
        foreach (['private-google-secret', 'private-sms-token', 'private-momo-secret', 'private-ai-secret', 'private-bank-secret'] as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent());
        }
        Http::assertNothingSent();
    }

    public function test_partial_configuration_does_not_expose_unusable_customer_actions(): void
    {
        $this->google(['integrations.google.client_secret' => '']);
        config(['integrations.sms.enabled' => true, 'integrations.sms.token' => '', 'integrations.bank.enabled' => true, 'integrations.bank.key' => '', 'mail.default' => 'log']);
        $this->getJson('/api/v1/hub')->assertOk()->assertJsonPath('data.google_enabled', false)->assertJsonPath('data.sms_enabled', false)->assertJsonPath('data.email_enabled', false)->assertJsonPath('data.bank', null);
        $this->assertFalse(app(IntegrationStatus::class)->ready('google'));
    }

    public function test_google_network_error_returns_to_login_and_does_not_create_an_account(): void
    {
        $this->google();
        Http::fake(['https://oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_client'], 401)]);
        $this->withSession(['google_oauth' => ['state' => 'state', 'created' => now()->timestamp, 'user_id' => null]])->get('/auth/google/callback?state=state&code=code')->assertRedirect('/#auth');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertNull(session('google_oauth'));
        $this->assertStringContainsString('Google', session('auth_notice'));
    }

    public function test_google_can_create_verified_customer_and_link_different_google_email_without_switching_user(): void
    {
        $this->google();
        Http::fake(['https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'token']), 'https://openidconnect.googleapis.com/v1/userinfo' => Http::sequence()->push(['sub' => 'google-new', 'email' => 'google@example.test', 'email_verified' => true, 'name' => 'Google Player'])->push(['sub' => 'google-other', 'email' => 'different@example.test', 'email_verified' => true])]);
        $this->withSession(['google_oauth' => ['state' => 'state', 'created' => now()->timestamp, 'user_id' => null]])->get('/auth/google/callback?state=state&code=code')->assertRedirect('/#account');
        $googleUser = User::where('google_sub', 'google-new')->firstOrFail();
        $this->assertAuthenticatedAs($googleUser);
        $this->assertNotNull($googleUser->email_verified_at);
        $this->assertSame('customer', $googleUser->role);
        $user = User::factory()->create(['email' => 'app@example.test', 'email_verified_at' => null]);
        $this->actingAs($user)->withSession(['google_oauth' => ['state' => 'link', 'created' => now()->timestamp, 'user_id' => $user->id]])->get('/auth/google/callback?state=link&code=code')->assertRedirect('/#account');
        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-other', $user->fresh()->google_sub);
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_google_link_cannot_take_a_provider_account_owned_by_another_customer(): void
    {
        $this->google();
        $owner = User::factory()->create();
        $owner->forceFill(['google_sub' => 'claimed'])->save();
        $other = User::factory()->create();
        Http::fake(['https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'token']), 'https://openidconnect.googleapis.com/v1/userinfo' => Http::response(['sub' => 'claimed', 'email' => $owner->email, 'email_verified' => true])]);
        $this->actingAs($other)->withSession(['google_oauth' => ['state' => 'link', 'created' => now()->timestamp, 'user_id' => $other->id]])->get('/auth/google/callback?state=link&code=code')->assertRedirect('/#account');
        $this->assertAuthenticatedAs($other);
        $this->assertNull($other->fresh()->google_sub);
        $this->assertSame('claimed', $owner->fresh()->google_sub);
    }

    public function test_sms_resend_cooldown_and_expired_provider_code_never_verify_a_phone(): void
    {
        $this->phone();
        $user = User::factory()->create();
        Http::fake(['*/Verifications' => Http::response(['status' => 'pending']), '*/VerificationCheck' => Http::response(['code' => 20404], 404)]);
        $this->actingAs($user)->postJson('/api/v1/me/phone/send', ['phone' => '0912345678'])->assertOk();
        Http::assertSent(fn ($r) => $r['To'] === '+84912345678' && $r['Channel'] === 'sms');
        $this->postJson('/api/v1/me/phone/send', ['phone' => '0912345678'])->assertStatus(429);
        Http::assertSentCount(1);
        $this->postJson('/api/v1/me/phone/verify', ['code' => '123456'])->assertUnprocessable();
        $this->assertNull($user->fresh()->phone_verified_at);
    }

    public function test_provider_sms_rate_limit_is_reported_without_saving_a_verification_session(): void
    {
        $this->phone();
        Http::fake(['*' => Http::response(['code' => 60203], 429)]);
        $this->actingAs(User::factory()->create())->postJson('/api/v1/me/phone/send', ['phone' => '0912345678'])->assertStatus(429);
        $this->assertNull(session('phone_verification'));
    }

    public function test_log_mailer_does_not_claim_an_otp_was_sent_or_store_a_live_code(): void
    {
        config(['mail.default' => 'log']);
        Mail::fake();
        $user = User::factory()->create();
        $this->postJson('/api/v1/recovery/send', ['email' => $user->email])->assertStatus(503);
        $this->postJson('/api/v1/recovery/send', ['email' => 'missing@example.test'])->assertStatus(503);
        $this->assertDatabaseCount('auth_codes', 0);
        Mail::assertNothingSent();
    }

    public function test_smtp_failure_revokes_the_code_and_success_sends_the_actual_code_mail(): void
    {
        config(['mail.default' => 'smtp', 'mail.from.address' => 'hub@example.test']);
        $user = User::factory()->create();
        Mail::shouldReceive('getDefaultDriver')->andReturn('smtp');
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
        $this->postJson('/api/v1/recovery/send', ['email' => $user->email])->assertStatus(503);
        $this->assertNotNull(AuthCode::firstOrFail()->used_at);
        $this->travel(61)->seconds();
        Mail::fake();
        $this->postJson('/api/v1/recovery/send', ['email' => $user->email])->assertOk();
        Mail::assertSent(RecoveryCodeMail::class, fn ($mail) => $mail->hasTo($user->email) && preg_match('/^[0-9]{6}$/', $mail->code));
        $this->assertSame(1, AuthCode::whereNull('used_at')->count());
    }

    public function test_changed_duplicate_sepay_payload_is_rejected_without_another_credit(): void
    {
        config(['integrations.bank.enabled' => true, 'integrations.bank.key' => 'key', 'integrations.bank.account' => '12345']);
        $booking = Booking::factory()->create(['total_amount' => 100000, 'paid_amount' => 0]);
        $data = ['id' => 1111, 'accountNumber' => '12345', 'transferType' => 'in', 'transferAmount' => 100000, 'content' => $booking->reference];
        $this->withHeader('Authorization', 'Apikey key')->postJson('/api/v1/payments/bank/webhook', $data)->assertOk();
        $this->postJson('/api/v1/payments/bank/webhook', [...$data, 'content' => 'Different reference'])->assertConflict();
        $this->assertSame(100000, $booking->fresh()->paid_amount);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_incomplete_model_output_is_not_used_to_auto_publish_a_post(): void
    {
        config(['ai.moderation.provider' => 'ollama']);
        Http::fake(['*' => Http::response(['done' => false, 'message' => ['content' => '{"decision":"allow","reason":"ok","categories":[],"confidence":1}']])]);
        $result = app(ModerationService::class)->inspect('Bài viết cần kiểm tra');
        $this->assertSame('review', $result['decision']);
        $this->assertSame('unavailable', $result['source']);
    }

    public function test_diagnostic_command_shows_missing_fields_without_network_or_secret_values(): void
    {
        $this->google();
        $this->artisan('hub:integrations', ['--json' => true])->expectsOutputToContain('"id": "google"')->doesntExpectOutputToContain('private-google-secret')->assertSuccessful();
        Http::assertNothingSent();
    }
}
