<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('telegram:send-reminders')
    ->everyMinute()
    ->timezone(config('app.timezone'))
    ->withoutOverlapping(2);

Schedule::command('telegram:send-weekly-summary')
    ->everyMinute()
    ->timezone(config('app.timezone'))
    ->withoutOverlapping(2);
