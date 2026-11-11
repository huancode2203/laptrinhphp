<?php

namespace App;

use App\Models\Booking;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class BookingReminderService
{
    public function ready(string $channel): bool
    {
        return $channel === 'email' ? app(EmailSettings::class)->ready() : (bool) (config('integrations.sms.enabled') && config('integrations.sms.sid') && config('integrations.sms.token') && config('integrations.sms.sender'));
    }

    public function sendDue(): int
    {
        $sent = 0;
        $bookings = Booking::where('status', 'confirmed')->whereBetween('starts_at', [now(), now()->addMinutes(30)])->whereColumn('paid_amount', '>=', 'deposit_amount')->with('user', 'court.branch')->limit(200)->get();
        foreach ($bookings as $booking) {
            foreach (['email', 'sms'] as $channel) {
                $user = $booking->user;
                if (! $user->active || ! $user->{$channel.'_reminders'} || ! $this->ready($channel) || ($channel === 'email' ? ! $user->email_verified_at : ! $user->phone_verified_at)) {
                    continue;
                }
                $lock = Cache::lock('hub:reminder:'.$booking->id.':'.$channel, 60);
                if (! $lock->get()) {
                    continue;
                }
                try {
                    $delivery = DB::table('booking_deliveries')->where('booking_id', $booking->id)->where('channel', $channel)->first();
                    if ($delivery && ($delivery->status === 'sent' || $delivery->attempts >= 3 || ($delivery->retry_at && now()->lt($delivery->retry_at)))) {
                        continue;
                    }
                    if (! $delivery) {
                        DB::table('booking_deliveries')->insert(['booking_id' => $booking->id, 'channel' => $channel, 'status' => 'pending', 'attempts' => 0, 'created_at' => now(), 'updated_at' => now()]);
                        $delivery = DB::table('booking_deliveries')->where('booking_id', $booking->id)->where('channel', $channel)->first();
                    }
                    DB::table('booking_deliveries')->where('id', $delivery->id)->increment('attempts');
                    try {
                        $text = 'Badminton Hub · '.$booking->reference.' sắp đến giờ: '.$booking->starts_at->format('d/m/Y H:i').' – '.$booking->ends_at->format('H:i').', '.$booking->court->name.', '.$booking->court->branch->name.'. Xem lịch tại '.rtrim(config('app.url'), '/').'/#account/bookings/'.$booking->id;
                        if ($channel === 'email') {
                            abort_unless(app(EmailSettings::class)->apply(), 503);
                            Mail::raw($text, fn ($message) => $message->to($user->email)->subject('Nhắc lịch ra sân · '.$booking->reference));
                        } else {
                            $phone = preg_replace('/^0/', '+84', $user->phone);
                            Http::asForm()->withBasicAuth(config('integrations.sms.sid'), config('integrations.sms.token'))->connectTimeout(5)->timeout(15)->post('https://api.twilio.com/2010-04-01/Accounts/'.config('integrations.sms.sid').'/Messages.json', ['To' => $phone, 'From' => config('integrations.sms.sender'), 'Body' => $text])->throw();
                        }
                        DB::table('booking_deliveries')->where('id', $delivery->id)->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null, 'retry_at' => null, 'updated_at' => now()]);
                        $sent++;
                    } catch (\Throwable) {
                        DB::table('booking_deliveries')->where('id', $delivery->id)->update(['status' => 'failed', 'last_error' => 'Nhà cung cấp chưa xác nhận gửi thông báo.', 'retry_at' => now()->addMinutes(5), 'updated_at' => now()]);
                    }
                } finally {
                    $lock->release();
                }
            }
        }

        return $sent;
    }
}
