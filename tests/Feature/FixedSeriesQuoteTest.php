<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FixedSeriesQuoteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 12)->setTime(9, 0));
    }

    private function payload(Court $court, array $overrides = []): array
    {
        return array_replace(['request_key' => (string) Str::uuid(), 'court_id' => $court->id, 'starts_at' => '2026-10-13 13:00', 'ends_at' => '2026-10-13 15:00', 'fixed' => true, 'occurrences' => 4], $overrides);
    }

    public function test_series_total_services_discount_and_deposit_match_all_created_bookings(): void
    {
        $court = Court::factory()->create();
        $customer = User::factory()->create();
        $service = Service::factory()->create(['branch_id' => $court->branch_id, 'price' => 10000]);
        $data = $this->payload($court, ['services' => [['id' => $service->id, 'quantity' => 2]]]);
        $quote = $this->actingAs($customer)->postJson('/api/v1/courts/'.$court->id.'/quote', $data)->assertOk()
            ->assertJsonPath('data.occurrences', 4)->assertJsonCount(4, 'data.sessions')
            ->assertJsonPath('data.court_amount', 800000)->assertJsonPath('data.service_amount', 80000)
            ->assertJsonPath('data.fixed_discount_amount', 120000)->assertJsonPath('data.total_amount', 760000)
            ->assertJsonPath('data.deposit_amount', 380000)->json('data');
        $this->assertDatabaseCount('bookings', 0);
        $this->postJson('/api/v1/bookings/series', $data)->assertCreated()->assertJsonCount(4, 'data');
        foreach (['court_amount', 'service_amount', 'discount_amount', 'total_amount', 'deposit_amount'] as $field) {
            $this->assertEquals($quote[$field], Booking::sum($field));
        }
    }

    public function test_date_specific_rates_and_per_booking_deposit_rounding_are_not_multiplied_from_first_week(): void
    {
        $court = Court::factory()->create(['hourly_rate' => 100001]);
        $customer = User::factory()->create(['wallet_balance' => 1000000]);
        DB::table('pricing_rules')->insert(['branch_id' => $court->branch_id, 'court_id' => $court->id, 'name' => 'Giá buổi thứ hai', 'date' => '2026-10-20', 'from_minute' => 780, 'to_minute' => 900, 'hourly_rate' => 200001, 'priority' => 10, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $data = $this->payload($court, ['ends_at' => '2026-10-13 14:30', 'occurrences' => 2, 'pay_with_wallet' => true, 'deposit_only' => true]);
        $quote = $this->actingAs($customer)->postJson('/api/v1/courts/'.$court->id.'/quote', $data)->assertOk()
            ->assertJsonPath('data.sessions.0.total_amount', 127503)->assertJsonPath('data.sessions.1.total_amount', 255003)
            ->assertJsonPath('data.total_amount', 382506)->assertJsonPath('data.deposit_amount', 191254)->json('data');
        $this->postJson('/api/v1/bookings/series', $data)->assertCreated();
        $this->assertEquals($quote['total_amount'], Booking::sum('total_amount'));
        $this->assertEquals($quote['deposit_amount'], DB::table('payments')->sum('amount'));
        $this->assertEquals(1000000 - $quote['deposit_amount'], $customer->refresh()->wallet_balance);
    }

    public function test_membership_and_fixed_discounts_are_shown_separately_and_counter_deposit_is_zero(): void
    {
        $court = Court::factory()->create();
        $customer = User::factory()->create();
        $plan = MembershipPlan::factory()->create(['discount_percent' => 10]);
        Membership::factory()->create(['user_id' => $customer->id, 'membership_plan_id' => $plan->id]);
        $data = $this->payload($court, ['occurrences' => 2]);
        $this->actingAs($customer)->postJson('/api/v1/courts/'.$court->id.'/quote', $data)->assertOk()
            ->assertJsonPath('data.other_discount_amount', 40000)->assertJsonPath('data.fixed_discount_amount', 60000)
            ->assertJsonPath('data.total_amount', 300000)->assertJsonPath('data.deposit_amount', 150000);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $court->branch_id]);
        $this->actingAs($manager)->postJson('/api/v1/courts/'.$court->id.'/quote', $data + ['user_id' => $customer->id])->assertOk()
            ->assertJsonPath('data.total_amount', 300000)->assertJsonPath('data.deposit_amount', 0);
        $this->postJson('/api/v1/bookings/series', $data + ['user_id' => $customer->id])->assertCreated();
        $this->assertEquals(300000, Booking::sum('total_amount'));
        $this->assertEquals(0, Booking::sum('deposit_amount'));
    }

    public function test_later_conflict_identifies_the_session_and_series_creation_rolls_back(): void
    {
        $court = Court::factory()->create();
        Booking::factory()->create(['court_id' => $court->id, 'starts_at' => '2026-10-27 13:00', 'ends_at' => '2026-10-27 15:00']);
        $this->actingAs(User::factory()->create());
        $data = $this->payload($court);
        foreach (['courts/'.$court->id.'/quote', 'bookings/series'] as $path) {
            $error = $this->postJson('/api/v1/'.$path, $data)->assertUnprocessable()->json('errors.starts_at.0');
            $this->assertStringContainsString('Buổi thứ 3 (27/10/2026)', $error);
        }
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_all_sessions_must_fit_the_booking_horizon_and_weeks_must_be_valid(): void
    {
        $court = Court::factory()->create();
        $court->branch->update(['booking_days' => 14]);
        $this->actingAs(User::factory()->create());
        $error = $this->postJson('/api/v1/courts/'.$court->id.'/quote', $this->payload($court))->assertUnprocessable()->json('errors.starts_at.0');
        $this->assertStringContainsString('Buổi thứ 3', $error);
        $this->assertStringContainsString('26/10/2026', $error);
        foreach ([1, 13, 2.5, [], 'abc'] as $weeks) {
            $this->postJson('/api/v1/courts/'.$court->id.'/quote', $this->payload($court, ['occurrences' => $weeks]))->assertUnprocessable()->assertJsonValidationErrors('occurrences');
        }
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_fixed_coupon_error_does_not_claim_a_hard_coded_discount(): void
    {
        $court = Court::factory()->create();
        $court->branch->update(['fixed_discount' => 20]);
        $this->actingAs(User::factory()->create());
        foreach (['courts/'.$court->id.'/quote', 'bookings/series'] as $path) {
            $message = $this->postJson('/api/v1/'.$path, $this->payload($court, ['coupon_code' => 'ANY']))->assertUnprocessable()->json('message');
            $this->assertStringContainsString('ưu đãi riêng', $message);
            $this->assertStringNotContainsString('15%', $message);
        }
    }

    public function test_past_community_time_and_invalid_auth_fields_have_actionable_vietnamese_messages(): void
    {
        $court = Court::factory()->create();
        $this->actingAs(User::factory()->create());
        $error = $this->postJson('/api/v1/community/posts', ['branch_id' => $court->branch_id, 'title' => 'Giao lưu cầu lông', 'content' => 'Tìm người chơi cùng chiều nay.', 'type' => 'Giao lưu', 'skill_level' => 'Mọi trình độ', 'starts_at' => '2026-10-11 13:00', 'capacity' => 2])->assertUnprocessable()->json('errors.starts_at.0');
        $this->assertStringContainsString('thời điểm hiện tại', $error);
        $this->assertStringNotContainsString('now', $error);
        $error = $this->postJson('/api/v1/bookings', $this->payload($court, ['ends_at' => '2026-10-13 12:00']))->assertUnprocessable()->json('errors.ends_at.0');
        $this->assertStringContainsString('sau giờ bắt đầu', $error);
        $this->assertStringNotContainsString('starts_at', $error);
        $this->postJson('/api/v1/register', ['name' => 'Lê Minh Anh', 'email' => 'minhanh@example.test', 'password' => 'Test@12345', 'password_confirmation' => 'Other@12345'])->assertUnprocessable()->assertJsonPath('errors.password.0', 'Hai mật khẩu chưa giống nhau. Vui lòng nhập lại mật khẩu xác nhận.');

    }
}
