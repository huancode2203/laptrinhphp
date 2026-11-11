<?php

namespace App;

use App\Models\Booking;
use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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
                    abort_unless($existing->partner_code === 'PAYOS' && $existing->user_id === $user->id && $existing->purpose === $data['purpose'] && $existing->booking_id === (isset($data['booking_id']) ? (int) $data['booking_id'] : null) && ($extra['deposit_only'] ?? false) === (bool) ($data['deposit_only'] ?? false) && (in_array($existing->purpose, ['booking', 'series'], true) || $existing->amount === (int) $data['amount']), 409, 'Yêu cầu này đã dùng cho một giao dịch khác.');

                    return $existing;
                }
                $booking = null;
                $allocations = [];
                $amount = (int) ($data['amount'] ?? 0);
                if (in_array($data['purpose'], ['booking', 'series'], true)) {
                    $booking = Booking::lockForUpdate()->findOrFail($data['booking_id']);
                    abort_unless($booking->user_id === $user->id, 403);
                    abort_unless(in_array($booking->status, ['confirmed', 'checked_in'], true), 422, 'Lịch sân không còn chờ thanh toán.');
                    if ($data['purpose'] === 'series') {
                        abort_unless($booking->series_key, 422, 'Lịch này không thuộc một chuỗi cố định.');
                    }
                    $members = $data['purpose'] === 'series'
                        ? Booking::where('series_key', $booking->series_key)->where('user_id', $user->id)->whereIn('status', ['confirmed', 'checked_in'])->with('court')->orderBy('id')->lockForUpdate()->get()
                        : collect([$booking]);
                    abort_if($members->count() > 52, 422, 'Chỉ thanh toán tối đa 52 buổi trong một giao dịch.');
                    foreach ($members as $member) {
                        abort_unless($member->court->branch_id === $booking->court->branch_id, 422, 'Các buổi thanh toán chung phải cùng chi nhánh.');
                        $due = max(0, (! empty($data['deposit_only']) ? $member->deposit_amount : $member->total_amount) - $member->paid_amount);
                        if ($due > 0) {
                            $allocations[$member->id] = $due;
                        }
                    }
                    abort_if($allocations === [], 422, 'Các buổi đã được thanh toán đủ phần được chọn.');
                    abort_if($this->pendingFor(array_keys($allocations))->exists(), 409, 'Một buổi đang có giao dịch chờ xác nhận. Hãy kiểm tra giao dịch trước khi thanh toán tiếp.');
                    $amount = array_sum($allocations);
                }
                abort_unless($amount >= 1000 && $amount <= 50000000, 422, 'Số tiền thanh toán phải từ 1.000 đến 50.000.000đ.');

                $expires = now()->addMinutes(15)->toImmutable();
                foreach ($booking ? $members : [] as $member) {
                    if (isset($allocations[$member->id]) && $member->payment_expires_at && $member->paid_amount < $member->deposit_amount) {
                        $expires = $expires->min($member->payment_expires_at);
                    }
                }

                $attempt = PaymentAttempt::create(['request_key' => $data['request_key'], 'order_id' => (string) random_int(1000000000000, 900000000000000), 'user_id' => $user->id, 'booking_id' => $booking?->id, 'purpose' => $data['purpose'], 'partner_code' => 'PAYOS', 'amount' => $amount, 'order_info' => $data['purpose'] === 'series' ? 'Thanh toán '.count($allocations).' buổi cố định' : ($booking ? 'BH '.$booking->reference : 'BH NAP VI'), 'extra_data' => base64_encode(json_encode(['deposit_only' => (bool) ($data['deposit_only'] ?? false)])), 'status' => 'pending', 'expires_at' => $expires]);
                foreach ($allocations as $id => $due) {
                    DB::table('payment_attempt_bookings')->insert(['payment_attempt_id' => $attempt->id, 'booking_id' => $id, 'amount' => $due]);
                }

                return $attempt;
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
            $callback = rtrim(config('app.url'), '/').'/payments/payos/return';
            $payload = ['orderCode' => (int) $attempt->order_id, 'amount' => $attempt->amount, 'description' => 'BH'.substr($attempt->order_id, -7), 'returnUrl' => $callback, 'cancelUrl' => $callback];
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
        abort_unless((int) $data['amount'] > 0 && (int) $data['amount'] <= 2147483647 && ($data['currency'] ?? '') === 'VND' && (empty($extra['payment_link_id']) || $extra['payment_link_id'] === ($data['paymentLinkId'] ?? null)), 409, 'Giao dịch PayOS không khớp đơn thanh toán.');
        $this->sync($attempt, true);
    }

    public function sync(PaymentAttempt $attempt, bool $force = false): PaymentAttempt
    {
        abort_unless($attempt->partner_code === 'PAYOS', 404);
        if (! $force && in_array($attempt->status, ['paid', 'credited', 'partial'], true)) {
            return $attempt;
        }
        $data = $this->request('GET', '/v2/payment-requests/'.$attempt->order_id);
        if (array_key_exists('amountPaid', $data)) {
            $reported = filter_var($data['amountPaid'], FILTER_VALIDATE_INT);
            abort_unless($reported !== false && $reported >= max($attempt->received_amount, $attempt->settled_amount) && $reported <= 2147483647, 502, 'Số tiền PayOS báo chưa khớp khoản đã ghi nhận. Cần đối soát lại, lịch chưa bị hủy.');
        }
        $extra = json_decode(base64_decode($attempt->extra_data), true);
        abort_unless((string) ($data['orderCode'] ?? '') === $attempt->order_id && (int) ($data['amount'] ?? -1) === $attempt->amount && is_string($data['id'] ?? null) && preg_match('/^[a-zA-Z0-9_-]{6,100}$/', $data['id']) && (empty($extra['payment_link_id']) || $data['id'] === $extra['payment_link_id']), 502, 'Kết quả đối soát PayOS không khớp.');
        if (empty($extra['payment_link_id'])) {
            $attempt->update(['extra_data' => base64_encode(json_encode([...$extra, 'payment_link_id' => $data['id']])), 'pay_url' => 'https://pay.payos.vn/web/'.$data['id']]);
        }
        if (($data['status'] ?? '') === 'PAID') {
            abort_unless((int) ($data['amountPaid'] ?? -1) >= $attempt->amount && (int) ($data['amountRemaining'] ?? -1) === 0, 502, 'PayOS chưa xác nhận đủ số tiền.');
            app(PaymentSettlement::class)->receive($attempt, 0, 'PAYOS:'.$data['id'], (int) $data['amountPaid']);
        } elseif (in_array($data['status'] ?? '', ['CANCELLED', 'EXPIRED'], true)) {
            if (filter_var($data['amountPaid'] ?? null, FILTER_VALIDATE_INT) !== 0) {
                $this->observe($attempt, $data, 'Ngân hàng báo hủy hoặc hết hạn nhưng có khoản tiền cần đối chiếu.');
            }
            abort_unless(filter_var($data['amountPaid'] ?? null, FILTER_VALIDATE_INT) === 0, 409, 'Chưa thể đóng thanh toán vì ngân hàng có thể đã nhận một phần tiền. Vui lòng liên hệ chi nhánh để đối chiếu; lịch chưa bị hủy.');
            PaymentAttempt::whereKey($attempt->id)->whereNotIn('status', ['paid', 'credited'])->update(['status' => 'failed']);
            $ids = DB::table('payment_attempt_bookings')->where('payment_attempt_id', $attempt->id)->pluck('booking_id');
            if ($ids->isEmpty() && $attempt->booking_id) {
                $ids->push($attempt->booking_id);
            }
            foreach ($ids as $bookingId) {
                DB::transaction(function () use ($bookingId): void {
                    $booking = Booking::lockForUpdate()->find($bookingId);
                    if ($booking && $booking->source === 'online' && $booking->status === 'confirmed' && $booking->paid_amount === 0) {
                        app(BookingService::class)->changeStatus($booking->user, $booking, 'cancelled');
                    }
                }, 5);
            }
        } elseif ((int) ($data['amountPaid'] ?? 0) > 0) {
            $this->observe($attempt, $data, 'Đã nhận một phần tiền; đang chờ thanh toán đủ hoặc chi nhánh đối chiếu.');
        }

        return $attempt->refresh();
    }

    public function pendingFor(array $bookingIds): Builder
    {
        return PaymentAttempt::where('partner_code', 'PAYOS')->where('status', 'pending')
            ->where(fn ($q) => $q->where('expires_at', '>', now())->orWhereNotNull('review_reason'))
            ->where(fn ($q) => $q->whereIn('booking_id', $bookingIds)->orWhereIn('id', DB::table('payment_attempt_bookings')->whereIn('booking_id', $bookingIds)->select('payment_attempt_id')));
    }

    public function seriesOptions(Booking $booking): array
    {
        if (! $booking->series_key) {
            return [];
        }
        $rows = Booking::where('series_key', $booking->series_key)->where('user_id', $booking->user_id)->whereIn('status', ['confirmed', 'checked_in'])->orderBy('starts_at')->get();

        return ['sessions' => $rows->map(fn ($row) => ['id' => $row->id, 'reference' => $row->reference, 'starts_at' => $row->starts_at->toIso8601String(), 'remaining' => max(0, $row->total_amount - $row->paid_amount), 'deposit_remaining' => max(0, $row->deposit_amount - $row->paid_amount)])->all(), 'remaining_amount' => $rows->sum(fn ($row) => max(0, $row->total_amount - $row->paid_amount)), 'deposit_remaining' => $rows->sum(fn ($row) => max(0, $row->deposit_amount - $row->paid_amount))];
    }

    private function observe(PaymentAttempt $attempt, array $data, string $reason): void
    {
        $amount = filter_var($data['amountPaid'] ?? null, FILTER_VALIDATE_INT);
        abort_if($amount !== false && ($amount < 0 || $amount > 2147483647), 502, 'Số tiền ngân hàng trả về chưa hợp lệ.');
        DB::transaction(function () use ($attempt, $data, $amount, $reason): void {
            $row = PaymentAttempt::lockForUpdate()->findOrFail($attempt->id);
            if (in_array($row->status, ['paid', 'credited', 'partial'], true) && ($amount === false || $amount <= $row->settled_amount)) {
                return;
            }
            $row->update(['status' => 'pending', 'received_amount' => max($row->received_amount, (int) $amount), 'review_reason' => $reason]);
            DB::table('payment_reconciliations')->updateOrInsert(['payment_attempt_id' => $row->id], ['status' => 'pending', 'provider_status' => $data['status'] ?? 'UNKNOWN', 'expected_amount' => $row->amount, 'received_amount' => $row->received_amount, 'reason' => $reason, 'resolved_by' => null, 'resolved_at' => null, 'updated_at' => now(), 'created_at' => now()]);
        }, 5);
    }

    public function connectWebhook(): string
    {
        $url = rtrim(config('app.url'), '/').'/api/v1/payments/payos/webhook';
        abort_unless(filter_var($url, FILTER_VALIDATE_URL) && str_starts_with($url, 'https://') && app(PayosSettings::class)->ready(), 422, 'Cần tên miền HTTPS và cấu hình PayOS thật trước khi kết nối webhook.');
        $keys = app(PayosSettings::class)->credentials();
        $response = Http::withHeaders(['x-client-id' => $keys['client_id'], 'x-api-key' => $keys['api_key']])->acceptJson()->connectTimeout(5)->timeout(30)->post('https://api-merchant.payos.vn/confirm-webhook', ['webhookUrl' => $url]);
        abort_unless($response->successful() && $response->json('code') === '00' && $response->json('data.webhookUrl') === $url, 502, 'PayOS chưa xác nhận được webhook. Kiểm tra tên miền và kết nối kênh thanh toán.');

        return $url;
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
