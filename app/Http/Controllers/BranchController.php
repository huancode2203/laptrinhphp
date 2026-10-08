<?php

namespace App\Http\Controllers;

use App\BookingService;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Models\Court;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BranchController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate(['district' => ['sometimes', 'string', 'max:100']]);
        $query = Branch::query()->where('active', true)->withCount(['courts' => fn ($query) => $query->where('status', 'active')]);
        if (isset($data['district'])) {
            $query->where('district', $data['district']);
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
        $data = $request->validate(['starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at']]);
        [$start, $end] = $engine->window($data['starts_at'], $data['ends_at']);
        $courts = $branch->courts()->where('status', 'active')
            ->withCount([
                'bookings as conflicts' => fn ($query) => $query->where('status', '!=', 'cancelled')->where('starts_at', '<', $end)->where('ends_at', '>', $start),
                'maintenances as maintenance_conflicts' => fn ($query) => $query->where('status', 'scheduled')->where('starts_at', '<', $end)->where('ends_at', '>', $start),
            ])->get();

        return response()->json(['data' => $courts->map(fn (Court $court) => [
            'id' => $court->id, 'name' => $court->name, 'available' => $court->conflicts === 0 && $court->maintenance_conflicts === 0,
            'court_amount' => $engine->quote($court, $start, $end)['court_amount'],
        ])]);
    }

    public function quote(Request $request, Court $court, BookingService $engine): JsonResponse
    {
        abort_unless($court->status === 'active' && $court->branch->active, 404);
        $data = $request->validate([
            'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'],
            'services' => ['sometimes', 'array', 'max:20'],
            'services.*.id' => ['required', 'integer', 'distinct', 'exists:services,id'],
            'services.*.quantity' => ['required', 'integer', 'min:1', 'max:10'],
        ]);
        [$start, $end] = $engine->window($data['starts_at'], $data['ends_at']);

        return response()->json(['data' => $engine->quote($court, $start, $end, $data['services'] ?? []), 'currency' => 'VND']);
    }

    public function schedule(Request $request, Branch $branch, BookingService $engine): JsonResponse
    {
        abort_unless($branch->active, 404);
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
        $day = CarbonImmutable::parse($data['date'], config('app.timezone'))->startOfDay();
        abort_if($day->lt(now()->startOfDay()) || $day->gt(now()->addDays(90)->startOfDay()), 422, 'Chọn ngày từ hôm nay đến 90 ngày tới.');
        $courts = $branch->courts()->with([
            'bookings' => fn ($query) => $query->where('status', '!=', 'cancelled')->where('starts_at', '<', $day->addDay())->where('ends_at', '>', $day),
            'maintenances' => fn ($query) => $query->where('status', 'scheduled')->where('starts_at', '<', $day->addDay())->where('ends_at', '>', $day),
        ])->orderBy('id')->get();
        $schedule = $courts->map(function (Court $court) use ($day, $engine): array {
            $slots = [];
            for ($start = $day->setTime(3, 0); $start->lt($day->setTime(23, 0)); $start = $start->addMinutes(30)) {
                $end = $start->addMinutes(30);
                $state = $court->status === 'active' ? 'available' : $court->status;
                if ($start->lt(now()->startOfMinute())) {
                    $state = 'past';
                } elseif ($court->maintenances->contains(fn ($item) => $item->starts_at->lt($end) && $item->ends_at->gt($start))) {
                    $state = 'maintenance';
                } elseif ($court->bookings->contains(fn ($item) => $item->starts_at->lt($end) && $item->ends_at->gt($start))) {
                    $state = 'reserved';
                }
                $slots[] = ['starts_at' => $start->toIso8601String(), 'ends_at' => $end->toIso8601String(), 'state' => $state, 'available' => $state === 'available', 'court_amount' => $engine->quote($court, $start, $end)['court_amount']];
            }

            return ['id' => $court->id, 'name' => $court->name, 'status' => $court->status, 'slots' => $slots];
        });

        return response()->json(['data' => ['branch_id' => $branch->id, 'date' => $day->toDateString(), 'slot_minutes' => 30, 'courts' => $schedule], 'currency' => 'VND']);
    }
}
