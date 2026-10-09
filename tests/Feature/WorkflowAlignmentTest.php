<?php

namespace Tests\Feature;

use App\EventWaitlistService;
use App\Models\Booking;
use App\Models\Court;
use App\Models\GameEvent;
use App\Models\Product;
use App\Models\User;
use App\Notifications\HubNotification;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RealisticDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkflowAlignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 12)->setTime(8, 0));
        config(['ai.search.provider' => 'local', 'ai.moderation.provider' => 'local']);
        Http::preventStrayRequests();
    }

    public function test_demo_data_has_280_linked_rows_and_is_idempotent_with_balanced_payments(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(RealisticDemoSeeder::class);
        $counts = json_decode(DB::table('demo_data_batches')->value('counts'), true);
        $this->assertSame(280, array_sum($counts));
        $before = [];
        foreach ($counts as $table => $count) {
            $before[$table] = DB::table($table)->count();
        }
        $this->seed(RealisticDemoSeeder::class);
        foreach ($before as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
        foreach (Booking::where('reference', 'like', 'DEMO-BH-%')->with('payments')->get() as $b) {
            $this->assertSame($b->paid_amount, (int) $b->payments->sum('amount'));
            $this->assertSame($b->total_amount, $b->court_amount + $b->service_amount + $b->product_amount - $b->discount_amount);
            $this->assertTrue($b->created_at->lte(now()));
        }
        $this->assertDatabaseCount('reviews', 20);
        $this->assertDatabaseCount('post_comments', 20);
        $this->assertDatabaseCount('event_registrations', 16);
        $this->assertDatabaseCount('coaches', 8);
        $customer = User::where('email', 'customer@badminton.test')->firstOrFail();
        $this->actingAs($customer)->getJson('/api/v1/account')->assertOk()->assertJsonCount(4, 'data.my_posts')->assertJsonCount(8, 'data.coupons');
        $admin = User::where('email', 'admin@badminton.test')->firstOrFail();
        $total = (int) DB::table('payments')->sum('amount') + (int) DB::table('commerce_payments')->sum('amount');
        $this->actingAs($admin)->getJson('/api/v1/manage/reports?from=2026-10-01&to=2026-10-12')->assertOk()->assertJsonPath('data.collected', $total);
    }

    public function test_ai_chat_is_private_idempotent_reuses_context_and_rechecks_real_availability(): void
    {
        $customer = User::factory()->create();
        $court = Court::factory()->create();
        $court->branch->update(['district' => 'Bình Thạnh']);
        $payload = ['request_key' => (string) Str::uuid(), 'message' => 'Hôm nay từ 13h tới 15h ở Bình Thạnh'];
        $this->actingAs($customer)->postJson('/api/v1/ai/chat', $payload)->assertCreated()->assertJsonPath('data.1.result.courts.0.court_id', $court->id);
        $this->postJson('/api/v1/ai/chat', $payload)->assertCreated();
        $this->assertDatabaseCount('ai_search_turns', 1);
        Booking::factory()->create(['court_id' => $court->id, 'starts_at' => '2026-10-13 13:00', 'ends_at' => '2026-10-13 15:00']);
        $this->postJson('/api/v1/ai/chat', ['request_key' => (string) Str::uuid(), 'message' => 'Ngày mai thì sao?'])->assertCreated()->assertJsonPath('data.1.result.filters.date', '2026-10-13')->assertJsonPath('data.1.result.filters.start_time', '13:00')->assertJsonPath('data.1.result.total_count', 0);
        $this->getJson('/api/v1/ai/chat')->assertOk()->assertJsonCount(4, 'data');
        $this->actingAs(User::factory()->create())->getJson('/api/v1/ai/chat')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/ai/chat', $payload)->assertUnprocessable();
    }

    public function test_pos_additions_are_atomic_and_collected_once_on_the_booking_invoice(): void
    {
        $court = Court::factory()->create();
        $customer = User::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $court->branch_id]);
        $b = Booking::factory()->create(['court_id' => $court->id, 'user_id' => $customer->id, 'court_amount' => 200000, 'total_amount' => 200000, 'paid_amount' => 0, 'starts_at' => '2026-10-12 13:00', 'ends_at' => '2026-10-12 15:00']);
        $p = Product::factory()->create(['branch_id' => $court->branch_id, 'price' => 10000, 'stock' => 20]);
        $payload = ['request_key' => (string) Str::uuid(), 'branch_id' => $court->branch_id, 'booking_id' => $b->id, 'method' => 'booking', 'items' => [['product_id' => $p->id, 'quantity' => 3]]];
        $this->actingAs($manager)->postJson('/api/v1/manage/sales', $payload)->assertCreated();
        $this->postJson('/api/v1/manage/sales', $payload)->assertCreated();
        $this->assertSame(17, $p->fresh()->stock);
        $this->assertSame(230000, $b->fresh()->total_amount);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('commerce_payments', 0);
        $this->postJson('/api/v1/manage/bookings/'.$b->id.'/payments', ['request_key' => (string) Str::uuid(), 'amount' => 230000, 'method' => 'cash'])->assertOk();
        $this->getJson('/api/v1/bookings/'.$b->id.'/receipt')->assertOk()->assertJsonPath('data.booking.product_items.0.quantity', 3)->assertJsonPath('data.collected', 230000);
        $this->getJson('/api/v1/manage/reports?branch_id='.$court->branch_id.'&from=2026-10-12&to=2026-10-12')->assertOk()->assertJsonPath('data.net_revenue', 230000)->assertJsonPath('data.pos_revenue', 30000);
        $this->actingAs($customer)->getJson('/api/v1/bookings/'.$b->id.'/receipt.pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_pos_rejects_other_branch_and_closed_bookings_without_changing_stock(): void
    {
        $court = Court::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $court->branch_id]);
        $p = Product::factory()->create(['branch_id' => $court->branch_id, 'stock' => 10]);
        $b = Booking::factory()->create();
        $payload = ['request_key' => (string) Str::uuid(), 'branch_id' => $court->branch_id, 'booking_id' => $b->id, 'method' => 'booking', 'items' => [['product_id' => $p->id, 'quantity' => 1]]];
        $this->actingAs($manager)->postJson('/api/v1/manage/sales', $payload)->assertForbidden();
        $b->update(['court_id' => $court->id, 'status' => 'cancelled']);
        $this->postJson('/api/v1/manage/sales', $payload)->assertUnprocessable();
        $this->assertSame(10, $p->fresh()->stock);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_staff_sees_branch_spending_and_finds_customers_by_phone_for_quick_booking(): void
    {
        $court = Court::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $court->branch_id]);
        $customer = User::factory()->create(['phone' => '0900123456']);
        Booking::factory()->create(['court_id' => $court->id, 'user_id' => $customer->id, 'paid_amount' => 180000]);
        Booking::factory()->create(['user_id' => $customer->id, 'paid_amount' => 999999]);
        $this->actingAs($manager)->getJson('/api/v1/manage/customers?branch_id='.$court->branch_id)->assertOk()->assertJsonPath('data.0.spent', 180000)->assertJsonPath('data.0.visits', 1);
        $this->getJson('/api/v1/manage/customers?search=0900123456&branch_id='.$court->branch_id)->assertOk()->assertJsonPath('data.0.id', $customer->id);
        $this->postJson('/api/v1/bookings', ['request_key' => (string) Str::uuid(), 'court_id' => $court->id, 'user_id' => $customer->id, 'starts_at' => '2026-10-12T16:00:00+07:00', 'ends_at' => '2026-10-12T18:00:00+07:00'])->assertCreated()->assertJsonPath('data.user_id', $customer->id)->assertJsonPath('data.court_amount', 360000);
    }

    public function test_matrix_exposes_booking_links_only_to_staff_with_read_permission(): void
    {
        $court = Court::factory()->create();
        $booking = Booking::factory()->create(['court_id' => $court->id, 'starts_at' => '2026-10-12 13:00', 'ends_at' => '2026-10-12 15:00']);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $court->branch_id]);
        $url = '/api/v1/branches/'.$court->branch_id.'/schedule?date=2026-10-12';
        $public = $this->getJson($url)->assertOk()->json('data.courts.0.slots');
        $this->assertFalse(collect($public)->contains(fn ($s) => isset($s['booking_id']) || isset($s['user_id']) || isset($s['customer'])));
        $private = $this->actingAs($manager)->getJson($url)->assertOk()->json('data.courts.0.slots');
        $this->assertTrue(collect($private)->contains(fn ($s) => ($s['booking_id'] ?? null) === $booking->id));
        $this->getJson('/api/v1/bookings/'.$booking->id)->assertOk();
        DB::table('role_permissions')->insert(['role' => 'manager', 'permission' => 'bookings.read', 'allowed' => false]);
        $revoked = $this->getJson($url)->assertOk()->json('data.courts.0.slots');
        $this->assertFalse(collect($revoked)->contains(fn ($s) => isset($s['booking_id'])));
        $this->getJson('/api/v1/bookings/'.$booking->id)->assertForbidden();
    }

    public function test_only_the_owner_can_remove_a_notification(): void
    {
        $owner = User::factory()->create();
        $owner->notify(new HubNotification(['title' => 'Lịch sân', 'body' => 'Đã xác nhận']));
        $id = $owner->notifications()->first()->id;
        $this->actingAs(User::factory()->create())->deleteJson('/api/v1/notifications/'.$id)->assertNotFound();
        $this->actingAs($owner)->deleteJson('/api/v1/notifications/'.$id)->assertOk();
        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('unread_count', 0);
    }

    public function test_full_event_queues_without_charge_then_promotes_and_confirms_wallet_once(): void
    {
        $a = User::factory()->create(['wallet_balance' => 200000]);
        $b = User::factory()->create(['wallet_balance' => 200000]);
        $c = User::factory()->create(['wallet_balance' => 200000]);
        $event = GameEvent::factory()->create(['capacity' => 2, 'fee' => 50000, 'starts_at' => '2026-10-15 09:00', 'ends_at' => '2026-10-15 12:00']);
        foreach ([$a, $b] as $u) {
            $this->actingAs($u)->postJson('/api/v1/events/'.$event->id.'/register', ['method' => 'wallet'])->assertOk()->assertJsonPath('data.status', 'confirmed');
        }
        $this->actingAs($c)->postJson('/api/v1/events/'.$event->id.'/register', ['method' => 'wallet'])->assertOk()->assertJsonPath('data.status', 'waiting');
        $this->assertSame(200000, $c->fresh()->wallet_balance);
        $this->actingAs($a)->deleteJson('/api/v1/events/'.$event->id.'/register')->assertOk();
        $this->assertDatabaseHas('event_registrations', ['game_event_id' => $event->id, 'user_id' => $c->id, 'status' => 'pending']);
        $this->actingAs($c)->postJson('/api/v1/events/'.$event->id.'/register', ['method' => 'wallet'])->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->postJson('/api/v1/events/'.$event->id.'/register', ['method' => 'wallet'])->assertOk();
        $this->assertSame(150000, $c->fresh()->wallet_balance);
        $this->assertSame(2, $event->registrations()->where('status', 'confirmed')->count());
    }

    public function test_maintenance_promotes_waitlist_after_pending_ticket_expiry(): void
    {
        $event = GameEvent::factory()->create(['capacity' => 2, 'fee' => 50000]);
        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = User::factory()->create();
        foreach ([$a, $b, $c] as $u) {
            $this->actingAs($u)->postJson('/api/v1/events/'.$event->id.'/register', ['method' => 'cash'])->assertOk();
        }
        $this->travel(31)->minutes();
        $this->assertSame(1, app(EventWaitlistService::class)->maintain());
        $this->assertSame(2, $event->registrations()->where('status', 'expired')->count());
        $this->assertDatabaseHas('event_registrations', ['game_event_id' => $event->id, 'user_id' => $c->id, 'status' => 'pending']);
    }

    public function test_admin_branch_creation_adds_real_courts_and_assigns_manager(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);
        $r = $this->actingAs($admin)->postJson('/api/v1/admin/branches', ['name' => 'Hub Quận 10', 'district' => 'Quận 10', 'address' => '123 Thành Thái', 'active' => true, 'court_count' => 4, 'hourly_rate' => 130000, 'peak_rate' => 190000, 'weekend_rate' => 210000, 'manager_id' => $manager->id])->assertCreated();
        $id = $r->json('data.id');
        $this->assertSame(4, Court::where('branch_id', $id)->count());
        $this->assertDatabaseHas('courts', ['branch_id' => $id, 'hourly_rate' => 130000]);
        $this->assertDatabaseHas('user_branches', ['user_id' => $manager->id, 'branch_id' => $id]);
        $this->actingAs($manager)->getJson('/api/v1/manage/hub?branch_id='.$id)->assertOk();
    }
}
