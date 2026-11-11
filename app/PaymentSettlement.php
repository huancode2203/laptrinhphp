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
    public function receive(PaymentAttempt $attempt, int $resultCode, string $transId, ?int $receivedAmount = null, bool $allowPartial = false): void
    {
        app(FeatureService::class)->expireBookings();
        DB::transaction(function () use ($attempt, $resultCode, $transId, $receivedAmount, $allowPartial): void {
            $a = PaymentAttempt::lockForUpdate()->findOrFail($attempt->id);
            abort_unless($a->partner_code === 'PAYOS', 410, 'Phương thức thanh toán này đã ngừng hỗ trợ. Vui lòng liên hệ chi nhánh để đối chiếu giao dịch cũ.');
            if ($resultCode !== 0) {
                if (! in_array($a->status, ['paid', 'credited', 'partial'], true)) {
                    $a->update(['result_code' => $resultCode, 'status' => $resultCode === 9000 ? 'pending' : 'failed']);
                }

                return;
            }
            abort_if($a->trans_id && $a->trans_id !== $transId, 409, 'Mã giao dịch không khớp.');
            $received = $receivedAmount ?? $a->amount;
            abort_unless($received > 0 && $received <= 2147483647 && ($allowPartial || $received >= $a->amount), 422, 'Số tiền thực nhận chưa đủ hoặc vượt giới hạn.');
            $previous = $a->settled_amount;
            if ($previous === 0 && in_array($a->status, ['paid', 'credited'], true)) {
                $previous = $a->amount;
            }
            abort_if($received < $previous, 409, 'Số tiền ngân hàng báo nhỏ hơn khoản đã ghi nhận. Cần đối chiếu giao dịch.');
            $delta = $received - $previous;
            if ($delta === 0) {
                return;
            }
            abort_if(PaymentAttempt::where('trans_id', $transId)->where('id', '!=', $a->id)->exists(), 409, 'Giao dịch đã xử lý.');
            $owner = User::lockForUpdate()->findOrFail($a->user_id);
            $allocations = DB::table('payment_attempt_bookings')->where('payment_attempt_id', $a->id)->orderBy('booking_id')->lockForUpdate()->get();
            if ($allocations->isEmpty() && $a->booking_id) {
                abort_if($a->purpose === 'series', 409, 'Thiếu bảng phân bổ của chuỗi; cần chi nhánh đối chiếu trước khi ghi tiền.');
                DB::table('payment_attempt_bookings')->insert(['payment_attempt_id' => $a->id, 'booking_id' => $a->booking_id, 'amount' => $a->amount, 'settled_amount' => min($previous, $a->amount)]);
                $allocations = DB::table('payment_attempt_bookings')->where('payment_attempt_id', $a->id)->get();
            }
            $available = $delta;
            $paid = 0;
            foreach ($allocations as $allocation) {
                $booking = Booking::lockForUpdate()->findOrFail($allocation->booking_id);
                abort_unless($booking->user_id === $owner->id, 409, 'Chủ lịch không khớp giao dịch ngân hàng.');
                $portion = min($available, max(0, $allocation->amount - $allocation->settled_amount));
                $due = in_array($booking->status, ['confirmed', 'checked_in'], true) ? max(0, $booking->total_amount - $booking->paid_amount) : 0;
                $partPaid = min($portion, $due);
                if ($partPaid > 0) {
                    $requestKey = $previous === 0 && $allocations->count() === 1 ? $a->request_key : (string) Uuid::uuid5($a->request_key, 'payos-booking-'.$booking->id.'-'.$received);
                    Payment::create(['booking_id' => $booking->id, 'received_by' => $owner->id, 'request_key' => $requestKey, 'kind' => 'payment', 'method' => 'payos', 'amount' => $partPaid, 'notes' => 'Chuyển khoản PayOS '.$transId]);
                    $booking->increment('paid_amount', $partPaid);
                    $paid += $partPaid;
                }
                if ($portion > 0) {
                    DB::table('payment_attempt_bookings')->where('id', $allocation->id)->increment('settled_amount', $portion);
                    $available -= $portion;
                }
            }
            $extra = $delta - $paid;
            if ($extra > 0) {
                $walletKey = $previous > 0 ? (string) Uuid::uuid5($a->request_key, 'payos-additional-'.$received) : ($paid > 0 ? (string) Uuid::uuid5($a->request_key, 'momo-excess') : $a->request_key);
                app(CommerceService::class)->wallet($owner, $owner, $extra, 'payos_topup', $walletKey, $a->booking_id ? 'Số tiền chuyển dư hoặc đến sau khi lịch thay đổi' : 'Nạp ví bằng chuyển khoản', null, 'payment_attempt', $a->id);
            }
            $a->forceFill(['status' => $received < $a->amount ? 'partial' : ($a->booking_id && $paid === 0 && $previous === 0 ? 'credited' : 'paid'), 'trans_id' => $transId, 'result_code' => 0, 'received_amount' => $received, 'settled_amount' => $received, 'review_reason' => null])->save();
            DB::table('payment_reconciliations')->where('payment_attempt_id', $a->id)->where('status', 'pending')->update(['status' => 'resolved', 'received_amount' => $received, 'resolved_at' => now(), 'updated_at' => now()]);
            app(BookingService::class)->audit($owner, 'payos.received', $a->id, ['amount' => $delta, 'trans_id' => $transId, 'booking_paid' => $paid, 'wallet_credit' => $extra], 'payment_attempt');
            $owner->notify(new HubNotification(['type' => 'payment.received', 'title' => 'Đã ghi nhận chuyển khoản', 'body' => 'Đã ghi nhận '.number_format($delta, 0, ',', '.').'đ'.($extra > 0 ? ', trong đó '.number_format($extra, 0, ',', '.').'đ vào số dư HUB.' : '.')]));
        }, 5);
    }
}
