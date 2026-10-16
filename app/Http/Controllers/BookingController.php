<?php

namespace App\Http\Controllers;

use App\BookingService;
use App\CommerceService;
use App\FeatureService;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\PaymentAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

class BookingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate(['status' => ['sometimes', Rule::in(['confirmed', 'checked_in', 'completed', 'cancelled'])], 'pending' => ['sometimes', 'boolean'], 'page' => ['sometimes', 'integer', 'between:1,100000']]);
        $query = Booking::query()->where('user_id', $request->user()->id);
        app(FeatureService::class)->expireBookings();
        if ($request->boolean('pending')) {
            $query->where('status', 'confirmed')->where('ends_at', '>', now())->whereColumn('paid_amount', '<', 'total_amount')->orderBy('payment_expires_at');
        }
        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }

        return BookingResource::collection($query->with(['court.branch', 'services', 'payments', 'sales.items'])->orderByDesc('starts_at')->paginate(20));
    }

    public function store(StoreBookingRequest $request, BookingService $engine): BookingResource
    {
        return DB::transaction(function () use ($request, $engine): BookingResource {
            $booking = $engine->create($request->user(), $request->validated());
            if ($request->boolean('pay_with_wallet')) {
                app(CommerceService::class)->payBooking($request->user(), $booking, (string) Uuid::uuid5($request->input('request_key'), 'wallet-payment'), $request->boolean('deposit_only'));
            }

            return new BookingResource($booking->refresh()->load(['court.branch', 'services', 'payments', 'sales.items']));
        }, 5);
    }

    public function series(StoreBookingRequest $request, BookingService $engine): JsonResponse
    {
        $extra = $request->validate(['occurrences' => ['required', 'integer', 'between:2,12']]);
        $data = $request->validated();
        abort_if(! empty($data['coupon_code']), 422, 'Lịch cố định có ưu đãi riêng, không dùng thêm mã giảm giá.');
        [$start, $end] = $engine->window($data['starts_at'], $data['ends_at']);
        $existingCount = Booking::where('series_key', $data['request_key'])->count();
        abort_if($existingCount > 0 && $existingCount !== (int) $extra['occurrences'], 409, 'Không thay đổi số tuần của yêu cầu đã tạo.');
        $rows = DB::transaction(function () use ($engine, $request, $data, $extra, $start, $end): array {
            $rows = [];
            for ($i = 0; $i < $extra['occurrences']; $i++) {
                $item = $data;
                $item['request_key'] = (string) Uuid::uuid5($data['request_key'], 'week-'.$i);
                $item['starts_at'] = $start->addWeeks($i)->toIso8601String();
                $item['ends_at'] = $end->addWeeks($i)->toIso8601String();
                $item['_fixed'] = $data['request_key'];
                if ($i > 0) {
                    unset($item['hold_id']);
                }
                try {
                    $booking = $engine->create($request->user(), $item);
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages(collect($exception->errors())->map(fn (array $messages) => array_map(fn (string $message) => 'Buổi thứ '.($i + 1).' ('.$start->addWeeks($i)->format('d/m/Y').'): '.$message, $messages))->all());
                }
                if ($request->boolean('pay_with_wallet')) {
                    app(CommerceService::class)->payBooking($request->user(), $booking, (string) Uuid::uuid5($data['request_key'], 'wallet-payment-'.$i), $request->boolean('deposit_only'));
                }
                $rows[] = new BookingResource($booking->refresh()->load(['court.branch', 'services', 'payments', 'sales.items']));
            }

            return $rows;
        }, 5);

        return response()->json(['data' => $rows], 201);
    }

    public function show(Request $request, Booking $booking, BookingService $engine): BookingResource
    {
        if ($request->user()->role === 'customer') {
            abort_unless($booking->user_id === $request->user()->id, 403);
        } else {
            $engine->assertManage($request->user(), $booking->court->branch_id, 'bookings.read');
        }

        return new BookingResource($booking->load(['court.branch', 'services', 'payments', 'sales.items', 'user']));
    }

    public function status(Request $request, Booking $booking, BookingService $engine): BookingResource
    {
        $data = $request->validate(['status' => ['required', Rule::in(['cancelled', 'checked_in', 'completed'])]]);

        return new BookingResource($engine->changeStatus($request->user(), $booking, $data['status']));
    }

    public function reschedule(Request $request, Booking $booking, BookingService $engine): BookingResource
    {
        $data = $request->validate(['starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'], 'court_id' => 'sometimes|integer|exists:courts,id']);

        return new BookingResource($engine->reschedule($request->user(), $booking, $data));
    }

    public function receipt(Request $request, Booking $booking, BookingService $engine): JsonResponse
    {
        $this->show($request, $booking, $engine);
        $booking->load('user');

        return response()->json(['data' => [
            'payment_attempts' => PaymentAttempt::where('booking_id', $booking->id)->where('status', 'paid')->whereNotIn('partner_code', ['DEMO_MOMO', 'DEMO_BANK'])->get(), 'refund_requests' => DB::table('refund_requests')->where('booking_id', $booking->id)->get(),
            'pending_payment_attempts' => PaymentAttempt::where('booking_id', $booking->id)->where('status', 'pending')->get(['id', 'amount', 'status', 'expires_at', 'partner_code', 'pay_url']),
            'reference' => $booking->reference, 'issued_at' => $booking->created_at->toIso8601String(), 'currency' => 'VND',
            'customer' => ['name' => $booking->user->name, 'phone' => $booking->user->phone],
            'branch' => ['name' => $booking->court->branch->name, 'address' => $booking->court->branch->address],
            'booking' => new BookingResource($booking),
            'collected' => $booking->payments->where('kind', 'payment')->sum('amount'),
            'refunded' => $booking->payments->where('kind', 'refund')->sum('amount'),
        ]]);
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

    public function services(Request $request, Booking $booking, BookingService $engine): BookingResource
    {
        $data = $request->validate(['request_key' => 'required|uuid', 'services' => 'required|array|list|min:1|max:20', 'services.*.id' => 'required|integer|distinct|exists:services,id', 'services.*.quantity' => 'required|integer|between:1,100']);

        return new BookingResource($engine->addServices($request->user(), $booking, $data));
    }
}
