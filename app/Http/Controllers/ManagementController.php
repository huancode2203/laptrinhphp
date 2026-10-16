<?php

namespace App\Http\Controllers;

use App\AccessService;
use App\BookingService;
use App\FeatureService;
use App\Http\Resources\BookingResource;
use App\Http\Resources\BranchResource;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Court;
use App\Models\Maintenance;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManagementController extends Controller
{
    public function __construct(private BookingService $engine) {}

    private function scope(Builder $query, Request $request, string $column = 'branch_id'): Builder
    {
        $request->validate(['branch_id' => ['sometimes', 'integer', 'exists:branches,id']]);

        return app(AccessService::class)->scope($query, $request, $column);
    }

    public function branches(Request $request): AnonymousResourceCollection
    {
        return BranchResource::collection($this->scope(Branch::query(), $request, 'id')->withCount('courts')->get());
    }

    public function saveBranch(Request $request, ?Branch $branch = null): BranchResource
    {
        $branch ??= new Branch;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'district' => ['required', 'string', 'max:100'],
            'ward' => ['sometimes', 'string', 'max:100'], 'google_place_id' => ['nullable', 'string', 'max:255'], 'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'], 'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'address' => ['required', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:30'],
            'amenities' => ['sometimes', 'array', 'max:20'], 'amenities.*' => ['string', 'max:100'], 'active' => ['required', 'boolean'],
            'opens_minute' => 'sometimes|integer|between:0,1380|multiple_of:30', 'closes_minute' => 'sometimes|integer|between:30,1410|multiple_of:30|gt:opens_minute',
            'peak_minute' => 'sometimes|integer|between:0,1410|multiple_of:30', 'booking_days' => 'sometimes|integer|between:1,180',
            'deposit_percent' => 'sometimes|integer|between:0,100', 'payment_minutes' => 'sometimes|integer|between:5,120', 'fixed_discount' => 'sometimes|integer|between:0,50',
            'court_count' => 'sometimes|integer|between:1,40', 'hourly_rate' => 'sometimes|integer|between:1000,10000000', 'peak_rate' => 'sometimes|integer|between:1000,10000000', 'weekend_rate' => 'sometimes|integer|between:1000,10000000',
            'manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'manager')->where('active', true)],
        ]);
        DB::transaction(function () use ($branch, $data, $request): void {
            $data['ward'] = $data['ward'] ?? $data['district'];
            $creating = ! $branch->exists;
            $branch->fill(array_diff_key($data, array_flip(['court_count', 'hourly_rate', 'peak_rate', 'weekend_rate', 'manager_id'])));
            abort_if(($branch->opens_minute ?? 180) >= ($branch->closes_minute ?? 1380), 422, 'Giờ đóng cửa phải sau giờ mở cửa.');
            $branch->save();
            if ($creating) {
                for ($number = 1; $number <= ($data['court_count'] ?? 6); $number++) {
                    $branch->courts()->create(['name' => sprintf('Sân %02d', $number), 'status' => 'active', 'hourly_rate' => $data['hourly_rate'] ?? 100000, 'peak_rate' => $data['peak_rate'] ?? 180000, 'weekend_rate' => $data['weekend_rate'] ?? 200000]);
                }
            }
            if (! empty($data['manager_id'])) {
                DB::table('user_branches')->updateOrInsert(['user_id' => $data['manager_id'], 'branch_id' => $branch->id], []);
            }
            $this->engine->audit($request->user(), 'branch.saved', $branch->id, $data, 'branch');
        }, 5);

        return new BranchResource($branch);
    }

    public function calendar(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate(['date' => ['required_without:from', 'date_format:Y-m-d'], 'from' => 'required_without:date|date_format:Y-m-d', 'to' => 'required_with:from|date_format:Y-m-d|after_or_equal:from', 'status' => ['sometimes', Rule::in(['confirmed', 'checked_in', 'completed', 'cancelled'])]]);
        $from = CarbonImmutable::parse($data['from'] ?? $data['date']);
        $to = CarbonImmutable::parse($data['to'] ?? $data['date']);
        abort_if($from->diffInDays($to) > 42, 422);
        $query = Booking::query()->whereBetween('starts_at', [$from->startOfDay(), $to->endOfDay()])->whereHas('court', fn ($query) => $this->scope($query, $request));
        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }

        return BookingResource::collection($query->with(['user', 'court.branch', 'services', 'payments'])->orderBy('starts_at')->get());
    }

    public function findBooking(Request $request): BookingResource
    {
        $data = $request->validate(['reference' => ['required', 'string', 'max:32']]);
        $booking = Booking::query()->where('reference', $data['reference'])->whereHas('court', fn ($query) => $this->scope($query, $request))->firstOrFail();

        return new BookingResource($booking->load(['user', 'court.branch', 'services', 'payments']));
    }

    public function posBookings(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate(['branch_id' => ['required', 'integer', 'exists:branches,id']]);
        $this->engine->assertManage($request->user(), (int) $data['branch_id'], 'bookings.read');
        abort_unless(app(AccessService::class)->allows($request->user(), 'bookings.write') && app(AccessService::class)->allows($request->user(), 'pos.write'), 403);
        app(FeatureService::class)->expireBookings();
        $query = Booking::query()->whereHas('court', fn ($court) => $this->scope($court, $request))
            ->where(fn ($bookings) => $bookings->where('status', 'checked_in')->orWhere(fn ($bookings) => $bookings->where('status', 'confirmed')->whereDate('starts_at', now()->toDateString())));

        return BookingResource::collection($query->with(['user', 'court.branch'])->orderByRaw("CASE WHEN status = 'checked_in' THEN 0 ELSE 1 END")->orderBy('starts_at')->orderBy('id')->get());
    }

    public function courts(Request $request): JsonResponse
    {
        if ($request->has('page') || $request->has('search') || $request->has('status')) {
            $data = $request->validate(['page' => 'sometimes|integer|min:1|max:100000', 'per_page' => 'sometimes|integer|between:1,50', 'search' => 'nullable|string|max:100', 'status' => ['nullable', Rule::in(['active', 'maintenance', 'locked'])]]);
            $query = $this->scope(Court::query(), $request)->with('branch');
            if (! empty($data['search'])) {
                $query->where('name', 'like', '%'.str_replace(['%', '_'], '', $data['search']).'%');
            }
            if (! empty($data['status'])) {
                $query->where('status', $data['status']);
            }

            return response()->json(['data' => $query->orderBy('id')->paginate($data['per_page'] ?? 10)]);
        }

        return response()->json(['data' => $this->scope(Court::query(), $request)->with('branch')->orderBy('id')->get()]);
    }

    public function saveCourt(Request $request, ?Court $court = null): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:100', Rule::unique('courts')->where('branch_id', $request->integer('branch_id'))->ignore($court?->id)],
            'status' => ['required', Rule::in(['active', 'maintenance', 'locked'])],
            'hourly_rate' => ['required', 'integer', 'min:1000', 'max:10000000'],
            'peak_rate' => ['required', 'integer', 'min:1000', 'max:10000000'],
            'weekend_rate' => ['required', 'integer', 'min:1000', 'max:10000000'],
        ]);
        $this->engine->assertManage($request->user(), (int) $data['branch_id']);

        return DB::transaction(function () use ($request, $court, $data): JsonResponse {
            if ($court) {
                $court = Court::query()->lockForUpdate()->findOrFail($court->id);
                $this->engine->assertManage($request->user(), $court->branch_id);
                if ($court->branch_id !== (int) $data['branch_id']) {
                    throw ValidationException::withMessages(['branch_id' => 'Không chuyển sân sang chi nhánh khác.']);
                }
                if ($data['status'] !== 'active' && $court->bookings()->whereIn('status', ['confirmed', 'checked_in'])->where('ends_at', '>', now())->lockForUpdate()->first()) {
                    throw ValidationException::withMessages(['status' => 'Sân còn lịch đặt. Xử lý các lịch trước khi khóa sân, hoặc tạo lịch bảo trì theo khung giờ.']);
                }
                if ($data['status'] !== 'active' && ($court->blocks()->where('active', true)->where('ends_at', '>', now())->exists() || $court->holds()->where('released', false)->where('expires_at', '>', now())->exists())) {
                    throw ValidationException::withMessages(['status' => 'Sân còn sự kiện hoặc đang giữ chỗ. Xử lý các lịch này trước khi khóa sân.']);
                }
            } else {
                $court = new Court;
            }
            $court->fill($data)->save();
            $this->engine->audit($request->user(), 'court.saved', $court->id, $data, 'court');

            return response()->json(['data' => $court]);
        }, 5);
    }

    public function services(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->scope(Service::query(), $request)->orderBy('id')->get()]);
    }

    public function saveService(Request $request, ?Service $service = null): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:100', Rule::unique('services')->where('branch_id', $request->integer('branch_id'))->ignore($service?->id)],
            'unit' => ['required', 'string', 'max:30'], 'price' => ['required', 'integer', 'min:0', 'max:10000000'], 'active' => ['required', 'boolean'],
        ]);
        $this->engine->assertManage($request->user(), (int) $data['branch_id']);
        if ($service) {
            $this->engine->assertManage($request->user(), $service->branch_id);
            if ($service->branch_id !== (int) $data['branch_id']) {
                throw ValidationException::withMessages(['branch_id' => 'Không chuyển dịch vụ sang chi nhánh khác.']);
            }
        }
        $service ??= new Service;
        $service->fill($data)->save();
        $this->engine->audit($request->user(), 'service.saved', $service->id, $data, 'service');

        return response()->json(['data' => $service]);
    }

    public function maintenances(Request $request): JsonResponse
    {
        return response()->json(['data' => Maintenance::query()->whereHas('court', fn ($query) => $this->scope($query, $request))->with('court.branch')->orderByDesc('starts_at')->paginate(30)]);
    }

    public function createMaintenance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'court_id' => ['required', 'integer', 'exists:courts,id'],
            'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $start = CarbonImmutable::parse($data['starts_at'], config('app.timezone'))->setTimezone(config('app.timezone'));
        $end = CarbonImmutable::parse($data['ends_at'], config('app.timezone'))->setTimezone(config('app.timezone'));
        if ($end->lte($start) || $start->lt(now()->startOfMinute())) {
            throw ValidationException::withMessages(['starts_at' => 'Lịch bảo trì phải ở tương lai và có giờ kết thúc sau giờ bắt đầu.']);
        }

        return DB::transaction(function () use ($request, $data, $start, $end): JsonResponse {
            $court = Court::query()->lockForUpdate()->findOrFail($data['court_id']);
            $this->engine->assertManage($request->user(), $court->branch_id);
            $this->engine->assertAvailable($court, $start, $end);
            $maintenance = Maintenance::create(['court_id' => $court->id, 'starts_at' => $start, 'ends_at' => $end, 'reason' => $data['reason']]);
            $this->engine->audit($request->user(), 'maintenance.created', $maintenance->id, $data, 'maintenance');

            return response()->json(['data' => $maintenance], 201);
        }, 5);
    }

    public function maintenanceStatus(Request $request, Maintenance $maintenance): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['completed', 'cancelled'])]]);

        return DB::transaction(function () use ($request, $maintenance, $data): JsonResponse {
            $court = Court::query()->lockForUpdate()->findOrFail($maintenance->court_id);
            $this->engine->assertManage($request->user(), $court->branch_id);
            $maintenance = Maintenance::query()->lockForUpdate()->findOrFail($maintenance->id);
            if ($maintenance->status !== 'scheduled' && $maintenance->status !== $data['status']) {
                throw ValidationException::withMessages(['status' => 'Lịch bảo trì đã kết thúc hoặc đã hủy.']);
            }
            if ($data['status'] === 'completed' && now()->lt($maintenance->ends_at)) {
                throw ValidationException::withMessages(['status' => 'Chỉ hoàn tất khi đã hết thời gian bảo trì.']);
            }
            $maintenance->update($data);
            $this->engine->audit($request->user(), 'maintenance.'.$data['status'], $maintenance->id, $data, 'maintenance');

            return response()->json(['data' => $maintenance]);
        }, 5);
    }

    public function customers(Request $request): JsonResponse
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'user_id' => 'nullable|integer|exists:users,id', 'branch_id' => 'nullable|integer|exists:branches,id']);
        $branch = $data['branch_id'] ?? ($request->user()->role === 'admin' ? null : ($request->user()->branch_id ?? (app(AccessService::class)->branches($request->user())[0] ?? 0)));
        abort_if($request->user()->role !== 'admin' && ! $branch, 403);
        if ($branch) {
            app(BookingService::class)->assertManage($request->user(), $branch);
        }
        $term = str_replace(['%', '_', '\\'], '', $data['search'] ?? '');
        $query = User::query()->where('role', 'customer')->where('active', true);
        if ($term !== '') {
            $alternate = str_starts_with($term, '+84') ? '0'.substr($term, 3) : (str_starts_with($term, '0') ? '+84'.substr($term, 1) : $term);
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%')->orWhere('phone', 'like', '%'.$term.'%')->orWhere('phone', 'like', '%'.$alternate.'%'));
        } elseif (! empty($data['user_id'])) {
            $query->whereKey($data['user_id']);
        } else {
            $query->whereHas('bookings', fn ($q) => $q->when($branch, fn ($q) => $q->whereHas('court', fn ($q) => $q->where('branch_id', $branch))));
        }
        $scope = fn ($q) => $q->where('status', '!=', 'cancelled')->when($branch, fn ($q) => $q->whereHas('court', fn ($q) => $q->where('branch_id', $branch)));
        $customers = $query->withCount(['bookings as visits' => $scope])->withSum(['bookings as spent' => $scope], 'paid_amount')->withMax(['bookings as last_visit' => $scope], 'starts_at')->withCasts(['spent' => 'integer', 'visits' => 'integer'])->with(['memberships' => fn ($q) => $q->where('expires_at', '>', now())->with('plan')])->orderBy('name')->limit(50)->get(['id', 'name', 'email', 'phone']);

        return response()->json(['data' => $customers]);
    }

    public function reports(Request $request): JsonResponse
    {
        $data = $request->validate(['from' => ['sometimes', 'date_format:Y-m-d'], 'to' => ['sometimes', 'date_format:Y-m-d']]);
        $from = CarbonImmutable::parse($data['from'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $to = CarbonImmutable::parse($data['to'] ?? now()->toDateString())->endOfDay();
        if ($to->lt($from) || $from->diffInDays($to) > 366) {
            throw ValidationException::withMessages(['to' => 'Chọn khoảng thống kê hợp lệ, tối đa một năm.']);
        }
        $payments = Payment::query()->whereBetween('created_at', [$from, $to])->whereHas('booking.court', fn ($query) => $this->scope($query, $request));
        $revenue = (clone $payments)->where('kind', 'payment')->sum('amount');
        $refunds = (clone $payments)->where('kind', 'refund')->sum('amount');
        $daily = (clone $payments)->selectRaw("DATE(created_at) AS date, SUM(CASE WHEN kind = 'refund' THEN -CAST(amount AS SIGNED) ELSE CAST(amount AS SIGNED) END) AS revenue")->groupByRaw('DATE(created_at)')->orderBy('date')->get();
        $bookings = Booking::query()->whereBetween('starts_at', [$from, $to])->whereHas('court', fn ($query) => $this->scope($query, $request));

        return response()->json(['data' => [
            'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'collected' => (int) $revenue, 'refunded' => (int) $refunds, 'net_revenue' => (int) $revenue - (int) $refunds,
            'bookings' => (clone $bookings)->count(), 'cancelled' => (clone $bookings)->where('status', 'cancelled')->count(),
            'outstanding' => (int) (clone $bookings)->where('status', '!=', 'cancelled')->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) AS amount')->value('amount'),
            'daily' => $daily->map(fn ($row) => ['date' => $row->date, 'revenue' => (int) $row->revenue]),
        ]]);
    }

    public function users(Request $request): JsonResponse
    {
        return response()->json(['data' => User::query()->orderByDesc('id')->paginate(30, ['id', 'name', 'email', 'phone', 'role', 'branch_id'])]);
    }

    public function userRole(Request $request, User $user): JsonResponse
    {
        abort_if($user->id === $request->user()->id, 422, 'Không tự thay đổi quyền tài khoản đang đăng nhập.');
        $data = $request->validate([
            'role' => ['required', Rule::in(['customer', 'manager', 'staff', 'cashier', 'admin'])],
            'branch_id' => ['nullable', 'required_if:role,manager,staff,cashier', 'integer', 'exists:branches,id'],
        ]);
        DB::transaction(function () use ($user, $data): void {
            DB::table('user_branches')->where('user_id', $user->id)->delete();
            $user->forceFill(['role' => $data['role'], 'branch_id' => in_array($data['role'], ['manager', 'staff', 'cashier'], true) ? $data['branch_id'] : null])->save();
        });
        $this->engine->audit($request->user(), 'user.role_changed', $user->id, $data, 'user');

        return response()->json(['data' => $user]);
    }
}
