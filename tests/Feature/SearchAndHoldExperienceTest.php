<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\CourtHold;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class SearchAndHoldExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 18)->setTime(8, 0));
        Http::preventStrayRequests();
    }

    public function test_released_holds_do_not_consume_the_two_attempt_quota(): void
    {
        $court = Court::factory()->create();
        $owner = User::factory()->create();
        $this->actingAs($owner);
        for ($i = 0; $i < 5; $i++) {
            $id = $this->postJson('/api/v1/holds', ['court_id' => $court->id, 'starts_at' => '2026-10-19T13:00:00+07:00', 'ends_at' => '2026-10-19T15:00:00+07:00', 'request_key' => (string) Str::uuid()])->assertOk()->json('data.id');
            $this->deleteJson('/api/v1/holds/'.$id)->assertOk();
            $this->getJson('/api/v1/holds/active')->assertJsonPath('data', null);
        }
        $this->assertSame(0, CourtHold::where('released', false)->count());
    }

    public function test_expired_unreleased_holds_still_limit_abuse_and_a_foreign_user_cannot_release_them(): void
    {
        $court = Court::factory()->create();
        $owner = User::factory()->create();
        CourtHold::factory()->count(2)->create(['court_id' => $court->id, 'user_id' => $owner->id, 'expires_at' => now()->subMinute(), 'created_at' => now()->subMinutes(7), 'released' => false]);
        $this->actingAs($owner)->postJson('/api/v1/holds', ['court_id' => $court->id, 'starts_at' => '2026-10-19T13:00:00+07:00', 'ends_at' => '2026-10-19T15:00:00+07:00', 'request_key' => (string) Str::uuid()])->assertUnprocessable();
        $id = CourtHold::first()->id;
        $this->actingAs(User::factory()->create())->deleteJson('/api/v1/holds/'.$id)->assertForbidden();
    }

    public function test_search_keeps_explicit_times_and_returns_personal_favorite_state(): void
    {
        $court = Court::factory()->create();
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $filters = ['date' => '2026-10-19', 'start_time' => '13:00', 'end_time' => '15:00', 'district' => '', 'amenities' => []];
        $this->putJson('/api/v1/courts/'.$court->id.'/favorite', ['active' => true])->assertOk();
        $response = $this->postJson('/api/v1/ai/courts', ['message' => 'Hôm nay 8 giờ', 'filters' => $filters])->assertOk()->assertJsonPath('data.filters.date', '2026-10-19')->assertJsonPath('data.filters.start_time', '13:00')->assertJsonPath('data.filters.end_time', '15:00');
        $found = collect($response->json('data.courts'))->firstWhere('court_id', $court->id);
        $this->assertTrue($found['is_favorite']);
        $this->putJson('/api/v1/courts/'.$court->id.'/favorite', ['active' => false])->assertOk();
        $response = $this->postJson('/api/v1/ai/courts', ['message' => 'Tìm sân', 'filters' => $filters])->assertOk();
        $this->assertFalse(collect($response->json('data.courts'))->firstWhere('court_id', $court->id)['is_favorite']);
        Http::assertNothingSent();
    }

    public function test_search_pagination_reaches_every_result_without_reinterpreting_the_selected_time(): void
    {
        Court::factory()->count(7)->create();
        $filters = ['date' => '2026-10-19', 'start_time' => '13:00', 'end_time' => '15:00', 'district' => '', 'amenities' => []];
        $ids = [];
        for ($page = 1; $page <= 3; $page++) {
            $result = $this->postJson('/api/v1/ai/courts', ['message' => 'Hôm nay lúc 8h', 'filters' => $filters, 'page' => $page, 'per_page' => 3])->assertOk()->assertJsonPath('data.current_page', $page)->assertJsonPath('data.last_page', 3)->assertJsonPath('data.total_count', 7)->assertJsonPath('data.filters.start_time', '13:00')->json('data.courts');
            $ids = [...$ids, ...array_column($result, 'court_id')];
        }
        $this->assertCount(7, array_unique($ids));
        $this->assertCount(7, $ids);
        $this->postJson('/api/v1/ai/courts', ['message' => 'Tìm sân', 'filters' => $filters, 'page' => 0])->assertUnprocessable();
        $this->postJson('/api/v1/ai/courts', ['message' => 'Tìm sân', 'filters' => $filters, 'per_page' => 25])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_filter_pagination_has_a_separate_limit_and_cannot_bypass_model_chat_limits(): void
    {
        config(['ai.search.provider' => 'local']);
        Court::factory()->create();
        $this->actingAs(User::factory()->create());
        $filters = ['date' => '2026-10-19', 'start_time' => '13:00', 'end_time' => '15:00'];
        for ($i = 0; $i < 8; $i++) {
            $this->postJson('/api/v1/ai/courts', ['message' => 'Tìm sân', 'filters' => $filters, 'page' => 1])->assertOk();
        }
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/v1/ai/courts', ['message' => 'Ngày mai từ 13h đến 15h'])->assertOk();
        }
        $this->postJson('/api/v1/ai/chat', ['message' => 'Tìm sân', 'request_key' => (string) Str::uuid(), 'filters' => $filters])->assertStatus(429);
        $this->postJson('/api/v1/ai/courts', ['message' => 'Tìm sân', 'filters' => $filters])->assertOk();
        $this->assertDatabaseCount('ai_search_turns', 0);
        Http::assertNothingSent();
    }
}
