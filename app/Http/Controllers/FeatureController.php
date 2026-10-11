<?php

namespace App\Http\Controllers;

use App\AccessService;
use App\BookingService;
use App\CommerceService;
use App\Http\Resources\BookingResource;
use App\LoyaltyService;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\CommunityPost;
use App\Models\Conversation;
use App\Models\Court;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use App\Notifications\HubNotification;
use App\Rules\VietnamesePhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

class FeatureController extends Controller
{
    public function bookingChat(Request $r, Booking $booking): JsonResponse
    {
        if ($booking->user_id !== $r->user()->id) {
            app(BookingService::class)->assertManage($r->user(), $booking->court->branch_id);
        }
        $conversation = DB::transaction(function () use ($r, $booking): Conversation {
            Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $c = Conversation::firstOrCreate(['booking_id' => $booking->id], ['title' => 'Hỗ trợ '.$booking->reference, 'type' => 'support', 'created_by' => $r->user()->id]);
            $staff = User::where('active', true)->whereIn('role', ['admin', 'manager', 'staff', 'cashier'])->get()->filter(fn ($u) => app(AccessService::class)->manages($u, $booking->court->branch_id))->pluck('id')->all();
            $c->members()->syncWithoutDetaching(array_unique([$booking->user_id, ...$staff]));

            return $c;
        }, 5);

        return response()->json(['data' => $conversation]);
    }

    public function account(Request $r): JsonResponse
    {
        $id = $r->user()->id;

        return response()->json(['data' => [
            'points' => DB::table('loyalty_entries')->where('user_id', $id)->latest('id')->limit(100)->get(),
            'rewards' => DB::table('loyalty_rewards')->where('active', true)->get(),
            'favorites' => Court::whereIn('id', DB::table('favorite_courts')->where('user_id', $id)->select('court_id'))->with('branch')->get(),
            'saved_posts' => CommunityPost::whereIn('id', DB::table('saved_posts')->where('user_id', $id)->select('community_post_id'))->where('moderation_status', 'approved')->where('status', '!=', 'hidden')->with('user:id,name', 'branch:id,name')->get(),
            'blocked' => DB::table('blocked_users')->where('user_id', $id)->join('users', 'users.id', '=', 'blocked_users.blocked_id')->get(['users.id', 'users.name']),
            'tickets' => DB::table('support_tickets')->where('user_id', $id)->latest('id')->get(),
            'reviews' => Review::where('user_id', $id)->get(),
            'sessions' => DB::table('coach_sessions')->where('user_id', $id)->join('coaches', 'coaches.id', '=', 'coach_sessions.coach_id')->select('coach_sessions.*', 'coaches.name')->get(),
        ]]);
    }

    public function favorite(Request $r, Court $court): JsonResponse
    {
        $d = $r->validate(['active' => 'required|boolean']);
        $q = DB::table('favorite_courts');
        if ($d['active']) {
            $q->insertOrIgnore(['user_id' => $r->user()->id, 'court_id' => $court->id]);
        } else {
            $q->where('user_id', $r->user()->id)->where('court_id', $court->id)->delete();
        }

        return response()->json(['message' => 'Đã cập nhật sân yêu thích.']);
    }

    public function redeem(Request $r, LoyaltyService $service): JsonResponse
    {
        $d = $r->validate(['request_key' => 'required|uuid', 'reward_id' => 'required|integer|exists:loyalty_rewards,id']);

        return response()->json(['data' => $service->redeem($r->user(), $d['reward_id'], $d['request_key'])]);
    }

    public function saved(Request $r, CommunityPost $post): JsonResponse
    {
        abort_unless($post->status !== 'hidden' && $post->moderation_status === 'approved', 404);
        $d = $r->validate(['active' => 'required|boolean']);
        $q = DB::table('saved_posts');
        if ($d['active']) {
            $q->insertOrIgnore(['user_id' => $r->user()->id, 'community_post_id' => $post->id]);
        } else {
            $q->where('user_id', $r->user()->id)->where('community_post_id', $post->id)->delete();
        }

        return response()->json(['message' => 'Đã cập nhật bài đã lưu.']);
    }

