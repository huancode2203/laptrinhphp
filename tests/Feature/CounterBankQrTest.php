<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Court;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\PayosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\FakesBankCheckout;
use Tests\TestCase;

class CounterBankQrTest extends TestCase
{
    use FakesBankCheckout, RefreshDatabase;

    private Court $court;

    private User $customer;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 23)->setTime(9, 0));
        $this->court = Court::factory()->create();
        $this->customer = User::factory()->create();
        $this->manager = User::factory()->create(['role' => 'manager', 'branch_id' => $this->court->branch_id]);
        config(['services.payos' => ['client_id' => 'test-client', 'api_key' => 'test-key', 'checksum_key' => 'test-checksum']]);
        Http::fake(['https://api-merchant.payos.vn/v2/payment-requests' => function ($request) {
            $data = ['orderCode' => $request['orderCode'], 'amount' => $request['amount'], 'currency' => 'VND', 'paymentLinkId' => 'counter-'.$request['orderCode'], 'checkoutUrl' => 'https://pay.payos.vn/web/counter-'.$request['orderCode'], 'qrCode' => 'TEST-BANK-QR-'.$request['orderCode']];

            return Http::response(['code' => '00', 'data' => $data, 'signature' => app(PayosService::class)->sign($data)]);
        }]);
        $this->actingAs($this->manager);
    }

    private function book(bool $series = false): int
    {
        $result = $this->postJson('/api/v1/bookings'.($series ? '/series' : ''), ['request_key' => (string) Str::uuid(), 'court_id' => $this->court->id, 'user_id' => $this->customer->id, 'starts_at' => '2026-11-24 13:00', 'ends_at' => '2026-11-24 15:00', 'occurrences' => 2])->assertCreated();

        return $result->json($series ? 'data.0.id' : 'data.id');
    }

    private function checkout(int $booking, bool $series = false): PaymentAttempt
    {
        $result = $this->postJson('/api/v1/payments/payos', ['request_key' => (string) Str::uuid(), 'booking_id' => $booking, 'purpose' => $series ? 'series' : 'booking', 'return_to' => '#manage/bookings'])->assertOk();

        return PaymentAttempt::findOrFail($result->json('data.id'));
    }

    public function test_manager_gets_the_signed_bank_qr_and_can_reopen_it_without_a_customer_login(): void
    {
        $booking = $this->book();
        $attempt = $this->checkout($booking);
        $this->assertSame(200000, $attempt->amount);
        $this->assertSame('TEST-BANK-QR-'.$attempt->order_id, $attempt->qr_code);
        for ($i = 0; $i < 2; $i++) {
            $this->getJson('/api/v1/bookings/'.$booking.'/receipt')->assertOk()
                ->assertJsonPath('data.pending_payment_attempts.0.id', $attempt->id)
                ->assertJsonPath('data.pending_payment_attempts.0.qr_code', $attempt->qr_code)
                ->assertJsonPath('data.pending_payment_attempts.0.order_id', $attempt->order_id)
                ->assertJsonPath('data.booking.paid_amount', 0);
        }
        $this->postJson('/api/v1/payments/payos', ['request_key' => (string) Str::uuid(), 'booking_id' => $booking, 'purpose' => 'booking'])->assertConflict();
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertDatabaseCount('payments', 0);
        Http::assertSentCount(1);
    }

    public function test_manager_can_check_the_bank_confirmation_and_only_credit_the_invoice_once(): void
    {
        $booking = $this->book();
        $attempt = $this->checkout($booking);
        $this->settleBankCheckout($attempt);
        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/v1/payments/payos/'.$attempt->id.'/sync')->assertOk()->assertJsonPath('data.status', 'paid');
        }
        $this->getJson('/api/v1/bookings/'.$booking.'/receipt')->assertOk()
            ->assertJsonPath('data.booking.paid_amount', 200000)->assertJsonPath('data.booking.remaining_amount', 0)->assertJsonCount(0, 'data.pending_payment_attempts');
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_one_counter_qr_covers_the_entire_fixed_series(): void
    {
        $booking = $this->book(true);
        $attempt = $this->checkout($booking, true);
        $this->assertSame((int) Booking::sum('total_amount'), $attempt->amount);
        $this->assertDatabaseCount('payment_attempt_bookings', 2);
        foreach (Booking::all() as $session) {
            $this->getJson('/api/v1/bookings/'.$session->id.'/receipt')->assertOk()->assertJsonPath('data.pending_payment_attempts.0.qr_code', $attempt->qr_code);
        }
        $this->settleBankCheckout($attempt);
        foreach (Booking::all() as $session) {
            $this->assertSame($session->total_amount, $session->paid_amount);
        }
    }

    public function test_other_branches_and_unrelated_customers_cannot_view_or_sync_the_qr(): void
    {
        $booking = $this->book();
        $attempt = $this->checkout($booking);
        foreach ([User::factory()->create(['role' => 'manager', 'branch_id' => Court::factory()->create()->branch_id]), User::factory()->create()] as $outsider) {
            $this->actingAs($outsider)->getJson('/api/v1/bookings/'.$booking.'/receipt')->assertForbidden();
            $this->postJson('/api/v1/payments/payos/'.$attempt->id.'/sync')->assertForbidden();
        }
        Http::assertSentCount(1);
    }

    public function test_cancelled_bank_qr_does_not_mark_the_counter_invoice_paid_or_cancel_the_booking(): void
    {
        $booking = $this->book();
        $attempt = $this->checkout($booking);
        $data = ['id' => 'counter-'.$attempt->order_id, 'orderCode' => (int) $attempt->order_id, 'amount' => $attempt->amount, 'amountPaid' => 0, 'amountRemaining' => $attempt->amount, 'status' => 'CANCELLED'];
        Http::fake(['https://api-merchant.payos.vn/v2/payment-requests/'.$attempt->order_id => Http::response(['code' => '00', 'data' => $data, 'signature' => app(PayosService::class)->sign($data)])]);
        $this->postJson('/api/v1/payments/payos/'.$attempt->id.'/sync')->assertOk()->assertJsonPath('data.status', 'failed');
        $this->assertSame('confirmed', Booking::findOrFail($booking)->status);
        $this->assertSame(0, Booking::findOrFail($booking)->paid_amount);
        $this->assertDatabaseCount('payments', 0);
        $this->assertNotSame($attempt->id, $this->checkout($booking)->id);
    }
}
