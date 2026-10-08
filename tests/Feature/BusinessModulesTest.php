<?php

namespace Tests\Feature;

use App\Models\AuthCode;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\CommunityPost;
use App\Models\Conversation;
use App\Models\Coupon;
use App\Models\Court;
use App\Models\CourtBlock;
use App\Models\GameEvent;
use App\Models\MembershipPlan;
use App\Models\Product;
use App\Models\TournamentMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessModulesTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $manager;

    private Court $court;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 12)->setTime(8, 0));
        $this->customer = User::factory()->create();
        $this->court = Court::factory()->create();
        $this->manager = User::factory()->create(['role' => 'manager', 'branch_id' => $this->court->branch_id]);
    }

    private function payload(array $extra = []): array
    {
        return array_replace(['request_key' => (string) Str::uuid(), 'court_id' => $this->court->id, 'starts_at' => '2026-10-13 10:00', 'ends_at' => '2026-10-13 11:00'], $extra);
    }

    private function book(): Booking
    {
        $r = $this->actingAs($this->customer)->postJson('/api/v1/bookings', $this->payload())->assertCreated();

        return Booking::findOrFail($r->json('data.id'));
    }

    public function test_bootstrap_and_management_data_contain_real_counts_and_scoped_courts(): void
    {
        Court::factory()->create();
        $this->getJson('/api/v1/hub')->assertOk()->assertJsonPath('data.stats.bookings', 0)->assertJsonPath('data.momo_enabled', false);
        $r = $this->actingAs($this->manager)->getJson('/api/v1/manage/hub?date=2026-10-13')->assertOk();
        $this->assertCount(1, $r->json('data.courts'));
        $staff = User::factory()->create(['role' => 'staff', 'branch_id' => $this->court->branch_id]);
        $this->actingAs($staff)->getJson('/api/v1/manage/courts')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/v1/manage/courts', [])->assertForbidden();
    }

    public function test_disabled_accounts_are_logged_out_and_cannot_login(): void
    {
        $this->customer->forceFill(['active' => false])->save();
        $this->actingAs($this->customer)->getJson('/api/v1/account')->assertForbidden();
        $this->postJson('/api/v1/login', ['email' => $this->customer->email, 'password' => 'password'])->assertUnprocessable();
    }

    public function test_holds_block_other_customers_expire_and_preserve_public_privacy(): void
    {
        $h = $this->actingAs($this->customer)->postJson('/api/v1/holds', $this->payload())->assertOk();
        $this->postJson('/api/v1/holds', $this->payload())->assertUnprocessable();
        $other = User::factory()->create();
        $this->actingAs($other)->postJson('/api/v1/bookings', $this->payload())->assertUnprocessable();
        $this->getJson('/api/v1/branches/'.$this->court->branch_id.'/schedule?date=2026-10-13')->assertOk()->assertDontSee($this->customer->email)->assertJsonPath('data.courts.0.slots.14.state', 'held');
        $this->travel(6)->minutes();
        $this->postJson('/api/v1/bookings', $this->payload())->assertCreated();
    }

    public function test_wallet_topup_and_booking_payment_retry_only_charge_once_and_cancellation_refunds(): void
    {
        $b = $this->book();
        $d = ['request_key' => (string) Str::uuid(), 'branch_id' => $this->court->branch_id, 'amount' => 200000, 'method' => 'cash'];
        $this->actingAs($this->manager)->postJson('/api/v1/manage/users/'.$this->customer->id.'/topup', $d)->assertOk();
        $this->postJson('/api/v1/manage/users/'.$this->customer->id.'/topup', $d)->assertOk();
        $pay = ['request_key' => (string) Str::uuid()];
        $this->actingAs($this->customer)->postJson('/api/v1/bookings/'.$b->id.'/wallet-payment', $pay)->assertOk();
        $this->postJson('/api/v1/bookings/'.$b->id.'/wallet-payment', $pay)->assertOk();
        $this->assertEquals(100000, $this->customer->refresh()->wallet_balance);
        $this->patchJson('/api/v1/bookings/'.$b->id.'/status', ['status' => 'cancelled'])->assertOk();
        $this->patchJson('/api/v1/bookings/'.$b->id.'/status', ['status' => 'cancelled'])->assertOk();
        $this->assertEquals(200000, $this->customer->refresh()->wallet_balance);
        $this->assertDatabaseCount('wallet_entries', 3);
    }

    public function test_wallet_insufficient_funds_roll_back_and_cross_branch_staff_cannot_topup(): void
    {
        $b = $this->book();
        $this->postJson('/api/v1/bookings/'.$b->id.'/wallet-payment', ['request_key' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertDatabaseCount('payments', 0);
        $this->actingAs($this->manager)->postJson('/api/v1/manage/users/'.$this->customer->id.'/topup', ['request_key' => (string) Str::uuid(), 'branch_id' => Branch::factory()->create()->id, 'amount' => 1000, 'method' => 'cash'])->assertForbidden();
    }

    public function test_membership_and_coupon_discounts_are_server_calculated_and_once_per_customer(): void
    {
        $plan = MembershipPlan::factory()->create(['price' => 199000, 'discount_percent' => 10]);
        $this->customer->forceFill(['wallet_balance' => 500000])->save();
        $d = ['request_key' => (string) Str::uuid()];
        $this->actingAs($this->customer)->postJson('/api/v1/memberships/'.$plan->id.'/buy', $d)->assertOk();
        $this->postJson('/api/v1/memberships/'.$plan->id.'/buy', $d)->assertOk();
        $this->assertEquals(301000, $this->customer->refresh()->wallet_balance);
        $coupon = Coupon::factory()->create(['code' => 'WELCOME', 'value' => 10]);
        $this->postJson('/api/v1/bookings', $this->payload(['coupon_code' => 'WELCOME', 'discount_amount' => 100000]))->assertCreated()->assertJsonPath('data.total_amount', 81000);
        $this->postJson('/api/v1/bookings', $this->payload(['starts_at' => '2026-10-13 12:00', 'ends_at' => '2026-10-13 13:00', 'coupon_code' => 'WELCOME']))->assertUnprocessable();
    }

    public function test_recurring_bookings_roll_back_if_one_week_conflicts_and_retry_does_not_duplicate(): void
    {
        $this->actingAs($this->customer)->postJson('/api/v1/bookings', $this->payload(['starts_at' => '2026-10-20 10:00', 'ends_at' => '2026-10-20 11:00']))->assertCreated();
        $p = $this->payload(['occurrences' => 3]);
        $this->postJson('/api/v1/bookings/series', $p)->assertUnprocessable();
        $this->assertDatabaseCount('bookings', 1);
        $p = $this->payload(['occurrences' => 3, 'starts_at' => '2026-10-13 12:00', 'ends_at' => '2026-10-13 13:00']);
        $this->postJson('/api/v1/bookings/series', $p)->assertCreated()->assertJsonPath('data.0.total_amount', 85000);
        $this->postJson('/api/v1/bookings/series', $p)->assertCreated();
        $this->assertDatabaseCount('bookings', 4);
        $this->postJson('/api/v1/bookings/series', array_replace($p, ['occurrences' => 4]))->assertConflict();
    }

    public function test_pos_snapshots_prices_decrements_inventory_once_and_rejects_insufficient_stock(): void
    {
        $p = Product::factory()->create(['branch_id' => $this->court->branch_id, 'stock' => 3, 'price' => 10000]);
        $d = ['request_key' => (string) Str::uuid(), 'branch_id' => $this->court->branch_id, 'method' => 'cash', 'total_amount' => 1, 'items' => [['product_id' => $p->id, 'quantity' => 2]]];
        $r = $this->actingAs($this->manager)->postJson('/api/v1/manage/sales', $d)->assertCreated()->assertJsonPath('data.total_amount', 20000);
        $this->postJson('/api/v1/manage/sales', $d)->assertCreated();
        $this->assertEquals(1, $p->refresh()->stock);
        $d['request_key'] = (string) Str::uuid();
        $this->postJson('/api/v1/manage/sales', $d)->assertUnprocessable();
        $this->assertDatabaseCount('sales', 1);
        $this->getJson('/api/v1/manage/reports?from=2026-10-12&to=2026-10-12')->assertOk()->assertJsonPath('data.net_revenue', 20000);
    }

    public function test_inventory_adjustment_has_idempotency_and_cannot_go_negative(): void
    {
        $p = Product::factory()->create(['branch_id' => $this->court->branch_id, 'stock' => 3]);
        $d = ['request_key' => (string) Str::uuid(), 'delta' => 2, 'reason' => 'Nhập hàng'];
        $this->actingAs($this->manager)->postJson('/api/v1/manage/products/'.$p->id.'/stock', $d)->assertOk();
        $this->postJson('/api/v1/manage/products/'.$p->id.'/stock', $d)->assertOk();
        $this->assertEquals(5, $p->refresh()->stock);
        $d['request_key'] = (string) Str::uuid();
        $d['delta'] = -6;
        $this->postJson('/api/v1/manage/products/'.$p->id.'/stock', $d)->assertUnprocessable();
        $this->assertEquals(5, $p->refresh()->stock);
    }

    public function test_community_host_approval_respects_capacity_and_private_chat_membership(): void
    {
        $post = CommunityPost::factory()->create(['user_id' => $this->manager->id, 'branch_id' => $this->court->branch_id, 'capacity' => 1]);
        $join = $this->actingAs($this->customer)->postJson('/api/v1/community/posts/'.$post->id.'/join')->assertOk()->json('data.id');
        $this->patchJson('/api/v1/community/posts/'.$post->id.'/joins/'.$join, ['status' => 'accepted'])->assertForbidden();
        $this->actingAs($this->manager)->patchJson('/api/v1/community/posts/'.$post->id.'/joins/'.$join, ['status' => 'accepted'])->assertOk();
        $conv = Conversation::where('community_post_id', $post->id)->firstOrFail();
        $this->actingAs($this->customer)->postJson('/api/v1/conversations/'.$conv->id.'/messages', ['request_key' => (string) Str::uuid(), 'content' => 'Xin chào'])->assertCreated();
        $this->getJson('/api/v1/conversations/'.$conv->id.'/messages')->assertOk()->assertJsonCount(1, 'data');
        $other = User::factory()->create();
        $this->actingAs($other)->getJson('/api/v1/conversations/'.$conv->id.'/messages')->assertForbidden();
        $this->postJson('/api/v1/community/posts/'.$post->id.'/join')->assertUnprocessable();
    }

    public function test_chat_retry_is_idempotent_and_hidden_posts_and_reports_are_moderated(): void
    {
        $post = CommunityPost::factory()->create();
        $peer = User::factory()->create(['public_profile' => true]);
        $c = $this->actingAs($this->customer)->postJson('/api/v1/conversations/direct', ['user_id' => $peer->id])->assertOk()->json('data.id');
        $d = ['request_key' => (string) Str::uuid(), 'content' => 'Hello'];
        $this->postJson('/api/v1/conversations/'.$c.'/messages', $d)->assertCreated();
        $this->postJson('/api/v1/conversations/'.$c.'/messages', $d)->assertCreated();
        $this->assertDatabaseCount('chat_messages', 1);
        $this->postJson('/api/v1/community/posts/'.$post->id.'/report', ['reason' => 'Nội dung cần xem xét'])->assertCreated();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->getJson('/api/v1/admin/hub')->assertOk()->assertJsonCount(1, 'data.reports');
        $this->patchJson('/api/v1/admin/posts/'.$post->id.'/status', ['status' => 'hidden'])->assertOk();
        $this->actingAs($this->customer)->getJson('/api/v1/community/posts/'.$post->id)->assertNotFound();
        $this->assertDatabaseHas('content_reports', ['status' => 'resolved']);
    }

    public function test_event_blocks_courts_and_capacity_and_wallet_cancellation_refund(): void
    {
        $event = GameEvent::factory()->create(['branch_id' => $this->court->branch_id, 'capacity' => 2, 'fee' => 10000]);
        CourtBlock::factory()->create(['court_id' => $this->court->id, 'game_event_id' => $event->id, 'starts_at' => '2026-10-13 10:00', 'ends_at' => '2026-10-13 12:00']);
        $this->actingAs($this->customer)->postJson('/api/v1/bookings', $this->payload())->assertUnprocessable();
        $this->customer->forceFill(['wallet_balance' => 20000])->save();
        $this->postJson('/api/v1/events/'.$event->id.'/register', ['method' => 'wallet'])->assertOk();
        $this->postJson('/api/v1/events/'.$event->id.'/register', ['method' => 'wallet'])->assertOk();
        $this->assertEquals(10000, $this->customer->refresh()->wallet_balance);
        $other = User::factory()->create();
        $this->actingAs($other)->postJson('/api/v1/events/'.$event->id.'/register', ['method' => 'cash'])->assertOk();
        $third = User::factory()->create();
        $this->actingAs($third)->postJson('/api/v1/events/'.$event->id.'/register', ['method' => 'cash'])->assertUnprocessable();
        $this->actingAs($this->customer)->deleteJson('/api/v1/events/'.$event->id.'/register')->assertOk();
        $this->assertEquals(20000, $this->customer->refresh()->wallet_balance);
    }

    public function test_event_create_rejects_booked_courts_and_draw_advances_byes_and_score_winners(): void
    {
        $this->book();
        $d = ['branch_id' => $this->court->branch_id, 'title' => 'Mini Cup', 'type' => 'league', 'description' => 'Thi đấu', 'format' => 'Loại trực tiếp đơn', 'skill_level' => 'Mọi trình độ', 'starts_at' => '2026-10-13 10:00', 'ends_at' => '2026-10-13 12:00', 'capacity' => 4, 'fee' => 0, 'court_ids' => [$this->court->id]];
        $this->actingAs($this->manager)->postJson('/api/v1/manage/events', $d)->assertUnprocessable();
        $d['starts_at'] = '2026-10-13 12:00';
        $d['ends_at'] = '2026-10-13 14:00';
        $id = $this->postJson('/api/v1/manage/events', $d)->assertCreated()->json('data.id');
        foreach (User::factory()->count(3)->create() as $u) {
            $this->actingAs($u)->postJson('/api/v1/events/'.$id.'/register', ['method' => 'cash'])->assertOk();
        }
        $this->actingAs($this->manager)->postJson('/api/v1/manage/events/'.$id.'/draw')->assertOk();
        $match = TournamentMatch::where('game_event_id', $id)->where('round', 1)->whereNotNull('player_a')->whereNotNull('player_b')->firstOrFail();
        $this->patchJson('/api/v1/manage/matches/'.$match->id.'/score', ['score_a' => 21, 'score_b' => 10])->assertOk();
        $final = TournamentMatch::where('game_event_id', $id)->where('round', 2)->firstOrFail();
        $this->assertNotNull($final->player_a);
        $this->assertNotNull($final->player_b);
    }

    public function test_recovery_codes_limit_guesses_and_verified_tokens_are_single_use(): void
    {
        Mail::fake();
        $this->postJson('/api/v1/recovery/send', ['email' => $this->customer->email])->assertOk();
        Mail::assertSentCount(1);
        $code = AuthCode::where('user_id', $this->customer->id)->firstOrFail();
        $code->update(['code_hash' => Hash::make('123456')]);
        $this->postJson('/api/v1/recovery/verify', ['email' => $this->customer->email, 'code' => '000000'])->assertUnprocessable();
        $token = $this->postJson('/api/v1/recovery/verify', ['email' => $this->customer->email, 'code' => '123456'])->assertOk()->json('data.reset_token');
        $this->assertNotNull($this->customer->refresh()->email_verified_at);
        $d = ['email' => $this->customer->email, 'reset_token' => $token, 'password' => 'Changed@12345', 'password_confirmation' => 'Changed@12345'];
        $this->postJson('/api/v1/recovery/reset', $d)->assertOk();
        $this->assertTrue(Hash::check('Changed@12345', $this->customer->refresh()->password));
        $this->postJson('/api/v1/recovery/reset', $d)->assertUnprocessable();
    }

    public function test_expired_otp_and_five_wrong_attempts_cannot_be_used(): void
    {
        $c = AuthCode::factory()->create(['user_id' => $this->customer->id, 'email' => $this->customer->email, 'attempts' => 5]);
        $this->postJson('/api/v1/recovery/verify', ['email' => $this->customer->email, 'code' => '123456'])->assertUnprocessable();
        $c->update(['attempts' => 0, 'expires_at' => now()->subMinute()]);
        $this->postJson('/api/v1/recovery/verify', ['email' => $this->customer->email, 'code' => '123456'])->assertUnprocessable();
    }

    public function test_wallet_booking_and_series_are_atomic_when_balance_is_insufficient(): void
    {
        $this->actingAs($this->customer)->postJson('/api/v1/bookings', $this->payload(['pay_with_wallet' => true]))->assertUnprocessable();
        $this->assertDatabaseCount('bookings', 0);
        $this->customer->forceFill(['wallet_balance' => 100000])->save();
        $this->postJson('/api/v1/bookings/series', $this->payload(['pay_with_wallet' => true, 'occurrences' => 2]))->assertUnprocessable();
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertEquals(100000, $this->customer->refresh()->wallet_balance);
        $this->customer->forceFill(['wallet_balance' => 300000])->save();
        $d = $this->payload(['pay_with_wallet' => true, 'occurrences' => 2]);
        $this->postJson('/api/v1/bookings/series', $d)->assertCreated();
        $this->postJson('/api/v1/bookings/series', $d)->assertCreated();
        $this->assertDatabaseCount('bookings', 2);
        $this->assertDatabaseCount('payments', 2);
        $this->assertEquals(130000, $this->customer->refresh()->wallet_balance);
    }

    public function test_admin_can_create_staff_and_customer_cannot_and_password_is_hidden(): void
    {
        $d = ['name' => 'Nhân viên mới', 'email' => 'new@badminton.test', 'role' => 'staff', 'branch_id' => $this->court->branch_id, 'password' => 'NewStaff@123', 'password_confirmation' => 'NewStaff@123'];
        $this->actingAs($this->customer)->postJson('/api/v1/admin/users', $d)->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin']);
        $r = $this->actingAs($admin)->postJson('/api/v1/admin/users', $d)->assertCreated()->assertJsonPath('data.role', 'staff')->assertJsonMissingPath('data.password');
        $this->assertTrue(Hash::check('NewStaff@123', User::find($r->json('data.id'))->password));
    }

    public function test_changing_email_invalidates_old_verification_and_reset_tokens(): void
    {
        $token = Str::random(64);
        $oldEmail = $this->customer->email;
        $code = AuthCode::factory()->create(['user_id' => $this->customer->id, 'email' => $oldEmail, 'verified_at' => now(), 'reset_hash' => hash('sha256', $token)]);
        $this->actingAs($this->customer)->patchJson('/api/v1/me', ['name' => $this->customer->name, 'email' => 'changed@badminton.test', 'current_password' => 'password'])->assertOk();
        $this->assertNotNull($code->refresh()->used_at);
        $this->postJson('/api/v1/recovery/reset', ['email' => $oldEmail, 'reset_token' => $token, 'password' => 'NewSecure@123', 'password_confirmation' => 'NewSecure@123'])->assertUnprocessable();
        $this->postJson('/api/v1/recovery/verify', ['email' => $oldEmail, 'code' => '123456'])->assertUnprocessable();
        $this->assertTrue(Hash::check('password', $this->customer->refresh()->password));
    }
}
