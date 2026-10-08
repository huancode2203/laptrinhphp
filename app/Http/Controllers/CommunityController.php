<?php

namespace App\Http\Controllers;

use App\BookingService;
use App\Models\ChatMessage;
use App\Models\CommunityPost;
use App\Models\Conversation;
use App\Models\PostComment;
use App\Models\PostJoin;
use App\Models\User;
use App\Notifications\HubNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CommunityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['branch_id' => ['sometimes', 'integer', 'exists:branches,id'], 'type' => ['sometimes', 'string', 'max:40'], 'search' => ['sometimes', 'string', 'max:100']]);
        $query = CommunityPost::where(function ($q) use ($request): void {
            $q->where('status', '!=', 'hidden');
            if ($request->user()) {
                $q->orWhere('user_id', $request->user()->id);
            }
        });
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
        $posts = $query->with(['user:id,name,skill_level', 'branch:id,name,district'])->withCount(['likes', 'comments', 'joins as joined_count' => fn ($q) => $q->where('status', 'accepted')])->latest()->paginate(20);
        $posts->through(function ($post) use ($request): array {
            $item = $post->toArray();
            $item['liked'] = $request->user() ? $post->likes()->where('users.id', $request->user()->id)->exists() : false;
            $item['my_join'] = $request->user() ? $post->joins()->where('user_id', $request->user()->id)->value('status') : null;

            return $item;
        });

        return response()->json(['data' => $posts]);
    }

    public function show(Request $request, CommunityPost $post): JsonResponse
    {
        abort_if($post->status === 'hidden' && $post->user_id !== $request->user()?->id && $request->user()?->role !== 'admin', 404);
        $post->load(['user:id,name,skill_level', 'branch:id,name,district', 'comments.user:id,name'])->loadCount(['likes', 'joins as joined_count' => fn ($q) => $q->where('status', 'accepted')]);
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
        }
        $data = $request->validate(['branch_id' => ['required', 'integer', 'exists:branches,id'], 'title' => ['required', 'string', 'max:150'], 'content' => ['required', 'string', 'max:5000'], 'type' => ['required', Rule::in(['Tìm người đánh', 'Tìm đối thủ', 'Tuyển thành viên', 'Chia sẻ', 'Hỏi đáp', 'Giao lưu'])], 'skill_level' => ['required', 'string', 'max:40'], 'starts_at' => ['required', 'date', 'after:now'], 'capacity' => ['required', 'integer', 'between:1,100'], 'status' => ['sometimes', Rule::in(['open', 'closed'])]]);
        $post = DB::transaction(function () use ($request, $post, $data): CommunityPost {
            if ($post) {
                $post = CommunityPost::query()->lockForUpdate()->findOrFail($post->id);
                abort_unless($post->user_id === $request->user()->id && $post->status !== 'hidden', 403);
                abort_if($post->joins()->where('status', 'accepted')->count() > $data['capacity'], 422, 'Số suất không được nhỏ hơn số người đã duyệt.');
            } else {
                $post = new CommunityPost;
                $post->user_id = $request->user()->id;
            }
            $post->fill($data)->save();
            app(BookingService::class)->audit($request->user(), 'community.post_saved', $post->id, [], 'post');

            return $post;
        }, 5);

        return response()->json(['data' => $post], $post->wasRecentlyCreated ? 201 : 200);
    }

    public function join(Request $request, CommunityPost $post): JsonResponse
    {
        $join = DB::transaction(function () use ($request, $post): PostJoin {
            $post = CommunityPost::query()->lockForUpdate()->findOrFail($post->id);
            abort_if($post->user_id === $request->user()->id, 422, 'Bạn là người tổ chức.');
            abort_unless($post->status === 'open' && $post->starts_at->gt(now()), 422, 'Bài đăng đã đóng hoặc buổi chơi đã bắt đầu.');
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
                abort_unless($post->status === 'open' && $post->starts_at->gt(now()), 422);
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
        abort_if($post->status === 'hidden', 404);
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
        abort_if($post->status === 'hidden', 404);
        $data = $request->validate(['content' => ['required', 'string', 'max:2000']]);
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
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'closed', 'hidden'])]]);
        $post->update($data);
        DB::table('content_reports')->where('community_post_id', $post->id)->update(['status' => 'resolved', 'updated_at' => now()]);
        app(BookingService::class)->audit($request->user(), 'community.moderated', $post->id, $data, 'post');

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

    public function conversations(Request $request): JsonResponse
    {
        $items = Conversation::whereHas('members', fn ($q) => $q->where('users.id', $request->user()->id))->with(['members:id,name,skill_level', 'messages' => fn ($q) => $q->latest('id')->limit(1)])->latest('updated_at')->get();

        return response()->json(['data' => $items]);
    }

    public function direct(Request $request): JsonResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        abort_if($data['user_id'] == $request->user()->id, 422);
        $peer = User::where('active', true)->where('public_profile', true)->findOrFail($data['user_id']);
        $ids = [$request->user()->id, $peer->id];
        sort($ids);
        $conversation = DB::transaction(function () use ($ids, $peer, $request): Conversation {
            User::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            $conversation = Conversation::firstOrCreate(['direct_key' => implode(':', $ids)], ['title' => $request->user()->name.' · '.$peer->name]);
            $conversation->members()->syncWithoutDetaching($ids);

            return $conversation;
        }, 5);

        return response()->json(['data' => $conversation]);
    }

    private function assertMember(Request $request, Conversation $conversation): void
    {
        abort_unless($conversation->members()->where('users.id', $request->user()->id)->exists(), 403);
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $this->assertMember($request, $conversation);
        $data = $request->validate(['after' => ['sometimes', 'integer', 'min:0']]);
        $query = $conversation->messages()->with('user:id,name');
        $messages = isset($data['after']) ? $query->where('id', '>', $data['after'])->orderBy('id')->limit(100)->get() : $query->latest('id')->limit(100)->get()->reverse()->values();
        $conversation->members()->updateExistingPivot($request->user()->id, ['read_at' => now()]);

        return response()->json(['data' => $messages]);
    }

    public function send(Request $request, Conversation $conversation): JsonResponse
    {
        $this->assertMember($request, $conversation);
        $data = $request->validate(['request_key' => ['required', 'uuid'], 'content' => ['required', 'string', 'max:2000']]);
        $message = DB::transaction(function () use ($request, $conversation, $data): ChatMessage {
            $conversation = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
            $this->assertMember($request, $conversation);
            $existing = ChatMessage::where('request_key', $data['request_key'])->first();
            if ($existing) {
                abort_unless($existing->conversation_id === $conversation->id && $existing->user_id === $request->user()->id && $existing->content === $data['content'], 422);

                return $existing;
            }
            $message = $conversation->messages()->create($data + ['user_id' => $request->user()->id]);
            $conversation->touch();

            return $message;
        }, 5);

        return response()->json(['data' => $message->load('user:id,name')], 201);
    }

    public function retract(Request $request, ChatMessage $message): JsonResponse
    {
        abort_unless($message->user_id === $request->user()->id, 403);
        $message->update(['content' => '[Tin nhắn đã thu hồi]', 'deleted' => true]);

        return response()->json(['message' => 'Đã thu hồi tin nhắn.']);
    }
}
