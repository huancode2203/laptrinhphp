<?php

namespace App;

use App\Models\GameEvent;
use App\Notifications\HubNotification;
use Illuminate\Support\Facades\DB;

class EventWaitlistService
{
    public function advance(GameEvent $event): int
    {
        return DB::transaction(function () use ($event): int {
            $event = GameEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ($event->status !== 'published' || $event->starts_at->lte(now()) || $event->matches()->exists()) {
                return 0;
            }
            $event->registrations()->where('status', 'pending')->where('paid_amount', 0)->where('created_at', '<=', now()->subMinutes(30))->update(['status' => 'expired', 'updated_at' => now()]);
            $free = max(0, $event->capacity - $event->registrations()->whereIn('status', ['pending', 'confirmed'])->count());
            $waiting = $event->registrations()->where('status', 'waiting')->orderBy('created_at')->orderBy('id')->limit($free)->lockForUpdate()->get();
            foreach ($waiting as $reg) {
                $reg->update(['status' => $event->fee > 0 ? 'pending' : 'confirmed', 'method' => 'cash', 'created_at' => now()]);
                $reg->user->notify(new HubNotification(['type' => 'event.waitlist_available', 'title' => 'Đã có chỗ cho bạn', 'body' => $event->title.($event->fee > 0 ? ' · Thanh toán trong 30 phút để xác nhận vé.' : ''), 'event_id' => $event->id]));
            }

            return $waiting->count();
        }, 5);
    }

    public function maintain(): int
    {
        return GameEvent::where('status', 'published')->where('starts_at', '>', now())->whereHas('registrations', fn ($q) => $q->whereIn('status', ['pending', 'waiting']))->get()->sum(fn ($event) => $this->advance($event));
    }
}
