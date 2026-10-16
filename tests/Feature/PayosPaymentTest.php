<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\PayosService;
use App\PayosSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PayosPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.payos' => ['client_id' => 'client-test', 'api_key' => 'api-test', 'checksum_key' => 'checksum-test']]);
    }

    private function reply(array $data): array
    {
        return ['code' => '00', 'data' => $data, 'signature' => app(PayosService::class)->sign($data)];
    }

    private function attempt(array $attributes = []): PaymentAttempt
    {
        return PaymentAttempt::factory()->create([...['partner_code' => 'PAYOS', 'order_id' => (string) random_int(1000000000, 9000000000), 'extra_data' => base64_encode(json_encode(['payment_link_id' => 'abc123', 'deposit_only' => false])), 'amount' => 100000], ...$attributes]);
    }

    public function test_creates_signed_checkout_with_server_amount_and_idempotency(): void
    {
        $customer = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $customer->id, 'court_amount' => 200000, 'total_amount' => 200000, 'deposit_amount' => 60000, 'paid_amount' => 10000]);
        Http::fake(['*/v2/payment-requests' => fn ($request) => Http::response($this->reply(['orderCode' => $request['orderCode'], 'amount' => $request['amount'], 'currency' => 'VND', 'paymentLinkId' => 'abc123', 'checkoutUrl' => 'https://pay.payos.vn/web/abc123']))]);
        $data = ['request_key' => (string) Str::uuid(), 'purpose' => 'booking', 'booking_id' => $booking->id, 'deposit_only' => false, 'amount' => 1];
        $this->actingAs($customer)->postJson('/api/v1/payments/payos', $data)->assertOk()->assertJsonPath('data.amount', 190000);
        $this->postJson('/api/v1/payments/payos', $data)->assertOk();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->header('x-client-id') === ['client-test'] && $request['signature'] === app(PayosService::class)->sign(collect($request->data())->only(['orderCode', 'amount', 'description', 'returnUrl', 'cancelUrl'])->all()));
        $this->assertEquals(10000, $booking->fresh()->paid_amount);
        $this->postJson('/api/v1/payments/payos', [...$data, 'deposit_only' => true])->assertConflict();
    }

    public function test_webhook_and_sync_credit_once_and_check_ownership(): void
    {
        $customer = User::factory()->create(['wallet_balance' => 0]);
        $attempt = $this->attempt(['user_id' => $customer->id, 'purpose' => 'wallet', 'booking_id' => null]);
        Http::fake(['*/v2/payment-requests/*' => Http::response($this->reply(['id' => 'abc123', 'orderCode' => (int) $attempt->order_id, 'amount' => 100000, 'amountPaid' => 100000, 'amountRemaining' => 0, 'status' => 'PAID', 'transactions' => []]))]);
        $data = ['orderCode' => (int) $attempt->order_id, 'amount' => 100000, 'currency' => 'VND', 'paymentLinkId' => 'abc123', 'code' => '00', 'description' => 'BH', 'reference' => 'bank123'];
        $body = ['code' => '00', 'success' => true, 'data' => $data, 'signature' => app(PayosService::class)->sign($data)];
        $this->postJson('/api/v1/payments/payos/webhook', $body)->assertOk();
        $this->postJson('/api/v1/payments/payos/webhook', $body)->assertOk();
        $this->actingAs($customer)->postJson('/api/v1/payments/payos/'.$attempt->id.'/sync')->assertOk()->assertJsonPath('data.status', 'paid');
        $this->assertEquals(100000, $customer->fresh()->wallet_balance);
        $this->assertDatabaseCount('wallet_entries', 1);
        $this->actingAs(User::factory()->create())->postJson('/api/v1/payments/payos/'.$attempt->id.'/sync')->assertForbidden();
    }

    public function test_invalid_signatures_and_amount_mismatches_cannot_change_money(): void
    {
        $customer = User::factory()->create(['wallet_balance' => 0]);
        $attempt = $this->attempt(['user_id' => $customer->id, 'purpose' => 'wallet', 'booking_id' => null]);
        $data = ['orderCode' => (int) $attempt->order_id, 'amount' => 100000, 'currency' => 'VND', 'paymentLinkId' => 'abc123', 'code' => '00'];
        $this->postJson('/api/v1/payments/payos/webhook', ['code' => '00', 'success' => true, 'data' => $data, 'signature' => str_repeat('0', 64)])->assertForbidden();
        $wrong = [...$data, 'amount' => 99999];
        $this->postJson('/api/v1/payments/payos/webhook', ['code' => '00', 'success' => true, 'data' => $wrong, 'signature' => app(PayosService::class)->sign($wrong)])->assertConflict();
        Http::fake(['*' => Http::response(['code' => '00', 'data' => ['id' => 'abc123'], 'signature' => str_repeat('0', 64)])]);
        $this->actingAs($customer)->postJson('/api/v1/payments/payos/'.$attempt->id.'/sync')->assertStatus(502);
        $this->get('/payments/payos/return?orderCode='.$attempt->order_id.'&status=PAID&code=00')->assertRedirect();
        $this->assertEquals('pending', $attempt->fresh()->status);
        $this->assertEquals(0, $customer->fresh()->wallet_balance);
    }

    public function test_late_payment_for_cancelled_booking_is_preserved_in_wallet(): void
    {
        $booking = Booking::factory()->create(['status' => 'cancelled', 'total_amount' => 100000, 'paid_amount' => 0]);
        $attempt = $this->attempt(['user_id' => $booking->user_id, 'booking_id' => $booking->id, 'purpose' => 'booking']);
        Http::fake(['*' => Http::response($this->reply(['id' => 'abc123', 'orderCode' => (int) $attempt->order_id, 'amount' => 100000, 'amountPaid' => 100000, 'amountRemaining' => 0, 'status' => 'PAID']))]);
        $this->actingAs($booking->user)->postJson('/api/v1/payments/payos/'.$attempt->id.'/sync')->assertOk()->assertJsonPath('data.status', 'credited');
        $this->assertEquals(0, $booking->fresh()->paid_amount);
        $this->assertEquals(100000, $booking->user->fresh()->wallet_balance);
    }

    public function test_only_admin_can_store_encrypted_credentials_and_secrets_are_never_returned(): void
    {
        config(['services.payos' => []]);
        $keys = ['client_id' => 'client-private', 'api_key' => 'api-private-key', 'checksum_key' => 'checksum-private-key'];
        $this->actingAs(User::factory()->create(['role' => 'manager']))->putJson('/api/v1/admin/integrations/payos', $keys)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->putJson('/api/v1/admin/integrations/payos', $keys)->assertOk()->assertJsonPath('data.configured', true)->assertDontSee('api-private-key');
        $this->assertStringNotContainsString('api-private-key', DB::table('integration_settings')->where('key', 'payos')->value('value'));
        $this->assertSame($keys, app(PayosSettings::class)->credentials());
    }

    public function test_retry_recovers_a_link_created_before_connection_was_lost(): void
    {
        $customer = User::factory()->create(['wallet_balance' => 0]);
        $attempt = $this->attempt(['user_id' => $customer->id, 'purpose' => 'wallet', 'booking_id' => null, 'pay_url' => null, 'extra_data' => base64_encode(json_encode(['deposit_only' => false]))]);
        Http::fake(['*/v2/payment-requests/*' => Http::response($this->reply(['id' => 'abc123', 'orderCode' => (int) $attempt->order_id, 'amount' => 100000, 'amountPaid' => 0, 'amountRemaining' => 100000, 'status' => 'PENDING']))]);
        $this->actingAs($customer)->postJson('/api/v1/payments/payos', ['purpose' => 'wallet', 'amount' => 100000, 'request_key' => $attempt->request_key])->assertOk()->assertJsonPath('data.pay_url', 'https://pay.payos.vn/web/abc123');
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertEquals(0, $customer->fresh()->wallet_balance);
        Http::assertSent(fn ($request) => $request->method() === 'GET');
    }

    public function test_signature_matches_an_independent_canonical_request(): void
    {
        $data = ['returnUrl' => 'https://example.com/return', 'orderCode' => 123456, 'description' => 'BH 123456', 'cancelUrl' => 'https://example.com/cancel', 'amount' => 60000];
        $expected = hash_hmac('sha256', 'amount=60000&cancelUrl=https://example.com/cancel&description=BH 123456&orderCode=123456&returnUrl=https://example.com/return', 'checksum-test');
        $this->assertSame($expected, app(PayosService::class)->sign($data));
    }
}
