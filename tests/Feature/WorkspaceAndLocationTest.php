<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Court;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkspaceAndLocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 16)->setTime(9, 0));
        config(['ai.search.provider' => 'local']);
    }

    public function test_courts_are_paginated_filtered_and_scoped_to_assigned_branch(): void
    {
        $branch = Branch::factory()->create();
        Court::factory()->count(23)->create(['branch_id' => $branch->id]);
        Court::factory()->create(['branch_id' => $branch->id, 'name' => 'Sân VIP', 'status' => 'locked']);
        Court::factory()->create(['name' => 'Sân ngoài phạm vi']);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $branch->id]);
        $this->actingAs($manager)->getJson('/api/v1/manage/courts?branch_id='.$branch->id.'&page=2&per_page=10')->assertOk()->assertJsonPath('data.total', 24)->assertJsonCount(10, 'data.data')->assertJsonPath('data.last_page', 3);
        $this->getJson('/api/v1/manage/courts?branch_id='.$branch->id.'&search=VIP&status=locked&page=1')->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.name', 'Sân VIP');
        $this->getJson('/api/v1/manage/courts?branch_id='.Branch::factory()->create()->id.'&page=1')->assertForbidden();
        $this->getJson('/api/v1/manage/courts?per_page=1000&page=1')->assertUnprocessable();
    }

    public function test_ward_search_is_exact_for_manual_and_natural_language_queries(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $a = Court::factory()->create();
        $a->branch->update(['ward' => 'Phường Tân Phú']);
        $b = Court::factory()->create();
        $b->branch->update(['ward' => 'Phường Tân Phú Trung']);
        foreach (['Ngày mai từ 13h đến 15h ở phường Tân Phú', 'Ngày mai từ 13h đến 15h ở Tân Phú', 'Ngày mai từ 13h đến 15h khu vực phường Tân Phú đi nha'] as $message) {
            $this->postJson('/api/v1/ai/courts', ['message' => $message])->assertOk()->assertJsonPath('data.total_count', 1)->assertJsonPath('data.courts.0.court_id', $a->id);
        }
        $this->postJson('/api/v1/ai/courts', ['message' => 'Ngày mai từ 13h đến 15h ở phường Tân Phú Trung'])->assertOk()->assertJsonPath('data.total_count', 1)->assertJsonPath('data.courts.0.court_id', $b->id);
        $this->postJson('/api/v1/ai/courts', ['message' => 'Tìm sân', 'filters' => ['date' => '2026-10-17', 'start_time' => '13:00', 'end_time' => '15:00', 'ward' => 'tan phu']])->assertOk()->assertJsonPath('data.total_count', 1);
        $this->postJson('/api/v1/ai/courts', ['message' => 'Tìm sân', 'filters' => ['date' => '2026-10-17', 'start_time' => '13:00', 'end_time' => '15:00', 'ward' => []]])->assertUnprocessable();
        config(['ai.search.provider' => 'ollama']);
        Http::fake(['*/api/chat' => Http::response(['done' => true, 'message' => ['content' => json_encode(['date' => '2026-10-17', 'start_time' => '13:00', 'end_time' => '15:00', 'district' => 'Phường Tân Phú Trung', 'ward' => 'Phường Tân Phú Trung', 'max_budget' => null, 'amenities' => [], 'clarification' => null])]])]);
        $this->postJson('/api/v1/ai/courts', ['message' => 'Ngày mai 13h đến 15h ở phường Tân Phú'])->assertOk()->assertJsonPath('data.mode', 'model')->assertJsonPath('data.total_count', 1)->assertJsonPath('data.courts.0.court_id', $a->id);
    }

    public function test_area_name_in_branch_brand_does_not_override_the_actual_ward(): void
    {
        $a = Court::factory()->create();
        $a->branch->update(['ward' => 'Xã Củ Chi', 'district' => 'Tân Phú', 'name' => 'Sân Tân Phú Trung']);
        $this->postJson('/api/v1/ai/courts', ['message' => 'Ngày mai 13h đến 15h ở phường Tân Phú'])->assertOk()->assertJsonPath('data.total_count', 0);
        $this->postJson('/api/v1/ai/courts', ['message' => 'Ngày mai 13h đến 15h ở Tân Phú Trung'])->assertOk()->assertJsonPath('data.total_count', 0);
        $this->postJson('/api/v1/ai/courts', ['message' => 'Ngày mai 13h đến 15h ở TP.HCM'])->assertOk()->assertJsonPath('data.total_count', 1);
    }

    public function test_admin_and_manager_cannot_use_customer_account_or_book_for_themselves(): void
    {
        $court = Court::factory()->create();
        foreach (['admin', 'manager', 'staff', 'cashier'] as $role) {
            $user = User::factory()->create(['role' => $role, 'branch_id' => $court->branch_id]);
            $this->actingAs($user)->getJson('/api/v1/account')->assertForbidden();
            $this->postJson('/api/v1/bookings', ['request_key' => (string) Str::uuid(), 'court_id' => $court->id, 'starts_at' => '2026-10-17 13:00', 'ends_at' => '2026-10-17 14:00'])->assertUnprocessable()->assertJsonValidationErrors('user_id');
        }
        $this->getJson('/api/v1/admin/hub')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson('/api/v1/admin/hub')->assertOk();
        $this->actingAs(User::factory()->create())->getJson('/api/v1/admin/hub')->assertForbidden();
    }

    public function test_full_payment_covers_every_week_without_leaving_a_balance(): void
    {
        $court = Court::factory()->create();
        $customer = User::factory()->create(['wallet_balance' => 1000000]);
        $result = $this->actingAs($customer)->postJson('/api/v1/bookings/series', ['request_key' => (string) Str::uuid(), 'court_id' => $court->id, 'starts_at' => '2026-10-17 13:00', 'ends_at' => '2026-10-17 14:00', 'occurrences' => 4, 'pay_with_wallet' => true, 'deposit_only' => false])->assertCreated()->assertJsonCount(4, 'data');
        foreach ($result->json('data') as $booking) {
            $this->assertSame($booking['total_amount'], $booking['paid_amount']);
            $this->assertSame(0, $booking['remaining_amount']);
        }
        $this->assertSame(1000000 - array_sum(array_column($result->json('data'), 'paid_amount')), $customer->fresh()->wallet_balance);
    }

    public function test_directions_use_place_id_coordinates_and_ward_address(): void
    {
        $branch = Branch::factory()->create(['ward' => 'Phường Tân Phú', 'address' => '18 Đường số 1, Phường Tân Phú, Thành phố Hồ Chí Minh', 'latitude' => 10.8, 'longitude' => 106.6, 'google_place_id' => 'ChIJ-test']);
        $result = $this->getJson('/api/v1/branches/'.$branch->id)->assertOk()->assertJsonPath('data.ward', 'Phường Tân Phú')->json('data.directions_url');
        parse_str(parse_url($result, PHP_URL_QUERY), $query);
        $this->assertSame('1', $query['api']);
        $this->assertSame('ChIJ-test', $query['destination_place_id']);
        $this->assertStringContainsString('10.8', $query['destination']);
    }
}
