<?php

namespace App\Http\Controllers;

use App\BookingService;
use App\MembershipCheckout;
use App\Models\Booking;
use App\Models\PaymentAttempt;
use App\PayosService;
use App\PayosSettings;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayosController extends Controller
{
    public function create(Request $request, PayosService $payos): JsonResponse
    {
        $data = $request->validate(['request_key' => 'required|uuid', 'purpose' => 'required|in:booking,series,wallet', 'booking_id' => 'exclude_unless:purpose,booking,series|required|integer', 'amount' => 'exclude_unless:purpose,wallet|required|integer|min:1000|max:50000000', 'deposit_only' => 'sometimes|boolean']);
        $actor = $request->user();
        $owner = $actor;
        if ($actor->role !== 'customer') {
            abort_unless(in_array($data['purpose'], ['booking', 'series'], true), 403, 'Tài khoản làm việc chỉ tạo PayOS cho lịch của khách trong chi nhánh được giao.');
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

        return response()->json(['data' => $payos->sync($attempt, true)]);
    }

    public function resumeMembership(Request $request, PaymentAttempt $attempt, PayosService $payos): JsonResponse
    {
        $this->authorizeAttempt($request, $attempt);
        abort_unless($attempt->purpose === 'membership' && $attempt->status === 'pending' && $attempt->expires_at->isFuture(), 422, 'Giao dịch không còn chờ thanh toán. Kiểm tra trạng thái hoặc tạo giao dịch mới.');
        $order = DB::table('membership_payment_orders')->where('payment_attempt_id', $attempt->id)->first();
        abort_unless($order, 409, 'Chưa có thông tin gói hội viên để tiếp tục thanh toán.');
        $resumed = $payos->create($attempt->user, ['request_key' => $attempt->request_key, 'purpose' => 'membership', 'membership_plan_id' => $order->membership_plan_id, 'branch_id' => $order->branch_id, 'actor_id' => $order->initiated_by]);

        return response()->json(['data' => $resumed]);
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
            return redirect()->away(rtrim(config('app.url'), '/').'/#auth');
        }
        $this->authorizeAttempt($request, $attempt);
        try {
            app(PayosService::class)->sync($attempt);
        } catch (\Throwable) {
            $request->session()->flash('auth_notice', 'Chưa kiểm tra được kết quả chuyển khoản. Mở lịch thanh toán và chọn kiểm tra trạng thái để cập nhật.');
        }
        if ($request->user()->role !== 'customer') {
            return redirect()->away(rtrim(config('app.url'), '/').'/#'.($request->user()->role === 'admin' ? 'admin/payments' : ($attempt->purpose === 'membership' ? 'manage/customers' : 'manage/bookings')));
        }

        return redirect()->away(rtrim(config('app.url'), '/').'/#account/'.($attempt->booking_id ? 'bookings/'.$attempt->booking_id : ($attempt->purpose === 'membership' ? 'membership' : 'wallet')));
    }

    private function authorizeAttempt(Request $request, PaymentAttempt $attempt): void
    {
        if ($attempt->user_id === $request->user()->id) {
            return;
        }
        if ($attempt->purpose === 'membership' && $request->user()->role !== 'customer') {
            $branch = app(MembershipCheckout::class)->branch($attempt);
            abort_unless($branch, 403);
            app(BookingService::class)->assertManage($request->user(), $branch, 'payments.write');

            return;
        }
        abort_unless($attempt->booking_id && $request->user()->role !== 'customer', 403);
        app(BookingService::class)->assertManage($request->user(), $attempt->booking->court->branch_id, 'payments.write');
        app(BookingService::class)->assertManage($request->user(), $attempt->booking->court->branch_id, 'bookings.read');
    }
}
