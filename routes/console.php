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

// Email System Schedules

// Daily job status report - every day at 8:00 AM
app(Schedule::class)->command('emails:daily-report')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->onSuccess(function () {
        \Log::info('Daily job report sent successfully');
    })
    ->onFailure(function () {
        \Log::error('Daily job report failed to send');
    });

// Weekly sales report - every Monday at 9:00 AM
app(Schedule::class)->command('emails:weekly-report')
    ->weeklyOn(1, '09:00') // Monday at 9 AM
    ->withoutOverlapping()
    ->runInBackground()
    ->onSuccess(function () {
        \Log::info('Weekly sales report sent successfully');
    })
    ->onFailure(function () {
        \Log::error('Weekly sales report failed to send');
    });

// Monthly sales report - 1st of each month at 9:00 AM
app(Schedule::class)->command('emails:monthly-report')
    ->monthlyOn(1, '09:00') // 1st of month at 9 AM
    ->withoutOverlapping()
    ->runInBackground()
    ->onSuccess(function () {
        \Log::info('Monthly sales report sent successfully');
    })
    ->onFailure(function () {
        \Log::error('Monthly sales report failed to send');
    });

// Subscription renewal reminders - daily at 10:00 AM to check for upcoming renewals
app(Schedule::class)->command('subscriptions:check-renewals')
    ->dailyAt('10:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->onSuccess(function () {
        \Log::info('Subscription renewal check completed successfully');
    })
    ->onFailure(function () {
        \Log::error('Subscription renewal check failed');
    });
