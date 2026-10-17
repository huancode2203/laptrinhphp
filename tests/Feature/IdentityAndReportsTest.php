<?php

namespace Tests\Feature;

use App\CommerceService;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Maintenance;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdentityAndReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_profile_and_admin_share_canonical_phone_and_email_validation(): void
    {
        $existing = User::factory()->create(['phone' => '0901 234 567']);
        $data = ['name' => 'Nguyễn Hoàng Nam', 'email' => 'NAM@BADMINTON.TEST', 'phone' => '+84 (901) 234-567', 'password' => 'Demo@12345', 'password_confirmation' => 'Demo@12345'];
        $this->postJson('/api/v1/register', $data)->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->postJson('/api/v1/register', [...$data, 'phone' => '0912.345.678'])->assertCreated()->assertJsonPath('data.phone', '+84912345678')->assertJsonPath('data.email', 'nam@badminton.test');
        $this->assertDatabaseHas('users', ['phone_normalized' => '+84912345678']);
        $this->assertDatabaseCount('users', 2);
        $this->patchJson('/api/v1/me', ['name' => 'Nguyễn Hoàng Nam', 'email' => 'nam@badminton.test', 'phone' => '0901234567'])->assertUnprocessable();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->postJson('/api/v1/admin/users', [...$data, 'email' => 'other@badminton.test', 'role' => 'customer'])->assertUnprocessable();
        $this->assertSame('+84901234567', $existing->fresh()->phone);
    }

    public function test_phone_validation_detects_legacy_formatted_duplicates_and_keeps_verified_equivalent_number(): void
    {
        $legacy = User::factory()->create(['phone' => null]);
        DB::table('users')->where('id', $legacy->id)->update(['phone' => '0901.234.567', 'phone_normalized' => null]);
        $this->postJson('/api/v1/register', ['name' => 'Lê Hải Anh', 'email' => 'anh@badminton.test', 'phone' => '0901234567', 'password' => 'Demo@12345', 'password_confirmation' => 'Demo@12345'])->assertUnprocessable();
        $user = User::factory()->create(['phone' => '0912345678', 'phone_verified_at' => now()]);
        $this->actingAs($user)->patchJson('/api/v1/me', ['name' => $user->name, 'email' => $user->email, 'phone' => '0912 345 678'])->assertOk();
        $this->assertNotNull($user->fresh()->phone_verified_at);
        $this->postJson('/api/v1/login', ['email' => '0912 345 678', 'password' => 'password'])->assertOk();
    }

    public function test_admin_contact_update_persists_without_erasing_secondary_branch_assignments(): void
    {
        $court = Court::factory()->create();
        $other = Court::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $court->branch_id]);
        DB::table('user_branches')->insert(['user_id' => $manager->id, 'branch_id' => $other->branch_id]);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patchJson('/api/v1/admin/users/'.$manager->id.'/role', ['role' => 'manager', 'branch_id' => (string) $court->branch_id, 'name' => 'Lê Hoàng Anh', 'email' => 'ANH@BADMINTON.TEST', 'phone' => '0901.234.567'])->assertOk()->assertJsonPath('data.name', 'Lê Hoàng Anh')->assertJsonPath('data.phone', '+84901234567');
        $this->assertDatabaseHas('user_branches', ['user_id' => $manager->id, 'branch_id' => $other->branch_id]);
        $this->getJson('/api/v1/admin/history/users?search=0901234567')->assertOk()->assertJsonPath('data.data.0.id', $manager->id)->assertJsonMissingPath('data.data.0.phone_normalized');
        User::factory()->create(['phone' => '0912345678']);
        $this->patchJson('/api/v1/admin/users/'.$manager->id.'/role', ['role' => 'customer', 'phone' => '0912 345 678'])->assertUnprocessable();
        $this->assertSame('manager', $manager->fresh()->role);
        $this->assertDatabaseHas('user_branches', ['user_id' => $manager->id, 'branch_id' => $other->branch_id]);
    }

    public function test_adding_products_after_deposit_never_reallocates_historical_receipts(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 17)->setTime(9, 0));
        $booking = Booking::factory()->create(['court_amount' => 100000, 'discount_amount' => 0, 'service_amount' => 0, 'product_amount' => 0, 'total_amount' => 100000, 'paid_amount' => 50000]);
        $payment = Payment::factory()->create(['booking_id' => $booking->id, 'amount' => 50000]);
        $before = app(ReportService::class)->data('2026-10-17', '2026-10-17', $booking->court->branch_id);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $booking->court->branch_id]);
        $product = Product::factory()->create(['branch_id' => $manager->branch_id, 'price' => 10000, 'stock' => 100]);
        $this->travel(1)->days();
        app(CommerceService::class)->sale($manager, ['request_key' => (string) Str::uuid(), 'branch_id' => $manager->branch_id, 'booking_id' => $booking->id, 'method' => 'booking', 'items' => [['product_id' => $product->id, 'quantity' => 2]]]);
        $after = app(ReportService::class)->data('2026-10-17', '2026-10-17', $manager->branch_id);
        $this->assertSame($before['by_product'], $after['by_product']);
        $this->assertSame(0, $after['pos_revenue']);
        $next = Payment::factory()->create(['booking_id' => $booking->id, 'amount' => 70000]);
        $this->assertSame(50000, array_sum(array_column($payment->allocation['items'], 'amount')));
        $this->assertSame(70000, array_sum(array_column($next->allocation['items'], 'amount')));
        $report = app(ReportService::class)->data('2026-10-18', '2026-10-18', $manager->branch_id);
        $this->assertSame(20000, $report['pos_revenue']);
        $refund = Payment::factory()->create(['booking_id' => $booking->id, 'kind' => 'refund', 'amount' => 120000]);
        $this->assertSame(120000, array_sum(array_column($refund->allocation['items'], 'amount')));
    }

    public function test_assigned_branch_summary_and_overlapping_downtime_are_scoped_correctly(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 17)->setTime(9, 0));
        $a = Court::factory()->create();
        $b = Court::factory()->create();
        $foreign = Court::factory()->create();
        $a->branch->update(['opens_minute' => 480, 'closes_minute' => 1080]);
        foreach ([$a, $b, $foreign] as $index => $court) {
            $booking = Booking::factory()->create(['court_id' => $court->id]);
            Payment::factory()->create(['booking_id' => $booking->id, 'amount' => ($index + 1) * 100000]);
        }
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $a->branch_id]);
        DB::table('user_branches')->insert(['user_id' => $manager->id, 'branch_id' => $b->branch_id]);
        $this->actingAs($manager)->getJson('/api/v1/manage/reports?from=2026-10-17&to=2026-10-17')->assertOk()->assertJsonPath('data.collected', 300000);
        $this->getJson('/api/v1/manage/reports?from=2026-10-17&to=2026-10-17&branch_id='.$foreign->branch_id)->assertForbidden();
        Maintenance::factory()->create(['court_id' => $a->id, 'starts_at' => '2026-10-17 09:00', 'ends_at' => '2026-10-17 11:00', 'status' => 'scheduled']);
        Maintenance::factory()->create(['court_id' => $a->id, 'starts_at' => '2026-10-17 10:00', 'ends_at' => '2026-10-17 12:00', 'status' => 'scheduled']);
        Maintenance::factory()->create(['court_id' => $a->id, 'starts_at' => '2026-10-17 13:00', 'ends_at' => '2026-10-17 14:00', 'status' => 'completed']);
        Maintenance::factory()->create(['court_id' => $a->id, 'starts_at' => '2026-10-17 14:00', 'ends_at' => '2026-10-17 15:00', 'status' => 'cancelled']);
        $report = app(ReportService::class)->data('2026-10-17', '2026-10-17', $a->branch_id);
        $this->assertSame(360, $report['occupancy'][0]['available_minutes']);
    }
}
