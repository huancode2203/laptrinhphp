<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PersonalChatDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_is_personal_and_new_messages_reopen_without_old_history(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $room = Conversation::create(['title' => 'Hai người chơi', 'direct_key' => min($a->id, $b->id).':'.max($a->id, $b->id)]);
        $room->members()->attach([$a->id, $b->id]);
        $this->actingAs($b)->postJson('/api/v1/conversations/'.$room->id.'/messages', ['request_key' => (string) Str::uuid(), 'content' => 'Hẹn ra sân'])->assertCreated();
        $this->actingAs($a)->deleteJson('/api/v1/conversations/'.$room->id)->assertOk();
        $this->getJson('/api/v1/conversations')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/conversations/'.$room->id.'/messages')->assertOk()->assertJsonCount(0, 'data')->assertJsonMissingPath('members.0.pivot.hidden_at');
        $this->actingAs($b)->getJson('/api/v1/conversations/'.$room->id.'/messages')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/v1/conversations/'.$room->id.'/messages', ['request_key' => (string) Str::uuid(), 'content' => 'Tin mới'])->assertCreated();
        $this->actingAs($a)->getJson('/api/v1/conversations')->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.members.0.pivot.cleared_through_id');
        $this->getJson('/api/v1/conversations/'.$room->id.'/messages')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.content', 'Tin mới');
        $this->assertDatabaseCount('chat_messages', 2);
        $this->actingAs(User::factory()->create())->deleteJson('/api/v1/conversations/'.$room->id)->assertForbidden();
    }

    public function test_ai_history_can_be_cleared_only_by_its_owner(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        foreach ([$a, $b] as $user) {
            DB::table('ai_search_turns')->insert(['user_id' => $user->id, 'request_key' => (string) Str::uuid(), 'message' => 'Tìm sân', 'result' => json_encode(['message' => 'Bạn muốn ngày nào?', 'filters' => []]), 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->actingAs($a)->deleteJson('/api/v1/ai/chat')->assertOk();
        $this->getJson('/api/v1/ai/chat')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($b)->getJson('/api/v1/ai/chat')->assertOk()->assertJsonCount(2, 'data');
        $this->assertDatabaseCount('ai_search_turns', 1);
    }
}
