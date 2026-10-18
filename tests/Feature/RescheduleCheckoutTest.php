<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RescheduleCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 17)->setTime(9, 0));
    }

    public function test_cheaper_move_preview_does_not_mutate_and_confirmation_does_not_refund(): void
    {
        $court = Court::factory()->create(['hourly_rate' => 100000, 'peak_rate' => 100000]);
        $target = Court::factory()->create(['branch_id' => $court->branch_id, 'hourly_rate' => 60000, 'peak_rate' => 60000, 'weekend_rate' => 60000]);
        $customer = User::factory()->create(['wallet_balance' => 0]);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $court->branch_id]);
        $booking = Booking::factory()->create(['user_id' => $customer->id, 'court_id' => $court->id, 'starts_at' => '2026-10-18 13:00', 'ends_at' => '2026-10-18 14:00', 'court_amount' => 100000, 'total_amount' => 100000, 'paid_amount' => 100000, 'service_amount' => 0]);
        Payment::factory()->create(['booking_id' => $booking->id, 'amount' => 100000]);
        $data = ['court_id' => $target->id, 'starts_at' => '2026-10-18 13:00', 'ends_at' => '2026-10-18 14:00'];
        $base = '/api/v1/bookings/'.$booking->id;
        $this->actingAs($manager)->postJson($base.'/reschedule-quote', $data)->assertOk()->assertJsonPath('data.refund_amount', 40000)->assertJsonPath('data.booking.total_amount', 60000);
        $this->assertEquals($court->id, $booking->fresh()->court_id);
        $this->assertDatabaseCount('payments', 1);
        $data += ['request_key' => (string) Str::uuid(), 'expected_total' => 60000];
        $this->patchJson($base.'/reschedule', [...$data, 'expected_total' => 1])->assertConflict();
        $this->patchJson($base.'/reschedule', $data)->assertUnprocessable();
        $this->assertSame(0, $customer->fresh()->wallet_balance);
        $this->assertSame($court->id, $booking->fresh()->court_id);
        $this->assertSame(100000, $booking->fresh()->paid_amount);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('wallet_entries', 0);
    }

    public function test_refund_fields_are_rejected_and_never_change_the_paid_bill(): void
    {
        $court = Court::factory()->create(['hourly_rate' => 50000, 'peak_rate' => 50000, 'weekend_rate' => 50000]);
        $booking = Booking::factory()->create(['court_id' => $court->id, 'starts_at' => '2026-10-18 13:00', 'ends_at' => '2026-10-18 15:00', 'court_amount' => 100000, 'total_amount' => 100000, 'paid_amount' => 100000]);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $court->branch_id]);
        $data = ['court_id' => $court->id, 'starts_at' => '2026-10-18 13:00', 'ends_at' => '2026-10-18 14:00', 'request_key' => (string) Str::uuid(), 'refund_method' => 'cash'];
        $url = '/api/v1/bookings/'.$booking->id.'/reschedule';
        $this->actingAs($manager)->patchJson($url, $data)->assertUnprocessable();
        DB::table('role_permissions')->insert(['role' => 'manager', 'permission' => 'payments.write', 'allowed' => false]);
        $this->patchJson($url, [...$data, 'refund_confirmed' => true])->assertUnprocessable();
        DB::table('role_permissions')->where('role', 'manager')->update(['allowed' => true]);
        $this->patchJson($url, [...$data, 'refund_confirmed' => true])->assertUnprocessable();
        $this->assertDatabaseMissing('payments', ['booking_id' => $booking->id, 'kind' => 'refund']);
        $this->assertSame(100000, $booking->fresh()->paid_amount);
    }

    public function test_end_of_play_announces_checkout_once_without_closing_or_collecting_money(): void
    {
        $booking = Booking::factory()->create(['status' => 'checked_in', 'starts_at' => '2026-10-17 07:00', 'ends_at' => '2026-10-17 08:00', 'total_amount' => 120000, 'paid_amount' => 100000]);
        $this->artisan('hub:maintain')->assertSuccessful();
        $this->artisan('hub:maintain')->assertSuccessful();
        $this->actingAs($booking->user)->getJson('/api/v1/bookings/'.$booking->id)->assertOk()->assertJsonPath('data.needs_checkout', true)->assertJsonPath('data.remaining_amount', 20000);
        $this->assertSame('checked_in', $booking->fresh()->status);
        $this->assertNotNull($booking->fresh()->checkout_due_at);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(1, $booking->user->notifications()->where('data->type', 'booking.checkout_due')->count());
    }
}
