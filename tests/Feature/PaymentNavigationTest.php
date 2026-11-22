<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\MembershipPlan;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\PayosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentNavigationTest extends TestCase
{
    use RefreshDatabase;

    private array $statuses = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://pdh2203.id.vn/badminton', 'services.payos' => ['client_id' => 'test-client', 'api_key' => 'test-api', 'checksum_key' => 'test-checksum']]);
        Http::preventStrayRequests();
        Http::fake(['*/v2/payment-requests' => fn ($request) => Http::response($this->signed(['orderCode' => $request['orderCode'], 'amount' => $request['amount'], 'currency' => 'VND', 'paymentLinkId' => 'navigation123', 'checkoutUrl' => 'https://pay.payos.vn/web/navigation123', 'qrCode' => 'TEST-ONLY-QR']))]);
        Http::fake(['*/v2/payment-requests/*' => function ($request) {
            $attempt = PaymentAttempt::where('order_id', basename($request->url()))->firstOrFail();
            $status = $this->statuses[$attempt->id] ?? 'PENDING';
            $paid = $status === 'PAID' ? $attempt->amount : 0;

            return Http::response($this->signed(['id' => 'navigation123', 'orderCode' => (int) $attempt->order_id, 'amount' => $attempt->amount, 'amountPaid' => $paid, 'amountRemaining' => $attempt->amount - $paid, 'status' => $status]));
        }]);
    }

    private function signed(array $data): array
    {
        return ['code' => '00', 'data' => $data, 'signature' => app(PayosService::class)->sign($data)];
    }

    private function fakeStatus(PaymentAttempt $attempt, string $status): void
    {
        $this->statuses[$attempt->id] = $status;
    }

    public function test_wallet_checkout_and_success_stay_on_the_actual_local_port_and_credit_once(): void
    {
        $customer = User::factory()->create(['wallet_balance' => 0]);
        $id = $this->actingAs($customer)->postJson('http://localhost:8088/api/v1/payments/payos', ['request_key' => (string) Str::uuid(), 'purpose' => 'wallet', 'amount' => 100000, 'return_to' => '#account/wallet'])->assertOk()->json('data.id');
        Http::assertSent(fn ($request) => $request['returnUrl'] === 'http://localhost:8088/payments/payos/return' && $request['cancelUrl'] === $request['returnUrl']);
        $attempt = PaymentAttempt::findOrFail($id);
        $this->fakeStatus($attempt, 'PAID');
        $url = 'http://localhost:8088/payments/payos/return?orderCode='.$attempt->order_id;
        $this->get($url)->assertStatus(302)->assertHeader('Location', '/#account/wallet');
        $this->get($url)->assertStatus(302)->assertHeader('Location', '/#account/wallet');
        $this->assertSame(100000, $customer->fresh()->wallet_balance);
        $this->assertDatabaseCount('wallet_entries', 1);
    }

    public function test_public_subpath_checkout_and_cancel_ignore_a_stale_localhost_configuration(): void
    {
        config(['app.url' => 'http://localhost/badminton']);
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->withHeaders(['X-Forwarded-Host' => 'pdh2203.id.vn', 'X-Forwarded-Proto' => 'https', 'X-Forwarded-Prefix' => '/badminton']);
        $customer = User::factory()->create(['wallet_balance' => 0]);
        $id = $this->actingAs($customer)->postJson('/api/v1/payments/payos', ['request_key' => (string) Str::uuid(), 'purpose' => 'wallet', 'amount' => 100000, 'return_to' => '#account/wallet'])->assertOk()->json('data.id');
        Http::assertSent(fn ($request) => $request['returnUrl'] === 'https://pdh2203.id.vn/badminton/payments/payos/return' && $request['cancelUrl'] === $request['returnUrl']);
        $attempt = PaymentAttempt::findOrFail($id);
        $this->fakeStatus($attempt, 'CANCELLED');
        $this->get('http://localhost/payments/payos/return?orderCode='.$attempt->order_id.'&cancel=true')->assertStatus(302)->assertHeader('Location', '/badminton/#account/wallet');
        $this->assertSame(0, $customer->fresh()->wallet_balance);
    }

    public function test_booking_and_recurring_payments_return_to_the_saved_customer_page(): void
    {
        foreach (['booking', 'series'] as $purpose) {
            $booking = Booking::factory()->create(['series_key' => $purpose === 'series' ? (string) Str::uuid() : null]);
            $id = $this->actingAs($booking->user)->postJson('http://127.0.0.1:8088/api/v1/payments/payos', ['request_key' => (string) Str::uuid(), 'purpose' => $purpose, 'booking_id' => $booking->id, 'return_to' => '#account/bookings/pending'])->assertOk()->json('data.id');
            $attempt = PaymentAttempt::findOrFail($id);
            $this->fakeStatus($attempt, 'PENDING');
            $this->get('http://127.0.0.1:8088/payments/payos/return?orderCode='.$attempt->order_id.'&status=PAID')->assertStatus(302)->assertHeader('Location', '/#account/bookings/pending');
            $this->assertSame(0, $booking->fresh()->paid_amount);
        }
    }

    public function test_counter_wallet_and_membership_payments_keep_the_staff_page(): void
    {
        $branch = Branch::factory()->create();
        $customer = User::factory()->create();
        $plan = MembershipPlan::factory()->create(['points_threshold' => 0]);
        foreach (['manager' => '#manage/customers', 'admin' => '#admin/payments'] as $role => $target) {
            $this->actingAs(User::factory()->create(['role' => $role, 'branch_id' => $branch->id]));
            foreach (['topup', 'membership'] as $kind) {
                $data = ['request_key' => (string) Str::uuid(), 'branch_id' => $branch->id, 'method' => 'bank', 'amount' => 100000, 'plan_id' => $plan->id, 'return_to' => $target];
                $id = $this->postJson('http://localhost:8088/api/v1/manage/users/'.$customer->id.'/'.$kind, $data)->assertOk()->json('data.id');
                $attempt = PaymentAttempt::findOrFail($id);
                $this->fakeStatus($attempt, 'CANCELLED');
                $this->get('http://localhost:8088/payments/payos/return?orderCode='.$attempt->order_id)->assertStatus(302)->assertHeader('Location', '/'.$target);
            }
        }
    }

    public function test_manager_booking_keeps_the_calendar_page(): void
    {
        $booking = Booking::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $booking->court->branch_id]);
        $id = $this->actingAs($manager)->postJson('/api/v1/payments/payos', ['request_key' => (string) Str::uuid(), 'purpose' => 'booking', 'booking_id' => $booking->id, 'return_to' => '#manage/calendar'])->assertOk()->json('data.id');
        $attempt = PaymentAttempt::findOrFail($id);
        $this->fakeStatus($attempt, 'PENDING');
        $this->get('/payments/payos/return?orderCode='.$attempt->order_id)->assertStatus(302)->assertHeader('Location', '/#manage/calendar');
    }

    public function test_external_or_other_role_destinations_cannot_override_the_wallet_return(): void
    {
        $customer = User::factory()->create();
        foreach (['https://evil.example', '//evil.example', '#admin/payments', '#account/wallet%0ahttps://evil.example', '#account/wallet?next=https://evil.example'] as $target) {
            $id = $this->actingAs($customer)->postJson('http://localhost:8088/api/v1/payments/payos', ['request_key' => (string) Str::uuid(), 'purpose' => 'wallet', 'amount' => 100000, 'return_to' => $target])->assertOk()->json('data.id');
            $attempt = PaymentAttempt::findOrFail($id);
            $this->fakeStatus($attempt, 'PENDING');
            $this->get('http://localhost:8088/payments/payos/return?orderCode='.$attempt->order_id.'&return_to=https://evil.example')->assertStatus(302)->assertHeader('Location', '/#account/wallet');
        }
    }

    public function test_expired_session_returns_to_login_on_the_current_origin_without_changing_money(): void
    {
        $attempt = PaymentAttempt::factory()->create(['partner_code' => 'PAYOS', 'purpose' => 'wallet', 'booking_id' => null]);
        $this->get('http://localhost:8088/payments/payos/return?orderCode='.$attempt->order_id)->assertStatus(302)->assertHeader('Location', '/#auth');
        Http::assertNothingSent();

    }

    public function test_background_checks_allow_polling_without_using_the_sensitive_action_limit(): void
    {
        $customer = User::factory()->create(['wallet_balance' => 0]);
        $id = $this->actingAs($customer)->postJson('/api/v1/payments/payos', ['request_key' => (string) Str::uuid(), 'purpose' => 'wallet', 'amount' => 100000])->assertOk()->json('data.id');
        $this->getJson('/api/v1/account/history/payments')->assertOk()->assertJsonPath('data.data.0.purpose', 'wallet');
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/v1/payments/payos/'.$id.'/sync')->assertOk()->assertJsonPath('data.status', 'pending');
        }
        $this->assertSame(0, $customer->fresh()->wallet_balance);
    }
}
