<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-users', function (User $user): bool {
            return $user->isAdmin();
        });

        // Register event listeners for job status tracking
        $this->registerEventListeners();
    }

    /**
     * Register event listeners for job status tracking.
     */
    private function registerEventListeners(): void
    {
        $this->app['events']->listen(
            \App\Events\JobStatusUpdated::class,
            \App\Listeners\JobStatusUpdateNotificationListener::class
        );

        $this->app['events']->listen(
            \App\Events\JobLogCreated::class,
            \App\Listeners\JobLogNotificationListener::class
        );
    }
}
