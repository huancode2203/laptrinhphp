<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('hub:maintain')->everyMinute()->withoutOverlapping();
Schedule::command('hub:sync-payos')->everyMinute()->withoutOverlapping(5);
