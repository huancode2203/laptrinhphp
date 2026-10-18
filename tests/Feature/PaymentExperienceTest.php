<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\PayosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 18)->setTime(8, 0));
        config(['services.payos' => ['client_id' => 'client-test', 'api_key' => 'api-test', 'checksum_key' => 'checksum-test']]);
        Http::preventStrayRequests();
    }

    private function signed(array $data): array
    {
        return ['code' => '00', 'data' => $data, 'signature' => app(PayosService::class)->sign($data)];
    }

    private function attempt(Booking $booking): PaymentAttempt
    {
        return PaymentAttempt::factory()->create(['booking_id' => $booking->id, 'user_id' => $booking->user_id, 'partner_code' => 'PAYOS', 'order_id' => (string) random_int(1000000000000, 9000000000000), 'amount' => 100000, 'extra_data' => base64_encode(json_encode(['payment_link_id' => 'experience123']))]);
    }

    private function fakeStatus(PaymentAttempt $attempt, string $status, int $amountPaid = 0): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['*/v2/payment-requests/*' => Http::response($this->signed(['id' => 'experience123', 'orderCode' => (int) $attempt->order_id, 'amount' => $attempt->amount, 'amountPaid' => $amountPaid, 'amountRemaining' => $attempt->amount - $amountPaid, 'status' => $status]))]);
    }

    public function test_verified_cancel_return_cancels_only_an_unpaid_online_booking(): void
    {
        $booking = Booking::factory()->create(['source' => 'online', 'paid_amount' => 0]);
        $attempt = $this->attempt($booking);
        $this->fakeStatus($attempt, 'CANCELLED');
        $this->actingAs($booking->user)->get('/payments/payos/return?orderCode='.$attempt->order_id.'&cancel=true')->assertRedirect('/#account/bookings/'.$booking->id);
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame('failed', $attempt->fresh()->status);
        $this->getJson('/api/v1/bookings/'.$booking->id)->assertJsonPath('data.status_label', 'Đã hủy');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_forged_cancel_query_does_not_cancel_a_payment_still_pending_at_the_bank(): void
    {
        $booking = Booking::factory()->create(['paid_amount' => 0]);
        $attempt = $this->attempt($booking);
        $this->fakeStatus($attempt, 'PENDING');
        $this->actingAs($booking->user)->get('/payments/payos/return?orderCode='.$attempt->order_id.'&cancel=true&status=CANCELLED')->assertRedirect();
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame('pending', $attempt->fresh()->status);
        $this->getJson('/api/v1/bookings/'.$booking->id)->assertJsonPath('data.status_label', 'Chờ thanh toán');
    }

    public function test_terminal_provider_status_with_partial_money_requires_reconciliation_and_does_not_cancel_booking(): void
    {
        foreach (['CANCELLED', 'EXPIRED'] as $status) {
            $booking = Booking::factory()->create(['source' => 'online', 'paid_amount' => 0]);
            $attempt = $this->attempt($booking);
            $this->fakeStatus($attempt, $status, 30000);
            $this->actingAs($booking->user)->postJson('/api/v1/payments/payos/'.$attempt->id.'/sync')->assertConflict()->assertJsonPath('message', 'Chưa thể đóng thanh toán vì ngân hàng có thể đã nhận một phần tiền. Vui lòng liên hệ chi nhánh để đối chiếu; lịch chưa bị hủy.');
            $this->assertSame('confirmed', $booking->fresh()->status);
            $this->assertSame('pending', $attempt->fresh()->status);
            $this->assertSame(0, $booking->fresh()->paid_amount);
        }
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_cancelled_payment_does_not_erase_a_paid_deposit_or_a_counter_reservation(): void
    {
        foreach ([['source' => 'online', 'paid_amount' => 30000], ['source' => 'counter', 'paid_amount' => 0]] as $data) {
            $booking = Booking::factory()->create($data);
            $attempt = $this->attempt($booking);
            $this->fakeStatus($attempt, 'CANCELLED');
            $this->actingAs($booking->user)->postJson('/api/v1/payments/payos/'.$attempt->id.'/sync')->assertOk();
            $this->assertSame('confirmed', $booking->fresh()->status);
            $this->assertSame($data['paid_amount'], $booking->fresh()->paid_amount);
        }
    }

    public function test_manager_creates_payos_for_the_actual_customer_and_can_sync_only_in_assigned_branch(): void
    {
        $booking = Booking::factory()->create(['source' => 'counter', 'paid_amount' => 0]);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $booking->court->branch_id]);
        Http::fake(['*/v2/payment-requests' => fn ($request) => Http::response($this->signed(['orderCode' => $request['orderCode'], 'amount' => $request['amount'], 'currency' => 'VND', 'paymentLinkId' => 'experience123', 'checkoutUrl' => 'https://pay.payos.vn/web/experience123']))]);
        $body = ['request_key' => (string) Str::uuid(), 'purpose' => 'booking', 'booking_id' => $booking->id];
        $response = $this->actingAs($manager)->postJson('/api/v1/payments/payos', $body)->assertOk()->assertJsonPath('data.user_id', $booking->user_id)->assertJsonPath('data.amount', $booking->total_amount);
        $attempt = PaymentAttempt::findOrFail($response->json('data.id'));
        $this->fakeStatus($attempt, 'PENDING');
        $this->postJson('/api/v1/payments/payos/'.$attempt->id.'/sync')->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'manager']))->postJson('/api/v1/payments/payos', [...$body, 'request_key' => (string) Str::uuid()])->assertForbidden();
        $this->postJson('/api/v1/payments/payos/'.$attempt->id.'/sync')->assertForbidden();
        $this->assertSame(0, $booking->fresh()->paid_amount);
    }

    public function test_revoked_payment_permission_blocks_counter_payos_creation(): void
    {
        $booking = Booking::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $booking->court->branch_id]);
        DB::table('role_permissions')->insert(['role' => 'manager', 'permission' => 'payments.write', 'allowed' => false]);
        $this->actingAs($manager)->postJson('/api/v1/payments/payos', ['purpose' => 'booking', 'booking_id' => $booking->id, 'request_key' => (string) Str::uuid()])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_paid_bookings_and_refund_requests_cannot_be_cancelled_or_refunded_through_counter_ui_routes(): void
    {
        $booking = Booking::factory()->create(['paid_amount' => 30000]);
        $this->actingAs($booking->user)->patchJson('/api/v1/bookings/'.$booking->id.'/status', ['status' => 'cancelled'])->assertUnprocessable();
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $booking->court->branch_id]);
        $this->actingAs($manager)->patchJson('/api/v1/bookings/'.$booking->id.'/status', ['status' => 'cancelled'])->assertUnprocessable();
        $this->postJson('/api/v1/manage/bookings/'.$booking->id.'/payments', ['kind' => 'refund', 'amount' => 30000, 'method' => 'bank', 'request_key' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertSame(30000, $booking->fresh()->paid_amount);
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
    }
}
