<?php

namespace App;

use App\Models\Booking;
use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PayosService
{
    public function sign(array $data): string
    {
        ksort($data);
        $parts = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = array_map(function ($entry) {
                    if (is_array($entry)) {
                        ksort($entry);
                    }

                    return $entry;
                }, $value);
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            $parts[] = $key.'='.(in_array($value, [null, 'null', 'undefined'], true) ? '' : $value);
        }

        return hash_hmac('sha256', implode('&', $parts), app(PayosSettings::class)->credentials()['checksum_key']);
    }

    public function create(User $user, array $data): PaymentAttempt
    {
        abort_unless(app(PayosSettings::class)->ready(), 503, 'Thanh toán ngân hàng chưa khả dụng. Vui lòng liên hệ chi nhánh hoặc thử lại sau.');
        app(FeatureService::class)->expireBookings();

        return Cache::lock('payos:create:'.$user->id, 50)->block(3, function () use ($user, $data): PaymentAttempt {
            $attempt = DB::transaction(function () use ($user, $data): PaymentAttempt {
                $owner = User::lockForUpdate()->findOrFail($user->id);
                abort_unless($owner->active && $owner->role === 'customer', 422, 'Chỉ tạo thanh toán cho tài khoản khách hàng đang hoạt động.');
                $existing = PaymentAttempt::where('request_key', $data['request_key'])->first();
                if ($existing) {
                    $extra = json_decode(base64_decode($existing->extra_data), true);
                    abort_unless($existing->partner_code === 'PAYOS' && $existing->user_id === $user->id && $existing->purpose === $data['purpose'] && $existing->booking_id === (isset($data['booking_id']) ? (int) $data['booking_id'] : null) && ($extra['deposit_only'] ?? false) === (bool) ($data['deposit_only'] ?? false) && ($existing->purpose === 'booking' || $existing->amount === (int) $data['amount']), 409, 'Yêu cầu này đã dùng cho một giao dịch khác.');

                    return $existing;
                }
                $booking = null;
                $amount = (int) ($data['amount'] ?? 0);
                if ($data['purpose'] === 'booking') {
                    $booking = Booking::lockForUpdate()->findOrFail($data['booking_id']);
                    abort_unless($booking->user_id === $user->id, 403);
                    abort_unless(in_array($booking->status, ['confirmed', 'checked_in'], true), 422, 'Lịch sân không còn chờ thanh toán.');
                    $amount = max(0, (! empty($data['deposit_only']) ? $booking->deposit_amount : $booking->total_amount) - $booking->paid_amount);
                    abort_if(PaymentAttempt::where('booking_id', $booking->id)->where('partner_code', 'PAYOS')->where('status', 'pending')->where('expires_at', '>', now())->exists(), 409, 'Lịch này đang có giao dịch chờ xác nhận. Hãy kiểm tra giao dịch trước khi thanh toán tiếp.');
                }
                abort_unless($amount >= 1000 && $amount <= 50000000, 422, 'Số tiền thanh toán phải từ 1.000 đến 50.000.000đ.');

                $expires = now()->addMinutes(15)->toImmutable();
                if ($booking && $booking->payment_expires_at && $booking->paid_amount < $booking->deposit_amount) {
                    $expires = $expires->min($booking->payment_expires_at);
                }

                return PaymentAttempt::create(['request_key' => $data['request_key'], 'order_id' => (string) random_int(1000000000000, 900000000000000), 'user_id' => $user->id, 'booking_id' => $booking?->id, 'purpose' => $data['purpose'], 'partner_code' => 'PAYOS', 'amount' => $amount, 'order_info' => $booking ? 'BH '.$booking->reference : 'BH NAP VI', 'extra_data' => base64_encode(json_encode(['deposit_only' => (bool) ($data['deposit_only'] ?? false)])), 'status' => 'pending', 'expires_at' => $expires]);
            }, 5);
            if ($attempt->pay_url || $attempt->status !== 'pending') {
                return $attempt;
            }
            if (! $attempt->wasRecentlyCreated) {
                try {
                    return $this->sync($attempt);
                } catch (HttpException $exception) {
                    if ($exception->getStatusCode() !== 502) {
                        throw $exception;
                    }
                }
            }
            abort_if($attempt->expires_at->isPast(), 422, 'Giao dịch đã hết hạn. Vui lòng tạo giao dịch mới.');
            $payload = ['orderCode' => (int) $attempt->order_id, 'amount' => $attempt->amount, 'description' => 'BH'.substr($attempt->order_id, -7), 'returnUrl' => url('/payments/payos/return'), 'cancelUrl' => url('/payments/payos/return')];
            $payload['signature'] = $this->sign($payload);
            $payload['expiredAt'] = $attempt->expires_at->timestamp;
            $reply = $this->request('POST', '/v2/payment-requests', $payload);
            abort_unless((string) ($reply['orderCode'] ?? '') === $attempt->order_id && (int) ($reply['amount'] ?? -1) === $attempt->amount && ($reply['currency'] ?? '') === 'VND', 502, 'Thông tin thanh toán PayOS không khớp.');
            $url = $reply['checkoutUrl'] ?? '';
            abort_unless(is_string($url) && parse_url($url, PHP_URL_SCHEME) === 'https' && parse_url($url, PHP_URL_HOST) === 'pay.payos.vn', 502, 'Đường dẫn thanh toán PayOS không hợp lệ.');
            $extra = json_decode(base64_decode($attempt->extra_data), true);
            $attempt->update(['pay_url' => $url, 'extra_data' => base64_encode(json_encode([...$extra, 'payment_link_id' => $reply['paymentLinkId'] ?? '']))]);

            return $attempt->refresh();
        });
    }

    public function webhook(array $body): void
    {
        abort_unless(app(PayosSettings::class)->ready(), 503);
        $data = $body['data'];
        abort_unless(hash_equals($this->sign($data), $body['signature']), 403, 'Chữ ký PayOS không hợp lệ.');
        if ($body['success'] !== true || $body['code'] !== '00' || ($data['code'] ?? '') !== '00') {
            return;
        }
        $attempt = PaymentAttempt::where('partner_code', 'PAYOS')->where('order_id', (string) $data['orderCode'])->first();
        if (! $attempt) {
            return;
        }
        $extra = json_decode(base64_decode($attempt->extra_data), true);
        abort_unless((int) $data['amount'] === $attempt->amount && ($data['currency'] ?? '') === 'VND' && (empty($extra['payment_link_id']) || $extra['payment_link_id'] === ($data['paymentLinkId'] ?? null)), 409, 'Giao dịch PayOS không khớp đơn thanh toán.');
        $this->sync($attempt);
    }

    public function sync(PaymentAttempt $attempt): PaymentAttempt
    {
        abort_unless($attempt->partner_code === 'PAYOS', 404);
        if (in_array($attempt->status, ['paid', 'credited'], true)) {
            return $attempt;
        }
        $data = $this->request('GET', '/v2/payment-requests/'.$attempt->order_id);
        $extra = json_decode(base64_decode($attempt->extra_data), true);
        abort_unless((string) ($data['orderCode'] ?? '') === $attempt->order_id && (int) ($data['amount'] ?? -1) === $attempt->amount && is_string($data['id'] ?? null) && preg_match('/^[a-zA-Z0-9_-]{6,100}$/', $data['id']) && (empty($extra['payment_link_id']) || $data['id'] === $extra['payment_link_id']), 502, 'Kết quả đối soát PayOS không khớp.');
        if (empty($extra['payment_link_id'])) {
            $attempt->update(['extra_data' => base64_encode(json_encode([...$extra, 'payment_link_id' => $data['id']])), 'pay_url' => 'https://pay.payos.vn/web/'.$data['id']]);
        }
        if (($data['status'] ?? '') === 'PAID') {
            abort_unless((int) ($data['amountPaid'] ?? -1) >= $attempt->amount && (int) ($data['amountRemaining'] ?? -1) === 0, 502, 'PayOS chưa xác nhận đủ số tiền.');
            app(PaymentSettlement::class)->receive($attempt, 0, 'PAYOS:'.$data['id']);
        } elseif (in_array($data['status'] ?? '', ['CANCELLED', 'EXPIRED'], true)) {
            abort_unless(filter_var($data['amountPaid'] ?? null, FILTER_VALIDATE_INT) === 0, 409, 'Chưa thể đóng thanh toán vì ngân hàng có thể đã nhận một phần tiền. Vui lòng liên hệ chi nhánh để đối chiếu; lịch chưa bị hủy.');
            PaymentAttempt::whereKey($attempt->id)->whereNotIn('status', ['paid', 'credited'])->update(['status' => 'failed']);
            if ($attempt->booking_id) {
                DB::transaction(function () use ($attempt): void {
                    $booking = Booking::lockForUpdate()->find($attempt->booking_id);
                    if ($booking && $booking->source === 'online' && $booking->status === 'confirmed' && $booking->paid_amount === 0) {
                        app(BookingService::class)->changeStatus($booking->user, $booking, 'cancelled');
                    }
                }, 5);
            }
        }

        return $attempt->refresh();
    }

    private function request(string $method, string $path, array $data = []): array
    {
        $keys = app(PayosSettings::class)->credentials();
        abort_unless(app(PayosSettings::class)->ready(), 503, 'PayOS chưa được kết nối.');
        try {
            $response = Http::withHeaders(['x-client-id' => $keys['client_id'], 'x-api-key' => $keys['api_key']])->acceptJson()->connectTimeout(5)->timeout(20)->send($method, 'https://api-merchant.payos.vn'.$path, $method === 'GET' ? [] : ['json' => $data]);
        } catch (ConnectionException) {
            abort(503, 'Chưa kết nối được PayOS. Bạn có thể kiểm tra lại giao dịch trong Lịch của tôi.');
        }
        $reply = $response->json();
        abort_unless($response->successful() && is_array($reply) && ($reply['code'] ?? '') === '00' && is_array($reply['data'] ?? null), 502, 'PayOS chưa xác nhận yêu cầu. Vui lòng kiểm tra lại giao dịch trước khi thử thanh toán tiếp.');
        abort_unless(is_string($reply['signature'] ?? null) && hash_equals($this->sign($reply['data']), $reply['signature']), 502, 'Chưa xác thực được phản hồi PayOS.');

        return $reply['data'];
    }
}
