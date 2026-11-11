<?php

use App\BookingReminderService;
use App\PayosService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('hub:connect-webhook', function (): void {
    $url = app(PayosService::class)->connectWebhook();
    $this->info('PayOS đã xác nhận webhook: '.$url);
})->purpose('Xác nhận webhook PayOS trên tên miền HTTPS hiện tại');

Schedule::command('hub:maintain')->everyMinute()->withoutOverlapping();
Schedule::command('hub:sync-payos')->everyMinute()->withoutOverlapping(5);
Schedule::call(fn () => app(BookingReminderService::class)->sendDue())->name('hub:external-reminders')->everyMinute()->withoutOverlapping(5)->skip(fn () => app()->isDownForMaintenance());
