<?php

namespace Tests\Feature;

use App\CourtSearchService;
use App\Models\Branch;
use App\Models\Court;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\PayosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class BugV11RegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 22)->setTime(13, 22));
        config(['ai.search.provider' => 'local', 'services.payos' => ['client_id' => 'test-client', 'api_key' => 'test-key', 'checksum_key' => 'test-checksum']]);
        Http::preventStrayRequests();
    }

    private function court(): Court
    {
        return Court::factory()->create(['branch_id' => Branch::factory()->create(['ward' => 'Phường Tân Phú', 'opens_minute' => 360, 'closes_minute' => 1380, 'booking_days' => 30])->id]);
    }

    private function reply(array $data): array
    {
        return ['code' => '00', 'data' => $data, 'signature' => app(PayosService::class)->sign($data)];
    }

    private function fakeCheckout(): void
    {
        Http::fake(['*/v2/payment-requests' => fn ($request) => Http::response($this->reply(['orderCode' => $request['orderCode'], 'amount' => $request['amount'], 'currency' => 'VND', 'paymentLinkId' => 'wallet123', 'checkoutUrl' => 'https://pay.payos.vn/web/wallet123', 'qrCode' => 'BANK-WALLET-QR']))]);
    }

    public function test_counter_bank_topup_opens_qr_and_credits_only_after_verified_payment_once(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $branch->id]);
        $customer = User::factory()->create(['wallet_balance' => 0]);
        $this->fakeCheckout();
        $payload = ['request_key' => (string) Str::uuid(), 'branch_id' => $branch->id, 'amount' => 100000, 'method' => 'bank'];
        $id = $this->actingAs($manager)->postJson('/api/v1/manage/users/'.$customer->id.'/topup', $payload)->assertOk()->assertJsonPath('checkout_required', true)->assertJsonPath('data.qr_code', 'BANK-WALLET-QR')->json('data.id');
        $this->postJson('/api/v1/manage/users/'.$customer->id.'/topup', $payload)->assertOk()->assertJsonPath('data.id', $id);
        Http::assertSentCount(1);
        $this->assertSame(0, $customer->fresh()->wallet_balance);
        $this->assertDatabaseCount('wallet_entries', 0);
        $attempt = PaymentAttempt::findOrFail($id);
        Http::fake(['*/v2/payment-requests/*' => Http::response($this->reply(['id' => 'wallet123', 'orderCode' => (int) $attempt->order_id, 'amount' => 100000, 'amountPaid' => 100000, 'amountRemaining' => 0, 'status' => 'PAID']))]);
        $this->postJson('/api/v1/payments/payos/'.$id.'/sync')->assertOk()->assertJsonPath('data.status', 'paid');
        $this->postJson('/api/v1/payments/payos/'.$id.'/sync')->assertOk();
        $this->assertSame(100000, $customer->fresh()->wallet_balance);
        $this->assertDatabaseCount('wallet_entries', 1);
        $this->assertDatabaseHas('wallet_entries', ['branch_id' => $branch->id, 'actor_id' => $manager->id, 'user_id' => $customer->id, 'amount' => 100000]);
    }

    public function test_only_the_owner_or_correct_branch_can_resume_a_counter_wallet_checkout(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $branch->id]);
        $customer = User::factory()->create();
        $this->fakeCheckout();
        $payload = ['request_key' => (string) Str::uuid(), 'branch_id' => $branch->id, 'amount' => 250000, 'method' => 'bank'];
        $id = $this->actingAs($manager)->postJson('/api/v1/manage/users/'.$customer->id.'/topup', $payload)->assertOk()->json('data.id');
        $this->getJson('/api/v1/manage/users/'.$customer->id.'/wallet-payments?branch_id='.$branch->id)->assertOk()->assertJsonPath('data.0.id', $id);
        $this->postJson('/api/v1/payments/payos/'.$id.'/resume')->assertOk()->assertJsonPath('data.qr_code', 'BANK-WALLET-QR');
        $this->postJson('/api/v1/manage/users/'.$customer->id.'/topup', [...$payload, 'amount' => 200000])->assertConflict();
        $this->actingAs(User::factory()->create(['role' => 'manager', 'branch_id' => Branch::factory()->create()->id]))->postJson('/api/v1/payments/payos/'.$id.'/sync')->assertForbidden();
        $this->postJson('/api/v1/payments/payos/'.$id.'/resume')->assertForbidden();
        $this->getJson('/api/v1/manage/users/'.$customer->id.'/wallet-payments?branch_id='.$branch->id)->assertForbidden();
        $this->actingAs(User::factory()->create())->postJson('/api/v1/payments/payos/'.$id.'/sync')->assertForbidden();
        $this->actingAs($customer)->postJson('/api/v1/payments/payos/'.$id.'/resume')->assertOk();
    }

    public function test_unavailable_counter_bank_checkout_does_not_add_wallet_money(): void
    {
        $branch = Branch::factory()->create();
        $customer = User::factory()->create(['wallet_balance' => 0]);
        Http::fake(['*' => Http::response([], 503)]);
        $this->actingAs(User::factory()->create(['role' => 'manager', 'branch_id' => $branch->id]))->postJson('/api/v1/manage/users/'.$customer->id.'/topup', ['request_key' => (string) Str::uuid(), 'branch_id' => $branch->id, 'amount' => 100000, 'method' => 'bank'])->assertStatus(502);
        $this->assertSame(0, $customer->fresh()->wallet_balance);
        $this->assertDatabaseCount('wallet_entries', 0);
    }

    public function test_small_talk_mentioning_today_does_not_reuse_old_booking_times(): void
    {
        $this->court();
        $context = ['date' => '2026-11-22', 'start_time' => '09:00', 'end_time' => '10:00', 'district' => 'Phường Tân Phú', 'max_budget' => null, 'amenities' => []];
        $result = app(CourtSearchService::class)->search('Hôm nay tôi thất tình', null, null, $context);
        $this->assertTrue($result['needs_clarification']);
        $this->assertStringNotContainsString('đã qua', $result['message']);
        $this->assertSame([], $result['courts']);
    }

    public function test_a_wallet_request_cannot_switch_between_cash_and_bank(): void
    {
        $branch = Branch::factory()->create();
        $customer = User::factory()->create(['wallet_balance' => 0]);
        $this->actingAs(User::factory()->create(['role' => 'manager', 'branch_id' => $branch->id]));
        $this->fakeCheckout();
        $url = '/api/v1/manage/users/'.$customer->id.'/topup';
        $data = ['request_key' => (string) Str::uuid(), 'branch_id' => $branch->id, 'amount' => 100000, 'method' => 'bank'];
        $this->postJson($url, $data)->assertOk()->assertJsonPath('checkout_required', true);
        $this->postJson($url, [...$data, 'method' => 'cash'])->assertConflict();
        $this->assertSame(0, $customer->fresh()->wallet_balance);
        $cash = [...$data, 'request_key' => (string) Str::uuid(), 'method' => 'cash'];
        $this->postJson($url, $cash)->assertOk();
        $this->postJson($url, $cash)->assertOk();
        $this->postJson($url, [...$cash, 'method' => 'bank'])->assertConflict();
        $this->assertSame(100000, $customer->fresh()->wallet_balance);
        $this->assertDatabaseCount('wallet_entries', 1);
        Http::assertSentCount(1);
    }

    public function test_model_cannot_invent_a_location_or_budget_for_the_document_morning_request(): void
    {
        $court = $this->court();
        config(['ai.search.provider' => 'ollama']);
        Http::fake(['*/api/chat' => Http::response(['done' => true, 'message' => ['content' => json_encode(['date' => '2026-11-24', 'start_time' => '09:00', 'end_time' => '10:00', 'district' => 'Thành phố Hồ Chí Minh', 'max_budget' => 1, 'amenities' => [], 'clarification' => null])]])]);
        $result = app(CourtSearchService::class)->search('Tôi muốn đánh cầu vào ngày 24/11 9h sáng đến 10h sáng');
        $this->assertContains($court->id, array_column($result['courts'], 'court_id'));
        $this->assertNull($result['filters']['max_budget']);
        $this->assertNull($result['filters']['district']);
    }

    public function test_ai_chat_keeps_a_valid_follow_up_after_unrelated_small_talk(): void
    {
        $court = $this->court();
        $this->actingAs(User::factory()->create())->postJson('/api/v1/ai/chat', ['request_key' => (string) Str::uuid(), 'message' => 'Ngày 24/11 từ 9h sáng đến 10h sáng ở phường Tân Phú'])->assertCreated()->assertJsonPath('data.1.result.courts.0.court_id', $court->id);
        $this->postJson('/api/v1/ai/chat', ['request_key' => (string) Str::uuid(), 'message' => 'Hôm nay tôi thất tình'])->assertCreated()->assertJsonPath('data.1.result.needs_clarification', true);
        $this->postJson('/api/v1/ai/chat', ['request_key' => (string) Str::uuid(), 'message' => 'Vậy đổi sang ngày 25/11 nhé'])->assertCreated()->assertJsonPath('data.1.result.filters.date', '2026-11-25')->assertJsonPath('data.1.result.courts.0.court_id', $court->id);
    }

    public function test_model_cannot_reset_the_date_or_hours_when_only_changing_the_ward(): void
    {
        $court = $this->court();
        config(['ai.search.provider' => 'ollama']);
        Http::fake(['*/api/chat' => Http::response(['done' => true, 'message' => ['content' => json_encode(['date' => '2026-11-22', 'start_time' => '08:00', 'end_time' => '09:00', 'district' => 'Phường Tân Phú', 'max_budget' => null, 'amenities' => [], 'clarification' => null])]])]);
        $context = ['date' => '2026-11-24', 'start_time' => '09:00', 'end_time' => '10:00', 'district' => null, 'max_budget' => null, 'amenities' => []];
        $result = app(CourtSearchService::class)->search('Tôi chỉ muốn ở phường Tân Phú', null, null, $context);
        $this->assertFalse($result['needs_clarification']);
        $this->assertSame('2026-11-24', $result['filters']['date']);
        $this->assertSame('09:00', $result['filters']['start_time']);
        $this->assertSame('10:00', $result['filters']['end_time']);
        $this->assertContains($court->id, array_column($result['courts'], 'court_id'));
    }

    public function test_badge_uses_the_best_active_plan_without_exposing_membership_records(): void
    {
        $customer = User::factory()->create(['public_profile' => true]);
        $silver = MembershipPlan::factory()->create(['name' => 'Silver', 'discount_percent' => 5]);
        $vip = MembershipPlan::factory()->create(['name' => 'VIP', 'discount_percent' => 20]);
        Membership::factory()->create(['user_id' => $customer->id, 'membership_plan_id' => $vip->id]);
        Membership::factory()->create(['user_id' => $customer->id, 'membership_plan_id' => $silver->id]);
        $this->actingAs($customer)->getJson('/api/v1/hub')->assertOk()->assertJsonPath('data.user.membership_badge.tone', 'vip')->assertJsonMissingPath('data.user.badge_memberships');
        $this->getJson('/api/v1/community/players')->assertOk()->assertJsonPath('data.0.membership_badge.label', 'VIP')->assertDontSee('request_key');
        $customer->memberships()->update(['expires_at' => now()->subMinute()]);
        $this->getJson('/api/v1/community/players')->assertOk()->assertJsonPath('data.0.membership_badge', null);
    }

    public function test_future_memberships_do_not_display_a_vip_badge(): void
    {
        $customer = User::factory()->create(['public_profile' => true]);
        Membership::factory()->create(['user_id' => $customer->id, 'starts_at' => now()->addDay(), 'expires_at' => now()->addMonth(), 'membership_plan_id' => MembershipPlan::factory()->create(['name' => 'VIP'])->id]);
        $this->getJson('/api/v1/community/players')->assertOk()->assertJsonPath('data.0.membership_badge', null);
    }

    public function test_public_payment_return_does_not_use_a_stale_localhost_url(): void
    {
        config(['app.url' => 'http://localhost/badminton']);
        $customer = User::factory()->create();
        $attempt = PaymentAttempt::factory()->create(['user_id' => $customer->id, 'partner_code' => 'PAYOS', 'purpose' => 'wallet', 'booking_id' => null, 'order_id' => '1234567891234', 'extra_data' => base64_encode(json_encode(['payment_link_id' => 'wallet123']))]);
        Http::fake(['*' => Http::response($this->reply(['id' => 'wallet123', 'orderCode' => (int) $attempt->order_id, 'amount' => $attempt->amount, 'amountPaid' => 0, 'amountRemaining' => $attempt->amount, 'status' => 'PENDING']))]);
        $this->actingAs($customer)->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->withHeaders(['X-Forwarded-Host' => 'pdh2203.id.vn', 'X-Forwarded-Proto' => 'https', 'X-Forwarded-Prefix' => '/badminton'])->get('/payments/payos/return?orderCode='.$attempt->order_id)->assertRedirect('https://pdh2203.id.vn/badminton/#account/wallet');
    }
}
