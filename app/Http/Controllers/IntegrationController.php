<?php

namespace App\Http\Controllers;

use App\BookingService;
use App\CommerceService;
use App\FeatureService;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\MomoService;
use App\Notifications\HubNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

class IntegrationController extends Controller
{
    public function google(Request $r): RedirectResponse
    {
        abort_unless(config('integrations.google.client_id') && config('integrations.google.client_secret') && config('integrations.google.redirect_uri'), 503, 'Đăng nhập Google hiện chưa khả dụng.');
        $state = Str::random(48);
        $r->session()->put('google_oauth', ['state' => $state, 'created' => now()->timestamp, 'user_id' => $r->user()?->id]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query(['client_id' => config('integrations.google.client_id'), 'redirect_uri' => config('integrations.google.redirect_uri'), 'response_type' => 'code', 'scope' => 'openid email profile', 'state' => $state, 'prompt' => 'select_account']));
    }

    public function googleCallback(Request $r): RedirectResponse
    {
        $pending = $r->session()->pull('google_oauth');
        abort_unless($pending && now()->timestamp - $pending['created'] <= 600 && hash_equals($pending['state'], (string) $r->input('state')), 403);
        if (! $r->filled('code')) {
            return redirect('/#auth');
        }
        $token = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', ['code' => $r->input('code'), 'client_id' => config('integrations.google.client_id'), 'client_secret' => config('integrations.google.client_secret'), 'redirect_uri' => config('integrations.google.redirect_uri'), 'grant_type' => 'authorization_code'])->throw()->json('access_token');
        $profile = Http::withToken($token)->timeout(15)->get('https://openidconnect.googleapis.com/v1/userinfo')->throw()->json();
        abort_unless(($profile['email_verified'] ?? false) && filter_var($profile['email'] ?? '', FILTER_VALIDATE_EMAIL) && is_string($profile['sub'] ?? null), 403);
        $user = User::where('google_sub', $profile['sub'])->first();
        if (! $user) {
            $same = User::where('email', $profile['email'])->first();
            if ($same) {
                abort_unless($pending['user_id'] === $same->id && $r->user()?->id === $same->id, 409, 'Email đã có tài khoản. Đăng nhập bằng mật khẩu rồi liên kết Google trong Hồ sơ.');
                $same->forceFill(['google_sub' => $profile['sub'], 'email_verified_at' => now()])->save();
                $user = $same;
            } else {
                $user = User::create(['name' => Str::limit($profile['name'] ?? $profile['email'], 100, ''), 'email' => $profile['email'], 'password' => Str::random(60)]);
                $user->forceFill(['google_sub' => $profile['sub'], 'email_verified_at' => now()])->save();
            }
        }
        abort_unless($user->active, 403);
        Auth::login($user);
        $r->session()->regenerate();

        return redirect('/#account');
    }

    public function phoneSend(Request $r): JsonResponse
    {
        abort_unless(config('integrations.sms.enabled') && config('integrations.sms.sid') && config('integrations.sms.token') && config('integrations.sms.service'), 503, 'Xác thực điện thoại hiện chưa khả dụng.');
        $d = $r->validate(['phone' => ['required', 'regex:/^(0[35789][0-9]{8}|[+]84[35789][0-9]{8})$/']]);
        $phone = str_starts_with($d['phone'], '0') ? '+84'.substr($d['phone'], 1) : $d['phone'];
        abort_if(User::where('phone', $phone)->whereNotNull('phone_verified_at')->where('id', '!=', $r->user()->id)->exists(), 422, 'Số điện thoại đã được tài khoản khác xác thực.');
        $response = Http::asForm()->withBasicAuth(config('integrations.sms.sid'), config('integrations.sms.token'))->timeout(15)->post('https://verify.twilio.com/v2/Services/'.config('integrations.sms.service').'/Verifications', ['To' => $phone, 'Channel' => 'sms'])->throw()->json();
        abort_unless(($response['status'] ?? null) === 'pending', 502, 'Chưa gửi được mã xác thực.');
        $r->session()->put('phone_verification', ['phone' => $phone, 'user_id' => $r->user()->id, 'expires' => now()->addMinutes(10)->timestamp, 'attempts' => 0]);

        return response()->json(['message' => 'Mã xác thực đã được gửi tới điện thoại.']);
    }

