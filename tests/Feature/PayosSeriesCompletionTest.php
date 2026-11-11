<?php

namespace Tests\Feature;

use App\FeatureService;
use App\Models\Booking;
use App\Models\Court;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\PaymentSettlement;
use App\PayosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PayosSeriesCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(8, 0));
        config(['services.payos' => ['client_id' => 'client-test', 'api_key' => 'api-test', 'checksum_key' => 'checksum-test']]);
        Http::preventStrayRequests();
    }

    private function signed(array $data): array
    {
        return ['code' => '00', 'data' => $data, 'signature' => app(PayosService::class)->sign($data)];
    }

    private function bookings(): array
    {
        $owner = User::factory()->create(['wallet_balance' => 0]);
        $court = Court::factory()->create();
        $series = (string) Str::uuid();
        $rows = [];
        for ($week = 0; $week < 3; $week++) {
            $rows[] = Booking::factory()->create(['user_id' => $owner->id, 'court_id' => $court->id, 'series_key' => $series, 'booking_type' => 'fixed', 'starts_at' => now()->addDay()->addWeeks($week)->setTime(13, 0), 'ends_at' => now()->addDay()->addWeeks($week)->setTime(14, 0), 'total_amount' => 100000, 'paid_amount' => 0, 'deposit_amount' => 50000, 'payment_expires_at' => now()->addMinutes(10)]);
        }
        $this->actingAs($owner);

        return [$owner, $rows];
    }

    private function checkout(array $rows, bool $deposit = false): PaymentAttempt
    {
        Http::fake(['*/v2/payment-requests' => fn ($request) => Http::response($this->signed(['orderCode' => $request['orderCode'], 'amount' => $request['amount'], 'currency' => 'VND', 'paymentLinkId' => 'series123', 'checkoutUrl' => 'https://pay.payos.vn/web/series123']))]);
        $body = ['request_key' => (string) Str::uuid(), 'purpose' => 'series', 'booking_id' => $rows[0]->id, 'deposit_only' => $deposit, 'amount' => 1];
        $response = $this->postJson('/api/v1/payments/payos', $body)->assertOk()->assertJsonPath('data.amount', $deposit ? 150000 : 300000);
        $this->postJson('/api/v1/payments/payos', $body)->assertOk()->assertJsonPath('data.id', $response->json('data.id'));
        Http::assertSentCount(1);

        return PaymentAttempt::findOrFail($response->json('data.id'));
    }

    public function test_one_checkout_pays_each_weeks_deposit_once_and_blocks_overlapping_links(): void
    {
        [$owner, $rows] = $this->bookings();
        $attempt = $this->checkout($rows, true);
        $this->assertDatabaseCount('payment_attempt_bookings', 3);
        $this->postJson('/api/v1/payments/payos', ['request_key' => (string) Str::uuid(), 'purpose' => 'booking', 'booking_id' => $rows[1]->id])->assertConflict();
        app(PaymentSettlement::class)->receive($attempt, 0, 'PAYOS:series123', 150000);
        app(PaymentSettlement::class)->receive($attempt, 0, 'PAYOS:series123', 150000);
        foreach ($rows as $booking) {
            $this->assertSame(50000, $booking->fresh()->paid_amount);
        }
        $this->assertDatabaseCount('payments', 3);
        $this->assertSame(0, $owner->fresh()->wallet_balance);
        $this->assertSame(150000, $attempt->fresh()->settled_amount);
    }

    public function test_cancelled_week_routes_its_money_to_balance_without_erasing_other_weeks(): void
    {
        [$owner, $rows] = $this->bookings();
        $attempt = $this->checkout($rows);
        $rows[2]->update(['status' => 'cancelled']);
        app(PaymentSettlement::class)->receive($attempt, 0, 'PAYOS:series123', 300000);
        $this->assertSame(100000, $rows[0]->fresh()->paid_amount);
        $this->assertSame(100000, $rows[1]->fresh()->paid_amount);
        $this->assertSame(0, $rows[2]->fresh()->paid_amount);
        $this->assertSame(100000, $owner->fresh()->wallet_balance);
        $this->assertDatabaseCount('wallet_entries', 1);
    }

    public function test_cumulative_overpayments_are_credited_by_delta_without_duplicate_receipts(): void
    {
        [$owner, $rows] = $this->bookings();
        $attempt = PaymentAttempt::factory()->create(['user_id' => $owner->id, 'booking_id' => $rows[0]->id, 'amount' => 100000, 'partner_code' => 'PAYOS']);
        app(PaymentSettlement::class)->receive($attempt, 0, 'PAYOS:excess123', 140000);
        app(PaymentSettlement::class)->receive($attempt, 0, 'PAYOS:excess123', 160000);
        app(PaymentSettlement::class)->receive($attempt, 0, 'PAYOS:excess123', 160000);
        $this->assertSame(100000, $rows[0]->fresh()->paid_amount);
        $this->assertSame(60000, $owner->fresh()->wallet_balance);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('wallet_entries', 2);
        $this->assertSame(160000, $attempt->fresh()->settled_amount);
    }

    public function test_webhook_for_one_partial_transaction_uses_signed_cumulative_provider_total(): void
    {
        [$owner, $rows] = $this->bookings();
        $attempt = PaymentAttempt::factory()->create(['user_id' => $owner->id, 'booking_id' => $rows[0]->id, 'amount' => 100000, 'partner_code' => 'PAYOS', 'order_id' => '12345678902', 'extra_data' => base64_encode(json_encode(['payment_link_id' => 'partial456']))]);
        Http::fake(['*/v2/payment-requests/*' => Http::response($this->signed(['id' => 'partial456', 'orderCode' => 12345678902, 'amount' => 100000, 'amountPaid' => 100000, 'amountRemaining' => 0, 'status' => 'PAID']))]);
        $data = ['orderCode' => 12345678902, 'amount' => 30000, 'currency' => 'VND', 'paymentLinkId' => 'partial456', 'code' => '00'];
        $this->postJson('/api/v1/payments/payos/webhook', ['code' => '00', 'success' => true, 'data' => $data, 'signature' => app(PayosService::class)->sign($data)])->assertOk();
        $this->assertSame(100000, $rows[0]->fresh()->paid_amount);
        $this->assertSame(100000, $attempt->fresh()->settled_amount);
    }

    public function test_webhook_registration_uses_public_subpath_without_exposing_keys(): void
    {
        config(['app.url' => 'https://pdh2203.id.vn/badminton']);
        Http::fake(['*/confirm-webhook' => Http::response(['code' => '00', 'data' => ['webhookUrl' => 'https://pdh2203.id.vn/badminton/api/v1/payments/payos/webhook']])]);
        $this->assertSame('https://pdh2203.id.vn/badminton/api/v1/payments/payos/webhook', app(PayosService::class)->connectWebhook());
        Http::assertSent(fn ($request) => $request['webhookUrl'] === 'https://pdh2203.id.vn/badminton/api/v1/payments/payos/webhook' && ! isset($request['api_key']));
    }

    public function test_observed_partial_transfer_protects_booking_until_the_branch_reconciles_it(): void
    {
        [$owner, $rows] = $this->bookings();
        $attempt = PaymentAttempt::factory()->create(['user_id' => $owner->id, 'booking_id' => $rows[0]->id, 'amount' => 100000, 'partner_code' => 'PAYOS', 'order_id' => '12345678901', 'extra_data' => base64_encode(json_encode(['payment_link_id' => 'partial123']))]);
        Http::fake(['*/v2/payment-requests/*' => Http::response($this->signed(['id' => 'partial123', 'orderCode' => 12345678901, 'amount' => 100000, 'amountPaid' => 30000, 'amountRemaining' => 70000, 'status' => 'CANCELLED']))]);
        $this->postJson('/api/v1/payments/payos/'.$attempt->id.'/sync')->assertConflict();
        $this->assertDatabaseHas('payment_reconciliations', ['payment_attempt_id' => $attempt->id, 'received_amount' => 30000, 'status' => 'pending']);
        $this->travel(11)->minutes();
        app(FeatureService::class)->expireBookings();
        $this->assertSame('confirmed', $rows[0]->fresh()->status);
        app(PaymentSettlement::class)->receive($attempt, 0, 'PAYOS:partial123', 30000, true);
        $this->assertSame(30000, $rows[0]->fresh()->paid_amount);
        $this->assertSame('partial', $attempt->fresh()->status);
        $this->assertSame('resolved', DB::table('payment_reconciliations')->where('payment_attempt_id', $attempt->id)->value('status'));

    }
}
