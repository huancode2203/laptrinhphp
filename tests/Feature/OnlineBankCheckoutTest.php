<?php

namespace Tests\Feature;

use App\FeatureService;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Court;
use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\FakesBankCheckout;
use Tests\TestCase;

class OnlineBankCheckoutTest extends TestCase
{
    use FakesBankCheckout, RefreshDatabase;

    private Court $court;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 23)->setTime(9, 0));
        $this->customer = User::factory()->create(['wallet_balance' => 1000000]);
        $this->court = Court::factory()->create(['branch_id' => Branch::factory()->create(['deposit_percent' => 30, 'payment_minutes' => 15, 'booking_days' => 90])->id]);
        $this->actingAs($this->customer);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['request_key' => (string) Str::uuid(), 'court_id' => $this->court->id, 'starts_at' => '2026-11-24 08:00', 'ends_at' => '2026-11-24 11:00'], $overrides);
    }

    public function test_online_booking_always_returns_a_bank_checkout_with_the_server_total(): void
    {
        $this->fakeBankCheckout();
        $this->postJson('/api/v1/bookings', $this->payload(['total_amount' => 1, 'amount' => 1]))
            ->assertCreated()->assertJsonPath('checkout.partner_code', 'PAYOS')->assertJsonPath('checkout.amount', 300000)
            ->assertJsonPath('checkout.status', 'pending')->assertJsonPath('data.status_label', 'Chờ thanh toán')->assertJsonPath('data.paid_amount', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(1000000, $this->customer->fresh()->wallet_balance);
        Http::assertSentCount(1);
    }

    public function test_customers_cannot_choose_cash_wallet_or_pay_later(): void
    {
        foreach (['cash', 'wallet', 'later'] as $method) {
            $this->postJson('/api/v1/bookings', $this->payload(['payment_method' => $method]))->assertUnprocessable()->assertJsonValidationErrors('payment_method');
        }
        $this->postJson('/api/v1/bookings', $this->payload(['pay_with_wallet' => true]))->assertUnprocessable()->assertJsonValidationErrors('pay_with_wallet');
        $this->assertDatabaseCount('bookings', 0);
        Http::assertNothingSent();
    }

    public function test_missing_bank_configuration_does_not_leave_a_booking(): void
    {
        config(['services.payos' => ['client_id' => '', 'api_key' => '', 'checksum_key' => '']]);
        $this->postJson('/api/v1/bookings', $this->payload())->assertStatus(503);
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('payment_attempts', 0);
    }

    public function test_bank_failure_rolls_back_the_new_booking_and_payment_attempt(): void
    {
        config(['services.payos' => ['client_id' => 'test', 'api_key' => 'test', 'checksum_key' => 'test']]);
        Http::fake(['*' => Http::response([], 503)]);
        $this->postJson('/api/v1/bookings', $this->payload())->assertStatus(502);
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('payment_attempts', 0);
        $this->assertDatabaseCount('wallet_entries', 0);
    }

    public function test_retry_returns_the_same_booking_and_bank_link(): void
    {
        $this->fakeBankCheckout();
        $payload = $this->payload(['deposit_only' => true]);
        $response = $this->postJson('/api/v1/bookings', $payload)->assertCreated();
        $this->postJson('/api/v1/bookings', $payload)->assertOk()->assertJsonPath('data.id', $response->json('data.id'))->assertJsonPath('checkout.id', $response->json('checkout.id'));
        $this->postJson('/api/v1/bookings', [...$payload, 'deposit_only' => false])->assertConflict();
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('payment_attempts', 1);
        Http::assertSentCount(1);
    }

    public function test_fixed_bookings_create_one_bank_checkout_for_every_session(): void
    {
        $this->fakeBankCheckout();
        $response = $this->postJson('/api/v1/bookings/series', $this->payload(['occurrences' => 3, 'deposit_only' => true]))->assertCreated()->assertJsonCount(3, 'data')->assertJsonPath('checkout.purpose', 'series');
        $this->assertSame((int) Booking::sum('deposit_amount'), $response->json('checkout.amount'));
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertDatabaseCount('payment_attempt_bookings', 3);
    }

    public function test_counter_booking_keeps_its_separate_payment_workflow(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'manager', 'branch_id' => $this->court->branch_id]));
        $this->postJson('/api/v1/bookings', $this->payload(['user_id' => $this->customer->id]))->assertCreated()->assertJsonPath('data.source', 'counter');
        $this->assertDatabaseCount('payment_attempts', 0);
        Http::assertNothingSent();
    }

    public function test_a_signed_bank_payment_confirms_the_online_deposit_exactly_once(): void
    {
        $this->fakeBankCheckout();
        $result = $this->postJson('/api/v1/bookings', $this->payload(['deposit_only' => true]))->assertCreated()
            ->assertJsonPath('checkout.amount', 90000)->assertJsonPath('data.status_label', 'Chờ thanh toán');
        $attempt = PaymentAttempt::findOrFail($result->json('checkout.id'));
        $this->settleBankCheckout($attempt);
        $this->settleBankCheckout($attempt);
        $this->getJson('/api/v1/bookings/'.$result->json('data.id'))->assertOk()
            ->assertJsonPath('data.status_label', 'Đã đặt cọc')->assertJsonPath('data.paid_amount', 90000)->assertJsonPath('data.remaining_amount', 210000);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(1000000, $this->customer->fresh()->wallet_balance);
    }

    public function test_a_failed_fixed_series_checkout_rolls_back_every_session_and_keeps_the_hold(): void
    {
        $hold = $this->postJson('/api/v1/holds', $this->payload())->assertOk()->json('data.id');
        config(['services.payos' => ['client_id' => 'test', 'api_key' => 'test', 'checksum_key' => 'test']]);
        Http::fake(['*' => Http::response([], 503)]);
        $this->postJson('/api/v1/bookings/series', $this->payload(['hold_id' => $hold, 'occurrences' => 3]))->assertStatus(502);
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('payment_attempts', 0);
        $this->assertDatabaseCount('payment_attempt_bookings', 0);
        $this->assertDatabaseHas('court_holds', ['id' => $hold]);
    }

    public function test_a_customer_cannot_pay_an_existing_booking_with_wallet(): void
    {
        $booking = Booking::factory()->create(['user_id' => $this->customer->id, 'court_id' => $this->court->id]);
        $this->postJson('/api/v1/bookings/'.$booking->id.'/wallet-payment', ['request_key' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertSame(1000000, $this->customer->fresh()->wallet_balance);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_zero_deposit_online_bookings_still_expire_without_bank_payment(): void
    {
        $this->court->branch->update(['deposit_percent' => 0]);
        $this->fakeBankCheckout();
        $response = $this->postJson('/api/v1/bookings', $this->payload(['deposit_only' => true]))->assertCreated()->assertJsonPath('checkout.amount', 300000);
        $this->assertNotNull($response->json('data.payment_expires_at'));
        $this->travel(16)->minutes();
        $this->assertSame(1, app(FeatureService::class)->expireBookings());
        $this->assertSame('cancelled', Booking::findOrFail($response->json('data.id'))->status);
    }
}
