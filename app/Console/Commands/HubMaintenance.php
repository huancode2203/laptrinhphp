<?php

namespace App\Console\Commands;

use App\EventWaitlistService;
use App\FeatureService;
use App\Models\Booking;
use App\Notifications\HubNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HubMaintenance extends Command
{
    protected $signature = 'hub:maintain';

    protected $description = 'Giải phóng lịch quá hạn và gửi nhắc lịch sắp chơi';

    public function handle(FeatureService $features): int
    {
        app(EventWaitlistService::class)->maintain();
        $expired = $features->expireBookings();
        foreach (Booking::where('status', 'checked_in')->where('ends_at', '<=', now())->whereNull('checkout_due_at')->pluck('id') as $id) {
            DB::transaction(function () use ($id): void {
                $booking = Booking::lockForUpdate()->findOrFail($id);
                if ($booking->checkout_due_at || $booking->status !== 'checked_in') {
                    return;
                }
                $booking->forceFill(['checkout_due_at' => now()])->save();
                $booking->user->notify(new HubNotification(['type' => 'booking.checkout_due', 'title' => 'Buổi chơi đã hết giờ', 'body' => 'Vui lòng kiểm tra hóa đơn '.$booking->reference.' tại quầy. Tiền dịch vụ được tính riêng và không áp dụng giảm giá sân.', 'booking_id' => $booking->id]));
            });
        }
        $ids = Booking::where('status', 'confirmed')->whereNull('reminded_at')->whereBetween('starts_at', [now(), now()->addMinutes(30)])->pluck('id');
        foreach ($ids as $id) {
            DB::transaction(function () use ($id): void {
                $b = Booking::lockForUpdate()->findOrFail($id);
                if ($b->reminded_at || $b->status !== 'confirmed') {
                    return;
                }
                $b->user->notify(new HubNotification(['type' => 'booking.reminder', 'title' => 'Sắp đến giờ ra sân', 'body' => $b->court->branch->name.' · '.$b->court->name.' · '.$b->starts_at->format('H:i'), 'booking_id' => $b->id]));
                $b->reminded_at = now();
                $b->save();
            });
        }
        $this->info('Đã giải phóng '.$expired.' lịch quá hạn.');

        return self::SUCCESS;
    }
}
