<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CourtOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 16)->setTime(9, 0));
    }

    public function test_services_added_during_play_are_full_price_idempotent_and_cannot_change_a_closed_bill(): void
    {
        $court = Court::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $court->branch_id]);
        $booking = Booking::factory()->create(['court_id' => $court->id, 'starts_at' => '2026-10-16 08:00', 'ends_at' => '2026-10-16 10:00', 'status' => 'checked_in', 'court_amount' => 100000, 'discount_amount' => 20000, 'service_amount' => 0, 'product_amount' => 0, 'total_amount' => 80000, 'paid_amount' => 80000]);
        $service = Service::factory()->create(['branch_id' => $court->branch_id, 'price' => 10000]);
        $payload = ['request_key' => (string) Str::uuid(), 'services' => [['id' => $service->id, 'quantity' => 2]], 'discount_amount' => 999999];
        $path = '/api/v1/manage/bookings/'.$booking->id.'/services';
        $this->actingAs($manager)->postJson($path, $payload)->assertOk()->assertJsonPath('data.total_amount', 100000)->assertJsonPath('data.discount_amount', 20000)->assertJsonPath('data.remaining_amount', 20000);
        $this->postJson($path, $payload)->assertOk()->assertJsonPath('data.service_amount', 20000);
        $this->assertDatabaseHas('booking_services', ['booking_id' => $booking->id, 'quantity' => 2, 'amount' => 20000]);
        $this->postJson($path, [...$payload, 'services' => [['id' => $service->id, 'quantity' => 3]]])->assertConflict();
        $booking->update(['status' => 'completed']);
        $this->postJson($path, [...$payload, 'request_key' => (string) Str::uuid()])->assertUnprocessable();
        $this->actingAs($booking->user)->postJson($path, $payload)->assertForbidden();
    }

    public function test_manager_can_move_a_deposit_booking_only_to_a_free_court_in_same_branch(): void
    {
        $court = Court::factory()->create(['hourly_rate' => 100000]);
        $target = Court::factory()->create(['branch_id' => $court->branch_id, 'hourly_rate' => 100000]);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $court->branch_id]);
        $booking = Booking::factory()->create(['court_id' => $court->id, 'starts_at' => '2026-10-16 10:00', 'ends_at' => '2026-10-16 11:00', 'total_amount' => 100000, 'paid_amount' => 50000]);
        Booking::factory()->create(['court_id' => $target->id, 'starts_at' => '2026-10-16 13:00', 'ends_at' => '2026-10-16 14:00']);
        $path = '/api/v1/bookings/'.$booking->id.'/reschedule';
        $payload = ['court_id' => $target->id, 'starts_at' => '2026-10-16 13:00', 'ends_at' => '2026-10-16 14:00'];
        $this->actingAs($manager)->patchJson($path, $payload)->assertUnprocessable();
        $this->patchJson($path, [...$payload, 'court_id' => Court::factory()->create()->id])->assertForbidden();
        $this->patchJson($path, [...$payload, 'starts_at' => '2026-10-16 14:00', 'ends_at' => '2026-10-16 15:00'])->assertOk()->assertJsonPath('data.court_id', $target->id)->assertJsonPath('data.paid_amount', 50000);
        $this->getJson('/api/v1/branches/'.$court->branch_id.'/availability?starts_at=2026-10-16T14:00&ends_at=2026-10-16T15:00&exclude_booking_id='.$booking->id)->assertOk()->assertJsonPath('data.1.available', true);
        $this->actingAs(User::factory()->create())->getJson('/api/v1/branches/'.$court->branch_id.'/availability?starts_at=2026-10-16T14:00&ends_at=2026-10-16T15:00&exclude_booking_id='.$booking->id)->assertForbidden();
    }

    public function test_counter_customer_creation_detects_equivalent_phone_and_email(): void
    {
        $court = Court::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $court->branch_id]);
        User::factory()->create(['phone' => '+84901234567', 'email' => 'existing@badminton.test']);
        $data = ['branch_id' => $court->branch_id, 'name' => 'Trần Hải Nam', 'phone' => '0901234567', 'email' => 'new@badminton.test'];
        $this->actingAs($manager)->postJson('/api/v1/manage/customers', $data)->assertUnprocessable();
        $this->getJson('/api/v1/manage/customers?branch_id='.$court->branch_id.'&search=0901234567')->assertOk()->assertJsonPath('data.0.phone', '+84901234567');
        $this->postJson('/api/v1/manage/customers', [...$data, 'phone' => '0912345678', 'email' => 'EXISTING@badminton.test'])->assertUnprocessable();
        $this->postJson('/api/v1/manage/customers', [...$data, 'phone' => '0912345678', 'skill_level' => 'Trung cấp'])->assertCreated()->assertJsonPath('data.name', 'Trần Hải Nam')->assertJsonPath('data.skill_level', 'Trung cấp');
    }
}
