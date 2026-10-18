<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SeriesOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 17)->setTime(9, 0));
    }

    private function series(bool $paid = false): array
    {
        $court = Court::factory()->create(['hourly_rate' => 100000, 'peak_rate' => 100000, 'weekend_rate' => 100000]);
        $court->branch->update(['booking_days' => 90, 'fixed_discount' => 0]);
        $owner = User::factory()->create(['wallet_balance' => 0]);
        $key = (string) Str::uuid();
        $rows = [];
        foreach ([1, 8, 15] as $day) {
            $booking = Booking::factory()->create(['user_id' => $owner->id, 'court_id' => $court->id, 'series_key' => $key, 'booking_type' => 'fixed', 'starts_at' => now()->addDays($day)->setTime(13, 0), 'ends_at' => now()->addDays($day)->setTime(14, 0), 'total_amount' => 100000, 'paid_amount' => $paid ? 100000 : 0]);
            if ($paid) {
                Payment::factory()->create(['booking_id' => $booking->id, 'method' => 'wallet', 'amount' => 100000]);
            }
            $rows[] = $booking;
        }

        return [$court, $owner, $rows];
    }

    public function test_move_all_remaining_weeks_is_atomic_and_allows_shifting_into_own_previous_slots(): void
    {
        [$court, $owner, $rows] = $this->series();
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $court->branch_id]);
        $data = ['scope' => 'remaining', 'court_id' => $court->id, 'starts_at' => '2026-10-25 13:00', 'ends_at' => '2026-10-25 14:00'];
        $base = '/api/v1/bookings/'.$rows[0]->id;
        $this->actingAs($manager)->postJson($base.'/reschedule-quote', $data)->assertOk()->assertJsonCount(3, 'data.sessions')->assertJsonPath('data.booking.total_amount', 300000);
        $conflict = Booking::factory()->create(['court_id' => $court->id, 'starts_at' => '2026-11-08 13:00', 'ends_at' => '2026-11-08 14:00']);
        $data += ['request_key' => (string) Str::uuid(), 'expected_total' => 300000, 'expected_refund' => 0];
        $this->patchJson($base.'/reschedule', $data)->assertUnprocessable();
        $this->assertSame('2026-10-18', $rows[0]->fresh()->starts_at->toDateString());
        $conflict->update(['status' => 'cancelled']);
        $this->patchJson($base.'/reschedule', $data)->assertOk();
        $this->patchJson($base.'/reschedule', $data)->assertOk();
        $this->assertSame('2026-10-25', $rows[0]->fresh()->starts_at->toDateString());
        $this->assertSame('2026-11-08', $rows[2]->fresh()->starts_at->toDateString());
        $this->patchJson($base.'/reschedule', [...$data, 'starts_at' => '2026-10-26 13:00', 'ends_at' => '2026-10-26 14:00'])->assertConflict();
        $this->actingAs(User::factory()->create())->postJson($base.'/reschedule-quote', $data)->assertForbidden();
    }

    public function test_paid_series_cancellation_is_blocked_without_changing_finished_sessions(): void
    {
        [$court, $owner, $rows] = $this->series(true);
        $finished = Booking::factory()->create(['series_key' => $rows[0]->series_key, 'user_id' => $owner->id, 'court_id' => $court->id, 'status' => 'completed', 'starts_at' => now()->subWeek(), 'ends_at' => now()->subWeek()->addHour()]);
        $base = '/api/v1/bookings/'.$rows[0]->id;
        $this->actingAs($owner)->postJson($base.'/series-cancel-quote')->assertOk()->assertJsonPath('data.refund_amount', 300000)->assertJsonCount(3, 'data.sessions');
        $data = ['request_key' => (string) Str::uuid(), 'expected_refund' => 300000];
        $this->postJson($base.'/series-cancel', [...$data, 'expected_refund' => 1])->assertConflict();
        $this->assertSame('confirmed', $rows[0]->fresh()->status);
        $this->postJson($base.'/series-cancel', $data)->assertUnprocessable();
        $this->postJson($base.'/series-cancel', $data)->assertUnprocessable();
        $this->assertSame(0, $owner->fresh()->wallet_balance);
        $this->assertSame('confirmed', $rows[2]->fresh()->status);
        $this->assertSame('completed', $finished->fresh()->status);
        $this->assertDatabaseCount('wallet_entries', 0);
        $this->assertDatabaseCount('payments', 3);
    }

    public function test_cheaper_paid_series_move_is_blocked_without_partial_changes(): void
    {
        [$court, $owner, $rows] = $this->series(true);
        $target = Court::factory()->create(['branch_id' => $court->branch_id, 'hourly_rate' => 60000, 'peak_rate' => 60000, 'weekend_rate' => 60000]);
        $data = ['scope' => 'remaining', 'court_id' => $target->id, 'starts_at' => '2026-10-18 13:00', 'ends_at' => '2026-10-18 14:00'];
        $base = '/api/v1/bookings/'.$rows[0]->id;
        $this->actingAs($owner)->postJson($base.'/reschedule-quote', $data)->assertOk()->assertJsonPath('data.refund_amount', 120000);
        $this->patchJson($base.'/reschedule', [...$data, 'request_key' => (string) Str::uuid(), 'expected_refund' => 120000, 'expected_total' => 180000])->assertUnprocessable();
        $this->assertSame(0, $owner->fresh()->wallet_balance);
        foreach ($rows as $booking) {
            $this->assertEquals(100000, $booking->fresh()->paid_amount);
            $this->assertEquals($court->id, $booking->fresh()->court_id);
        }
    }
}
