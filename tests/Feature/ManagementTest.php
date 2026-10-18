<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Maintenance;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Court $court;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 12)->setTime(9, 0));
        $this->court = Court::factory()->create();
        $this->manager = User::factory()->create(['role' => 'manager', 'branch_id' => $this->court->branch_id]);
        $this->booking = Booking::factory()->create(['court_id' => $this->court->id, 'starts_at' => '2026-10-12 10:00', 'ends_at' => '2026-10-12 11:00']);
    }

    private function pay(array $overrides = []): array
    {
        return array_replace(['request_key' => (string) Str::uuid(), 'method' => 'cash', 'amount' => 100000], $overrides);
    }

    public function test_customer_and_other_branch_manager_cannot_record_payments(): void
    {
        $customer = User::factory()->create();
        $url = '/api/v1/manage/bookings/'.$this->booking->id.'/payments';
        $this->actingAs($customer)->postJson($url, $this->pay())->assertForbidden();
        $otherManager = User::factory()->create(['role' => 'manager', 'branch_id' => Court::factory()->create()->branch_id]);
        $this->actingAs($otherManager)->postJson($url, $this->pay())->assertForbidden();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_payment_is_idempotent_and_overpayment_is_rejected(): void
    {
        $url = '/api/v1/manage/bookings/'.$this->booking->id.'/payments';
        $data = $this->pay();
        $this->actingAs($this->manager)->postJson($url, $data)->assertOk();
        $this->postJson($url, $data)->assertOk();
        $this->postJson($url, $this->pay(['amount' => 1]))->assertUnprocessable();
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(100000, $this->booking->fresh()->paid_amount);
    }

    public function test_check_in_requires_full_payment_and_correct_time_then_completion_requires_end_time(): void
    {
        $url = '/api/v1/bookings/'.$this->booking->id.'/status';
        $this->actingAs($this->manager)->patchJson($url, ['status' => 'checked_in'])->assertUnprocessable();
        $this->postJson('/api/v1/manage/bookings/'.$this->booking->id.'/payments', $this->pay())->assertOk();
        $this->patchJson($url, ['status' => 'checked_in'])->assertUnprocessable();
        $this->travelTo(now()->setTime(9, 30));
        $this->patchJson($url, ['status' => 'checked_in'])->assertOk()->assertJsonPath('data.status', 'checked_in');
        $this->patchJson($url, ['status' => 'completed'])->assertUnprocessable();
        $this->travelTo(now()->setTime(11, 0));
        $this->patchJson($url, ['status' => 'completed'])->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertNotNull($this->booking->fresh()->checked_in_at);
    }

    public function test_paid_booking_cannot_be_cancelled_or_refunded_and_revenue_is_preserved(): void
    {
        $url = '/api/v1/manage/bookings/'.$this->booking->id.'/payments';
        $this->actingAs($this->manager)->postJson($url, $this->pay())->assertOk();
        $this->postJson($url, $this->pay(['kind' => 'refund']))->assertUnprocessable();
        $this->patchJson('/api/v1/bookings/'.$this->booking->id.'/status', ['status' => 'cancelled'])->assertUnprocessable();
        $this->postJson($url, $this->pay(['kind' => 'refund']))->assertUnprocessable();
        $this->postJson($url, $this->pay(['kind' => 'refund', 'amount' => 1]))->assertUnprocessable();
        $this->getJson('/api/v1/manage/reports?from=2026-10-12&to=2026-10-12')->assertOk()->assertJsonPath('data.collected', 100000)->assertJsonPath('data.refunded', 0)->assertJsonPath('data.net_revenue', 100000)->assertJsonPath('data.daily.0.revenue', 100000);
        $this->assertSame(100000, $this->booking->fresh()->paid_amount);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'booking.refund', 'subject_id' => $this->booking->id]);
    }

    public function test_manager_calendar_does_not_include_other_branch_bookings(): void
    {
        $other = Booking::factory()->create(['starts_at' => '2026-10-12 10:00', 'ends_at' => '2026-10-12 11:00']);
        $this->actingAs($this->manager)->getJson('/api/v1/manage/calendar?date=2026-10-12')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->booking->id);
        $this->getJson('/api/v1/manage/booking-search?reference='.$other->reference)->assertNotFound();
    }

    public function test_manager_cannot_edit_other_branch_court_service_or_admin_role(): void
    {
        $other = Court::factory()->create();
        $this->actingAs($this->manager)->putJson('/api/v1/manage/courts/'.$other->id, [
            'branch_id' => $other->branch_id, 'name' => 'Sân đổi tên', 'status' => 'active',
            'hourly_rate' => 100000, 'peak_rate' => 180000, 'weekend_rate' => 200000,
        ])->assertForbidden();
        $service = Service::factory()->create(['branch_id' => $other->branch_id]);
        $this->putJson('/api/v1/manage/services/'.$service->id, ['branch_id' => $other->branch_id, 'name' => 'Nước', 'unit' => 'chai', 'price' => 10000, 'active' => true])->assertForbidden();
        $this->patchJson('/api/v1/admin/users/'.$this->manager->id.'/role', ['role' => 'admin'])->assertForbidden();
    }

    public function test_maintenance_cannot_overlap_booking_and_blocks_future_reservations(): void
    {
        $data = ['court_id' => $this->court->id, 'starts_at' => '2026-10-12 10:00', 'ends_at' => '2026-10-12 11:00', 'reason' => 'Sửa sàn'];
        $this->actingAs($this->manager)->postJson('/api/v1/manage/maintenances', $data)->assertUnprocessable();
        $this->postJson('/api/v1/manage/maintenances', array_replace($data, ['starts_at' => '2026-10-12 12:00', 'ends_at' => '2026-10-12 13:00']))->assertCreated();
        $customer = User::factory()->create();
        $this->actingAs($customer)->postJson('/api/v1/bookings', ['request_key' => (string) Str::uuid(), 'court_id' => $this->court->id, 'starts_at' => '2026-10-12 12:00', 'ends_at' => '2026-10-12 13:00'])->assertUnprocessable();
        $maintenance = Maintenance::firstOrFail();
        $this->actingAs($this->manager)->patchJson('/api/v1/manage/maintenances/'.$maintenance->id, ['status' => 'completed'])->assertUnprocessable();
    }

    public function test_court_with_active_booking_cannot_be_locked(): void
    {
        $this->actingAs($this->manager)->putJson('/api/v1/manage/courts/'.$this->court->id, [
            'branch_id' => $this->court->branch_id, 'name' => $this->court->name, 'status' => 'locked',
            'hourly_rate' => 100000, 'peak_rate' => 180000, 'weekend_rate' => 200000,
        ])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertSame('active', $this->court->fresh()->status);
    }
}
