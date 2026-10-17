<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\CommunityPost;
use App\Models\Conversation;
use App\Models\User;
use App\Models\WalletEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommunityHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_can_page_back_and_personal_deletion_still_hides_old_messages(): void
    {
        $owner = User::factory()->create();
        $peer = User::factory()->create();
        $room = Conversation::factory()->create();
        $room->members()->attach([$owner->id, $peer->id]);
        $messages = ChatMessage::factory()->count(125)->create(['conversation_id' => $room->id, 'user_id' => $peer->id]);
        $url = '/api/v1/conversations/'.$room->id;
        $first = $this->actingAs($owner)->getJson($url.'/messages')->assertOk()->assertJsonCount(100, 'data')->assertJsonPath('has_more', true)->json('data.0.id');
        $this->getJson($url.'/messages?before='.$first)->assertOk()->assertJsonCount(25, 'data')->assertJsonPath('has_more', false)->assertJsonPath('data.0.id', $messages->first()->id);
        $this->getJson($url.'/messages?before='.$first.'&after=0')->assertUnprocessable();
        $this->deleteJson($url)->assertOk();
        $this->getJson($url.'/messages?before='.$first)->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs(User::factory()->create())->getJson($url.'/messages?before='.$first)->assertForbidden();
    }

    public function test_blocking_a_peer_hides_group_messages_and_saved_posts_without_disabling_the_group(): void
    {
        $owner = User::factory()->create();
        $peer = User::factory()->create();
        $friend = User::factory()->create();
        DB::table('blocked_users')->insert(['user_id' => $owner->id, 'blocked_id' => $peer->id]);
        $post = CommunityPost::factory()->create(['user_id' => $peer->id, 'moderation_status' => 'approved']);
        DB::table('saved_posts')->insert(['user_id' => $owner->id, 'community_post_id' => $post->id]);
        $room = Conversation::factory()->create(['direct_key' => null]);
        $room->members()->attach([$owner->id, $peer->id, $friend->id]);
        ChatMessage::factory()->create(['conversation_id' => $room->id, 'user_id' => $peer->id, 'content' => 'Nội dung bị ẩn']);
        ChatMessage::factory()->create(['conversation_id' => $room->id, 'user_id' => $friend->id, 'content' => 'Hẹn ra sân']);
        $this->actingAs($owner)->getJson('/api/v1/features/account')->assertOk()->assertJsonCount(0, 'data.saved_posts');
        $this->putJson('/api/v1/community/posts/'.$post->id.'/saved', ['active' => true])->assertForbidden();
        $this->getJson('/api/v1/conversations/'.$room->id.'/messages')->assertOk()->assertJsonCount(1, 'data')->assertDontSee('Nội dung bị ẩn');
        $this->postJson('/api/v1/conversations/'.$room->id.'/messages', ['request_key' => (string) Str::uuid(), 'content' => 'Tôi vẫn trao đổi cùng cả nhóm'])->assertCreated();
        $direct = Conversation::factory()->create(['direct_key' => $owner->id.':'.$peer->id]);
        $direct->members()->attach([$owner->id, $peer->id]);
        $this->postJson('/api/v1/conversations/'.$direct->id.'/messages', ['request_key' => (string) Str::uuid(), 'content' => 'Không được gửi riêng'])->assertForbidden();
    }

    public function test_repeated_reports_do_not_duplicate_pending_work_and_review_resolves_the_queue(): void
    {
        $post = CommunityPost::factory()->create(['moderation_status' => 'approved']);
        $customer = User::factory()->create();
        $url = '/api/v1/community/posts/'.$post->id.'/report';
        $this->actingAs($customer)->postJson($url, ['reason' => 'Nội dung không phù hợp'])->assertCreated();
        $this->postJson($url, ['reason' => 'Nội dung không phù hợp'])->assertCreated();
        $this->assertDatabaseCount('content_reports', 1);
        $this->getJson('/api/v1/admin/history/reports')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson('/api/v1/admin/history/reports?state=pending')->assertOk()->assertJsonCount(1, 'data.data');
        $this->patchJson('/api/v1/admin/posts/'.$post->id.'/moderation', ['decision' => 'rejected', 'reason' => 'Không phù hợp quy định cộng đồng'])->assertOk();
        $this->getJson('/api/v1/admin/history/reports?state=pending')->assertOk()->assertJsonCount(0, 'data.data');
        $this->getJson('/api/v1/admin/history/reports?state=resolved')->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_wallet_and_admin_user_history_are_paginated_private_and_hide_credentials(): void
    {
        $owner = User::factory()->create();
        $peer = User::factory()->create();
        WalletEntry::factory()->count(35)->create(['user_id' => $owner->id, 'description' => 'Lịch sử của tôi']);
        WalletEntry::factory()->create(['user_id' => $peer->id, 'description' => 'Giao dịch của người khác']);
        $this->actingAs($owner)->getJson('/api/v1/account/history/wallet?page=2')->assertOk()->assertJsonCount(15, 'data.data')->assertJsonPath('data.total', 35)->assertDontSee('Giao dịch của người khác');
        $this->getJson('/api/v1/account/history/passwords')->assertNotFound();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->getJson('/api/v1/admin/history/users?search='.urlencode($owner->email))->assertOk()->assertJsonCount(1, 'data.data')->assertJsonMissingPath('data.data.0.password')->assertJsonMissingPath('data.data.0.remember_token');
        $this->getJson('/api/v1/admin/history/audits?page=-1')->assertUnprocessable();
    }

    public function test_audit_download_honors_date_filters_and_escapes_spreadsheet_formulas(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (['2026-10-16', '2026-10-17'] as $date) {
            DB::table('audit_logs')->insert(['user_id' => $admin->id, 'action' => '=FORMULA', 'subject_type' => 'user', 'subject_id' => $admin->id, 'details' => '{}', 'created_at' => $date.' 10:00:00']);
        }
        $this->actingAs($admin)->getJson('/api/v1/admin/history/audits?from=2026-10-17')->assertOk()->assertJsonPath('data.total', 1);
        $response = $this->get('/api/v1/admin/history/audits?from=2026-10-17&download=1')->assertOk()->assertDownload('nhat-ky-hoat-dong.csv');
        $this->assertStringContainsString("'=FORMULA", $response->streamedContent());
        $this->assertStringNotContainsString('2026-10-16', $response->streamedContent());
        $this->getJson('/api/v1/admin/history/audits?from=2026-10-18&to=2026-10-17')->assertUnprocessable();
        $this->actingAs(User::factory()->create())->getJson('/api/v1/account/history/wallet?download=1')->assertForbidden();
    }
}
