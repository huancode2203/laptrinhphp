<?php

namespace App\Http\Controllers;

use App\BookingService;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return BookingResource::collection(Booking::query()->where('user_id', $request->user()->id)->with(['court.branch', 'services', 'payments'])->orderByDesc('starts_at')->paginate(20));
    }

    public function store(StoreBookingRequest $request, BookingService $engine): BookingResource
    {
        return new BookingResource($engine->create($request->user(), $request->validated()));
    }

    public function show(Request $request, Booking $booking, BookingService $engine): BookingResource
    {
        if ($request->user()->role === 'customer') {
            abort_unless($booking->user_id === $request->user()->id, 403);
        } else {
            $engine->assertManage($request->user(), $booking->court->branch_id);
        }

        return new BookingResource($booking->load(['court.branch', 'services', 'payments']));
    }

    public function status(Request $request, Booking $booking, BookingService $engine): BookingResource
    {
        $data = $request->validate(['status' => ['required', Rule::in(['cancelled', 'checked_in', 'completed'])]]);

        return new BookingResource($engine->changeStatus($request->user(), $booking, $data['status']));
    }

    public function payment(Request $request, Booking $booking, BookingService $engine): JsonResponse
    {
        $data = $request->validate([
            'request_key' => ['required', 'uuid'],
            'kind' => ['sometimes', Rule::in(['payment', 'refund'])],
            'amount' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'method' => ['required', Rule::in(['cash', 'bank'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $payment = $engine->recordPayment($request->user(), $booking, $data);

        return response()->json(['data' => ['id' => $payment->id, 'booking_id' => $payment->booking_id, 'kind' => $payment->kind, 'amount' => $payment->amount, 'method' => $payment->method], 'message' => 'Đã ghi nhận giao dịch tại quầy.']);
    }
}
