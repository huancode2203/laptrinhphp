<?php

namespace Tests\Feature;

use App\AiGateway;
use App\CourtSearchService;
use App\LoyaltyService;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\CommunityPost;
use App\Models\Conversation;
use App\Models\Court;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\PaymentSettlement;
use App\PayosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BugV03RegressionTest extends TestCase
{
    use RefreshDatabase;

    private array $bankReply = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.search.provider' => 'local', 'ai.moderation.provider' => 'local', 'services.payos' => ['client_id' => 'client-test', 'api_key' => 'api-test', 'checksum_key' => 'checksum-test']]);
        Http::preventStrayRequests();
    }

    private function reply(array $data): array
    {
        return ['code' => '00', 'data' => $data, 'signature' => app(PayosService::class)->sign($data)];
    }

    private function checkout(User $manager, User $customer, MembershipPlan $plan): PaymentAttempt
    {
        Http::fake(['*/v2/payment-requests' => fn ($request) => Http::response($this->reply(['orderCode' => $request['orderCode'], 'amount' => $request['amount'], 'currency' => 'VND', 'paymentLinkId' => 'member123', 'checkoutUrl' => 'https://pay.payos.vn/web/member123', 'qrCode' => 'BANK-QR-MEMBERSHIP']))]);
        $payload = ['request_key' => (string) Str::uuid(), 'plan_id' => $plan->id, 'branch_id' => $manager->branch_id, 'method' => 'bank'];
        $response = $this->actingAs($manager)->postJson('/api/v1/manage/users/'.$customer->id.'/membership', $payload)->assertOk()->assertJsonPath('checkout_required', true)->assertJsonPath('data.qr_code', 'BANK-QR-MEMBERSHIP');
        $this->postJson('/api/v1/manage/users/'.$customer->id.'/membership', $payload)->assertOk()->assertJsonPath('data.id', $response->json('data.id'));
        Http::assertSentCount(1);

        return PaymentAttempt::findOrFail($response->json('data.id'));
    }

    private function bankStatus(PaymentAttempt $attempt, string $status, int $paid): void
    {
        $this->bankReply = ['id' => 'member123', 'orderCode' => (int) $attempt->order_id, 'amount' => $attempt->amount, 'amountPaid' => $paid, 'amountRemaining' => max(0, $attempt->amount - $paid), 'status' => $status];
        Http::fake(['*/v2/payment-requests/*' => fn () => Http::response($this->reply($this->bankReply))]);
    }

    public function test_wallet_cancel_returns_to_public_domain_and_does_not_credit_money(): void
    {
        config(['app.url' => 'https://pdh2203.id.vn/badminton']);
        $customer = User::factory()->create(['wallet_balance' => 0]);
        $attempt = PaymentAttempt::factory()->create(['partner_code' => 'PAYOS', 'order_id' => '1234567890123', 'user_id' => $customer->id, 'booking_id' => null, 'purpose' => 'wallet', 'amount' => 100000, 'extra_data' => base64_encode(json_encode(['payment_link_id' => 'member123']))]);
        $this->bankStatus($attempt, 'CANCELLED', 0);
        $this->actingAs($customer)->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->withHeaders(['X-Forwarded-Host' => 'pdh2203.id.vn', 'X-Forwarded-Proto' => 'https', 'X-Forwarded-Prefix' => '/badminton'])->get('http://localhost/payments/payos/return?orderCode='.$attempt->order_id.'&cancel=true&status=CANCELLED')->assertStatus(302)->assertHeader('Location', '/badminton/#account/wallet');
        $this->assertSame('failed', $attempt->fresh()->status);
        $this->assertSame(0, $customer->fresh()->wallet_balance);
        $this->assertDatabaseCount('wallet_entries', 0);
    }

    public function test_an_expired_login_returns_to_the_public_login_page(): void
    {
        config(['app.url' => 'https://pdh2203.id.vn/badminton']);
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->withHeaders(['X-Forwarded-Host' => 'pdh2203.id.vn', 'X-Forwarded-Proto' => 'https', 'X-Forwarded-Prefix' => '/badminton'])->get('http://localhost/payments/payos/return?orderCode=123')->assertStatus(302)->assertHeader('Location', '/badminton/#auth');
    }

    public function test_insufficient_wallet_balance_never_activates_or_debits_a_membership(): void
    {
        $customer = User::factory()->create(['wallet_balance' => 1000]);
        $plan = MembershipPlan::factory()->create(['price' => 199000]);
        $this->actingAs($customer)->postJson('/api/v1/memberships/'.$plan->id.'/buy', ['request_key' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertSame(1000, $customer->fresh()->wallet_balance);
        $this->assertDatabaseCount('memberships', 0);
        $this->assertDatabaseCount('commerce_payments', 0);
        $this->assertDatabaseCount('wallet_entries', 0);
    }

    public function test_free_loyalty_tiers_are_awarded_by_points_and_cannot_be_purchased_repeatedly(): void
    {
        $customer = User::factory()->create(['earned_points' => 9, 'loyalty_points' => 9]);
        $tier = MembershipPlan::factory()->create(['name' => 'Hạng Đồng', 'price' => 0, 'points_threshold' => 10]);
        $this->actingAs($customer)->postJson('/api/v1/memberships/'.$tier->id.'/buy', ['request_key' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertDatabaseCount('memberships', 0);
        app(LoyaltyService::class)->award($customer, 10000, (string) Str::uuid(), 'Đủ điểm lên hạng');
        $this->assertDatabaseHas('memberships', ['user_id' => $customer->id, 'membership_plan_id' => $tier->id, 'amount' => 0]);
        $this->postJson('/api/v1/memberships/'.$tier->id.'/buy', ['request_key' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertDatabaseCount('memberships', 1);
    }

    public function test_direct_chat_title_is_the_other_person_for_each_viewer(): void
    {
        $first = User::factory()->create(['name' => 'Nguyễn Minh Anh', 'public_profile' => true]);
        $second = User::factory()->create(['name' => 'Trần Hoàng Phúc', 'public_profile' => true]);
        $this->actingAs($first)->postJson('/api/v1/conversations/direct', ['user_id' => $second->id])->assertOk()->assertJsonPath('data.title', $second->name);
        $this->getJson('/api/v1/conversations')->assertOk()->assertJsonPath('data.0.title', $second->name);
        $this->actingAs($second)->getJson('/api/v1/conversations')->assertOk()->assertJsonPath('data.0.title', $first->name);
        $this->assertDatabaseCount('conversations', 1);
    }

    public function test_chat_accepts_owned_images_without_text_but_rejects_empty_or_foreign_attachments(): void
    {
        Storage::fake('local');
        $customer = User::factory()->create();
        $peer = User::factory()->create();
        $room = Conversation::factory()->create();
        $room->members()->attach([$customer->id, $peer->id]);
        $id = $this->actingAs($customer)->postJson('/api/v1/media', ['file' => UploadedFile::fake()->image('court.png')])->assertCreated()->json('data.id');
        $payload = ['request_key' => (string) Str::uuid(), 'content' => '', 'attachments' => [$id]];
        $this->postJson('/api/v1/conversations/'.$room->id.'/messages', $payload)->assertCreated();
        $this->postJson('/api/v1/conversations/'.$room->id.'/messages', $payload)->assertCreated();
        $this->assertDatabaseCount('chat_messages', 1);
        $this->postJson('/api/v1/conversations/'.$room->id.'/messages', ['request_key' => (string) Str::uuid(), 'content' => '   ', 'attachments' => []])->assertUnprocessable();
        $this->actingAs($peer)->postJson('/api/v1/conversations/'.$room->id.'/messages', ['request_key' => (string) Str::uuid(), 'attachments' => [$id]])->assertUnprocessable();
        $this->assertDatabaseCount('chat_messages', 1);
    }

    public function test_booking_support_opens_a_reusable_room_with_the_correct_branch_staff(): void
    {
        $booking = Booking::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $booking->court->branch_id]);
        $other = User::factory()->create(['role' => 'manager', 'branch_id' => Branch::factory()->create()->id]);
        $url = '/api/v1/bookings/'.$booking->id.'/conversation';
        $room = $this->actingAs($booking->user)->postJson($url)->assertOk()->json('data.id');
        $this->postJson($url)->assertOk()->assertJsonPath('data.id', $room);
        $this->assertDatabaseHas('conversation_members', ['conversation_id' => $room, 'user_id' => $manager->id]);
        $this->assertDatabaseMissing('conversation_members', ['conversation_id' => $room, 'user_id' => $other->id]);
        $this->assertDatabaseCount('conversations', 1);
        $this->actingAs($manager)->getJson('/api/v1/conversations?support_branch_id='.$manager->branch_id)->assertOk()->assertJsonPath('data.0.id', $room);
        $this->postJson('/api/v1/conversations/'.$room.'/messages', ['request_key' => (string) Str::uuid(), 'content' => 'Chi nhánh đã nhận yêu cầu hỗ trợ.'])->assertCreated();
        $this->actingAs($booking->user)->getJson('/api/v1/conversations/'.$room.'/messages')->assertOk()->assertJsonPath('data.0.content', 'Chi nhánh đã nhận yêu cầu hỗ trợ.');
        $this->actingAs($other)->getJson('/api/v1/conversations?support_branch_id='.$manager->branch_id)->assertForbidden();
    }

    public function test_admin_can_remove_a_post_via_the_actual_status_route_and_notify_the_author(): void
    {
        $post = CommunityPost::factory()->create(['moderation_status' => 'approved']);
        $url = '/api/v1/admin/posts/'.$post->id.'/status';
        $this->actingAs($post->user)->patchJson($url, ['status' => 'hidden', 'reason' => 'Bài đã hết thời hạn giao lưu.'])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->patchJson($url, ['status' => 'hidden', 'reason' => 'Bài đã hết thời hạn giao lưu.'])->assertOk()->assertJsonPath('data.status', 'hidden');
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $post->user_id]);
        $this->getJson('/api/v1/admin/posts?state=hidden')->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_branch_assignment_updates_primary_and_additional_branches(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => Branch::factory()->create()->id]);
        $branches = Branch::factory()->count(2)->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->putJson('/api/v1/admin/users/'.$manager->id.'/branches', ['branch_ids' => $branches->modelKeys()])->assertOk();
        $this->assertSame($branches[0]->id, $manager->fresh()->branch_id);
        $this->assertDatabaseCount('user_branches', 2);
        $this->actingAs($manager)->putJson('/api/v1/admin/users/'.$manager->id.'/branches', ['branch_ids' => []])->assertForbidden();
    }

    public function test_explicit_morning_hours_override_incorrect_model_interpretation(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 17)->setTime(8, 0));
        $court = Court::factory()->create();
        config(['ai.search.provider' => 'ollama']);
        $this->mock(AiGateway::class)->shouldReceive('structured')->once()->andReturn(['date' => '2026-11-20', 'start_time' => '21:00', 'end_time' => '22:00', 'district' => null, 'max_budget' => null, 'amenities' => [], 'clarification' => null]);
        $result = app(CourtSearchService::class)->search('ngày 20/11 9h sáng đến 10h sáng');
        $this->assertSame('09:00', $result['filters']['start_time']);
        $this->assertSame('10:00', $result['filters']['end_time']);
        $this->assertSame($court->id, $result['courts'][0]['court_id']);
    }

    public function test_ai_feedback_does_not_repeat_the_previous_empty_search(): void
    {
        $customer = User::factory()->create();
        Court::factory()->create();
        $this->actingAs($customer)->postJson('/api/v1/ai/chat', ['request_key' => (string) Str::uuid(), 'message' => 'Ngày mai từ 9h sáng đến 10h sáng'])->assertCreated();
        $this->postJson('/api/v1/ai/chat', ['request_key' => (string) Str::uuid(), 'message' => 'dỏm'])->assertCreated()->assertJsonPath('data.1.result.needs_clarification', true)->assertJsonPath('data.1.result.mode', 'conversation')->assertJsonCount(0, 'data.1.result.courts');
    }

    public function test_counter_bank_membership_waits_for_verified_payment_and_activates_once(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => Branch::factory()->create()->id]);
        $customer = User::factory()->create(['wallet_balance' => 0]);
        $plan = MembershipPlan::factory()->create(['duration_days' => 30]);
        $attempt = $this->checkout($manager, $customer, $plan);
        $this->assertDatabaseCount('memberships', 0);
        $this->assertDatabaseCount('commerce_payments', 0);
        $this->getJson('/api/v1/manage/users/'.$customer->id.'/membership-payments?branch_id='.$manager->branch_id)->assertOk()->assertJsonPath('data.0.id', $attempt->id);
        $plan->update(['price' => 500000, 'duration_days' => 365, 'active' => false]);
        $this->bankStatus($attempt, 'PAID', $attempt->amount);
        $url = '/api/v1/payments/payos/'.$attempt->id.'/sync';
        $this->postJson($url)->assertOk()->assertJsonPath('data.status', 'paid');
        $this->postJson($url)->assertOk();
        $member = Membership::sole();
        $this->assertEquals(199000, $member->amount);
        $this->assertEquals(30, now()->startOfDay()->diffInDays($member->expires_at->startOfDay()));
        $this->assertDatabaseHas('commerce_payments', ['branch_id' => $manager->branch_id, 'actor_id' => $manager->id, 'method' => 'payos', 'amount' => 199000]);
        $this->assertDatabaseCount('commerce_payments', 1);
        $this->assertSame(0, $customer->fresh()->wallet_balance);
        $this->bankStatus($attempt, 'PAID', $attempt->amount + 1000);
        $this->postJson($url)->assertOk();
        $this->postJson($url)->assertOk();
        $this->assertDatabaseCount('memberships', 1);
        $this->assertSame(1000, $customer->fresh()->wallet_balance);
        $this->assertDatabaseCount('wallet_entries', 1);
    }

    public function test_cancelled_or_underpaid_membership_is_not_activated_and_other_branches_cannot_sync_it(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => Branch::factory()->create()->id]);
        $customer = User::factory()->create();
        $attempt = $this->checkout($manager, $customer, MembershipPlan::factory()->create());
        $this->bankStatus($attempt, 'PENDING', 1000);
        $this->postJson('/api/v1/payments/payos/'.$attempt->id.'/sync')->assertOk()->assertJsonPath('data.status', 'pending');
        $this->assertDatabaseCount('memberships', 0);
        $other = User::factory()->create(['role' => 'manager', 'branch_id' => Branch::factory()->create()->id]);
        $this->actingAs($other)->postJson('/api/v1/payments/payos/'.$attempt->id.'/sync')->assertForbidden();
        $this->assertDatabaseCount('commerce_payments', 0);
        $this->assertDatabaseCount('wallet_entries', 0);
    }

    public function test_cancelled_counter_membership_and_fake_paid_return_do_not_activate_it(): void
    {
        config(['app.url' => 'https://pdh2203.id.vn/badminton']);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => Branch::factory()->create()->id]);
        $customer = User::factory()->create();
        $attempt = $this->checkout($manager, $customer, MembershipPlan::factory()->create());
        $this->bankStatus($attempt, 'CANCELLED', 0);
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->withHeaders(['X-Forwarded-Host' => 'pdh2203.id.vn', 'X-Forwarded-Proto' => 'https', 'X-Forwarded-Prefix' => '/badminton'])->get('http://localhost/payments/payos/return?orderCode='.$attempt->order_id.'&status=PAID')->assertStatus(302)->assertHeader('Location', '/badminton/#manage/customers');
        $this->assertDatabaseCount('memberships', 0);
        $this->assertDatabaseCount('commerce_payments', 0);
        $this->assertSame('failed', $attempt->fresh()->status);
    }

    public function test_manually_reconciled_partial_membership_money_stays_in_wallet_without_later_double_activation(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => Branch::factory()->create()->id]);
        $customer = User::factory()->create(['wallet_balance' => 0]);
        $attempt = $this->checkout($manager, $customer, MembershipPlan::factory()->create());
        app(PaymentSettlement::class)->receive($attempt, 0, 'PAYOS:member123', 1000, true);
        $this->assertSame(1000, $customer->fresh()->wallet_balance);
        app(PaymentSettlement::class)->receive($attempt, 0, 'PAYOS:member123', $attempt->amount);
        $this->assertSame($attempt->amount, $customer->fresh()->wallet_balance);
        $this->assertDatabaseCount('memberships', 0);
        $this->assertSame('credited', $attempt->fresh()->status);
    }

    public function test_money_arriving_after_cancelled_membership_is_credited_once_instead_of_activating_the_cancelled_purchase(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => Branch::factory()->create()->id]);
        $customer = User::factory()->create(['wallet_balance' => 0]);
        $attempt = $this->checkout($manager, $customer, MembershipPlan::factory()->create());
        $this->bankStatus($attempt, 'CANCELLED', 0);
        $url = '/api/v1/payments/payos/'.$attempt->id.'/sync';
        $this->postJson($url)->assertOk()->assertJsonPath('data.status', 'failed');
        $this->bankStatus($attempt, 'PAID', $attempt->amount);
        $this->postJson($url)->assertOk()->assertJsonPath('data.status', 'credited');
        $this->postJson($url)->assertOk();
        $this->assertDatabaseCount('memberships', 0);
        $this->assertDatabaseCount('wallet_entries', 1);
        $this->assertSame($attempt->amount, $customer->fresh()->wallet_balance);
    }

    public function test_incomplete_membership_checkout_can_be_resumed_without_creating_a_second_order(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => Branch::factory()->create()->id]);
        $customer = User::factory()->create();
        $plan = MembershipPlan::factory()->create();
        $creates = 0;
        Http::fake(function ($request) use (&$creates) {
            if ($request->method() === 'GET') {
                return Http::response([], 404);
            }
            if (++$creates === 1) {
                return Http::response([], 503);
            }

            return Http::response($this->reply(['orderCode' => $request['orderCode'], 'amount' => $request['amount'], 'currency' => 'VND', 'paymentLinkId' => 'member123', 'checkoutUrl' => 'https://pay.payos.vn/web/member123', 'qrCode' => 'BANK-QR-MEMBERSHIP']));
        });
        $this->actingAs($manager)->postJson('/api/v1/manage/users/'.$customer->id.'/membership', ['request_key' => (string) Str::uuid(), 'plan_id' => $plan->id, 'branch_id' => $manager->branch_id, 'method' => 'bank'])->assertStatus(502);
        $attempt = PaymentAttempt::sole();
        $this->assertNull($attempt->pay_url);
        $this->postJson('/api/v1/payments/payos/'.$attempt->id.'/resume-membership')->assertOk()->assertJsonPath('data.qr_code', 'BANK-QR-MEMBERSHIP');
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertDatabaseCount('memberships', 0);
        $this->assertSame(2, $creates);
    }

    public function test_membership_partial_payment_reconciliation_is_visible_only_in_its_branch(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => Branch::factory()->create()->id]);
        $customer = User::factory()->create(['wallet_balance' => 0]);
        $attempt = $this->checkout($manager, $customer, MembershipPlan::factory()->create());
        $this->bankStatus($attempt, 'EXPIRED', 1000);
        $this->postJson('/api/v1/payments/payos/'.$attempt->id.'/sync')->assertConflict();
        $url = '/api/v1/manage/operations/reconciliations?branch_id='.$manager->branch_id;
        $id = $this->getJson($url)->assertOk()->assertJsonCount(1, 'data.data')->json('data.data.0.id');
        $other = User::factory()->create(['role' => 'manager', 'branch_id' => Branch::factory()->create()->id]);
        $this->actingAs($other)->getJson('/api/v1/manage/operations/reconciliations?branch_id='.$other->branch_id)->assertOk()->assertJsonCount(0, 'data.data');
        $this->postJson('/api/v1/manage/operations/reconcile', ['id' => $id, 'notes' => 'Đã kiểm tra khoản chuyển thiếu.'])->assertForbidden();
        $this->actingAs($manager)->postJson('/api/v1/manage/operations/reconcile', ['id' => $id, 'notes' => 'Đã đối chiếu khoản chuyển thiếu và giữ số tiền trong ví khách.'])->assertOk();
        $this->assertSame(1000, $customer->fresh()->wallet_balance);
        $this->assertDatabaseCount('memberships', 0);
    }

    public function test_cash_membership_still_works_and_customer_cannot_create_a_staff_payment(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => Branch::factory()->create()->id]);
        $customer = User::factory()->create();
        $plan = MembershipPlan::factory()->create();
        $payload = ['request_key' => (string) Str::uuid(), 'plan_id' => $plan->id, 'branch_id' => $manager->branch_id, 'method' => 'cash'];
        $this->actingAs($manager)->postJson('/api/v1/manage/users/'.$customer->id.'/membership', $payload)->assertOk();
        $this->assertDatabaseHas('memberships', ['user_id' => $customer->id, 'method' => 'cash']);
        $this->actingAs($customer)->postJson('/api/v1/manage/users/'.$customer->id.'/membership', $payload)->assertForbidden();
        $this->postJson('/api/v1/payments/payos', ['request_key' => (string) Str::uuid(), 'purpose' => 'membership', 'amount' => 1000])->assertUnprocessable();
        $this->assertDatabaseCount('memberships', 1);
    }
}
