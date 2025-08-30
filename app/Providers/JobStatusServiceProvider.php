<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\JobStatusService;
use Illuminate\Support\ServiceProvider;

class JobStatusServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(JobStatusService::class, function ($app) {
            return new JobStatusService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
