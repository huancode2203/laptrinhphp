<?php

namespace Tests\Feature;

use App\Models\CommunityPost;
use App\Models\User;
use App\Notifications\HubNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminCommunityWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_paginate_rejected_posts_and_notify_the_author(): void
    {
        Notification::fake();
        $posts = CommunityPost::factory()->count(13)->create(['moderation_status' => 'rejected']);
        CommunityPost::factory()->create(['moderation_status' => 'pending']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson('/api/v1/admin/posts?state=rejected&page=2&per_page=10')->assertOk()->assertJsonPath('data.total', 13)->assertJsonCount(3, 'data.data');
        $post = $posts->first();
        $this->patchJson('/api/v1/admin/posts/'.$post->id.'/moderation', ['decision' => 'rejected', 'reason' => 'Vui lòng bỏ nội dung quảng cáo không liên quan.'])->assertOk()->assertJsonPath('data.moderation_result.human_reason', 'Vui lòng bỏ nội dung quảng cáo không liên quan.');
        Notification::assertSentTo($post->user, HubNotification::class, fn ($notification) => $notification->toDatabase($post->user)['post_id'] === $post->id);
        $this->getJson('/api/v1/admin/posts?state=all')->assertOk()->assertJsonPath('data.total', 14);
        $this->actingAs(User::factory()->create(['role' => 'manager']))->getJson('/api/v1/admin/posts')->assertForbidden();
    }

    public function test_approved_post_content_is_locked_and_reply_must_belong_to_same_post(): void
    {
        $post = CommunityPost::factory()->create(['moderation_status' => 'approved']);
        $this->actingAs($post->user)->putJson('/api/v1/community/posts/'.$post->id, ['title' => 'Nội dung thay đổi'])->assertUnprocessable();
        $this->assertNotSame('Nội dung thay đổi', $post->fresh()->title);
        config(['ai.moderation.provider' => 'local']);
        $comment = $this->postJson('/api/v1/community/posts/'.$post->id.'/comments', ['content' => 'Chào bạn, mình muốn giao lưu.'])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/community/posts/'.$post->id.'/comments', ['content' => 'Mời bạn cùng tham gia.', 'parent_id' => $comment])->assertCreated()->assertJsonPath('data.parent_id', $comment);
        $other = CommunityPost::factory()->create(['moderation_status' => 'approved']);
        $this->postJson('/api/v1/community/posts/'.$other->id.'/comments', ['content' => 'Trả lời khác bài.', 'parent_id' => $comment])->assertUnprocessable();
    }
}