    public function phoneVerify(Request $r): JsonResponse
    {
        $d = $r->validate(['code' => 'required|digits:6']);
        $pending = $r->session()->get('phone_verification');
        abort_unless($pending && $pending['user_id'] === $r->user()->id && $pending['expires'] > now()->timestamp && $pending['attempts'] < 5, 422, 'Yêu cầu xác thực đã hết hạn.');
        $pending['attempts']++;
        $r->session()->put('phone_verification', $pending);
        $response = Http::asForm()->withBasicAuth(config('integrations.sms.sid'), config('integrations.sms.token'))->timeout(15)->post('https://verify.twilio.com/v2/Services/'.config('integrations.sms.service').'/VerificationCheck', ['To' => $pending['phone'], 'Code' => $d['code']])->throw()->json();
        abort_unless(($response['status'] ?? null) === 'approved', 422, 'Mã xác thực không đúng.');
        DB::transaction(function () use ($r, $pending): void {
            $user = User::lockForUpdate()->findOrFail($r->user()->id);
            abort_if(User::where('phone', $pending['phone'])->whereNotNull('phone_verified_at')->where('id', '!=', $user->id)->lockForUpdate()->exists(), 422);
            $user->forceFill(['phone' => $pending['phone'], 'phone_verified_at' => now()])->save();
        }, 5);
        $r->session()->forget('phone_verification');

        return response()->json(['message' => 'Đã xác thực số điện thoại.']);
    }

    public function bank(Request $r, CommerceService $commerce): JsonResponse
    {
        abort_unless(config('integrations.bank.enabled') && config('integrations.bank.key') && config('integrations.bank.account'), 503);
        abort_unless(hash_equals('Apikey '.config('integrations.bank.key'), (string) $r->header('Authorization')), 403);
        $d = $r->validate(['id' => 'required|integer|min:1', 'accountNumber' => 'required|string|max:60', 'transferType' => 'required|in:in,out', 'transferAmount' => 'required|integer|between:1,50000000', 'content' => 'nullable|string|max:1000', 'code' => 'nullable|string|max:100']);
        abort_unless($d['accountNumber'] === config('integrations.bank.account'), 422);
        if ($d['transferType'] === 'out') {
            return response()->json(['success' => true]);
        }
        app(FeatureService::class)->expireBookings();
        DB::transaction(function () use ($d, $commerce): void {
            $existing = DB::table('integration_transactions')->where('provider', 'sepay')->where('transaction_id', (string) $d['id'])->first();
            if ($existing) {
                abort_unless($existing->amount === $d['transferAmount'], 409);

                return;
            }
            preg_match('/BH-?[A-Z0-9]{10}\b/', strtoupper(($d['code'] ?? '').' '.($d['content'] ?? '')), $m);
            $ref = $m[0] ?? '';
            if ($ref && ! str_contains($ref, '-')) {
                $ref = 'BH-'.substr($ref, 2);
            }
            $booking = $ref ? Booking::where('reference', $ref)->lockForUpdate()->first() : null;
            $id = DB::table('integration_transactions')->insertGetId(['provider' => 'sepay', 'transaction_id' => (string) $d['id'], 'booking_id' => $booking?->id, 'account' => $d['accountNumber'], 'content' => trim(($d['code'] ?? '').' '.($d['content'] ?? '')), 'amount' => $d['transferAmount'], 'status' => $booking ? 'received' : 'unmatched', 'created_at' => now(), 'updated_at' => now()]);
            if (! $booking) {
                return;
            }
            $this->creditBank($booking, $d['transferAmount'], (string) $d['id'], $id, $booking->user, $commerce);
        }, 5);

        return response()->json(['success' => true]);
    }

