<?php

namespace App\Http\Controllers;

use App\BookingService;
use App\Models\Booking;
use App\Models\PaymentAttempt;
use App\PayosService;
use App\PayosSettings;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PayosController extends Controller
{
    public function create(Request $request, PayosService $payos): JsonResponse
    {
        $data = $request->validate(['request_key' => 'required|uuid', 'purpose' => 'required|in:booking,wallet', 'booking_id' => 'exclude_unless:purpose,booking|required|integer', 'amount' => 'exclude_unless:purpose,wallet|required|integer|min:1000|max:50000000', 'deposit_only' => 'sometimes|boolean']);
        $actor = $request->user();
        $owner = $actor;
        if ($actor->role !== 'customer') {
            abort_unless($data['purpose'] === 'booking', 403, 'Tài khoản làm việc chỉ tạo PayOS cho lịch của khách trong chi nhánh được giao.');
            $booking = Booking::with('court', 'user')->findOrFail($data['booking_id']);
            app(BookingService::class)->assertManage($actor, $booking->court->branch_id, 'payments.write');
            app(BookingService::class)->assertManage($actor, $booking->court->branch_id, 'bookings.read');
            $owner = $booking->user;
        }

        try {
            $attempt = $payos->create($owner, $data);
            if ($actor->id !== $owner->id) {
                app(BookingService::class)->audit($actor, 'payos.checkout_created', $attempt->id, ['booking_id' => $attempt->booking_id, 'amount' => $attempt->amount, 'user_id' => $owner->id], 'payment_attempt');
            }
        } catch (LockTimeoutException) {
            abort(409, 'Một yêu cầu thanh toán khác đang được xử lý. Vui lòng chờ rồi kiểm tra giao dịch hiện có.');
        }

        return response()->json(['data' => $attempt]);
    }

    public function sync(Request $request, PaymentAttempt $attempt, PayosService $payos): JsonResponse
    {
        $this->authorizeAttempt($request, $attempt);

        return response()->json(['data' => $payos->sync($attempt)]);
    }

    public function webhook(Request $request, PayosService $payos): JsonResponse
    {
        $data = $request->validate(['code' => 'required|string', 'success' => 'required|boolean', 'data' => 'required|array', 'data.orderCode' => 'required|integer|min:1', 'data.amount' => 'required|integer|min:0', 'signature' => 'required|string|size:64']);
        $payos->webhook($request->all());

        return response()->json(['code' => '00', 'desc' => 'success']);
    }

    public function settings(Request $request, PayosSettings $settings): JsonResponse
    {
        if ($request->isMethod('put')) {
            $data = $request->validate(['client_id' => 'required|string|regex:/^[a-zA-Z0-9_-]{8,200}$/', 'api_key' => 'required|string|regex:/^[a-zA-Z0-9_-]{8,250}$/', 'checksum_key' => 'required|string|regex:/^[a-zA-Z0-9_-]{8,250}$/']);
            $settings->save($data);
            app(BookingService::class)->audit($request->user(), 'payos.settings_updated', 0, [], 'integration');
        }

        return response()->json(['data' => $settings->status()]);
    }

    public function returned(Request $request): RedirectResponse
    {
        $order = $request->query('orderCode');
        $attempt = is_string($order) && ctype_digit($order) ? PaymentAttempt::where('partner_code', 'PAYOS')->where('order_id', $order)->first() : null;

        if (! $attempt || ! $request->user()) {
            return redirect('/#auth');
        }
        $this->authorizeAttempt($request, $attempt);
        try {
            app(PayosService::class)->sync($attempt);
        } catch (\Throwable) {
            $request->session()->flash('auth_notice', 'Chưa kiểm tra được kết quả chuyển khoản. Mở lịch thanh toán và chọn kiểm tra trạng thái để cập nhật.');
        }
        if ($request->user()->role !== 'customer') {
            return redirect('/#'.($request->user()->role === 'admin' ? 'admin/payments' : 'manage/bookings'));
        }

        return redirect('/#account/'.($attempt->booking_id ? 'bookings/'.$attempt->booking_id : 'wallet'));
    }

    private function authorizeAttempt(Request $request, PaymentAttempt $attempt): void
    {
        if ($attempt->user_id === $request->user()->id) {
            return;
        }
        abort_unless($attempt->booking_id && $request->user()->role !== 'customer', 403);
        app(BookingService::class)->assertManage($request->user(), $attempt->booking->court->branch_id, 'payments.write');
        app(BookingService::class)->assertManage($request->user(), $attempt->booking->court->branch_id, 'bookings.read');
    }
}
