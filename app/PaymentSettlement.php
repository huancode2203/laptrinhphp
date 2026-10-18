<?php

namespace App;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Notifications\HubNotification;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class PaymentSettlement
{
    public function receive(PaymentAttempt $attempt, int $resultCode, string $transId): void
    {
        app(FeatureService::class)->expireBookings();
        DB::transaction(function () use ($attempt, $resultCode, $transId): void {
            $a = PaymentAttempt::lockForUpdate()->findOrFail($attempt->id);
            abort_unless($a->partner_code === 'PAYOS', 410, 'Phương thức thanh toán này đã ngừng hỗ trợ. Vui lòng liên hệ chi nhánh để đối chiếu giao dịch cũ.');
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
            $method = 'payos';
            $due = $booking && in_array($booking->status, ['confirmed', 'checked_in'], true) ? max(0, $booking->total_amount - $booking->paid_amount) : 0;
            $paid = min($a->amount, $due);
            if ($paid > 0) {
                Payment::create(['booking_id' => $booking->id, 'received_by' => $owner->id, 'request_key' => $a->request_key, 'kind' => 'payment', 'method' => $method, 'amount' => $paid, 'notes' => $method.' '.$transId]);
                $booking->increment('paid_amount', $paid);
            }
            $extra = $a->amount - $paid;
            if ($extra > 0) {
                $walletKey = $paid > 0 ? (string) Uuid::uuid5(Uuid::fromString($a->request_key), 'momo-excess') : $a->request_key;
                app(CommerceService::class)->wallet($owner, $owner, $extra, $method.'_topup', $walletKey, $booking ? 'Tiền vượt số cần trả hoặc đến sau khi lịch thay đổi' : 'Nạp ví bằng chuyển khoản', null, 'payment_attempt', $a->id);
            }
            $a->forceFill(['status' => $booking && $paid === 0 ? 'credited' : 'paid', 'trans_id' => $transId, 'result_code' => 0])->save();
            app(BookingService::class)->audit($owner, $method.'.received', $a->id, ['amount' => $a->amount, 'trans_id' => $transId, 'booking_paid' => $paid, 'wallet_credit' => $extra], 'payment_attempt');
            $owner->notify(new HubNotification(['type' => 'payment.received', 'title' => 'Đã xác nhận chuyển khoản', 'body' => 'Đã nhận thanh toán '.number_format($a->amount).'đ.']));
        }, 5);
    }
}
