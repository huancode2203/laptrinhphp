<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\PayosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PayosReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 17)->setTime(8, 0));
        config(['services.payos' => ['client_id' => 'client-test', 'api_key' => 'api-test', 'checksum_key' => 'checksum-test']]);
        Http::preventStrayRequests();
    }

    private function signed(array $data): array
    {
        return ['code' => '00', 'data' => $data, 'signature' => app(PayosService::class)->sign($data)];
    }

    private function attempt(array $attributes = []): PaymentAttempt
    {
        return PaymentAttempt::factory()->create([...['partner_code' => 'PAYOS', 'order_id' => (string) random_int(1000000000000, 9000000000000), 'extra_data' => base64_encode(json_encode(['payment_link_id' => 'link123', 'deposit_only' => false]))], ...$attributes]);
    }

    public function test_checkout_uses_nine_character_bank_description_and_does_not_extend_deposit_deadline(): void
    {
        $customer = User::factory()->create();
        $deadline = now()->addMinutes(4);
        $booking = Booking::factory()->create(['user_id' => $customer->id, 'paid_amount' => 0, 'deposit_amount' => 60000, 'payment_expires_at' => $deadline]);
        Http::fake(['*/v2/payment-requests' => fn ($request) => Http::response($this->signed(['orderCode' => $request['orderCode'], 'amount' => $request['amount'], 'currency' => 'VND', 'paymentLinkId' => 'link123', 'checkoutUrl' => 'https://pay.payos.vn/web/link123']))]);
        $response = $this->actingAs($customer)->postJson('/api/v1/payments/payos', ['purpose' => 'booking', 'booking_id' => $booking->id, 'deposit_only' => true, 'request_key' => (string) Str::uuid()])->assertOk()->assertJsonPath('data.amount', 60000);
        $attempt = PaymentAttempt::findOrFail($response->json('data.id'));
        $this->assertTrue($attempt->expires_at->eq($deadline));
        Http::assertSent(fn ($request) => preg_match('/^BH[0-9]{7}$/D', $request['description']) === 1 && $request['expiredAt'] === $deadline->timestamp);
        $this->assertSame(0, $booking->fresh()->paid_amount);
    }

    public function test_wallet_topup_creates_a_real_provider_link_without_crediting_before_confirmation(): void
    {
        $customer = User::factory()->create(['wallet_balance' => 0]);
        Http::fake(['*/v2/payment-requests' => fn ($request) => Http::response($this->signed(['orderCode' => $request['orderCode'], 'amount' => $request['amount'], 'currency' => 'VND', 'paymentLinkId' => 'link123', 'checkoutUrl' => 'https://pay.payos.vn/web/link123']))]);
        $data = ['purpose' => 'wallet', 'amount' => 50000, 'request_key' => (string) Str::uuid()];
        $this->actingAs($customer)->postJson('/api/v1/payments/payos', $data)->assertOk()->assertJsonPath('data.pay_url', 'https://pay.payos.vn/web/link123');
        $this->postJson('/api/v1/payments/payos', $data)->assertOk();
        $this->assertSame(0, $customer->fresh()->wallet_balance);
        $this->assertDatabaseCount('wallet_entries', 0);
        Http::assertSentCount(1);
    }

    public function test_scheduled_reconciliation_records_actual_payment_once_and_preserves_late_cancelled_payment(): void
    {
        $customer = User::factory()->create(['wallet_balance' => 0]);
        $booking = Booking::factory()->create(['user_id' => $customer->id, 'total_amount' => 200000, 'paid_amount' => 0, 'payment_expires_at' => null]);
        $cancelled = Booking::factory()->create(['user_id' => $customer->id, 'status' => 'cancelled', 'paid_amount' => 0]);
        $first = $this->attempt(['user_id' => $customer->id, 'purpose' => 'booking', 'booking_id' => $booking->id, 'amount' => 60000]);
        $second = $this->attempt(['user_id' => $customer->id, 'purpose' => 'booking', 'booking_id' => $cancelled->id, 'amount' => 50000, 'expires_at' => now()->subMinute(), 'extra_data' => base64_encode(json_encode(['payment_link_id' => 'link456']))]);
        $this->attempt(['partner_code' => 'DEMO_BANK']);
        $this->attempt(['status' => 'paid']);
        $this->attempt(['expires_at' => now()->subDays(2)]);
        Http::fake(['*/v2/payment-requests/*' => function ($request) use ($first, $second) {
            $attempt = str_ends_with($request->url(), $first->order_id) ? $first : $second;

            return Http::response($this->signed(['id' => json_decode(base64_decode($attempt->extra_data), true)['payment_link_id'], 'orderCode' => (int) $attempt->order_id, 'amount' => $attempt->amount, 'amountPaid' => $attempt->amount, 'amountRemaining' => 0, 'status' => 'PAID']));
        }]);
        $this->artisan('hub:sync-payos')->assertSuccessful();
        $this->artisan('hub:sync-payos')->assertSuccessful();
        $this->assertSame(60000, $booking->fresh()->paid_amount);
        $this->assertSame(0, $cancelled->fresh()->paid_amount);
        $this->assertSame(50000, $customer->fresh()->wallet_balance);
        $this->assertDatabaseHas('payments', ['booking_id' => $booking->id, 'method' => 'payos', 'amount' => 60000]);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('wallet_entries', 1);
        Http::assertSentCount(2);
    }

    public function test_failed_gateway_check_does_not_change_money_or_expose_keys_and_rotates_the_batch(): void
    {
        $customer = User::factory()->create(['wallet_balance' => 0]);
        $first = $this->attempt(['user_id' => $customer->id, 'updated_at' => now()->subHours(2)]);
        $second = $this->attempt(['user_id' => $customer->id, 'updated_at' => now()->subHour()]);
        Http::fake(['*' => Http::response(['code' => '401', 'desc' => 'api-test checksum-test'], 401)]);
        $this->assertSame(1, Artisan::call('hub:sync-payos', ['--limit' => 1, '--json' => true]));
        $output = Artisan::output();
        $this->assertSame(1, json_decode($output, true)['errors']);
        $this->assertStringNotContainsString('api-test', $output);
        $this->assertStringNotContainsString('checksum-test', $output);
        $this->assertSame('pending', $first->fresh()->status);
        $this->assertSame(0, $customer->fresh()->wallet_balance);
        $this->assertSame(1, Artisan::call('hub:sync-payos', ['--limit' => 1]));
        Http::assertSent(fn ($request) => str_ends_with($request->url(), $first->order_id));
        Http::assertSent(fn ($request) => str_ends_with($request->url(), $second->order_id));
        Http::assertSentCount(2);
    }

    public function test_missing_keys_and_invalid_batch_size_never_contact_the_gateway(): void
    {
        config(['services.payos' => []]);
        $this->attempt();
        $this->artisan('hub:sync-payos', ['--json' => true])->expectsOutput('{"configured":false,"checked":0,"settled":0,"failed":0,"errors":0}')->assertSuccessful();
        $this->artisan('hub:sync-payos', ['--limit' => 0])->assertExitCode(2);
        Http::assertNothingSent();
    }
}
