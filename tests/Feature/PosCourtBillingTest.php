<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosCourtBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 12)->setTime(14, 0));
    }

    public function test_picker_prioritizes_players_on_court_without_using_the_management_calendar_date(): void
    {
        $court = Court::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $court->branch_id]);
        $playing = Booking::factory()->create(['court_id' => $court->id, 'status' => 'checked_in', 'starts_at' => '2026-10-11 22:00', 'ends_at' => '2026-10-11 23:00']);
        $today = Booking::factory()->create(['court_id' => Court::factory()->create(['branch_id' => $court->branch_id])->id, 'starts_at' => '2026-10-12 15:00', 'ends_at' => '2026-10-12 16:00']);
        foreach (['completed', 'cancelled'] as $status) {
            Booking::factory()->create(['court_id' => $court->id, 'status' => $status, 'starts_at' => '2026-10-12 10:00', 'ends_at' => '2026-10-12 11:00']);
        }
        Booking::factory()->create(['court_id' => $court->id, 'starts_at' => '2026-10-13 10:00', 'ends_at' => '2026-10-13 11:00']);
        Booking::factory()->create(['status' => 'checked_in']);
        $this->actingAs($manager)->getJson('/api/v1/manage/sales/open-bookings?branch_id='.$court->branch_id.'&date=2026-09-01')
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $playing->id)->assertJsonPath('data.1.id', $today->id)
            ->assertJsonPath('data.0.court.name', $court->name)->assertJsonPath('data.0.customer.name', $playing->user->name);
    }

    public function test_picker_requires_branch_assignment_and_pos_and_booking_permissions(): void
    {
        $court = Court::factory()->create();
        $otherCourt = Court::factory()->create();
        $url = '/api/v1/manage/sales/open-bookings?branch_id='.$court->branch_id;
        foreach (['admin', 'manager', 'staff', 'cashier'] as $role) {
            $user = User::factory()->create(['role' => $role, 'branch_id' => $court->branch_id]);
            $this->actingAs($user)->getJson($url)->assertOk();
            if ($role !== 'admin') {
                $this->getJson('/api/v1/manage/sales/open-bookings?branch_id='.$otherCourt->branch_id)->assertForbidden();
            }
        }
        $this->actingAs(User::factory()->create())->getJson($url)->assertForbidden();
        $staff = User::factory()->create(['role' => 'staff', 'branch_id' => $court->branch_id]);
        $this->actingAs($staff);
        foreach (['pos.read', 'pos.write', 'bookings.read', 'bookings.write'] as $permission) {
            DB::table('role_permissions')->updateOrInsert(['role' => 'staff', 'permission' => $permission], ['allowed' => false]);
            $this->getJson($url)->assertForbidden();
            DB::table('role_permissions')->where('role', 'staff')->where('permission', $permission)->update(['allowed' => true]);
        }
        $this->getJson('/api/v1/manage/sales/open-bookings')->assertUnprocessable()->assertJsonValidationErrors('branch_id');
        $this->getJson('/api/v1/manage/sales/open-bookings?branch_id[]=1')->assertUnprocessable()->assertJsonValidationErrors('branch_id');
    }

    public function test_paid_player_can_buy_water_repeatedly_and_pay_only_the_added_amount_without_duplicate_stock_or_money(): void
    {
        $court = Court::factory()->create();
        $customer = User::factory()->create(['wallet_balance' => 50000]);
        $cashier = User::factory()->create(['role' => 'cashier', 'branch_id' => $court->branch_id]);
        $booking = Booking::factory()->create(['court_id' => $court->id, 'user_id' => $customer->id, 'status' => 'checked_in', 'starts_at' => '2026-10-12 13:00', 'ends_at' => '2026-10-12 15:00', 'paid_amount' => 100000]);
        Payment::factory()->create(['booking_id' => $booking->id, 'received_by' => $cashier->id, 'amount' => 100000, 'method' => 'cash']);
        $water = Product::factory()->create(['branch_id' => $court->branch_id, 'name' => 'Aquafina 500ml', 'price' => 10000, 'stock' => 5]);
        $payload = ['request_key' => (string) Str::uuid(), 'branch_id' => $court->branch_id, 'booking_id' => (string) $booking->id, 'method' => 'booking', 'items' => [['product_id' => $water->id, 'quantity' => 2]]];
        $this->actingAs($cashier)->postJson('/api/v1/manage/sales', $payload)->assertCreated()->assertJsonPath('data.total_amount', 20000)->assertJsonPath('data.user_id', $customer->id);
        $this->postJson('/api/v1/manage/sales', $payload)->assertCreated();
        $this->assertSame(120000, $booking->fresh()->total_amount);
        $this->assertSame(100000, $booking->fresh()->paid_amount);
        $this->assertSame(20000, $booking->fresh()->product_amount);
        $this->assertSame(3, $water->fresh()->stock);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('commerce_payments', 0);
        $this->getJson('/api/v1/manage/sales/open-bookings?branch_id='.$court->branch_id)->assertOk()->assertJsonPath('data.0.remaining_amount', 20000);
        $paymentKey = (string) Str::uuid();
        $this->actingAs($customer)->postJson('/api/v1/bookings/'.$booking->id.'/wallet-payment', ['request_key' => $paymentKey])->assertOk();
        $this->postJson('/api/v1/bookings/'.$booking->id.'/wallet-payment', ['request_key' => $paymentKey])->assertOk();
        $this->assertSame(30000, $customer->fresh()->wallet_balance);
        $this->assertSame(120000, $booking->fresh()->paid_amount);
        $this->assertSame('checked_in', $booking->fresh()->status);
        $this->actingAs($cashier)->postJson('/api/v1/manage/sales', $payload)->assertCreated();
        $payload['request_key'] = (string) Str::uuid();
        $payload['items'][0]['quantity'] = 1;
        $this->postJson('/api/v1/manage/sales', $payload)->assertCreated();
        $this->assertSame(130000, $booking->fresh()->total_amount);
        $this->assertSame(120000, $booking->fresh()->paid_amount);
        $this->assertSame(2, $water->fresh()->stock);
        $this->postJson('/api/v1/manage/bookings/'.$booking->id.'/payments', ['request_key' => (string) Str::uuid(), 'amount' => 10000, 'method' => 'cash'])->assertOk();
        $this->getJson('/api/v1/bookings/'.$booking->id.'/receipt')->assertOk()->assertJsonPath('data.booking.remaining_amount', 0)->assertJsonPath('data.collected', 130000)->assertJsonCount(2, 'data.booking.product_items');
        $this->assertDatabaseCount('sales', 2);
        $booking->update(['status' => 'completed']);
        $payload['request_key'] = (string) Str::uuid();
        $this->postJson('/api/v1/manage/sales', $payload)->assertUnprocessable();
        $this->assertSame(2, $water->fresh()->stock);
        $this->assertDatabaseCount('sales', 2);

    }
}
