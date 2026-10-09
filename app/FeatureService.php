<?php

namespace App;

use App\Models\Booking;
use App\Models\Court;
use App\Models\User;
use App\Notifications\HubNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FeatureService
{
    public function expireBookings(): int
    {
        $expired = 0;
        $ids = Booking::where('status', 'confirmed')->whereNotNull('payment_expires_at')->where('payment_expires_at', '<=', now())->whereColumn('paid_amount', '<', 'deposit_amount')->limit(200)->pluck('id');
        foreach ($ids as $id) {
            DB::transaction(function () use ($id, &$expired): void {
                $booking = Booking::find($id);
                if (! $booking) {
                    return;
                }
                Court::lockForUpdate()->findOrFail($booking->court_id);
                $booking = Booking::lockForUpdate()->findOrFail($id);
                if ($booking->status !== 'confirmed' || $booking->paid_amount >= $booking->deposit_amount || $booking->payment_expires_at->gt(now())) {
                    return;
                }
                $walletNet = $booking->payments()->where('method', 'wallet')->get()->sum(fn ($p) => $p->kind === 'refund' ? -$p->amount : $p->amount);
                if ($booking->paid_amount > 0 && $walletNet === $booking->paid_amount) {
                    app(CommerceService::class)->refundWalletBooking($booking->user, $booking);
                }
                $booking->status = 'cancelled';
                $booking->save();
                DB::table('coach_sessions')->where('booking_id', $id)->update(['status' => 'cancelled', 'updated_at' => now()]);
                $booking->user->notify(new HubNotification(['type' => 'booking.expired', 'title' => 'Lịch đã hết hạn giữ chỗ', 'body' => 'Lịch '.$booking->reference.' chưa đủ tiền cọc trong thời hạn thanh toán.']));
                app(BookingService::class)->notifyWaitlist($booking->court, $booking->starts_at, $booking->ends_at);
                $expired++;
            }, 5);
        }

        return $expired;
    }

    public function media(array $ids, User $user, bool $imagesOnly = true): void
    {
        if ($imagesOnly && DB::table('media_uploads')->whereIn('id', $ids)->whereNotIn('mime', ['image/jpeg', 'image/png', 'image/webp'])->exists()) {
            throw ValidationException::withMessages(['images' => 'Chỉ chọn ảnh JPG, PNG hoặc WebP.']);
        }
        if (count($ids) !== count(array_unique($ids)) || DB::table('media_uploads')->where('user_id', $user->id)->whereIn('id', $ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['images' => 'Ảnh đính kèm không thuộc tài khoản của bạn.']);
        }
    }
}
