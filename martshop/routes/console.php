<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reservations:release-expired')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('settlements:release-due')
    ->everyFiveMinutes()
    ->withoutOverlapping(15);

Schedule::command('auth:clear-resets')
    ->dailyAt('03:20')
    ->withoutOverlapping(10);
