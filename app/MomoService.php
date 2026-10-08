<?php

namespace App;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Notifications\HubNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class MomoService
{
    public function enabled(): bool
    {
        return (bool) config('momo.enabled') && config('momo.partner_code') && config('momo.access_key') && config('momo.secret_key') && str_starts_with(config('momo.ipn_url'), 'https://') && str_starts_with(config('momo.redirect_url'), 'https://');
    }

    public function sign(array $fields): string
    {
        ksort($fields);

        return hash_hmac('sha256', collect($fields)->map(fn ($v, $k) => $k.'='.$v)->implode('&'), config('momo.secret_key'));
    }

    public function create(User $user, array $d): PaymentAttempt
    {
        abort_unless($this->enabled(), 503, 'MoMo chưa được cấu hình.');
        $attempt = DB::transaction(function () use ($user, $d): PaymentAttempt {
            $owner = User::lockForUpdate()->findOrFail($user->id);
            $existing = PaymentAttempt::where('request_key', $d['request_key'])->first();
            if ($existing) {
                abort_unless($existing->user_id === $user->id && $existing->purpose === $d['purpose'] && $existing->booking_id === ($d['booking_id'] ?? null) && ($d['purpose'] === 'booking' || $existing->amount === (int) $d['amount']), 409, 'Khóa giao dịch đã được sử dụng.');

                return $existing;
            }
            $booking = null;
            $amount = (int) ($d['amount'] ?? 0);
            if ($d['purpose'] === 'booking') {
                $booking = Booking::lockForUpdate()->findOrFail($d['booking_id']);
                abort_unless($booking->user_id === $user->id, 403);
                abort_unless($booking->status === 'confirmed', 422, 'Lịch đặt không còn chờ thanh toán.');
                $amount = $booking->total_amount - $booking->paid_amount;
                abort_if(PaymentAttempt::where('booking_id', $booking->id)->where('status', 'pending')->where('expires_at', '>', now())->exists(), 409, 'Lịch đặt đang có một giao dịch MoMo chờ xác nhận.');
            }
            abort_if($amount < 1000 || $amount > 50000000, 422, 'Số tiền MoMo từ 1.000 đến 50.000.000đ.');

            return PaymentAttempt::create(['request_key' => $d['request_key'], 'order_id' => 'BH-'.str_replace('-', '', $d['request_key']), 'user_id' => $owner->id, 'booking_id' => $booking?->id, 'purpose' => $d['purpose'], 'partner_code' => config('momo.partner_code'), 'amount' => $amount, 'order_info' => $booking ? 'Thanh toan '.$booking->reference : 'Nap vi Badminton Hub', 'extra_data' => base64_encode(json_encode(['purpose' => $d['purpose']])), 'status' => 'pending', 'expires_at' => now()->addMinutes(15)]);
        }, 5);
        if ($attempt->pay_url || $attempt->status !== 'pending') {
            return $attempt;
        }
        $payload = ['partnerCode' => $attempt->partner_code, 'requestId' => $attempt->request_key, 'amount' => $attempt->amount, 'orderId' => $attempt->order_id, 'orderInfo' => $attempt->order_info, 'redirectUrl' => config('momo.redirect_url'), 'ipnUrl' => config('momo.ipn_url'), 'extraData' => $attempt->extra_data, 'requestType' => 'captureWallet'];
        $payload['signature'] = $this->sign(['accessKey' => config('momo.access_key'), ...$payload]);
        $payload['autoCapture'] = true;
        $payload['lang'] = 'vi';
        $endpoint = config('momo.endpoint');
        abort_unless(in_array($endpoint, ['https://test-payment.momo.vn/v2/gateway/api/create', 'https://payment.momo.vn/v2/gateway/api/create'], true), 503, 'Địa chỉ MoMo không hợp lệ.');
        try {
            $response = Http::connectTimeout(10)->timeout(35)->post($endpoint, $payload);
            $body = $response->json();
        } catch (\Throwable $e) {
            report($e);
            abort(503, 'Chưa kết nối được MoMo. Vui lòng kiểm tra lại trạng thái trước khi thử lại.');
        }
        if (! $response->successful() || ! is_array($body) || ($body['resultCode'] ?? -1) !== 0) {
            abort(502, 'MoMo chưa tạo được giao dịch.');
        }
        $url = $body['payUrl'] ?? '';
        $host = parse_url($url, PHP_URL_HOST);
        abort_unless(str_starts_with($url, 'https://') && in_array($host, ['test-payment.momo.vn', 'payment.momo.vn'], true) && ($body['orderId'] ?? null) === $attempt->order_id && ($body['requestId'] ?? null) === $attempt->request_key, 502, 'Phản hồi MoMo không hợp lệ.');
        $attempt->update(['pay_url' => $url]);

        return $attempt->refresh();
    }

