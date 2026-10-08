<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Maintenance;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private Court $court;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 12)->setTime(9, 0));
        $this->customer = User::factory()->create();
        $this->court = Court::factory()->create();
        $this->booking = Booking::factory()->create(['court_id' => $this->court->id, 'user_id' => $this->customer->id, 'starts_at' => '2026-10-12 10:00', 'ends_at' => '2026-10-12 11:00']);
    }

    private function endpoint(): string
    {
        return '/api/v1/bookings/'.$this->booking->id.'/reschedule';
    }

    public function test_rescheduling_reprices_booking_and_keeps_services_without_duplicate_notifications(): void
    {
        $service = Service::factory()->create(['branch_id' => $this->court->branch_id, 'price' => 10000]);
        $this->booking->services()->attach($service->id, ['name' => $service->name, 'quantity' => 2, 'unit_price' => 10000, 'amount' => 20000]);
        $data = ['starts_at' => '2026-10-12 16:00', 'ends_at' => '2026-10-12 17:00', 'total_amount' => 1];
        $this->actingAs($this->customer)->patchJson($this->endpoint(), $data)->assertOk()->assertJsonPath('data.total_amount', 200000)->assertJsonCount(1, 'data.services');
        $this->patchJson($this->endpoint(), $data)->assertOk();
        $this->assertSame(1, $this->customer->notifications()->count());
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'booking.rescheduled']);
        $this->assertDatabaseHas('booking_services', ['booking_id' => $this->booking->id, 'quantity' => 2]);
    }

    public function test_rescheduling_into_booking_or_maintenance_rolls_back_original_schedule(): void
    {
        Booking::factory()->create(['court_id' => $this->court->id, 'starts_at' => '2026-10-12 12:00', 'ends_at' => '2026-10-12 13:00']);
        Maintenance::factory()->create(['court_id' => $this->court->id, 'starts_at' => '2026-10-12 14:00', 'ends_at' => '2026-10-12 15:00']);
        $this->actingAs($this->customer)->patchJson($this->endpoint(), ['starts_at' => '2026-10-12 12:30', 'ends_at' => '2026-10-12 13:30'])->assertUnprocessable();
        $this->patchJson($this->endpoint(), ['starts_at' => '2026-10-12 14:00', 'ends_at' => '2026-10-12 15:00'])->assertUnprocessable();
        $this->assertSame('10:00', $this->booking->fresh()->starts_at->format('H:i'));
        $this->assertSame(0, $this->customer->notifications()->count());
        $this->patchJson($this->endpoint(), ['starts_at' => '2026-10-12 10:30', 'ends_at' => '2026-10-12 11:30'])->assertOk();
    }

    public function test_rescheduling_respects_ownership_branch_scope_and_paid_status(): void
    {
        $data = ['starts_at' => '2026-10-12 12:00', 'ends_at' => '2026-10-12 13:00'];
        $other = User::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => Court::factory()->create()->branch_id]);
        $this->actingAs($other)->patchJson($this->endpoint(), $data)->assertForbidden();
        $this->actingAs($manager)->patchJson($this->endpoint(), $data)->assertForbidden();
        $this->booking->update(['paid_amount' => 10000]);
        $this->actingAs($this->customer)->patchJson($this->endpoint(), $data)->assertUnprocessable();
        $this->booking->update(['paid_amount' => 0, 'status' => 'cancelled']);
        $this->patchJson($this->endpoint(), $data)->assertUnprocessable();
    }

    public function test_public_day_schedule_exposes_only_availability_and_prices(): void
    {
        Maintenance::factory()->create(['court_id' => $this->court->id, 'starts_at' => '2026-10-12 14:00', 'ends_at' => '2026-10-12 15:00']);
        $response = $this->getJson('/api/v1/branches/'.$this->court->branch_id.'/schedule?date=2026-10-12')->assertOk()->assertJsonCount(40, 'data.courts.0.slots');
        $slots = collect($response->json('data.courts.0.slots'))->keyBy(fn ($slot) => substr($slot['starts_at'], 11, 5));
        $this->assertSame('past', $slots['08:30']['state']);
        $this->assertSame('reserved', $slots['10:00']['state']);
        $this->assertSame('available', $slots['11:00']['state']);
        $this->assertSame('maintenance', $slots['14:00']['state']);
        $this->assertSame(90000, $slots['16:00']['court_amount']);
        $this->assertSame(['starts_at', 'ends_at', 'state', 'available', 'court_amount'], array_keys($slots['10:00']));
        $response->assertJsonMissing(['reference' => $this->booking->reference])->assertJsonMissing(['email' => $this->customer->email]);
        $this->getJson('/api/v1/branches/'.$this->court->branch_id.'/schedule?date=2026-10-11')->assertUnprocessable();
    }

    public function test_receipt_and_booking_filters_respect_privacy_and_cancelled_balance(): void
    {
        Booking::factory()->create(['user_id' => $this->customer->id, 'status' => 'cancelled']);
        $this->actingAs($this->customer)->getJson('/api/v1/bookings?status=confirmed')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/bookings/'.$this->booking->id.'/receipt')->assertOk()->assertJsonPath('data.currency', 'VND')->assertJsonPath('data.customer.name', $this->customer->name)->assertJsonPath('data.collected', 0);
        $this->booking->update(['status' => 'cancelled', 'paid_amount' => 10000]);
        $this->getJson('/api/v1/bookings/'.$this->booking->id)->assertOk()->assertJsonPath('data.remaining_amount', 0)->assertJsonPath('data.refund_pending', true);
        $this->actingAs(User::factory()->create())->getJson('/api/v1/bookings/'.$this->booking->id.'/receipt')->assertForbidden();
    }

    public function test_cancelled_maintenance_cannot_be_marked_completed_later(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $this->court->branch_id]);
        $maintenance = Maintenance::factory()->create(['court_id' => $this->court->id, 'starts_at' => '2026-10-12 07:00', 'ends_at' => '2026-10-12 08:00', 'status' => 'cancelled']);
        $this->actingAs($manager)->patchJson('/api/v1/manage/maintenances/'.$maintenance->id, ['status' => 'completed'])->assertUnprocessable();
        $this->assertSame('cancelled', $maintenance->fresh()->status);
    }
}
