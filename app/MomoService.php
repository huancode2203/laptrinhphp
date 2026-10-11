<?php

namespace App;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Notifications\HubNotification;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

class MomoService
{
    public function enabled(): bool
    {
        return app(IntegrationStatus::class)->ready('momo') || app(DemoService::class)->uses('momo');
    }

    public function sign(array $fields): string
    {
        ksort($fields);

        return hash_hmac('sha256', collect($fields)->map(fn ($v, $k) => $k.'='.$v)->implode('&'), config('momo.secret_key'));
    }

    public function create(User $user, array $d, ?string $demoProvider = null): PaymentAttempt
    {
        abort_if($demoProvider !== null && $demoProvider !== 'DEMO_BANK', 422);
        abort_unless($this->enabled() || ($demoProvider === 'DEMO_BANK' && app(DemoService::class)->active()), 503, 'MoMo chưa được cấu hình.');
        $partner = $demoProvider ?? (app(DemoService::class)->uses('momo') ? 'DEMO_MOMO' : config('momo.partner_code'));
        app(FeatureService::class)->expireBookings();
        $attempt = DB::transaction(function () use ($user, $d, $partner): PaymentAttempt {
            $owner = User::lockForUpdate()->findOrFail($user->id);
            $existing = PaymentAttempt::where('request_key', $d['request_key'])->first();
            if ($existing) {
                abort_unless((json_decode(base64_decode($existing->extra_data), true)['deposit_only'] ?? false) === ($d['deposit_only'] ?? false), 409);
                abort_unless($existing->partner_code === $partner && $existing->user_id === $user->id && $existing->purpose === $d['purpose'] && $existing->booking_id === ($d['booking_id'] ?? null) && ($d['purpose'] === 'booking' || $existing->amount === (int) $d['amount']), 409, 'Khóa giao dịch đã được sử dụng.');

                return $existing;
            }
            $booking = null;
            $amount = (int) ($d['amount'] ?? 0);
            if ($d['purpose'] === 'booking') {
                $booking = Booking::lockForUpdate()->findOrFail($d['booking_id']);
                abort_unless($booking->user_id === $user->id, 403);
                abort_unless(in_array($booking->status, ['confirmed', 'checked_in'], true), 422, 'Lịch đặt không còn chờ thanh toán.');
                $amount = (! empty($d['deposit_only']) ? $booking->deposit_amount : $booking->total_amount) - $booking->paid_amount;
                abort_if(PaymentAttempt::where('booking_id', $booking->id)->where('status', 'pending')->where('expires_at', '>', now())->exists(), 409, 'Lịch đặt đang có một giao dịch MoMo chờ xác nhận.');
            }
            abort_if($amount < 1000 || $amount > 50000000, 422, 'Số tiền MoMo từ 1.000 đến 50.000.000đ.');

            return PaymentAttempt::create(['request_key' => $d['request_key'], 'order_id' => 'BH-'.str_replace('-', '', $d['request_key']), 'user_id' => $owner->id, 'booking_id' => $booking?->id, 'purpose' => $d['purpose'], 'partner_code' => $partner, 'amount' => $amount, 'order_info' => $booking ? 'Thanh toan '.$booking->reference : 'Nap vi Badminton Hub', 'extra_data' => base64_encode(json_encode(['purpose' => $d['purpose'], 'deposit_only' => $d['deposit_only'] ?? false])), 'status' => 'pending', 'expires_at' => now()->addMinutes(15)]);
        }, 5);
        if ($attempt->pay_url || $attempt->status !== 'pending') {
            return $attempt;
        }
        if ($this->isDemo($attempt)) {
            $attempt->update(['pay_url' => '/#demo-payment/'.$attempt->id]);

            return $attempt->refresh();
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
        $attempt = PaymentAttempt::where('order_id', $d['orderId'])->firstOrFail();
        abort_if($this->isDemo($attempt), 403);
        abort_unless($attempt->partner_code === $d['partnerCode'] && $attempt->request_key === $d['requestId'] && (string) $attempt->amount === (string) $d['amount'] && $attempt->extra_data === $d['extraData'] && $attempt->order_info === $d['orderInfo'], 409, 'Nội dung giao dịch không khớp.');
        $this->settle($attempt, (int) $d['resultCode'], (string) $d['transId']);
    }

    public function sync(PaymentAttempt $attempt): PaymentAttempt
    {
        if ($this->isDemo($attempt)) {
            abort_unless(app(DemoService::class)->active(), 404);

            return $attempt;
        }
        abort_unless($this->enabled() && $attempt->partner_code === config('momo.partner_code'), 503, 'MoMo hiện chưa khả dụng.');
        if (in_array($attempt->status, ['paid', 'credited'], true)) {
            return $attempt;
        }
        $payload = ['partnerCode' => $attempt->partner_code, 'orderId' => $attempt->order_id, 'requestId' => (string) Str::uuid()];
        $payload['signature'] = $this->sign(['accessKey' => config('momo.access_key'), ...$payload]);
        $payload['lang'] = 'vi';
        try {
            $response = Http::connectTimeout(10)->timeout(35)->post(str_replace('/create', '/query', config('momo.endpoint')), $payload);
        } catch (ConnectionException) {
            abort(503, 'Chưa kiểm tra được MoMo. Tiền sẽ được cập nhật sau khi có xác nhận.');
        }
        $body = $response->json();
        abort_unless($response->successful() && is_array($body) && ($body['partnerCode'] ?? null) === $attempt->partner_code && ($body['orderId'] ?? null) === $attempt->order_id && ($body['requestId'] ?? null) === $payload['requestId'] && is_int($body['resultCode'] ?? null), 502, 'Phản hồi kiểm tra MoMo không hợp lệ.');
        if ($body['resultCode'] === 0) {
            abort_unless((string) ($body['amount'] ?? '') === (string) $attempt->amount && ($body['extraData'] ?? '') === $attempt->extra_data, 502, 'Số tiền kiểm tra MoMo không khớp.');
            $this->settle($attempt, 0, (string) ($body['transId'] ?? ''));
        } else {
            PaymentAttempt::whereKey($attempt->id)->whereNotIn('status', ['paid', 'credited'])->update(['result_code' => $body['resultCode']]);
        }

        return $attempt->refresh();
    }

    private function settle(PaymentAttempt $attempt, int $resultCode, string $transId): void
    {
        abort_if($resultCode === 0 && ! filter_var($transId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]), 400, 'Mã giao dịch không hợp lệ.');
        app(FeatureService::class)->expireBookings();
        DB::transaction(function () use ($attempt, $resultCode, $transId): void {
            $a = PaymentAttempt::lockForUpdate()->findOrFail($attempt->id);
            if (in_array($a->status, ['paid', 'credited'], true)) {
                abort_if($resultCode === 0 && $a->trans_id !== $transId, 409, 'Mã giao dịch không khớp.');

                return;
            }
            if ($resultCode !== 0) {
                $a->update(['result_code' => $resultCode, 'status' => $resultCode === 9000 ? 'pending' : 'failed']);

                return;
            }
            abort_if(PaymentAttempt::where('trans_id', $transId)->where('id', '!=', $a->id)->exists(), 409, 'Giao dịch đã xử lý.');
            $owner = User::lockForUpdate()->findOrFail($a->user_id);
            $booking = $a->booking_id ? Booking::lockForUpdate()->find($a->booking_id) : null;
            $demo = $this->isDemo($a);
            $method = $a->partner_code === 'DEMO_BANK' ? 'demo_bank' : ($demo ? 'demo_momo' : 'momo');
            $due = $booking && in_array($booking->status, ['confirmed', 'checked_in'], true) ? max(0, $booking->total_amount - $booking->paid_amount) : 0;
            $paid = min($a->amount, $due);
            if ($paid > 0) {
                Payment::create(['booking_id' => $booking->id, 'received_by' => $owner->id, 'request_key' => $a->request_key, 'kind' => 'payment', 'method' => $method, 'amount' => $paid, 'notes' => ($demo ? '[DEMO] ' : '').$method.' '.$transId]);
                $booking->increment('paid_amount', $paid);
            }
            $extra = $a->amount - $paid;
            if ($extra > 0) {
                $walletKey = $paid > 0 ? (string) Uuid::uuid5(Uuid::fromString($a->request_key), 'momo-excess') : $a->request_key;
                app(CommerceService::class)->wallet($owner, $owner, $extra, $demo ? 'demo_topup' : 'momo_topup', $walletKey, ($demo ? '[DEMO] ' : '').($booking ? 'Tiền vượt số cần trả hoặc đến sau khi lịch thay đổi' : 'Nạp ví qua '.$method), null, 'payment_attempt', $a->id);
            }
            $a->forceFill(['status' => $booking && $paid === 0 ? 'credited' : 'paid', 'trans_id' => $transId, 'result_code' => 0])->save();
            app(BookingService::class)->audit($owner, $demo ? 'demo.payment' : 'momo.received', $a->id, ['amount' => $a->amount, 'trans_id' => $transId, 'booking_paid' => $paid, 'wallet_credit' => $extra], 'payment_attempt');
            $owner->notify(new HubNotification(['type' => 'payment.received', 'title' => $demo ? 'Thanh toán demo thành công' : 'Đã xác nhận MoMo', 'body' => ($demo ? 'Giao dịch thử, không chuyển tiền thật: ' : 'MoMo đã xác nhận số tiền ').number_format($a->amount).'đ.']));
        }, 5);
    }

    public function isDemo(PaymentAttempt $attempt): bool
    {
        return in_array($attempt->partner_code, ['DEMO_MOMO', 'DEMO_BANK'], true);
    }

    public function completeDemo(PaymentAttempt $attempt, bool $success): PaymentAttempt
    {
        abort_unless(app(DemoService::class)->active() && $this->isDemo($attempt), 404);

        return DB::transaction(function () use ($attempt, $success): PaymentAttempt {
            $locked = PaymentAttempt::lockForUpdate()->findOrFail($attempt->id);
            if ($locked->status !== 'pending') {
                abort_unless(($success && in_array($locked->status, ['paid', 'credited'], true)) || (! $success && $locked->status === 'failed'), 409, 'Giao dịch demo đã kết thúc.');

                return $locked;
            }
            abort_if($locked->expires_at->isPast(), 422, 'Giao dịch demo đã hết hạn. Hãy tạo giao dịch mới.');
            $this->settle($locked, $success ? 0 : 1006, (string) (900000000000000 + $locked->id));

            return $locked->refresh();
        }, 5);
    }
}