    public function callback(array $d): void
    {
        $fields = ['accessKey' => config('momo.access_key')];
        foreach (['amount', 'extraData', 'message', 'orderId', 'orderInfo', 'orderType', 'partnerCode', 'payType', 'requestId', 'responseTime', 'resultCode', 'transId'] as $key) {
            abort_unless(array_key_exists($key, $d), 400);
            $fields[$key] = $d[$key];
        }
        abort_unless(config('momo.secret_key') && hash_equals($this->sign($fields), (string) ($d['signature'] ?? '')), 403, 'Chữ ký không hợp lệ.');
        DB::transaction(function () use ($d): void {
            $a = PaymentAttempt::where('order_id', $d['orderId'])->lockForUpdate()->firstOrFail();
            abort_unless($a->partner_code === $d['partnerCode'] && $a->request_key === $d['requestId'] && (string) $a->amount === (string) $d['amount'] && $a->extra_data === $d['extraData'] && $a->order_info === $d['orderInfo'], 409, 'Nội dung giao dịch không khớp.');
            if (in_array($a->status, ['paid', 'credited'], true)) {
                return;
            }
            if ((int) $d['resultCode'] !== 0) {
                $a->update(['result_code' => (int) $d['resultCode'], 'status' => (int) $d['resultCode'] === 9000 ? 'pending' : 'failed']);

                return;
            }
            abort_if(PaymentAttempt::where('trans_id', (string) $d['transId'])->where('id', '!=', $a->id)->exists(), 409, 'Giao dịch đã xử lý.');
            $owner = User::lockForUpdate()->findOrFail($a->user_id);
            $booking = $a->booking_id ? Booking::lockForUpdate()->find($a->booking_id) : null;
            $engine = app(BookingService::class);
            $commerce = app(CommerceService::class);
            if ($booking && $booking->status === 'confirmed' && $booking->total_amount - $booking->paid_amount === $a->amount) {
                Payment::create(['booking_id' => $booking->id, 'received_by' => $owner->id, 'request_key' => $a->request_key, 'kind' => 'payment', 'method' => 'momo', 'amount' => $a->amount, 'notes' => 'MoMo '.$d['transId']]);
                $booking->increment('paid_amount', $a->amount);
                $a->status = 'paid';
            } else {
                $commerce->wallet($owner, $owner, $a->amount, 'momo_topup', $a->request_key, $booking ? 'MoMo nhận sau khi lịch đặt thay đổi' : 'Nạp ví qua MoMo', null, 'payment_attempt', $a->id);
                $a->status = $booking ? 'credited' : 'paid';
            }
            $a->trans_id = (string) $d['transId'];
            $a->result_code = 0;
            $a->save();
            $engine->audit($owner, 'momo.received', $a->id, ['amount' => $a->amount, 'trans_id' => $a->trans_id], 'payment_attempt');
            $owner->notify(new HubNotification(['type' => 'payment.received', 'title' => 'Đã xác nhận MoMo', 'body' => 'MoMo đã xác nhận số tiền '.number_format($a->amount).'đ.']));
        }, 5);
    }
}
