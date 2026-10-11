<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Tests\TestCase;

class InputAndRecoveryRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 12)->setTime(9, 0));
    }

    private function window(Court $court): array
    {
        return ['court_id' => $court->id, 'request_key' => (string) Str::uuid(), 'starts_at' => '2026-10-12T13:00:00+07:00', 'ends_at' => '2026-10-12T15:00:00+07:00'];
    }

    public function test_active_hold_survives_reload_and_is_private_and_expires(): void
    {
        $court = Court::factory()->create();
        $owner = User::factory()->create();
        $hold = $this->actingAs($owner)->postJson('/api/v1/holds', $this->window($court))->assertOk()->json('data.id');
        $this->getJson('/api/v1/holds/active')->assertOk()->assertJsonPath('data.id', $hold)->assertJsonPath('data.court.branch.id', $court->branch_id);
        $this->actingAs(User::factory()->create())->getJson('/api/v1/holds/active')->assertOk()->assertJsonPath('data', null);
        $this->deleteJson('/api/v1/holds/'.$hold)->assertForbidden();
        $this->actingAs($owner)->getJson('/api/v1/holds/active')->assertJsonPath('data.id', $hold);
        $this->travel(6)->minutes();
        $this->getJson('/api/v1/holds/active')->assertJsonPath('data', null);
        $this->postJson('/api/v1/bookings', [...$this->window($court), 'hold_id' => $hold])->assertUnprocessable();
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_hold_cannot_be_reused_for_another_branch_or_time_and_release_allows_a_new_branch(): void
    {
        $a = Court::factory()->create();
        $b = Court::factory()->create();
        $this->actingAs(User::factory()->create());
        $hold = $this->postJson('/api/v1/holds', $this->window($a))->assertOk()->json('data.id');
        $this->postJson('/api/v1/bookings', [...$this->window($b), 'hold_id' => $hold])->assertUnprocessable();
        $this->postJson('/api/v1/holds', $this->window($b))->assertUnprocessable();
        $this->deleteJson('/api/v1/holds/'.$hold)->assertOk();
        $next = $this->postJson('/api/v1/holds', $this->window($b))->assertOk()->json('data.id');
        $this->getJson('/api/v1/holds/active')->assertJsonPath('data.id', $next)->assertJsonPath('data.court.branch.id', $b->branch_id);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_pending_filter_recovers_only_own_open_unpaid_bookings_and_expires_unpaid_deposits(): void
    {
        $user = User::factory()->create();
        $court = Court::factory()->create();
        $attributes = ['user_id' => $user->id, 'court_id' => $court->id, 'starts_at' => '2026-10-12 13:00', 'ends_at' => '2026-10-12 15:00', 'total_amount' => 200000, 'paid_amount' => 0, 'status' => 'confirmed', 'deposit_amount' => 50000, 'payment_expires_at' => now()->addMinutes(15)];
        $pending = Booking::factory()->create($attributes);
        Booking::factory()->create([...$attributes, 'paid_amount' => 200000]);
        Booking::factory()->create([...$attributes, 'status' => 'cancelled']);
        Booking::factory()->create([...$attributes, 'user_id' => User::factory()->create()->id]);
        $this->actingAs($user)->getJson('/api/v1/bookings?pending=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $pending->id);
        $this->travel(16)->minutes();
        $this->getJson('/api/v1/bookings?pending=1')->assertJsonCount(0, 'data');
        $this->assertSame('cancelled', $pending->fresh()->status);
    }

    public function test_bad_service_quantities_dates_and_associative_lists_never_create_bookings(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $court = Court::factory()->create();
        $service = Service::factory()->create(['branch_id' => $court->branch_id]);
        $this->actingAs(User::factory()->create());
        foreach ([-1, 0, 11, 1.5, 'abc', '1e2', '9999999999999999999999', ['quantity' => 1]] as $quantity) {
            $this->postJson('/api/v1/bookings', [...$this->window($court), 'services' => [['id' => $service->id, 'quantity' => $quantity]]])->assertUnprocessable()->assertJsonValidationErrors('services.0.quantity');
        }
        $this->postJson('/api/v1/bookings', [...$this->window($court), 'services' => ['first' => ['id' => $service->id, 'quantity' => 1]]])->assertUnprocessable()->assertJsonValidationErrors('services');
        foreach ([['starts_at' => 'bad date'], ['starts_at' => ['13:00']], ['starts_at' => '2026-10-12T13:00:00.001+07:00'], ['ends_at' => '2026-10-13T01:00:00+07:00'], ['ends_at' => '2026-10-12T12:00:00+07:00']] as $invalid) {
            $this->postJson('/api/v1/bookings', [...$this->window($court), ...$invalid])->assertUnprocessable();
        }
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('booking_services', 0);
    }

    public function test_invalid_pagination_is_rejected_for_every_role_and_public_lists(): void
    {
        $court = Court::factory()->create();
        foreach (['customer', 'staff', 'cashier', 'manager', 'admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'branch_id' => $court->branch_id]));
            foreach (['bookings', 'community/posts', 'events', 'notifications'] as $path) {
                foreach (['page=-1', 'page=1.5', 'page[]=1', 'per_page=999999'] as $invalid) {
                    $this->getJson('/api/v1/'.$path.'?'.$invalid)->assertUnprocessable();
                }
            }
            $this->getJson('/api/v1/hub')->assertOk()->assertJsonPath('data.user.role', $role);
        }
    }

    public function test_management_rejects_bad_money_stock_and_wrong_branch_without_writing(): void
    {
        $court = Court::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'manager', 'branch_id' => $court->branch_id]));
        $product = Product::factory()->create(['branch_id' => $court->branch_id, 'stock' => 5]);
        foreach ([-1, 0, 1.5, 101, '1e2', ['bad' => 1]] as $quantity) {
            $this->postJson('/api/v1/manage/sales', ['request_key' => (string) Str::uuid(), 'branch_id' => $court->branch_id, 'method' => 'cash', 'items' => [['product_id' => $product->id, 'quantity' => $quantity]]])->assertUnprocessable();
        }
        $booking = Booking::factory()->create(['court_id' => $court->id]);
        foreach ([-1, 0, 1.5, 'abc', ['bad' => 1], '99999999999999999999'] as $amount) {
            $this->postJson('/api/v1/manage/bookings/'.$booking->id.'/payments', ['request_key' => (string) Str::uuid(), 'amount' => $amount, 'method' => 'cash'])->assertUnprocessable();
        }
        $this->getJson('/api/v1/manage/features?branch_id[]=1')->assertUnprocessable();
        $this->getJson('/api/v1/manage/hub?branch_id='.Court::factory()->create()->branch_id)->assertForbidden();
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_form_string_ids_can_save_and_update_pricing_coaches_and_quotes(): void
    {
        $court = Court::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'manager', 'branch_id' => $court->branch_id]));
        $pricing = ['branch_id' => (string) $court->branch_id, 'court_id' => (string) $court->id, 'name' => 'Giờ trưa', 'from_minute' => '780', 'to_minute' => '900', 'hourly_rate' => '100000', 'priority' => '1', 'active' => true];
        $id = $this->postJson('/api/v1/manage/pricing', $pricing)->assertOk()->json('data.id');
        $this->putJson('/api/v1/manage/pricing/'.$id, $pricing)->assertOk();
        $coach = ['branch_id' => (string) $court->branch_id, 'name' => 'Nguyễn Thanh Phong', 'hourly_rate' => '150000', 'active' => true];
        $id = $this->postJson('/api/v1/manage/coaches', $coach)->assertOk()->json('data.id');
        $this->putJson('/api/v1/manage/coaches/'.$id, $coach)->assertOk();
        $customer = User::factory()->create();
        $this->actingAs($customer)->postJson('/api/v1/courts/'.$court->id.'/quote', [...$this->window($court), 'user_id' => (string) $customer->id])->assertOk();
    }

    public function test_associative_event_court_ids_and_bad_profile_values_are_validation_errors(): void
    {
        $court = Court::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'manager', 'branch_id' => $court->branch_id]));
        $this->postJson('/api/v1/manage/events', ['branch_id' => $court->branch_id, 'title' => 'Buổi giao lưu', 'type' => 'friendly', 'description' => 'Giao lưu cầu lông', 'format' => 'Đánh đôi', 'skill_level' => 'Trung bình', 'starts_at' => '2026-10-12 13:00', 'ends_at' => '2026-10-12 15:00', 'capacity' => 8, 'fee' => 0, 'court_ids' => ['first' => $court->id]])->assertUnprocessable()->assertJsonValidationErrors('court_ids');
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach (['1', 'abc', '09012', ['0901234567']] as $phone) {
            $this->patchJson('/api/v1/me', ['name' => $user->name, 'email' => $user->email, 'phone' => $phone])->assertUnprocessable()->assertJsonValidationErrors('phone');
        }
        $this->patchJson('/api/v1/me', ['name' => '   ', 'email' => $user->email])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertDatabaseCount('game_events', 0);
        $this->assertSame($user->name, $user->fresh()->name);
    }
}