    public function block(Request $r, User $user): JsonResponse
    {
        abort_if($user->id === $r->user()->id, 422);
        $d = $r->validate(['active' => 'required|boolean']);
        if ($d['active']) {
            DB::table('blocked_users')->insertOrIgnore(['user_id' => $r->user()->id, 'blocked_id' => $user->id]);
        } else {
            DB::table('blocked_users')->where('user_id', $r->user()->id)->where('blocked_id', $user->id)->delete();
        }

        return response()->json(['message' => 'Đã cập nhật danh sách chặn.']);
    }

    public function upload(Request $r): JsonResponse
    {
        $r->validate(['file' => 'required|file|max:5120|mimes:jpg,jpeg,png,webp,pdf']);
        $file = $r->file('file');
        $path = $file->store('hub-media', 'local');
        $id = DB::table('media_uploads')->insertGetId(['user_id' => $r->user()->id, 'path' => $path, 'name' => Str::limit($file->getClientOriginalName(), 180, ''), 'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => ['id' => $id, 'url' => '/api/v1/media/'.$id, 'name' => $file->getClientOriginalName(), 'mime' => $file->getMimeType()]], 201);
    }

    public function media(Request $r, int $media): Response
    {
        $item = DB::table('media_uploads')->find($media);
        abort_unless($item, 404);
        $allowed = $r->user()?->id === $item->user_id || ($r->user()?->role === 'admin' && CommunityPost::whereJsonContains('images', $media)->exists());
        if (! $allowed) {
            $allowed = CommunityPost::where('moderation_status', 'approved')->where('status', '!=', 'hidden')->whereJsonContains('images', $media)->exists()
                || Review::where('visible', true)->whereJsonContains('images', $media)->exists()
                || User::where('avatar_path', '/api/v1/media/'.$media)->exists();
        }
        if (! $allowed && $r->user()) {
            $allowed = DB::table('chat_messages')->where('deleted', false)->whereJsonContains('attachments', $media)->whereIn('conversation_id', app(AccessService::class)->conversations($r->user())->select('id'))->exists();
        }
        abort_unless($allowed, 404);

        return Storage::disk('local')->response($item->path, $item->name, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=120']);
    }

    public function ticket(Request $r): JsonResponse
    {
        $d = $r->validate(['branch_id' => 'nullable|integer|exists:branches,id', 'subject' => 'required|string|max:150', 'content' => 'required|string|max:5000']);
        $id = DB::table('support_tickets')->insertGetId($d + ['user_id' => $r->user()->id, 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => ['id' => $id], 'message' => 'Đã gửi yêu cầu hỗ trợ.'], 201);
    }

    public function pages(Request $r): JsonResponse
    {
        $q = DB::table('content_pages')->where('published', true);
        if ($r->filled('type')) {
            $q->where('type', $r->input('type'));
        }
        if ($r->filled('slug')) {
            $q->where('slug', $r->input('slug'));
        }

        return response()->json(['data' => $q->latest('id')->get()]);
    }

    public function coaches(Request $r): JsonResponse
    {
        $r->validate(['branch_id' => 'sometimes|integer|exists:branches,id']);

        return response()->json(['data' => DB::table('coaches')->where('active', true)->when($r->filled('branch_id'), fn ($q) => $q->where('branch_id', $r->integer('branch_id')))->get()]);
    }

    public function coaching(Request $r, BookingService $engine): JsonResponse
    {
        $d = $r->validate(['request_key' => 'required|uuid', 'coach_id' => 'required|integer|exists:coaches,id', 'court_id' => 'required|integer|exists:courts,id', 'starts_at' => 'required|date', 'ends_at' => 'required|date|after:starts_at', 'pay_with_wallet' => 'sometimes|boolean']);
        $result = DB::transaction(function () use ($r, $d, $engine): Booking {
            $coach = DB::table('coaches')->lockForUpdate()->find($d['coach_id']);
            $court = Court::findOrFail($d['court_id']);
            abort_unless($coach->active && $coach->branch_id === $court->branch_id, 422, 'Huấn luyện viên không hoạt động ở chi nhánh này.');
            [$start,$end] = $engine->window($d['starts_at'], $d['ends_at']);
            $existing = Booking::where('request_key', $d['request_key'])->first();
            if ($existing) {
                $session = DB::table('coach_sessions')->where('booking_id', $existing->id)->first();
                abort_unless($existing->user_id === $r->user()->id && $existing->court_id === $d['court_id'] && $existing->starts_at->eq($start) && $existing->ends_at->eq($end) && $session?->coach_id === $coach->id, 422);

                return $existing->load('court.branch', 'services', 'payments');
            }
            abort_if(DB::table('coach_sessions')->where('coach_id', $coach->id)->where('status', 'confirmed')->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists(), 422, 'Huấn luyện viên đã có lịch trong giờ này.');
            $fee = (int) round($coach->hourly_rate * $start->diffInMinutes($end) / 60);
            $service = Service::create(['branch_id' => $coach->branch_id, 'name' => 'Buổi học cùng '.$coach->name, 'price' => $fee, 'unit' => 'buổi', 'active' => true]);
            $booking = $engine->create($r->user(), $d + ['services' => [['id' => $service->id, 'quantity' => 1]]]);
            $service->active = false;
            $service->save();
            DB::table('coach_sessions')->insert(['coach_id' => $coach->id, 'booking_id' => $booking->id, 'user_id' => $r->user()->id, 'starts_at' => $start, 'ends_at' => $end, 'amount' => $fee, 'status' => 'confirmed', 'created_at' => now(), 'updated_at' => now()]);
            if ($r->boolean('pay_with_wallet')) {
                app(CommerceService::class)->payBooking($r->user(), $booking, (string) Uuid::uuid5($d['request_key'], 'coach-wallet'));
            }

            return $booking->refresh()->load('court.branch', 'services', 'payments');
        }, 5);

        return response()->json(['data' => new BookingResource($result)], 201);
    }

    public function policy(Request $r, Branch $branch): JsonResponse
    {
        app(BookingService::class)->assertManage($r->user(), $branch->id);
        $d = $r->validate(['opens_minute' => 'required|integer|between:0,1410|multiple_of:30', 'closes_minute' => 'required|integer|between:30,1440|multiple_of:30|gt:opens_minute', 'peak_minute' => 'required|integer|between:0,1440|multiple_of:30', 'booking_days' => 'required|integer|between:1,365', 'deposit_percent' => 'required|integer|between:0,100', 'payment_minutes' => 'required|integer|between:5,120', 'fixed_discount' => 'required|integer|between:0,50']);
        $branch->update($d);
        app(BookingService::class)->audit($r->user(), 'branch.policy', $branch->id, $d, 'branch');

        return response()->json(['data' => $branch]);
    }

    public function manage(Request $r, AccessService $access): JsonResponse
    {
        $r->validate(['branch_id' => 'sometimes|integer|exists:branches,id']);
        $branch = $r->integer('branch_id', $r->user()->branch_id ?? 0);
        abort_unless($access->manages($r->user(), $branch), 403);

        return response()->json(['data' => [
            'pricing' => DB::table('pricing_rules')->where('branch_id', $branch)->orderByDesc('priority')->get(),
            'coaches' => DB::table('coaches')->where('branch_id', $branch)->get(),
            'tickets' => $access->allows($r->user(), 'support.write') ? DB::table('support_tickets')->where(fn ($q) => $q->where('branch_id', $branch)->when($r->user()->role === 'admin', fn ($q) => $q->orWhereNull('branch_id')))->latest('id')->get() : [],
            'branches' => Branch::whereIn('id', $access->branches($r->user()))->get(),
        ]]);
    }

    public function pricing(Request $r, ?int $rule = null): JsonResponse
    {
        $d = $r->validate(['branch_id' => 'required|integer|exists:branches,id', 'court_id' => 'nullable|integer|exists:courts,id', 'name' => 'required|string|max:100', 'weekday' => 'nullable|integer|between:0,6', 'date' => 'nullable|date_format:Y-m-d', 'from_minute' => 'required|integer|between:0,1410|multiple_of:30', 'to_minute' => 'required|integer|between:30,1440|multiple_of:30|gt:from_minute', 'hourly_rate' => 'required|integer|between:1000,10000000', 'priority' => 'required|integer|between:0,1000', 'active' => 'required|boolean']);
        app(BookingService::class)->assertManage($r->user(), $d['branch_id']);
        if (! empty($d['court_id'])) {
            abort_unless(Court::findOrFail($d['court_id'])->branch_id === (int) $d['branch_id'], 422);
        }
        if ($rule) {
            $old = DB::table('pricing_rules')->find($rule);
            abort_unless($old, 404);
            abort_unless((int) $old->branch_id === (int) $d['branch_id'], 422);
            DB::table('pricing_rules')->where('id', $rule)->update($d + ['updated_at' => now()]);
        } else {
            $rule = DB::table('pricing_rules')->insertGetId($d + ['created_at' => now(), 'updated_at' => now()]);
        }
        app(BookingService::class)->audit($r->user(), 'pricing.saved', $rule, $d, 'pricing');

        return response()->json(['data' => DB::table('pricing_rules')->find($rule)]);
    }

    public function coach(Request $r, ?int $coach = null): JsonResponse
    {
        $d = $r->validate(['branch_id' => 'required|integer|exists:branches,id', 'name' => 'required|string|max:100', 'bio' => 'nullable|string|max:3000', 'hourly_rate' => 'required|integer|between:1000,10000000', 'active' => 'required|boolean']);
        app(BookingService::class)->assertManage($r->user(), $d['branch_id']);
        if ($coach) {
            $old = DB::table('coaches')->find($coach);
            abort_unless($old, 404);
            abort_unless((int) $old->branch_id === (int) $d['branch_id'], 422);
            DB::table('coaches')->where('id', $coach)->update($d + ['updated_at' => now()]);
        } else {
            $coach = DB::table('coaches')->insertGetId($d + ['created_at' => now(), 'updated_at' => now()]);
        }

        return response()->json(['data' => DB::table('coaches')->find($coach)]);
    }

    public function ticketReply(Request $r, int $ticket): JsonResponse
    {
        $item = DB::table('support_tickets')->find($ticket);
        abort_unless($item, 404);
        if ($item->branch_id) {
            app(BookingService::class)->assertManage($r->user(), $item->branch_id);
        } else {
            abort_unless($r->user()->role === 'admin', 403);
        }
        $d = $r->validate(['reply' => 'required|string|max:5000', 'status' => 'required|in:open,resolved']);
        DB::table('support_tickets')->where('id', $ticket)->update($d + ['replied_by' => $r->user()->id, 'updated_at' => now()]);
        User::findOrFail($item->user_id)->notify(new HubNotification(['type' => 'support.replied', 'title' => 'Yêu cầu hỗ trợ đã có phản hồi', 'body' => $d['reply']]));

        return response()->json(['message' => 'Đã gửi phản hồi.']);
    }

    public function reviewReply(Request $r, Review $review): JsonResponse
    {
        app(BookingService::class)->assertManage($r->user(), $review->branch_id);
        $d = $r->validate(['reply' => 'required|string|max:2000']);
        $review->update($d + ['replied_by' => $r->user()->id]);

        return response()->json(['data' => $review]);
    }

    public function customer(Request $r): JsonResponse
    {
        $d = $r->validate(['branch_id' => 'required|integer|exists:branches,id', 'name' => 'required|string|max:100', 'phone' => ['required', 'string', 'max:30', new VietnamesePhone], 'email' => 'nullable|email|unique:users,email']);
        app(BookingService::class)->assertManage($r->user(), $d['branch_id']);
        abort_if(User::where('phone', $d['phone'])->exists(), 422, 'Số điện thoại đã có tài khoản. Hãy tìm khách hiện có.');
        $user = User::create(['name' => $d['name'], 'phone' => $d['phone'], 'email' => $d['email'] ?? 'walkin-'.Str::uuid().'@walkin.invalid', 'password' => Str::random(50)]);
        app(BookingService::class)->audit($r->user(), 'customer.created', $user->id, [], 'user');

        return response()->json(['data' => $user], 201);
    }

    public function admin(Request $r): JsonResponse
    {
        return response()->json(['data' => ['pages' => DB::table('content_pages')->latest('id')->get(), 'rewards' => DB::table('loyalty_rewards')->get(),
            'permissions' => DB::table('role_permissions')->get(), 'permission_names' => AccessService::PERMISSIONS, 'user_branches' => DB::table('user_branches')->get(),
            'moderation' => CommunityPost::where('moderation_status', 'pending')->with('user:id,name', 'branch:id,name')->latest('id')->get(),
            'all_tickets' => DB::table('support_tickets')->latest('id')->get(),
            'bank_transactions' => DB::table('integration_transactions')->latest('id')->limit(100)->get(),
            'refund_requests' => DB::table('refund_requests')->latest('id')->limit(100)->get(),
            'ai' => ['search_provider' => config('ai.search.provider'), 'search_model' => config('ai.search.model'), 'moderation_provider' => config('ai.moderation.provider'), 'moderation_model' => config('ai.moderation.model')]]]);
    }

    public function pageSave(Request $r, ?int $page = null): JsonResponse
    {
        $d = $r->validate(['slug' => ['required', 'regex:/^[a-z0-9-]+$/', 'max:100', Rule::unique('content_pages')->ignore($page)], 'title' => 'required|string|max:150', 'type' => 'required|in:page,blog,faq,job', 'content' => 'required|string|max:15000', 'published' => 'required|boolean']);
        if ($page) {
            abort_unless(DB::table('content_pages')->find($page), 404);
            DB::table('content_pages')->where('id', $page)->update($d + ['updated_at' => now()]);
        } else {
            $page = DB::table('content_pages')->insertGetId($d + ['created_at' => now(), 'updated_at' => now()]);
        }

        return response()->json(['data' => DB::table('content_pages')->find($page)]);
    }

    public function rewardSave(Request $r, ?int $reward = null): JsonResponse
    {
        $d = $r->validate(['name' => 'required|string|max:100', 'points' => 'required|integer|between:1,1000000', 'wallet_amount' => 'required|integer|between:1000,10000000', 'active' => 'required|boolean']);
        if ($reward) {
            abort_unless(DB::table('loyalty_rewards')->find($reward), 404);
            DB::table('loyalty_rewards')->where('id', $reward)->update($d + ['updated_at' => now()]);
        } else {
            $reward = DB::table('loyalty_rewards')->insertGetId($d + ['created_at' => now(), 'updated_at' => now()]);
        }

        return response()->json(['data' => DB::table('loyalty_rewards')->find($reward)]);
    }

    public function permission(Request $r): JsonResponse
    {
        $d = $r->validate(['role' => 'required|in:manager,staff,cashier', 'permission' => ['required', Rule::in(AccessService::PERMISSIONS)], 'allowed' => 'required|boolean']);
        DB::table('role_permissions')->updateOrInsert(['role' => $d['role'], 'permission' => $d['permission']], ['allowed' => $d['allowed']]);
        app(BookingService::class)->audit($r->user(), 'permission.updated', 0, $d, 'role');

        return response()->json(['message' => 'Đã cập nhật quyền.']);
    }

    public function assignments(Request $r, User $user): JsonResponse
    {
        $d = $r->validate(['branch_ids' => 'present|array|list|max:30', 'branch_ids.*' => 'integer|distinct|exists:branches,id']);
        abort_unless(in_array($user->role, ['manager', 'staff', 'cashier'], true), 422);
        DB::transaction(function () use ($r, $user, $d): void {
            DB::table('user_branches')->where('user_id', $user->id)->delete();
            foreach ($d['branch_ids'] as $id) {
                DB::table('user_branches')->insert(['user_id' => $user->id, 'branch_id' => $id]);
            }
            $user->forceFill(['branch_id' => $d['branch_ids'][0] ?? null])->save();
            app(BookingService::class)->audit($r->user(), 'user.branches', $user->id, $d, 'user');
        });

        return response()->json(['message' => 'Đã giao chi nhánh.']);
    }

    public function moderate(Request $r, CommunityPost $post): JsonResponse
    {
        $d = $r->validate(['decision' => 'required|in:approved,rejected', 'reason' => 'required|string|max:500']);
        $post->forceFill(['moderation_status' => $d['decision'], 'moderated_by' => $r->user()->id, 'moderated_at' => now(), 'moderation_result' => array_merge($post->moderation_result ?? [], ['human_reason' => $d['reason']])])->save();
        $post->user->notify(new HubNotification(['type' => 'community.moderated', 'title' => $d['decision'] === 'approved' ? 'Bài viết đã được duyệt' : 'Bài viết cần điều chỉnh', 'body' => $d['reason']]));
        app(BookingService::class)->audit($r->user(), 'ai.moderation_decided', $post->id, $d, 'post');

        return response()->json(['data' => $post]);
    }
}
