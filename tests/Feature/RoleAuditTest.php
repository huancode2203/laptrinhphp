<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CommunityPost;
use App\Models\Court;
use App\Models\GameEvent;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoleAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocking_prevents_direct_post_access_and_interactions_in_both_directions_but_allows_leaving(): void
    {
        $post = CommunityPost::factory()->create(['moderation_status' => 'approved']);
        $viewer = User::factory()->create();
        $join = $post->joins()->create(['user_id' => $viewer->id, 'status' => 'pending']);
        DB::table('blocked_users')->insert(['user_id' => $post->user_id, 'blocked_id' => $viewer->id]);
        $url = '/api/v1/community/posts/'.$post->id;
        $this->actingAs($viewer)->getJson($url)->assertForbidden();
        $this->postJson($url.'/join')->assertForbidden();
        $this->putJson($url.'/like', ['liked' => true])->assertForbidden();
        $this->postJson($url.'/comments', ['content' => 'Hẹn gặp tại sân'])->assertForbidden();
        $this->actingAs($post->user)->patchJson($url.'/joins/'.$join->id, ['status' => 'accepted'])->assertForbidden();
        $this->actingAs($viewer)->deleteJson($url.'/join')->assertOk();
        $this->assertSame('withdrawn', $join->fresh()->status);
        $this->assertDatabaseCount('post_comments', 0);
        $this->assertDatabaseCount('post_likes', 0);
        DB::table('blocked_users')->where('user_id', $post->user_id)->delete();
        DB::table('blocked_users')->insert(['user_id' => $viewer->id, 'blocked_id' => $post->user_id]);
        $this->getJson($url)->assertForbidden();
        $this->postJson($url.'/join')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson($url)->assertOk();
    }

    private function deny(string $permission): void
    {
        DB::table('role_permissions')->updateOrInsert(['role' => 'staff', 'permission' => $permission], ['allowed' => false]);
    }

    public function test_staff_without_payment_permission_cannot_debit_customer_wallet_or_activate_membership_or_confirm_event(): void
    {
        $booking = Booking::factory()->create();
        $booking->user->forceFill(['wallet_balance' => 1000000])->save();
        $staff = User::factory()->create(['role' => 'staff', 'branch_id' => $booking->court->branch_id]);
        $this->deny('payments.write');
        $this->actingAs($staff)->postJson('/api/v1/bookings/'.$booking->id.'/wallet-payment', ['request_key' => (string) Str::uuid()])->assertForbidden();
        $plan = MembershipPlan::factory()->create();
        $this->postJson('/api/v1/manage/users/'.$booking->user_id.'/membership', ['request_key' => (string) Str::uuid(), 'branch_id' => $staff->branch_id, 'plan_id' => $plan->id, 'method' => 'cash'])->assertForbidden();
        $event = GameEvent::factory()->create(['branch_id' => $staff->branch_id]);
        $reg = $event->registrations()->create(['user_id' => $booking->user_id, 'status' => 'pending', 'amount' => 50000, 'paid_amount' => 0, 'method' => 'cash']);
        $this->postJson('/api/v1/manage/registrations/'.$reg->id.'/confirm', ['method' => 'cash'])->assertForbidden();
        $this->assertSame(1000000, $booking->user->fresh()->wallet_balance);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_staff_without_booking_read_cannot_get_another_customers_qr(): void
    {
        $booking = Booking::factory()->create();
        $this->deny('bookings.read');
        $this->actingAs(User::factory()->create(['role' => 'staff', 'branch_id' => $booking->court->branch_id]))->getJson('/api/v1/bookings/'.$booking->id.'/checkin-token')->assertForbidden();
        $this->actingAs($booking->user)->getJson('/api/v1/bookings/'.$booking->id.'/checkin-token')->assertOk();
    }

    public function test_unassigned_manager_cannot_read_company_reports_and_pivot_only_assignment_is_scoped(): void
    {
        $booking = Booking::factory()->create();
        Payment::factory()->create(['booking_id' => $booking->id, 'amount' => 100000]);
        $other = Booking::factory()->create();
        Payment::factory()->create(['booking_id' => $other->id, 'amount' => 300000]);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => null]);
        $url = '/api/v1/manage/reports?from='.now()->toDateString().'&to='.now()->toDateString();
        $this->actingAs($manager)->getJson($url)->assertForbidden();
        DB::table('user_branches')->insert(['user_id' => $manager->id, 'branch_id' => $booking->court->branch_id]);
        $this->getJson($url)->assertOk()->assertJsonPath('data.collected', 100000);
        $this->getJson($url.'&branch_id='.$other->court->branch_id)->assertForbidden();
    }

    public function test_checked_in_customer_can_pay_added_items_by_wallet(): void
    {
        $booking = Booking::factory()->create(['status' => 'checked_in', 'paid_amount' => 100000, 'total_amount' => 120000, 'product_amount' => 20000]);
        $booking->user->forceFill(['wallet_balance' => 50000])->save();
        $this->actingAs($booking->user)->postJson('/api/v1/bookings/'.$booking->id.'/wallet-payment', ['request_key' => (string) Str::uuid()])->assertOk();
        $this->assertSame(120000, $booking->fresh()->paid_amount);
        $this->assertSame(30000, $booking->user->fresh()->wallet_balance);
    }

    public function test_cancelling_event_also_cancels_waiting_registrations(): void
    {
        $event = GameEvent::factory()->create();
        $user = User::factory()->create();
        $reg = $event->registrations()->create(['user_id' => $user->id, 'status' => 'waiting', 'amount' => 0, 'paid_amount' => 0, 'method' => 'cash']);
        $this->actingAs(User::factory()->create(['role' => 'manager', 'branch_id' => $event->branch_id]))->patchJson('/api/v1/manage/events/'.$event->id.'/status', ['status' => 'cancelled'])->assertOk();
        $this->assertSame('cancelled', $reg->fresh()->status);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $user->id]);
    }

    public function test_staff_without_booking_write_cannot_hold_a_slot(): void
    {
        $booking = Booking::factory()->create();
        $staff = User::factory()->create(['role' => 'staff', 'branch_id' => $booking->court->branch_id]);
        $this->deny('bookings.write');
        $this->actingAs($staff)->postJson('/api/v1/holds', ['request_key' => (string) Str::uuid(), 'court_id' => $booking->court_id, 'starts_at' => $booking->starts_at->addHours(2)->toIso8601String(), 'ends_at' => $booking->ends_at->addHours(2)->toIso8601String()])->assertForbidden();
        $this->assertDatabaseCount('court_holds', 0);
    }

    public function test_ai_calendar_normalization_preserves_explicit_day_time_area_and_vietnamese_budget(): void
    {
        config(['ai.search.provider' => 'ollama']);
        $court = Court::factory()->create();
        $court->branch->update(['district' => 'Bình Thạnh']);
        Http::fake(['*/api/chat' => Http::response(['done' => true, 'message' => ['content' => json_encode(['date' => now()->subDay()->toDateString(), 'start_time' => '10:00', 'end_time' => '11:00', 'district' => 'Quận 1', 'max_budget' => null, 'amenities' => [], 'clarification' => null])]])]);
        $this->postJson('/api/v1/ai/courts', ['message' => 'Ngày mai 13h đến 15h ở Bình Thạnh dưới 500.000đ'])->assertOk()->assertJsonPath('data.mode', 'model')->assertJsonPath('data.filters.date', now()->addDay()->toDateString())->assertJsonPath('data.filters.start_time', '13:00')->assertJsonPath('data.filters.end_time', '15:00')->assertJsonPath('data.filters.district', 'Bình Thạnh')->assertJsonPath('data.filters.max_budget', 500000)->assertJsonPath('data.courts.0.court_id', $court->id);
    }
}
