<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\AdminSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class StripeConfigProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Set Stripe configuration from database when application boots
        $this->setStripeConfigFromDatabase();
    }

    /**
     * Load Stripe configuration from database and set it in Laravel config
     */
    private function setStripeConfigFromDatabase(): void
    {
        try {
            // Only run this if we have a database connection and the admin_settings table exists
            if (!$this->canAccessDatabase()) {
                return;
            }

            $stripeConfig = AdminSetting::getStripeConfig();

            // Only override config if we have database values
            if (!empty($stripeConfig['public_key']) && !empty($stripeConfig['secret_key'])) {
                Config::set('cashier.key', $stripeConfig['public_key']);
                Config::set('cashier.secret', $stripeConfig['secret_key']);

                if (!empty($stripeConfig['webhook_secret'])) {
                    Config::set('cashier.webhook.secret', $stripeConfig['webhook_secret']);
                }

                Log::info('Stripe configuration loaded from database', [
                    'has_public_key' => !empty($stripeConfig['public_key']),
                    'has_secret_key' => !empty($stripeConfig['secret_key']),
                    'has_webhook_secret' => !empty($stripeConfig['webhook_secret']),
                    'test_mode' => $stripeConfig['test_mode'],
                ]);
            }

        } catch (\Exception $e) {
            // Silently fail during boot to prevent application crashes
            // This can happen during migrations or if the database isn't ready
            Log::debug('Could not load Stripe configuration from database during boot', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if we can safely access the database
     */
    private function canAccessDatabase(): bool
    {
        try {
            // Check if we're in a console command that doesn't need database
            if (app()->runningInConsole()) {
                $command = $_SERVER['argv'][1] ?? '';
                $skipCommands = ['migrate', 'migrate:install', 'migrate:status', 'config:cache', 'config:clear'];

                foreach ($skipCommands as $skipCommand) {
                    if (str_contains($command, $skipCommand)) {
                        return false;
                    }
                }
            }

            // Try to check if the admin_settings table exists
            return \Schema::hasTable('admin_settings');

        } catch (\Exception $e) {
            return false;
        }
    }
}
