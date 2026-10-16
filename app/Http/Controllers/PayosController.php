<?php

namespace App\Http\Controllers;

use App\BookingService;
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
        abort_unless($request->user()->role === 'customer', 403, 'Tài khoản làm việc thu tiền khách trong khu điều hành chi nhánh.');
        $data = $request->validate(['request_key' => 'required|uuid', 'purpose' => 'required|in:booking,wallet', 'booking_id' => 'exclude_unless:purpose,booking|required|integer', 'amount' => 'exclude_unless:purpose,wallet|required|integer|min:1000|max:50000000', 'deposit_only' => 'sometimes|boolean']);

        try {
            $attempt = $payos->create($request->user(), $data);
        } catch (LockTimeoutException) {
            abort(409, 'Một yêu cầu thanh toán khác đang được xử lý. Vui lòng chờ rồi kiểm tra giao dịch hiện có.');
        }

        return response()->json(['data' => $attempt]);
    }

    public function sync(Request $request, PaymentAttempt $attempt, PayosService $payos): JsonResponse
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);

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

        return redirect('/#account'.($attempt && $request->user()?->id === $attempt->user_id ? '/bookings'.($attempt->booking_id ? '/'.$attempt->booking_id : '') : ''));
    }
}
