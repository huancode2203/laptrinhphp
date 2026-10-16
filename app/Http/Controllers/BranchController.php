<?php

namespace App\Http\Controllers;

use App\AccessService;
use App\BookingService;
use App\Http\Resources\BranchResource;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Court;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BranchController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate(['district' => ['sometimes', 'string', 'max:100'], 'ward' => ['sometimes', 'string', 'max:100']]);
        $query = Branch::query()->where('active', true)->withCount(['courts' => fn ($query) => $query->where('status', 'active')]);
        if (isset($data['ward']) || isset($data['district'])) {
            $area = $data['ward'] ?? $data['district'];
            $query->where(fn ($q) => $q->where('ward', $area)->orWhere(fn ($q) => $q->whereNull('ward')->where('district', $area)));
        }

        return BranchResource::collection($query->orderBy('id')->get());
    }

    public function show(Branch $branch): BranchResource
    {
        abort_unless($branch->active, 404);

        return new BranchResource($branch->load(['courts', 'services' => fn ($query) => $query->where('active', true)]));
    }

    public function availability(Request $request, Branch $branch, BookingService $engine): JsonResponse
    {
        abort_unless($branch->active, 404);
        $data = $request->validate(['starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'], 'exclude_booking_id' => 'sometimes|integer|exists:bookings,id']);
        $exclude = $data['exclude_booking_id'] ?? null;
        if ($exclude) {
            $booking = Booking::findOrFail($exclude);
            abort_unless($request->user() && $booking->court->branch_id === $branch->id, 403);
            if ($booking->user_id !== $request->user()->id) {
                $engine->assertManage($request->user(), $branch->id, 'bookings.write');
            }
        }
        [$start, $end] = $engine->window($data['starts_at'], $data['ends_at']);
        $first = $branch->courts()->where('status', 'active')->first();
        if ($first) {
            $engine->assertOpen($first, $start, $end, $request->user());
        }
        $courts = $branch->courts()->where('status', 'active')
            ->withCount([
                'bookings as conflicts' => fn ($query) => $query->when($exclude, fn ($q) => $q->where('id', '!=', $exclude))->where('status', '!=', 'cancelled')->where('starts_at', '<', $end)->where('ends_at', '>', $start),
                'maintenances as maintenance_conflicts' => fn ($query) => $query->where('status', 'scheduled')->where('starts_at', '<', $end)->where('ends_at', '>', $start),
                'holds as hold_conflicts' => fn ($query) => $query->where('released', false)->where('expires_at', '>', now())->where('user_id', '!=', $request->user()?->id ?? 0)->where('starts_at', '<', $end)->where('ends_at', '>', $start),
                'blocks as event_conflicts' => fn ($query) => $query->where('active', true)->where('starts_at', '<', $end)->where('ends_at', '>', $start),
            ])->get();

        return response()->json(['data' => $courts->map(fn (Court $court) => [
            'id' => $court->id, 'name' => $court->name, 'available' => $court->conflicts === 0 && $court->maintenance_conflicts === 0 && $court->hold_conflicts === 0 && $court->event_conflicts === 0,
            'court_amount' => $engine->quote($court, $start, $end)['court_amount'],
        ])]);
    }

    public function quote(Request $request, Court $court, BookingService $engine): JsonResponse
    {
        abort_unless($court->status === 'active' && $court->branch->active, 404);
        $data = $request->validate([
            'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'],
            'services' => ['sometimes', 'array', 'list', 'max:20'],
            'services.*.id' => ['required', 'integer', 'distinct', 'exists:services,id'],
            'services.*.quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'coupon_code' => ['nullable', 'string', 'max:30'], 'fixed' => ['sometimes', 'boolean'],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'occurrences' => ['sometimes', 'integer', 'between:2,12'],
        ]);
        [$start, $end] = $engine->window($data['starts_at'], $data['ends_at']);

        $owner = $request->user();
        if (isset($data['user_id']) && (int) $data['user_id'] !== $owner?->id) {
            abort_unless($owner, 403);
            $engine->assertManage($owner, $court->branch_id);
            $owner = User::where('role', 'customer')->findOrFail($data['user_id']);
        }

        $engine->assertOpen($court, $start, $end, $owner);

        if ($request->boolean('fixed') && isset($data['occurrences'])) {
            abort_if(! empty($data['coupon_code']), 422, 'Lịch cố định có ưu đãi riêng, không dùng thêm mã giảm giá.');

            return response()->json(['data' => $engine->seriesQuote($court, $start, $end, (int) $data['occurrences'], $data['services'] ?? [], $owner, $request->user()), 'currency' => 'VND']);
        }
        $quote = $engine->quote($court, $start, $end, $data['services'] ?? [], $owner, $data['coupon_code'] ?? null, $data['fixed'] ?? false);
        $quote['deposit_amount'] = $request->user()?->role === 'customer' ? (int) ceil($quote['total_amount'] * $court->branch->deposit_percent / 100) : 0;

        return response()->json(['data' => $quote, 'currency' => 'VND']);
    }

    public function schedule(Request $request, Branch $branch, BookingService $engine): JsonResponse
    {
        $staff = $request->user() && app(AccessService::class)->manages($request->user(), $branch->id);
        abort_unless($branch->active || $staff, 404);
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
        $day = CarbonImmutable::parse($data['date'], config('app.timezone'))->startOfDay();
        abort_if($day->lt($staff ? now()->subDays(366)->startOfDay() : now()->startOfDay()) || $day->gt(now()->addDays(90)->startOfDay()), 422, 'Ngày xem lịch nằm ngoài khoảng cho phép.');
        $courts = $branch->courts()->with([
            'bookings' => fn ($query) => $query->where('status', '!=', 'cancelled')->where('starts_at', '<', $day->addDay())->where('ends_at', '>', $day),
            'maintenances' => fn ($query) => $query->where('status', 'scheduled')->where('starts_at', '<', $day->addDay())->where('ends_at', '>', $day),
            'holds' => fn ($query) => $query->where('released', false)->where('expires_at', '>', now())->where('user_id', '!=', $request->user()?->id ?? 0)->where('starts_at', '<', $day->addDay())->where('ends_at', '>', $day),
            'blocks' => fn ($query) => $query->where('active', true)->where('starts_at', '<', $day->addDay())->where('ends_at', '>', $day),
        ])->orderBy('id')->get();
        $private = $staff && app(AccessService::class)->allows($request->user(), 'bookings.read');
        $schedule = $courts->map(function (Court $court) use ($day, $engine, $private): array {
            $slots = [];
            for ($start = $day->addMinutes($court->branch->opens_minute); $start->lt($day->addMinutes($court->branch->closes_minute)); $start = $start->addMinutes(30)) {
                $end = $start->addMinutes(30);
                $booking = $court->bookings->first(fn ($item) => $item->starts_at->lt($end) && $item->ends_at->gt($start));
                $state = $court->status === 'active' ? 'available' : $court->status;
                if ($court->maintenances->contains(fn ($item) => $item->starts_at->lt($end) && $item->ends_at->gt($start))) {
                    $state = 'maintenance';
                } elseif ($booking) {
                    $state = $booking->status === 'checked_in' ? 'playing' : ($booking->booking_type === 'fixed' ? 'fixed' : 'reserved');
                } elseif ($court->blocks->contains(fn ($item) => $item->starts_at->lt($end) && $item->ends_at->gt($start))) {
                    $state = 'event';
                } elseif ($court->holds->contains(fn ($item) => $item->starts_at->lt($end) && $item->ends_at->gt($start))) {
                    $state = 'held';
                } elseif ($start->lt(now()->startOfMinute())) {
                    $state = 'past';
                }
                $slots[] = ['starts_at' => $start->toIso8601String(), 'ends_at' => $end->toIso8601String(), 'state' => $state, 'available' => $state === 'available' && $start->gte(now()->startOfMinute()), 'court_amount' => $engine->quote($court, $start, $end)['court_amount'], ...($private && $booking ? ['booking_id' => $booking->id, 'reference' => $booking->reference] : [])];
            }

            return ['id' => $court->id, 'name' => $court->name, 'status' => $court->status, 'slots' => $slots];
        });

        return response()->json(['data' => ['branch_id' => $branch->id, 'date' => $day->toDateString(), 'slot_minutes' => 30, 'courts' => $schedule], 'currency' => 'VND']);
    }
}
