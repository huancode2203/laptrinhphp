<?php

namespace App\Http\Controllers;

use App\AccessService;
use App\BookingService;
use App\FeatureService;
use App\Http\Resources\BookingResource;
use App\Http\Resources\BranchResource;
use App\IntegrationStatus;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\CommunityPost;
use App\Models\Coupon;
use App\Models\Court;
use App\Models\CourtHold;
use App\Models\GameEvent;
use App\Models\Maintenance;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\Review;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Models\WalletEntry;
use App\MomoService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HubController extends Controller
{
    public function bootstrap(Request $r): JsonResponse
    {
        app(FeatureService::class)->expireBookings();
        $branches = Branch::where('active', true)->with(['courts', 'services' => fn ($q) => $q->where('active', true)])->withCount('courts')->get();
        $ratings = DB::table('reviews')->where('visible', true)->selectRaw('branch_id, AVG(rating) AS rating, COUNT(*) AS count')->groupBy('branch_id')->get()->keyBy('branch_id');
        $today = Booking::whereDate('starts_at', now()->toDateString())->where('status', '!=', 'cancelled')->get(['status', 'starts_at']);
        $stats = ['members' => User::where('role', 'customer')->where('active', true)->count(), 'bookings' => $today->count(), 'checkins' => $today->whereIn('status', ['checked_in', 'completed'])->count(), 'playing' => $today->where('status', 'checked_in')->count(), 'hours' => collect(range(6, 19))->map(fn ($h) => $today->filter(fn ($b) => $b->starts_at->hour === $h)->count())->all()];

        return response()->json(['data' => ['stats' => $stats, 'user' => $r->user(), 'branches' => BranchResource::collection($branches), 'plans' => MembershipPlan::where('active', true)->get(), 'ratings' => $ratings, 'momo_enabled' => app(MomoService::class)->enabled(), 'google_enabled' => app(IntegrationStatus::class)->ready('google'), 'sms_enabled' => app(IntegrationStatus::class)->ready('sms'), 'email_enabled' => app(IntegrationStatus::class)->emailEnabled(), 'auth_notice' => $r->session()->pull('auth_notice'), 'bank' => app(IntegrationStatus::class)->ready('sepay') ? collect(config('integrations.bank'))->only(['account', 'name', 'bank_bin'])->all() : null, 'permissions' => $r->user() ? collect(AccessService::PERMISSIONS)->mapWithKeys(fn ($p) => [$p => app(AccessService::class)->allows($r->user(), $p)])->all() : [], 'managed_branch_ids' => $r->user() ? app(AccessService::class)->branches($r->user()) : [], 'membership' => $r->user() ? Membership::where('user_id', $r->user()->id)->where('expires_at', '>', now())->with('plan')->latest('id')->first() : null]]);
    }

    public function account(Request $r): JsonResponse
    {
        return response()->json(['data' => [
            'my_posts' => CommunityPost::where('user_id', $r->user()->id)->with('branch:id,name,district')->latest('id')->get(),
            'my_matches' => CommunityPost::whereHas('joins', fn ($q) => $q->where('user_id', $r->user()->id)->whereIn('status', ['pending', 'accepted']))->where('moderation_status', 'approved')->where('status', '!=', 'hidden')->with('branch:id,name,district')->latest('starts_at')->get(),
            'coupons' => Coupon::where('active', true)->where('starts_at', '<=', now())->where('expires_at', '>', now())->get(['code', 'branch_id', 'kind', 'value', 'max_discount', 'minimum_amount', 'expires_at']),
            'user' => $r->user(), 'membership' => Membership::where('user_id', $r->user()->id)->where('expires_at', '>', now())->with('plan')->latest('id')->first(), 'payment_attempts' => PaymentAttempt::where('user_id', $r->user()->id)->latest('id')->limit(30)->get(), 'wallet_entries' => WalletEntry::where('user_id', $r->user()->id)->latest('id')->limit(100)->get(), 'memberships' => Membership::where('user_id', $r->user()->id)->with('plan')->latest('id')->get(), 'waitlist' => WaitlistEntry::where('user_id', $r->user()->id)->with('court.branch')->latest('id')->limit(50)->get()]]);
    }

    public function reviews(Branch $branch): JsonResponse
    {
        return response()->json(['data' => Review::where('branch_id', $branch->id)->where('visible', true)->with('user:id,name')->latest('id')->paginate(20)]);
    }

    private function branch(Request $r): ?int
    {
        $r->validate(['branch_id' => ['sometimes', 'integer', 'exists:branches,id']]);
        if ($r->user()->role !== 'admin') {
            $id = $r->integer('branch_id', $r->user()->branch_id ?? 0);
            abort_unless(app(AccessService::class)->manages($r->user(), $id), 403);

            return $id;
        }

        return $r->filled('branch_id') ? $r->integer('branch_id') : null;
    }

    public function manage(Request $r): JsonResponse
    {
        $r->validate(['date' => ['sometimes', 'date_format:Y-m-d']]);
        $branch = $this->branch($r);
        $date = $r->input('date', now()->toDateString());
        $scope = fn ($q) => $q->when($branch !== null, fn ($q) => $q->where('branch_id', $branch));
        $access = app(AccessService::class);
        $bookings = Booking::whereDate('starts_at', $date)->whereHas('court', $scope)->with(['user', 'court.branch', 'services', 'payments'])->orderBy('starts_at')->get();

        return response()->json(['data' => ['branches' => BranchResource::collection(Branch::query()->when($branch !== null, fn ($q) => $q->where('id', $branch))->get()), 'bookings' => $access->allows($r->user(), 'bookings.read') ? BookingResource::collection($bookings) : [], 'courts' => $scope(Court::with('branch'))->get(), 'services' => $scope(Service::query())->get(), 'products' => $scope(Product::query())->get(), 'sales' => $access->allows($r->user(), 'pos.read') ? $scope(Sale::with('items'))->latest('id')->limit(100)->get() : [], 'maintenances' => Maintenance::whereHas('court', $scope)->with('court')->latest('id')->limit(100)->get(), 'events' => $scope(GameEvent::query())->with('blocks')->withCount('registrations')->orderByDesc('starts_at')->limit(100)->get()]]);
    }

    public function report(Request $r): JsonResponse
    {
        $d = $r->validate(['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $from = CarbonImmutable::parse($d['from'])->startOfDay();
        $to = CarbonImmutable::parse($d['to'])->endOfDay();
        abort_if($from->diffInDays($to) > 366, 422, 'Tối đa một năm.');
        $branch = $this->branch($r);
        $scope = fn ($q) => $q->when($branch !== null, fn ($q) => $q->where('branch_id', $branch));
        $payments = Payment::whereBetween('created_at', [$from, $to])->whereHas('booking.court', $scope)->get(['amount', 'kind', 'created_at', 'method']);
        $commerce = $scope(DB::table('commerce_payments')->whereBetween('created_at', [$from, $to]))->get();
        $daily = [];
        $collected = 0;
        $refunded = 0;
        $methods = [];
        foreach ($payments->concat($commerce) as $p) {
            $value = $p->kind === 'refund' ? -(int) $p->amount : (int) $p->amount;
            $key = substr((string) $p->created_at, 0, 10);
            $daily[$key] = ($daily[$key] ?? 0) + $value;
            $methods[$p->method] = ($methods[$p->method] ?? 0) + $value;
            if ($value >= 0) {
                $collected += $value;
            } else {
                $refunded -= $value;
            }
        }
        ksort($daily);
        $bookings = Booking::whereBetween('starts_at', [$from, $to])->whereHas('court', $scope);
        $data = ['from' => $d['from'], 'to' => $d['to'], 'collected' => $collected, 'refunded' => $refunded, 'net_revenue' => $collected - $refunded, 'bookings' => (clone $bookings)->count(), 'cancelled' => (clone $bookings)->where('status', 'cancelled')->count(), 'outstanding' => (int) (clone $bookings)->where('status', '!=', 'cancelled')->selectRaw('COALESCE(SUM(total_amount-paid_amount),0) as total')->value('total'), 'daily' => collect($daily)->map(fn ($v, $k) => ['date' => $k, 'revenue' => $v])->values(), 'methods' => $methods];

        return response()->json(['data' => $data]);
    }

    public function csv(Request $r): StreamedResponse
    {
        $data = $this->report($r)->getData(true)['data'];

        return response()->streamDownload(function () use ($data): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Ngày', 'Doanh thu ròng (VND)']);
            foreach ($data['daily'] as $row) {
                fputcsv($out, [$row['date'], $row['revenue']]);
            }fclose($out);
        }, 'bao-cao.csv', ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    public function admin(Request $r): JsonResponse
    {
        return response()->json(['data' => ['stats' => ['users' => User::count(), 'pending_posts' => DB::table('community_posts')->where('moderation_status', 'pending')->count()], 'branches' => Branch::withCount('courts')->get(), 'users' => User::latest('id')->limit(500)->get(['id', 'name', 'email', 'phone', 'role', 'branch_id', 'active']), 'plans' => MembershipPlan::all(), 'coupons' => Coupon::latest('id')->get(), 'audits' => DB::table('audit_logs')->latest('id')->limit(100)->get(), 'reports' => DB::table('content_reports')->join('community_posts', 'community_posts.id', '=', 'content_reports.community_post_id')->select('content_reports.*', 'community_posts.title')->latest('content_reports.id')->limit(100)->get(), 'reviews' => Review::with('user:id,name')->latest('id')->limit(100)->get()]]);
    }

    public function userActive(Request $r, User $user, BookingService $engine): JsonResponse
    {
        $d = $r->validate(['active' => ['required', 'boolean']]);
        abort_if($user->id === $r->user()->id, 422, 'Không khóa chính tài khoản của bạn.');
        DB::transaction(function () use ($user, $d, $r, $engine): void {
            $user = User::lockForUpdate()->findOrFail($user->id);
            $user->forceFill($d)->save();
            if (! $d['active']) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
                CourtHold::where('user_id', $user->id)->update(['released' => true]);
            }$engine->audit($r->user(), 'user.active', $user->id, $d, 'user');
        });

        return response()->json(['data' => $user->refresh()]);
    }

    public function createUser(Request $r, BookingService $engine): JsonResponse
    {
        $d = $r->validate(['name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'unique:users,email'], 'phone' => ['nullable', 'string', 'max:30'], 'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'], 'role' => ['required', Rule::in(['customer', 'staff', 'cashier', 'manager', 'admin'])], 'branch_id' => ['nullable', 'required_if:role,staff,cashier,manager', 'integer', 'exists:branches,id']]);
        $user = DB::transaction(function () use ($d, $r, $engine): User {
            $user = User::create(collect($d)->only(['name', 'email', 'phone', 'password'])->all());
            $user->forceFill(['role' => $d['role'], 'branch_id' => in_array($d['role'], ['staff', 'cashier', 'manager'], true) ? $d['branch_id'] : null])->save();
            $engine->audit($r->user(), 'user.created', $user->id, ['role' => $user->role, 'branch_id' => $user->branch_id], 'user');

            return $user;
        });

        return response()->json(['data' => $user], 201);
    }

    public function reviewStatus(Request $r, Review $review): JsonResponse
    {
        $d = $r->validate(['visible' => ['required', 'boolean']]);
        $review->update($d);
        app(BookingService::class)->audit($r->user(), 'review.visibility', $review->id, $d, 'review');

        return response()->json(['data' => $review]);
    }
}
