<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Court;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AiNaturalLanguageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 22)->setTime(16, 0));
        config(['ai.search.provider' => 'local']);
        Http::preventStrayRequests();
        Court::factory()->create(['branch_id' => Branch::factory()->create(['ward' => 'Phường Tân Phú', 'opens_minute' => 360, 'closes_minute' => 1380, 'booking_days' => 30])->id]);
    }

    private function chat(string $message): TestResponse
    {
        return $this->postJson('/api/v1/ai/chat', ['request_key' => (string) Str::uuid(), 'message' => $message])->assertCreated();
    }

    public function test_next_weekend_at_ten_pm_for_two_hours_asks_which_day_in_both_interfaces(): void
    {
        $message = 'Cuối tuần sau 10h tối đánh 2 tiếng';
        $result = $this->postJson('/api/v1/ai/courts', ['message' => $message])->assertOk()->json('data');
        $this->assertTrue($result['needs_clarification']);
        $this->assertSame('22:00', $result['filters']['start_time']);
        $this->assertSame('00:00', $result['filters']['end_time']);
        $this->assertSame(['2026-11-28', '2026-11-29'], $result['filters']['date_options']);
        $this->assertStringContainsString('28/11', $result['message']);
        $this->assertStringContainsString('29/11', $result['message']);
        $this->assertStringNotContainsString('đã qua', $result['message']);
        $this->actingAs(User::factory()->create());
        $this->chat($message)->assertJsonPath('data.1.result.filters.start_time', '22:00')->assertJsonPath('data.1.result.needs_clarification', true);
    }

    public function test_weekend_follow_up_keeps_hours_explains_midnight_and_can_adjust_to_an_open_window(): void
    {
        $this->actingAs(User::factory()->create());
        $this->chat('Cuối tuần sau 10h tối đánh 2 tiếng');
        $reply = $this->chat('Thứ bảy nhé')->assertJsonPath('data.1.result.filters.date', '2026-11-28')->json('data.1.content');
        $this->assertStringContainsString('00:00', $reply);
        $this->assertStringNotContainsString('đã qua', $reply);
        $this->chat('Đổi sang 8h tối, chơi 2 tiếng')->assertJsonPath('data.1.result.filters.date', '2026-11-28')->assertJsonPath('data.1.result.filters.start_time', '20:00')->assertJsonPath('data.1.result.filters.end_time', '22:00')->assertJsonPath('data.1.result.needs_clarification', false)->assertJsonCount(1, 'data.1.result.courts');
    }

    public function test_named_weekday_next_week_and_duration_find_actual_courts(): void
    {
        $this->postJson('/api/v1/ai/courts', ['message' => 'Thứ bảy tuần sau lúc 8h tối đánh 2 giờ ở phường Tân Phú'])->assertOk()->assertJsonPath('data.filters.date', '2026-11-28')->assertJsonPath('data.filters.start_time', '20:00')->assertJsonPath('data.filters.end_time', '22:00')->assertJsonPath('data.total_count', 1);
    }

    public function test_day_after_tomorrow_half_hour_clock_and_minutes_are_understood(): void
    {
        $this->postJson('/api/v1/ai/courts', ['message' => 'Ngày mốt lúc 7h rưỡi tối chơi 90 phút'])->assertOk()->assertJsonPath('data.filters.date', '2026-11-24')->assertJsonPath('data.filters.start_time', '19:30')->assertJsonPath('data.filters.end_time', '21:00')->assertJsonPath('data.total_count', 1);
    }

    public function test_unrelated_chat_with_stale_context_asks_whether_the_person_wants_badminton(): void
    {
        $this->actingAs(User::factory()->create());
        $this->chat('Hôm nay từ 9h đến 10h');
        $reply = $this->chat('Hôm nay tôi buồn quá')->json('data.1.content');
        $this->assertStringContainsString('Bạn có muốn đánh cầu lông không', $reply);
        $this->assertStringNotContainsString('đã qua', $reply);
    }

    public function test_invalid_date_and_invalid_clock_ask_for_correction_instead_of_returning_errors(): void
    {
        foreach (['Ngày 31/02 từ 19h đến 21h', 'Ngày mai lúc 25h tối đánh 2 tiếng'] as $message) {
            $reply = $this->postJson('/api/v1/ai/courts', ['message' => $message])->assertOk()->assertJsonPath('data.needs_clarification', true)->assertJsonCount(0, 'data.courts')->json('data.message');
            $this->assertStringNotContainsString('đã qua', $reply);
        }
    }

    public function test_missing_date_is_not_invented_by_a_model(): void
    {
        config(['ai.search.provider' => 'ollama']);
        Http::fake(['*/api/chat' => Http::response(['done' => true, 'message' => ['content' => json_encode(['date' => '2026-11-22', 'start_time' => '22:00', 'end_time' => '00:00', 'district' => null, 'max_budget' => null, 'amenities' => [], 'clarification' => null])]])]);
        $this->postJson('/api/v1/ai/courts', ['message' => 'Tôi muốn đánh lúc 10h tối trong 2 tiếng'])->assertOk()->assertJsonPath('data.needs_clarification', true)->assertJsonPath('data.filters.date', null);
    }

    public function test_sunday_reply_uses_the_pending_weekend_instead_of_today(): void
    {
        $this->actingAs(User::factory()->create());
        $this->chat('Cuối tuần sau 8h tối đánh 2 tiếng');
        $this->chat('Chủ nhật nhé')->assertJsonPath('data.1.result.filters.date', '2026-11-29')->assertJsonPath('data.1.result.filters.start_time', '20:00')->assertJsonPath('data.1.result.needs_clarification', false);
    }

    public function test_a_new_time_without_an_end_does_not_receive_a_fabricated_duration(): void
    {
        $this->postJson('/api/v1/ai/courts', ['message' => 'Ngày mai tôi muốn đánh lúc 19h'])->assertOk()->assertJsonPath('data.needs_clarification', true)->assertJsonPath('data.filters.end_time', null);
    }
}
