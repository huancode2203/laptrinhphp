<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\PaymentSettlement;
use App\PayosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PaymentRetirementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 18)->setTime(8, 0));
        config(['demo.enabled' => true, 'services.payos' => ['client_id' => 'client-test', 'api_key' => 'api-test', 'checksum_key' => 'checksum-test']]);
        Http::preventStrayRequests();
    }

    private function provider(array $data): array
    {
        return ['code' => '00', 'data' => $data, 'signature' => app(PayosService::class)->sign($data)];
    }

    private function fakeCreation(): void
    {
        Http::fake(['*/v2/payment-requests' => fn ($request) => Http::response($this->provider(['orderCode' => $request['orderCode'], 'amount' => $request['amount'], 'currency' => 'VND', 'paymentLinkId' => 'retirement123', 'checkoutUrl' => 'https://pay.payos.vn/web/retirement123']))]);
    }

    public function test_removed_momo_and_demo_routes_cannot_create_settle_or_refund_money(): void
    {
        $owner = User::factory()->create(['wallet_balance' => 0]);
        $booking = Booking::factory()->create(['user_id' => $owner->id]);
        $attempt = PaymentAttempt::factory()->create(['user_id' => $owner->id, 'booking_id' => $booking->id, 'partner_code' => 'DEMO_MOMO']);
        foreach (['payments/momo', 'payments/momo/ipn', 'payments/momo/'.$attempt->id.'/sync', 'demo/payments/'.$attempt->id, 'demo/bookings/'.$booking->id.'/bank'] as $path) {
            $this->actingAs($owner)->postJson('/api/v1/'.$path, ['request_key' => (string) Str::uuid(), 'success' => true])->assertNotFound();
        }
        $this->getJson('/api/v1/payments/momo/'.$attempt->id)->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->postJson('/api/v1/manage/bookings/'.$booking->id.'/momo-refund', [])->assertNotFound();
        $this->assertSame(0, $owner->fresh()->wallet_balance);
        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
    }

    public function test_bootstrap_and_service_status_no_longer_offer_momo_or_invent_a_demo_bank(): void
    {
        config(['integrations.bank.account' => '', 'integrations.bank.name' => '', 'integrations.bank.bank_bin' => '']);
        $this->getJson('/api/v1/hub')->assertOk()->assertJsonMissingPath('data.momo_enabled')->assertJsonMissingPath('data.demo.momo')->assertJsonPath('data.bank', null)->assertJsonPath('data.payos_enabled', true);
        $status = $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson('/api/v1/admin/integrations')->assertOk();
        $this->assertNotContains('momo', array_column($status->json('data'), 'id'));
        Http::assertNothingSent();
    }

    public function test_retired_pending_attempt_is_kept_in_history_but_cannot_block_a_new_bank_checkout(): void
    {
        $booking = Booking::factory()->create(['paid_amount' => 0, 'deposit_amount' => 30000]);
        $legacy = PaymentAttempt::factory()->create(['user_id' => $booking->user_id, 'booking_id' => $booking->id, 'partner_code' => 'DEMO_MOMO', 'expires_at' => now()->addMinutes(10)]);
        $this->fakeCreation();
        $this->actingAs($booking->user)->postJson('/api/v1/payments/payos', ['purpose' => 'booking', 'booking_id' => $booking->id, 'deposit_only' => true, 'request_key' => (string) Str::uuid()])->assertOk()->assertJsonPath('data.amount', 30000);
        $receipt = $this->getJson('/api/v1/bookings/'.$booking->id.'/receipt')->assertOk();
        $this->assertCount(1, $receipt->json('data.pending_payment_attempts'));
        $this->assertSame('PAYOS', $receipt->json('data.pending_payment_attempts.0.partner_code'));
        $this->assertNotNull($legacy->fresh());
        $this->assertDatabaseCount('payment_attempts', 2);
    }

    public function test_existing_bank_checkout_blocks_another_request_until_its_status_is_resolved(): void
    {
        $booking = Booking::factory()->create(['paid_amount' => 0]);
        PaymentAttempt::factory()->create(['user_id' => $booking->user_id, 'booking_id' => $booking->id, 'partner_code' => 'PAYOS', 'expires_at' => now()->addMinutes(10)]);
        $this->actingAs($booking->user)->postJson('/api/v1/payments/payos', ['purpose' => 'booking', 'booking_id' => $booking->id, 'request_key' => (string) Str::uuid()])->assertConflict();
        $this->assertDatabaseCount('payment_attempts', 1);
        Http::assertNothingSent();
    }

    public function test_bank_checkout_rejects_foreign_bookings_closed_bills_and_invalid_purposes(): void
    {
        $booking = Booking::factory()->create();
        $body = ['purpose' => 'booking', 'booking_id' => $booking->id, 'request_key' => (string) Str::uuid()];
        $this->actingAs(User::factory()->create())->postJson('/api/v1/payments/payos', $body)->assertForbidden();
        $this->actingAs($booking->user);
        foreach (['cancelled', 'completed'] as $status) {
            $booking->update(['status' => $status]);
            $this->postJson('/api/v1/payments/payos', $body)->assertUnprocessable();
        }
        $this->postJson('/api/v1/payments/payos', [...$body, 'purpose' => 'momo'])->assertUnprocessable();
        $this->assertDatabaseCount('payment_attempts', 0);
        Http::assertNothingSent();
    }

    public function test_work_accounts_cannot_create_a_personal_bank_payment(): void
    {
        foreach (['admin', 'manager', 'staff', 'cashier'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->postJson('/api/v1/payments/payos', ['purpose' => 'wallet', 'amount' => 100000, 'request_key' => (string) Str::uuid()])->assertForbidden();
        }
        Http::assertNothingSent();
    }

    public function test_a_retired_provider_cannot_be_settled_even_by_calling_the_internal_service(): void
    {
        $attempt = PaymentAttempt::factory()->create(['partner_code' => 'DEMO_BANK']);
        try {
            app(PaymentSettlement::class)->receive($attempt, 0, 'retired-provider');
            $this->fail('Retired payment must not be settled.');
        } catch (HttpException $exception) {
            $this->assertSame(410, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('wallet_entries', 0);
    }
}
