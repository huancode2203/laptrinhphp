<?php

namespace App\Http\Controllers;

use App\AccessService;
use App\BookingService;
use App\FeatureService;
use App\Models\ChatMessage;
use App\Models\CommunityPost;
use App\Models\Conversation;
use App\Models\PostComment;
use App\Models\PostJoin;
use App\Models\User;
use App\ModerationService;
use App\Notifications\HubNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommunityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['branch_id' => ['sometimes', 'integer', 'exists:branches,id'], 'type' => ['sometimes', 'string', 'max:40'], 'search' => ['sometimes', 'string', 'max:100']]);
        $query = CommunityPost::where('status', '!=', 'hidden')->where(function ($q) use ($request): void {
            $q->where('status', '!=', 'hidden')->where('moderation_status', 'approved');
            if ($request->user()) {
                $q->orWhere('user_id', $request->user()->id);
            }
        });
        if ($request->user()) {
            $blocked = DB::table('blocked_users')->where('user_id', $request->user()->id)->pluck('blocked_id')
                ->merge(DB::table('blocked_users')->where('blocked_id', $request->user()->id)->pluck('user_id'))->all();
            $query->whereNotIn('user_id', $blocked);
        }
        if (isset($data['branch_id'])) {
            $query->where('branch_id', $data['branch_id']);
        }
        if (isset($data['type'])) {
            $query->where('type', $data['type']);
        }
        if (! empty($data['search'])) {
            $term = str_replace(['%', '_'], '', $data['search']);
            $query->where(fn ($q) => $q->where('title', 'like', '%'.$term.'%')->orWhere('content', 'like', '%'.$term.'%'));
        }
        $posts = $query->with(['user:id,name,skill_level', 'branch:id,name,district,ward'])->withCount(['likes', 'comments', 'joins as joined_count' => fn ($q) => $q->where('status', 'accepted')])->latest()->paginate(20);
        $posts->through(function ($post) use ($request): array {
            $item = $post->toArray();
            $item['saved'] = $request->user() ? DB::table('saved_posts')->where('user_id', $request->user()->id)->where('community_post_id', $post->id)->exists() : false;
            $item['liked'] = $request->user() ? $post->likes()->where('users.id', $request->user()->id)->exists() : false;
            $item['my_join'] = $request->user() ? $post->joins()->where('user_id', $request->user()->id)->value('status') : null;

            return $item;
        });

        return response()->json(['data' => $posts]);
    }

    public function show(Request $request, CommunityPost $post): JsonResponse
    {
        if ($request->user()?->role !== 'admin') {
            $this->assertNotBlocked($request->user(), $post->user_id);
        }
        abort_if(($post->status === 'hidden' || $post->moderation_status !== 'approved') && $post->user_id !== $request->user()?->id && $request->user()?->role !== 'admin', 404);
        $post->load(['user:id,name,skill_level', 'branch:id,name,district,ward', 'comments.user:id,name'])->loadCount(['likes', 'joins as joined_count' => fn ($q) => $q->where('status', 'accepted')]);
        $data = $post->toArray();
        $data['joins'] = $post->joins()->with('user:id,name,skill_level')->where(function ($q) use ($request, $post): void {
            if ($post->user_id !== $request->user()?->id) {
                $q->where('status', 'accepted');
            }
        })->get()->toArray();
        $data['my_join'] = $request->user() ? $post->joins()->where('user_id', $request->user()->id)->value('status') : null;

        return response()->json(['data' => $data]);
    }

    public function save(Request $request, ?CommunityPost $post = null): JsonResponse
    {
        if ($post) {
            abort_unless($post->user_id === $request->user()->id && $post->status !== 'hidden', 403);
            abort_if($post->moderation_status === 'approved', 422, 'Bài viết đã được duyệt nên không thể sửa nội dung. Bạn có thể đóng bài hoặc đăng bài mới.');
        }
        $data = $request->validate(['branch_id' => ['required', 'integer', 'exists:branches,id'], 'title' => ['required', 'string', 'max:150'], 'content' => ['required', 'string', 'max:5000'], 'type' => ['required', Rule::in(['Tìm người đánh', 'Tìm đối thủ', 'Tuyển thành viên', 'Chia sẻ', 'Hỏi đáp', 'Giao lưu'])], 'skill_level' => ['required', 'string', 'max:40'], 'starts_at' => ['required', 'date', 'after:now'], 'capacity' => ['required', 'integer', 'between:1,100'], 'status' => ['sometimes', Rule::in(['open', 'closed'])]]);
        $images = $request->validate(['images' => 'sometimes|array|list|max:6', 'images.*' => 'integer|distinct|exists:media_uploads,id']);
        app(FeatureService::class)->media($images['images'] ?? [], $request->user());
        $data = array_merge($data, $images);
        $moderation = app(ModerationService::class)->inspect($data['title']."\n".$data['content']);
        $post = DB::transaction(function () use ($request, $post, $data, $moderation): CommunityPost {
            if ($post) {
                $post = CommunityPost::query()->lockForUpdate()->findOrFail($post->id);
                abort_unless($post->user_id === $request->user()->id && $post->status !== 'hidden', 403);
                abort_if($post->moderation_status === 'approved', 422, 'Bài viết đã được duyệt nên không thể sửa nội dung.');
                abort_if($post->joins()->where('status', 'accepted')->count() > $data['capacity'], 422, 'Số suất không được nhỏ hơn số người đã duyệt.');
            } else {
                $post = new CommunityPost;
                $post->user_id = $request->user()->id;
            }
            $post->fill($data)->save();
            app(ModerationService::class)->apply($post, $moderation);
            app(BookingService::class)->audit($request->user(), 'community.post_saved', $post->id, [], 'post');

            return $post;
        }, 5);

        return response()->json(['data' => $post], $post->wasRecentlyCreated ? 201 : 200);
    }

    public function join(Request $request, CommunityPost $post): JsonResponse
    {
        $this->assertNotBlocked($request->user(), $post->user_id);
        $join = DB::transaction(function () use ($request, $post): PostJoin {
            $post = CommunityPost::query()->lockForUpdate()->findOrFail($post->id);
            abort_if($post->user_id === $request->user()->id, 422, 'Bạn là người tổ chức.');
            abort_unless($post->status === 'open' && $post->moderation_status === 'approved' && $post->starts_at->gt(now()), 422, 'Bài đăng đã đóng hoặc buổi chơi đã bắt đầu.');
            $existing = $post->joins()->where('user_id', $request->user()->id)->first();
            if ($existing && in_array($existing->status, ['pending', 'accepted'], true)) {
                return $existing;
            }
            abort_if($post->joins()->where('status', 'accepted')->count() >= $post->capacity, 422, 'Đội đã đủ người.');
            $join = PostJoin::updateOrCreate(['community_post_id' => $post->id, 'user_id' => $request->user()->id], ['status' => 'pending']);
            $post->user->notify(new HubNotification(['type' => 'community.join_requested', 'title' => 'Có yêu cầu tham gia đội', 'body' => $request->user()->name.' muốn tham gia '.$post->title.'.', 'post_id' => $post->id]));

            return $join;
        }, 5);

        return response()->json(['data' => $join]);
    }

    public function decideJoin(Request $request, CommunityPost $post, PostJoin $join): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['accepted', 'rejected'])]]);
        $join = DB::transaction(function () use ($request, $post, $join, $data): PostJoin {
            $post = CommunityPost::query()->lockForUpdate()->findOrFail($post->id);
            abort_unless($post->user_id === $request->user()->id && $join->community_post_id === $post->id, 403);
            $join = PostJoin::query()->lockForUpdate()->findOrFail($join->id);
            if ($join->status === $data['status']) {
                return $join;
            }
            if ($data['status'] === 'accepted') {
                $this->assertNotBlocked($request->user(), $join->user_id);
                abort_unless($post->status === 'open' && $post->moderation_status === 'approved' && $post->starts_at->gt(now()), 422);
                abort_if($post->joins()->where('status', 'accepted')->where('id', '!=', $join->id)->count() >= $post->capacity, 422, 'Đội đã đủ người.');
                $conversation = Conversation::firstOrCreate(['community_post_id' => $post->id], ['title' => $post->title]);
                $conversation->members()->syncWithoutDetaching([$post->user_id, $join->user_id]);
            } else {
                $conversation = Conversation::where('community_post_id', $post->id)->first();
                $conversation?->members()->detach($join->user_id);
            }
            $join->update($data);
            $join->user->notify(new HubNotification(['type' => 'community.join_decided', 'title' => $data['status'] === 'accepted' ? 'Đã được duyệt vào đội' : 'Yêu cầu tham gia chưa được duyệt', 'body' => $post->title, 'post_id' => $post->id]));

            return $join;
        }, 5);

        return response()->json(['data' => $join]);
    }

    public function leave(Request $request, CommunityPost $post): JsonResponse
    {
        DB::transaction(function () use ($request, $post): void {
            $post = CommunityPost::query()->lockForUpdate()->findOrFail($post->id);
            $post->joins()->where('user_id', $request->user()->id)->update(['status' => 'withdrawn']);
            Conversation::where('community_post_id', $post->id)->first()?->members()->detach($request->user()->id);
        }, 5);

        return response()->json(['message' => 'Đã rời đội.']);
    }

    public function like(Request $request, CommunityPost $post): JsonResponse
    {
        $this->assertNotBlocked($request->user(), $post->user_id);
        abort_if($post->status === 'hidden' || $post->moderation_status !== 'approved', 404);
        $data = $request->validate(['liked' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $post, $data): void {
            $post = CommunityPost::query()->lockForUpdate()->findOrFail($post->id);
            if ($data['liked']) {
                $post->likes()->syncWithoutDetaching([$request->user()->id]);
            } else {
                $post->likes()->detach($request->user()->id);
            }
        }, 5);

        return response()->json(['likes_count' => $post->likes()->count()]);
    }

    public function comment(Request $request, CommunityPost $post): JsonResponse
    {
        $this->assertNotBlocked($request->user(), $post->user_id);
        abort_if($post->status === 'hidden' || $post->moderation_status !== 'approved', 404);
        $data = $request->validate(['content' => ['required', 'string', 'max:2000'], 'parent_id' => 'nullable|integer|exists:post_comments,id']);
        if (! empty($data['parent_id'])) {
            abort_unless(PostComment::findOrFail($data['parent_id'])->community_post_id === $post->id, 422);
        }
        $analysis = app(ModerationService::class)->inspect($data['content']);
        abort_if($analysis['decision'] === 'review', 422, 'Bình luận cần điều chỉnh ngôn từ trước khi gửi.');
        $comment = $post->comments()->create($data + ['user_id' => $request->user()->id]);

        return response()->json(['data' => $comment->load('user:id,name')], 201);
    }

    public function deleteComment(Request $request, PostComment $comment): JsonResponse
    {
        abort_unless($comment->user_id === $request->user()->id || $request->user()->role === 'admin', 403);
        $comment->delete();

        return response()->json(['message' => 'Đã xóa bình luận.']);
    }

    public function report(Request $request, CommunityPost $post): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        DB::table('content_reports')->insert(['community_post_id' => $post->id, 'user_id' => $request->user()->id, 'reason' => $data['reason'], 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['message' => 'Đã gửi báo cáo cho quản trị viên.'], 201);
    }

    public function moderate(Request $request, CommunityPost $post): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'closed', 'hidden'])], 'reason' => 'nullable|string|max:500']);
        $reason = $data['reason'] ?? 'Bài viết đã được quản trị viên cập nhật trạng thái.';
        unset($data['reason']);
        $post->update($data);
        DB::table('content_reports')->where('community_post_id', $post->id)->update(['status' => 'resolved', 'updated_at' => now()]);
        app(BookingService::class)->audit($request->user(), 'community.moderated', $post->id, $data, 'post');
        $post->user->notify(new HubNotification(['type' => 'community.moderated', 'title' => $post->status === 'hidden' ? 'Bài viết đã được gỡ khỏi cộng đồng' : 'Đã cập nhật bài viết', 'body' => $post->title.': '.$reason, 'post_id' => $post->id]));

        return response()->json(['data' => $post]);
    }

    public function players(Request $request): JsonResponse
    {
        $data = $request->validate(['search' => ['sometimes', 'string', 'max:100'], 'skill_level' => ['sometimes', 'string', 'max:40']]);
        $query = User::where('active', true)->where('public_profile', true)->where('role', 'customer');
        if (! empty($data['search'])) {
            $query->where('name', 'like', '%'.str_replace(['%', '_'], '', $data['search']).'%');
        }
        if (! empty($data['skill_level'])) {
            $query->where('skill_level', $data['skill_level']);
        }

        return response()->json(['data' => $query->limit(40)->get(['id', 'name', 'skill_level'])]);
    }

    private function assertNotBlocked(?User $viewer, int $peerId): void
    {
        if (! $viewer || $viewer->id === $peerId) {
            return;
        }
        abort_if(DB::table('blocked_users')
            ->where(fn ($q) => $q->where('user_id', $viewer->id)->where('blocked_id', $peerId))
            ->orWhere(fn ($q) => $q->where('user_id', $peerId)->where('blocked_id', $viewer->id))
            ->exists(), 403, 'Không thể tương tác với tài khoản đã chặn.');
    }

    public function conversations(Request $request): JsonResponse
    {
        $items = app(AccessService::class)->conversations($request->user())->with(['members:id,name,skill_level,last_seen_at,avatar_path', 'messages' => fn ($q) => $q->latest('id')->limit(1)])->latest('updated_at')->get()->filter(function ($conversation) use ($request) {
            $pivot = $conversation->members->firstWhere('id', $request->user()->id)->pivot;

            return ! $pivot->hidden_at || ($conversation->messages->first()?->id ?? 0) > $pivot->cleared_through_id;
        })->map(function ($conversation) use ($request) {
            $cutoff = $conversation->members->firstWhere('id', $request->user()->id)->pivot->cleared_through_id;
            $conversation->setRelation('messages', $conversation->messages->filter(fn ($message) => $message->id > $cutoff)->values());
            $conversation->members->each(fn ($member) => $member->pivot->makeHidden(['hidden_at', 'cleared_through_id']));

            return $conversation;
        })->values();

        return response()->json(['data' => $items]);
    }

    public function direct(Request $request): JsonResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        abort_if($data['user_id'] == $request->user()->id, 422);
        $peer = User::where('active', true)->where('public_profile', true)->findOrFail($data['user_id']);
        abort_if(DB::table('blocked_users')->where(fn ($q) => $q->where('user_id', $request->user()->id)->where('blocked_id', $peer->id))->orWhere(fn ($q) => $q->where('user_id', $peer->id)->where('blocked_id', $request->user()->id))->exists(), 403, 'Không thể trò chuyện với tài khoản này.');
        $ids = [$request->user()->id, $peer->id];
        sort($ids);
        $conversation = DB::transaction(function () use ($ids, $peer, $request): Conversation {
            User::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            $conversation = Conversation::firstOrCreate(['direct_key' => implode(':', $ids)], ['title' => $request->user()->name.' · '.$peer->name]);
            $conversation->members()->syncWithoutDetaching($ids);
            $conversation->members()->updateExistingPivot($request->user()->id, ['hidden_at' => null]);

            return $conversation;
        }, 5);

        return response()->json(['data' => $conversation]);
    }

    private function assertMember(Request $request, Conversation $conversation): void
    {
        abort_unless(app(AccessService::class)->conversations($request->user())->whereKey($conversation->id)->exists(), 403);
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $this->assertMember($request, $conversation);
        $data = $request->validate(['after' => ['sometimes', 'integer', 'min:0']]);
        $cutoff = $conversation->members()->where('users.id', $request->user()->id)->firstOrFail()->pivot->cleared_through_id;
        $query = $conversation->messages()->where('id', '>', $cutoff)->with('user:id,name');
        $messages = isset($data['after']) ? $query->where('id', '>', $data['after'])->orderBy('id')->limit(100)->get() : $query->latest('id')->limit(100)->get()->reverse()->values();
        $conversation->members()->updateExistingPivot($request->user()->id, ['read_at' => now()]);

        $members = $conversation->members()->get(['users.id', 'name', 'last_seen_at']);
        $members->each(fn ($member) => $member->pivot->makeHidden(['hidden_at', 'cleared_through_id']));

        return response()->json(['data' => $messages, 'members' => $members, 'attachments' => DB::table('media_uploads')->whereIn('id', $messages->flatMap(fn ($m) => $m->attachments ?? []))->get(['id', 'name', 'mime'])]);
    }

    public function send(Request $request, Conversation $conversation): JsonResponse
    {
        $this->assertMember($request, $conversation);
        if (! $conversation->booking_id) {
            $peers = $conversation->members()->where('users.id', '!=', $request->user()->id)->pluck('users.id');
            abort_if(DB::table('blocked_users')->where(fn ($q) => $q->where('user_id', $request->user()->id)->whereIn('blocked_id', $peers))->orWhere(fn ($q) => $q->whereIn('user_id', $peers)->where('blocked_id', $request->user()->id))->exists(), 403, 'Không thể gửi tin nhắn cho tài khoản đã chặn.');
        }
        $data = $request->validate(['request_key' => ['required', 'uuid'], 'content' => ['required', 'string', 'max:2000'], 'attachments' => 'sometimes|array|list|max:4', 'attachments.*' => 'integer|distinct|exists:media_uploads,id']);
        if (isset($data['attachments'])) {
            $data['attachments'] = array_map('intval', $data['attachments']);
        }
        app(FeatureService::class)->media($data['attachments'] ?? [], $request->user(), false);
        $message = DB::transaction(function () use ($request, $conversation, $data): ChatMessage {
            $conversation = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
            $this->assertMember($request, $conversation);
            $existing = ChatMessage::where('request_key', $data['request_key'])->first();
            if ($existing) {
                abort_unless($existing->conversation_id === $conversation->id && $existing->user_id === $request->user()->id && $existing->content === $data['content'] && ($existing->attachments ?? []) === ($data['attachments'] ?? []), 422);

                return $existing;
            }
            $message = $conversation->messages()->create($data + ['user_id' => $request->user()->id]);
            $conversation->touch();

            return $message;
        }, 5);

        return response()->json(['data' => $message->load('user:id,name')], 201);
    }

    public function typing(Request $request, Conversation $conversation): JsonResponse
    {
        $this->assertMember($request, $conversation);
        $conversation->members()->updateExistingPivot($request->user()->id, ['typing_until' => now()->addSeconds(5)]);

        return response()->json(['message' => 'ok']);
    }

    public function deleteConversation(Request $request, Conversation $conversation): JsonResponse
    {
        DB::transaction(function () use ($request, $conversation): void {
            $conversation = Conversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $this->assertMember($request, $conversation);
            $conversation->members()->updateExistingPivot($request->user()->id, ['hidden_at' => now(), 'cleared_through_id' => $conversation->messages()->max('id') ?? 0, 'read_at' => now(), 'typing_until' => null]);
        });

        return response()->json(['message' => 'Đã xóa cuộc trò chuyện khỏi tài khoản của bạn.']);
    }

    public function stream(Request $request, Conversation $conversation): StreamedResponse
    {
        $this->assertMember($request, $conversation);
        $request->session()->save();

        return response()->stream(function () use ($conversation): void {
            $messages = $conversation->messages()->latest('id')->limit(100)->get(['id', 'updated_at', 'deleted']);
            $members = $conversation->members()->get(['users.id', 'last_seen_at']);
            echo "event: update\ndata: ".json_encode(['version' => hash('sha256', $messages->toJson().$members->toJson())])."\n\n";
            if (ob_get_level()) {
                ob_flush();
            }
            flush();
        }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache', 'X-Accel-Buffering' => 'no']);
    }

    public function deletePost(Request $request, CommunityPost $post): JsonResponse
    {
        abort_unless($post->user_id === $request->user()->id || $request->user()->role === 'admin', 403);
        $post->update(['status' => 'hidden']);

        return response()->json(['message' => 'Đã gỡ bài viết.']);
    }

    public function retract(Request $request, ChatMessage $message): JsonResponse
    {
        abort_unless($message->user_id === $request->user()->id, 403);
        $message->update(['content' => '[Tin nhắn đã thu hồi]', 'deleted' => true, 'attachments' => []]);

        return response()->json(['message' => 'Đã thu hồi tin nhắn.']);
    }
}