    private function creditBank(Booking $booking, int $amount, string $transaction, int $id, User $actor, CommerceService $commerce): void
    {
        $key = (string) Uuid::uuid5(Uuid::NAMESPACE_URL, 'sepay-'.$transaction);
        $due = $booking->status === 'confirmed' ? max(0, $booking->total_amount - $booking->paid_amount) : 0;
        $paid = min($due, $amount);
        if ($paid > 0) {
            Payment::create(['request_key' => $key, 'booking_id' => $booking->id, 'received_by' => $actor->id, 'kind' => 'payment', 'method' => 'bank', 'amount' => $paid, 'notes' => 'SePay '.$transaction]);
            $booking->increment('paid_amount', $paid);
        }
        $extra = $amount - $paid;
        if ($extra > 0) {
            $commerce->wallet($booking->user, $booking->user, $extra, 'bank_credit', (string) Uuid::uuid5($key, 'wallet'), 'Tiền chuyển khoản vượt số cần trả hoặc đến sau khi hủy', null, 'integration', $id);
        }
        app(BookingService::class)->audit($actor, 'bank.received', $booking->id, ['transaction_id' => $transaction, 'amount' => $amount]);
        $booking->user->notify(new HubNotification(['type' => 'bank.received', 'title' => 'Đã ghi nhận chuyển khoản', 'body' => $booking->reference.' · '.number_format($amount, 0, ',', '.').'đ', 'booking_id' => $booking->id]));
    }

    public function bankMatch(Request $r, int $transaction, CommerceService $commerce): JsonResponse
    {
        $d = $r->validate(['reference' => 'required|string|max:30']);
        app(FeatureService::class)->expireBookings();
        DB::transaction(function () use ($r, $transaction, $d, $commerce): void {
            $row = DB::table('integration_transactions')->where('provider', 'sepay')->lockForUpdate()->find($transaction);
            abort_unless($row, 404);
            $b = Booking::where('reference', strtoupper(trim($d['reference'])))->lockForUpdate()->firstOrFail();
            if ($row->status !== 'unmatched') {
                abort_unless($row->booking_id === $b->id, 409, 'Giao dịch đã khớp với lịch khác.');

                return;
            }
            $this->creditBank($b, $row->amount, $row->transaction_id, $row->id, $r->user(), $commerce);
            DB::table('integration_transactions')->where('id', $row->id)->update(['booking_id' => $b->id, 'status' => 'received', 'updated_at' => now()]);
            app(BookingService::class)->audit($r->user(), 'bank.matched', $row->id, ['booking_id' => $b->id], 'integration');
        }, 5);

        return response()->json(['message' => 'Đã đối soát và ghi nhận tiền.']);
    }

