<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

        // Register model observers
        \App\Models\SubscriptionPlan::observe(\App\Observers\SubscriptionPlanObserver::class);

        // Configure rate limiters
        $this->configureRateLimiters();

        // Register event listeners for job status tracking
        $this->registerEventListeners();
    }

    /**
     * Configure rate limiters for the application.
     */
    private function configureRateLimiters(): void
    {
        RateLimiter::for('downloads', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Too many download attempts. Please try again later.',
                    ], 429);
                });
        });
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
