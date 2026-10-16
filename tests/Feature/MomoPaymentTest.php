<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Court;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\MomoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class MomoPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 12)->setTime(8, 0));
        $this->customer = User::factory()->create();
        config(['momo.enabled' => true, 'momo.partner_code' => 'TEST', 'momo.access_key' => 'access', 'momo.secret_key' => 'secret', 'momo.ipn_url' => 'https://hub.example/api/v1/payments/momo/ipn', 'momo.redirect_url' => 'https://hub.example/#account']);
        Http::preventStrayRequests();
    }

    private function signedCallback(PaymentAttempt $a, array $overrides = []): array
    {
        $d = array_replace(['partnerCode' => 'TEST', 'orderId' => $a->order_id, 'requestId' => $a->request_key, 'amount' => $a->amount, 'orderInfo' => $a->order_info, 'orderType' => 'momo_wallet', 'transId' => '123456789', 'resultCode' => 0, 'message' => 'Successful', 'payType' => 'qr', 'responseTime' => 1791780000000, 'extraData' => $a->extra_data], $overrides);
        $d['signature'] = app(MomoService::class)->sign(['accessKey' => 'access', ...$d]);

        return $d;
    }

    private function attempt(array $overrides = []): PaymentAttempt
    {
        return PaymentAttempt::factory()->create(['user_id' => $this->customer->id, ...$overrides]);
    }

    public function test_payment_is_disabled_without_credentials(): void
    {
        config(['momo.secret_key' => '']);
        $this->actingAs($this->customer)->postJson('/api/v1/payments/momo', ['request_key' => (string) Str::uuid(), 'purpose' => 'wallet', 'amount' => 100000])->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_receipt_lists_pending_attempts_separately_and_restricts_access_to_owner(): void
    {
        $booking = Booking::factory()->create(['user_id' => $this->customer->id]);
        $pending = $this->attempt(['booking_id' => $booking->id, 'purpose' => 'booking']);
        $paid = $this->attempt(['booking_id' => $booking->id, 'purpose' => 'booking', 'status' => 'paid']);
        $url = '/api/v1/bookings/'.$booking->id.'/receipt';
        $this->actingAs($this->customer)->getJson($url)->assertOk()
            ->assertJsonCount(1, 'data.pending_payment_attempts')
            ->assertJsonPath('data.pending_payment_attempts.0.id', $pending->id)
            ->assertJsonPath('data.pending_payment_attempts.0.pay_url', $pending->pay_url)
            ->assertJsonCount(1, 'data.payment_attempts')
            ->assertJsonPath('data.payment_attempts.0.id', $paid->id);
        $this->actingAs(User::factory()->create())->getJson($url)->assertForbidden();
    }

    public function test_create_signature_server_amount_and_retry_reuses_one_attempt(): void
    {
        $court = Court::factory()->create();
        $b = $this->actingAs($this->customer)->postJson('/api/v1/bookings', ['request_key' => (string) Str::uuid(), 'court_id' => $court->id, 'starts_at' => '2026-10-13 10:00', 'ends_at' => '2026-10-13 11:00'])->assertCreated()->json('data.id');
        Http::fake(['*' => fn ($r) => Http::response(['resultCode' => 0, 'payUrl' => 'https://test-payment.momo.vn/pay/test', 'orderId' => $r['orderId'], 'requestId' => $r['requestId']])]);
        $d = ['request_key' => (string) Str::uuid(), 'purpose' => 'booking', 'booking_id' => $b, 'amount' => 1];
        $this->postJson('/api/v1/payments/momo', $d)->assertOk()->assertJsonPath('data.amount', 100000);
        $this->postJson('/api/v1/payments/momo', $d)->assertOk();
        Http::assertSentCount(1);
        Http::assertSent(function ($r): bool {
            $keys = ['accessKey' => 'access'];
            foreach (['amount', 'extraData', 'ipnUrl', 'orderId', 'orderInfo', 'partnerCode', 'redirectUrl', 'requestId', 'requestType'] as $k) {
                $keys[$k] = $r[$k];
            }

            return $r['signature'] === app(MomoService::class)->sign($keys) && $r['amount'] === 100000;
        });
        $this->assertEquals(0, Booking::find($b)->paid_amount);
    }

    public function test_signed_ipn_topup_is_exactly_once_and_foreign_user_cannot_read_attempt(): void
    {
        $a = $this->attempt();
        $d = $this->signedCallback($a);
        $this->postJson('/api/v1/payments/momo/ipn', $d)->assertNoContent();
        $this->postJson('/api/v1/payments/momo/ipn', $d)->assertNoContent();
        $this->assertEquals(100000, $this->customer->refresh()->wallet_balance);
        $this->assertDatabaseCount('wallet_entries', 1);
        $this->actingAs(User::factory()->create())->getJson('/api/v1/payments/momo/'.$a->id)->assertForbidden();
    }

    public function test_invalid_signature_amount_and_duplicate_provider_transaction_are_rejected(): void
    {
        $a = $this->attempt();
        $d = $this->signedCallback($a);
        $d['signature'] = str_repeat('0', 64);
        $this->postJson('/api/v1/payments/momo/ipn', $d)->assertForbidden();
        $this->postJson('/api/v1/payments/momo/ipn', $this->signedCallback($a, ['amount' => 99999]))->assertConflict();
        $this->postJson('/api/v1/payments/momo/ipn', $this->signedCallback($a))->assertNoContent();
        $b = $this->attempt();
        $this->postJson('/api/v1/payments/momo/ipn', $this->signedCallback($b))->assertConflict();
        $this->assertEquals(100000, $this->customer->refresh()->wallet_balance);
    }

    public function test_authorization_result_is_not_captured_and_success_later_can_be_processed(): void
    {
        $a = $this->attempt();
        $this->postJson('/api/v1/payments/momo/ipn', $this->signedCallback($a, ['resultCode' => 9000]))->assertNoContent();
        $this->assertEquals(0, $this->customer->refresh()->wallet_balance);
        $this->postJson('/api/v1/payments/momo/ipn', $this->signedCallback($a))->assertNoContent();
        $this->assertEquals(100000, $this->customer->refresh()->wallet_balance);
    }

    public function test_booking_ipn_marks_paid_and_late_cancelled_booking_credits_wallet_instead(): void
    {
        $court = Court::factory()->create();
        $booking = Booking::factory()->create(['court_id' => $court->id, 'user_id' => $this->customer->id, 'total_amount' => 100000, 'court_amount' => 100000, 'paid_amount' => 0]);
        $a = $this->attempt(['booking_id' => $booking->id, 'purpose' => 'booking']);
        $this->postJson('/api/v1/payments/momo/ipn', $this->signedCallback($a))->assertNoContent();
        $this->assertEquals(100000, $booking->refresh()->paid_amount);
        $this->assertDatabaseCount('payments', 1);
        $booking = Booking::factory()->create(['court_id' => $court->id, 'user_id' => $this->customer->id, 'total_amount' => 100000, 'court_amount' => 100000, 'paid_amount' => 0, 'status' => 'cancelled']);
        $b = $this->attempt(['booking_id' => $booking->id, 'purpose' => 'booking']);
        $this->postJson('/api/v1/payments/momo/ipn', $this->signedCallback($b, ['transId' => '222222']))->assertNoContent();
        $this->assertEquals('credited', $b->refresh()->status);
        $this->assertEquals(100000, $this->customer->refresh()->wallet_balance);
        $this->assertEquals(0, $booking->refresh()->paid_amount);
    }

    public function test_deposit_callback_records_partial_booking_payment_and_duplicate_is_harmless(): void
    {
        $booking = Booking::factory()->create(['user_id' => $this->customer->id, 'total_amount' => 200000, 'court_amount' => 200000, 'deposit_amount' => 60000, 'paid_amount' => 0]);
        $attempt = $this->attempt(['booking_id' => $booking->id, 'purpose' => 'booking', 'amount' => 60000, 'extra_data' => base64_encode(json_encode(['purpose' => 'booking', 'deposit_only' => true]))]);
        $callback = $this->signedCallback($attempt);
        $this->postJson('/api/v1/payments/momo/ipn', $callback)->assertNoContent();
        $this->postJson('/api/v1/payments/momo/ipn', $callback)->assertNoContent();
        $this->assertSame(60000, $booking->fresh()->paid_amount);
        $this->assertSame(0, $this->customer->fresh()->wallet_balance);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('wallet_entries', 0);
    }

    public function test_momo_money_after_another_payment_only_credits_the_excess_to_wallet(): void
    {
        $booking = Booking::factory()->create(['user_id' => $this->customer->id, 'total_amount' => 100000, 'court_amount' => 100000, 'paid_amount' => 70000]);
        $attempt = $this->attempt(['booking_id' => $booking->id, 'purpose' => 'booking']);
        $callback = $this->signedCallback($attempt);
        $this->postJson('/api/v1/payments/momo/ipn', $callback)->assertNoContent();
        $this->postJson('/api/v1/payments/momo/ipn', $callback)->assertNoContent();
        $this->assertSame(100000, $booking->fresh()->paid_amount);
        $this->assertSame(70000, $this->customer->fresh()->wallet_balance);
        $this->assertDatabaseHas('payments', ['booking_id' => $booking->id, 'amount' => 30000]);
        $this->assertDatabaseCount('wallet_entries', 1);
    }

    public function test_provider_query_recovers_a_missing_ipn_and_later_ipn_does_not_double_credit(): void
    {
        $attempt = $this->attempt();
        Http::fake(['*/query' => fn ($r) => Http::response(['partnerCode' => 'TEST', 'orderId' => $r['orderId'], 'requestId' => $r['requestId'], 'amount' => $attempt->amount, 'extraData' => $attempt->extra_data, 'transId' => 123456789, 'resultCode' => 0])]);
        $this->actingAs($this->customer)->postJson('/api/v1/payments/momo/'.$attempt->id.'/sync')->assertOk()->assertJsonPath('data.status', 'paid');
        $this->postJson('/api/v1/payments/momo/ipn', $this->signedCallback($attempt))->assertNoContent();
        $this->postJson('/api/v1/payments/momo/'.$attempt->id.'/sync')->assertOk();
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r['signature'] === app(MomoService::class)->sign(['accessKey' => 'access', 'partnerCode' => 'TEST', 'orderId' => $attempt->order_id, 'requestId' => $r['requestId']]));
        $this->assertSame(100000, $this->customer->fresh()->wallet_balance);
        $this->assertDatabaseCount('wallet_entries', 1);
    }

    public function test_provider_query_checks_owner_amount_and_pending_results_never_credit_money(): void
    {
        $attempt = $this->attempt();
        $url = '/api/v1/payments/momo/'.$attempt->id.'/sync';
        $this->actingAs(User::factory()->create())->postJson($url)->assertForbidden();
        Http::assertNothingSent();
        Http::fake(['*/query' => function ($r) use ($attempt) {
            static $calls = 0;
            $calls++;

            return Http::response(['partnerCode' => 'TEST', 'orderId' => $r['orderId'], 'requestId' => $r['requestId'], 'amount' => 1, 'extraData' => $attempt->extra_data, 'transId' => 123456789, 'resultCode' => $calls === 1 ? 0 : 9000]);
        }]);
        $this->actingAs($this->customer)->postJson($url)->assertStatus(502);
        $this->postJson($url)->assertOk()->assertJsonPath('data.status', 'pending');
        $this->assertSame(0, $this->customer->fresh()->wallet_balance);
    }

    public function test_zero_transaction_and_different_transaction_after_success_are_rejected(): void
    {
        $attempt = $this->attempt();
        $this->postJson('/api/v1/payments/momo/ipn', $this->signedCallback($attempt, ['transId' => '0']))->assertStatus(400);
        $this->assertDatabaseCount('wallet_entries', 0);
        $this->postJson('/api/v1/payments/momo/ipn', $this->signedCallback($attempt))->assertNoContent();
        $this->postJson('/api/v1/payments/momo/ipn', $this->signedCallback($attempt, ['transId' => '222222']))->assertConflict();
        $this->assertDatabaseCount('wallet_entries', 1);
    }
}