    public function momoRefund(Request $r, Booking $booking, MomoService $m): JsonResponse
    {
        app(BookingService::class)->assertManage($r->user(), $booking->court->branch_id);
        abort_unless($m->enabled(), 503, 'MoMo hiện chưa khả dụng.');
        $d = $r->validate(['request_key' => 'required|uuid', 'attempt_id' => 'required|integer|exists:payment_attempts,id', 'amount' => 'required|integer|between:1000,50000000']);
        $refund = DB::transaction(function () use ($booking, $d): object {
            $b = Booking::lockForUpdate()->findOrFail($booking->id);
            abort_unless($b->status === 'cancelled', 422, 'Chỉ hoàn lịch đã hủy.');
            $existing = DB::table('refund_requests')->where('request_key', $d['request_key'])->first();
            if ($existing) {
                abort_unless($existing->booking_id === $b->id && $existing->payment_attempt_id === $d['attempt_id'] && $existing->amount === $d['amount'], 409);

                return $existing;
            }
            $a = PaymentAttempt::lockForUpdate()->findOrFail($d['attempt_id']);
            abort_unless($a->booking_id === $b->id && $a->status === 'paid' && $a->trans_id, 422);
            $reserved = DB::table('refund_requests')->where('payment_attempt_id', $a->id)->whereIn('status', ['pending', 'paid'])->sum('amount');
            abort_if($reserved + $d['amount'] > $a->amount || $d['amount'] > $b->paid_amount, 422, 'Số tiền vượt phần có thể hoàn.');
            $id = DB::table('refund_requests')->insertGetId(['booking_id' => $b->id, 'payment_attempt_id' => $a->id, 'request_key' => $d['request_key'], 'order_id' => 'REF-'.str_replace('-', '', $d['request_key']), 'amount' => $d['amount'], 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

            return DB::table('refund_requests')->find($id);
        }, 5);
        if ($refund->status === 'paid') {
            return response()->json(['data' => $refund]);
        }
        $a = PaymentAttempt::findOrFail($refund->payment_attempt_id);
        $payload = ['partnerCode' => config('momo.partner_code'), 'orderId' => $refund->order_id, 'requestId' => $refund->request_key, 'amount' => $refund->amount, 'transId' => (int) $a->trans_id, 'description' => 'Hoan lich '.$booking->reference];
        $payload['signature'] = $m->sign(['accessKey' => config('momo.access_key'), ...$payload]);
        $payload['lang'] = 'vi';
        $endpoint = str_replace('/create', '/refund', config('momo.endpoint'));
        abort_unless(in_array($endpoint, ['https://test-payment.momo.vn/v2/gateway/api/refund', 'https://payment.momo.vn/v2/gateway/api/refund'], true), 503);
        $claimed = DB::table('refund_requests')->where('id', $refund->id)->whereNull('message')->update(['message' => 'Đã gửi yêu cầu, chờ đối soát', 'updated_at' => now()]);
        if ($claimed) {
            $body = Http::timeout(35)->post($endpoint, $payload)->throw()->json();
        } else {
            $query = ['partnerCode' => config('momo.partner_code'), 'orderId' => $refund->order_id, 'requestId' => (string) Str::uuid()];
            $query['signature'] = $m->sign(['accessKey' => config('momo.access_key'), ...$query]);
            $query['lang'] = 'vi';
            $reply = Http::timeout(35)->post($endpoint.'/query', $query)->throw()->json();
            abort_unless(($reply['partnerCode'] ?? null) === config('momo.partner_code') && ($reply['orderId'] ?? null) === $refund->order_id && ($reply['requestId'] ?? null) === $query['requestId'], 502);
            $item = collect($reply['refundTrans'] ?? [])->first(fn ($row) => ($row['orderId'] ?? null) === $refund->order_id);
            $body = ['orderId' => $refund->order_id, 'requestId' => $refund->request_key, 'partnerCode' => config('momo.partner_code'), 'resultCode' => $item['resultCode'] ?? -1, 'amount' => $item['amount'] ?? 0, 'message' => $reply['message'] ?? 'Chưa có kết quả hoàn tiền'];
        }
        abort_unless(($body['orderId'] ?? null) === $refund->order_id && ($body['requestId'] ?? null) === $refund->request_key && ($body['partnerCode'] ?? null) === config('momo.partner_code'), 502);
        if (($body['resultCode'] ?? -1) !== 0) {
            DB::table('refund_requests')->where('id', $refund->id)->update(['message' => Str::limit((string) ($body['message'] ?? 'Đang xử lý'), 250, ''), 'updated_at' => now()]);

            return response()->json(['data' => DB::table('refund_requests')->find($refund->id), 'message' => 'Yêu cầu đã gửi. Cần kiểm tra lại kết quả MoMo trước khi hoàn tại quầy.']);
        }
        abort_unless((int) ($body['amount'] ?? 0) === $refund->amount, 502);
        DB::transaction(function () use ($r, $booking, $refund): void {
            $row = DB::table('refund_requests')->lockForUpdate()->find($refund->id);
            if ($row->status === 'paid') {
                return;
            }
            $b = Booking::lockForUpdate()->findOrFail($booking->id);
            abort_if($b->paid_amount < $row->amount, 409, 'Sổ giao dịch cần được đối soát.');
            Payment::create(['request_key' => $row->request_key, 'booking_id' => $b->id, 'received_by' => $r->user()->id, 'kind' => 'refund', 'method' => 'momo', 'amount' => $row->amount]);
            $b->decrement('paid_amount', $row->amount);
            DB::table('refund_requests')->where('id', $row->id)->update(['status' => 'paid', 'message' => 'Đã hoàn', 'updated_at' => now()]);
            app(BookingService::class)->audit($r->user(), 'momo.refunded', $b->id, ['amount' => $row->amount]);
        }, 5);

        return response()->json(['data' => DB::table('refund_requests')->find($refund->id)]);
    }
}
