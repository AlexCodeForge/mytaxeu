<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Scheduling\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule credit expiration to run daily at 2:00 AM
app(Schedule::class)->command('credits:expire')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground();
