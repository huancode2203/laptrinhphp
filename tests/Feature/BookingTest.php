<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Court;
use App\Models\Maintenance;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private Court $court;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 12)->setTime(9, 0));
        $this->customer = User::factory()->create();
        $this->court = Court::factory()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'request_key' => (string) Str::uuid(), 'court_id' => $this->court->id,
            'starts_at' => '2026-10-12 10:00', 'ends_at' => '2026-10-12 11:00',
        ], $overrides);
    }

    public function test_guest_cannot_book_or_read_private_bookings(): void
    {
        $this->postJson('/api/v1/bookings', $this->payload())->assertUnauthorized();
        $this->getJson('/api/v1/bookings')->assertUnauthorized();
        $this->get('/api/v1/bookings')->assertUnauthorized();
    }

    public function test_server_calculates_peak_boundary_and_service_prices(): void
    {
        $service = Service::factory()->create(['branch_id' => $this->court->branch_id, 'price' => 10000]);
        $this->actingAs($this->customer)->postJson('/api/v1/bookings', $this->payload([
            'starts_at' => '2026-10-12 15:30', 'ends_at' => '2026-10-12 16:30',
            'court_amount' => 1, 'total_amount' => 1, 'status' => 'completed', 'paid_amount' => 1,
            'services' => [['id' => $service->id, 'quantity' => 2]],
        ]))->assertCreated()->assertJsonPath('data.court_amount', 140000)->assertJsonPath('data.service_amount', 20000)
            ->assertJsonPath('data.total_amount', 160000)->assertJsonPath('data.status', 'confirmed')->assertJsonPath('data.paid_amount', 0);
        $this->assertDatabaseHas('booking_services', ['name' => $service->name, 'unit_price' => 10000, 'quantity' => 2]);
    }

    public function test_weekend_rate_is_used(): void
    {
        $this->actingAs($this->customer)->postJson('/api/v1/bookings', $this->payload([
            'starts_at' => '2026-10-17 10:00', 'ends_at' => '2026-10-17 12:00',
        ]))->assertCreated()->assertJsonPath('data.court_amount', 400000);
    }

    public function test_overlapping_bookings_are_rejected_and_adjacent_slots_are_allowed(): void
    {
        $this->actingAs($this->customer)->postJson('/api/v1/bookings', $this->payload())->assertCreated();
        $this->postJson('/api/v1/bookings', $this->payload(['starts_at' => '2026-10-12 10:30', 'ends_at' => '2026-10-12 11:30']))->assertUnprocessable()->assertJsonValidationErrors('starts_at');
        $this->postJson('/api/v1/bookings', $this->payload(['starts_at' => '2026-10-12 11:00', 'ends_at' => '2026-10-12 12:00']))->assertCreated();
        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_same_request_is_idempotent_and_changed_payload_is_rejected(): void
    {
        $data = $this->payload();
        $first = $this->actingAs($this->customer)->postJson('/api/v1/bookings', $data)->assertCreated();
        $this->postJson('/api/v1/bookings', $data)->assertOk()->assertJsonPath('data.id', $first->json('data.id'));
        $this->postJson('/api/v1/bookings', array_replace($data, ['notes' => 'Khác']))->assertUnprocessable()->assertJsonValidationErrors('request_key');
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_maintenance_and_locked_courts_cannot_be_booked(): void
    {
        Maintenance::factory()->create(['court_id' => $this->court->id, 'starts_at' => '2026-10-12 10:00', 'ends_at' => '2026-10-12 11:00']);
        $this->actingAs($this->customer)->postJson('/api/v1/bookings', $this->payload())->assertUnprocessable();
        $this->court->update(['status' => 'locked']);
        $this->postJson('/api/v1/bookings', $this->payload(['starts_at' => '2026-10-12 12:00', 'ends_at' => '2026-10-12 13:00']))->assertUnprocessable()->assertJsonValidationErrors('court_id');
    }

    public function test_invalid_hours_past_dates_and_other_branch_services_are_rejected(): void
    {
        $this->actingAs($this->customer)->postJson('/api/v1/bookings', $this->payload(['starts_at' => '2026-10-12 08:00', 'ends_at' => '2026-10-12 09:00']))->assertUnprocessable();
        $this->postJson('/api/v1/bookings', $this->payload(['starts_at' => '2026-10-12 10:15']))->assertUnprocessable();
        $otherService = Service::factory()->create();
        $this->postJson('/api/v1/bookings', $this->payload(['services' => [['id' => $otherService->id, 'quantity' => 1]]]))->assertUnprocessable()->assertJsonValidationErrors('services');
        $this->postJson('/api/v1/bookings', $this->payload(['services' => [['id' => $otherService->id, 'quantity' => -1]]]))->assertUnprocessable();
    }

    public function test_customer_cannot_impersonate_others_or_view_their_reservations(): void
    {
        $other = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $other->id]);
        $this->actingAs($this->customer)->postJson('/api/v1/bookings', $this->payload(['user_id' => $other->id]))->assertForbidden();
        $this->getJson('/api/v1/bookings/'.$booking->id)->assertForbidden();
        $this->patchJson('/api/v1/bookings/'.$booking->id.'/status', ['status' => 'cancelled'])->assertForbidden();
    }

    public function test_cancellation_releases_slot_and_cannot_change_paid_or_checked_in_bookings(): void
    {
        $created = $this->actingAs($this->customer)->postJson('/api/v1/bookings', $this->payload())->json('data.id');
        $this->patchJson('/api/v1/bookings/'.$created.'/status', ['status' => 'cancelled'])->assertOk();
        $this->postJson('/api/v1/bookings', $this->payload())->assertCreated();
        $paid = Booking::factory()->create(['user_id' => $this->customer->id, 'paid_amount' => 100000]);
        $this->patchJson('/api/v1/bookings/'.$paid->id.'/status', ['status' => 'cancelled'])->assertUnprocessable();
    }

    public function test_public_availability_has_no_customer_information(): void
    {
        Booking::factory()->create(['court_id' => $this->court->id, 'starts_at' => '2026-10-12 10:00', 'ends_at' => '2026-10-12 11:00']);
        $response = $this->getJson('/api/v1/branches/'.$this->court->branch_id.'/availability?starts_at=2026-10-12%2010:00&ends_at=2026-10-12%2011:00');
        $response->assertOk()->assertJsonPath('data.0.available', false);
        $this->assertSame(['id', 'name', 'available', 'court_amount'], array_keys($response->json('data.0')));
    }

    public function test_registration_cannot_assign_admin_role_and_login_has_rate_limit(): void
    {
        $this->postJson('/api/v1/register', ['name' => 'Người mới', 'email' => 'new@example.test', 'password' => 'Test@12345', 'password_confirmation' => 'Test@12345', 'role' => 'admin'])->assertCreated()->assertJsonPath('data.role', 'customer');
        $this->postJson('/api/v1/logout')->assertOk();
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/v1/login', ['email' => 'new@example.test', 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson('/api/v1/login', ['email' => 'new@example.test', 'password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_write_requests_require_csrf_and_valid_token_allows_login(): void
    {
        $this->app['env'] = 'local';
        $this->customer->update(['password' => 'Test@12345']);
        $credentials = ['email' => $this->customer->email, 'password' => 'Test@12345'];
        $this->postJson('/api/v1/login', $credentials)->assertStatus(419);
        $token = 'valid-test-session-token';
        $this->withSession(['_token' => $token])->postJson('/api/v1/login', $credentials, ['X-CSRF-TOKEN' => $token])->assertOk();
    }

    public function test_demo_seed_is_repeatable_and_creates_design_branch_and_court_counts(): void
    {
        $this->seed();
        $this->seed();
        $this->assertSame(8, Branch::where('name', 'like', 'Badminton Hub – %')->count());
        $this->assertSame(77, Court::whereHas('branch', fn ($q) => $q->where('name', 'like', 'Badminton Hub – %'))->count());
    }
}
