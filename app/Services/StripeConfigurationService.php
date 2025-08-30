<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdminSetting;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Stripe;
use Stripe\StripeClient;

class StripeConfigurationService
{
    private ?StripeClient $stripeClient = null;

    /**
     * Get the current Stripe configuration.
     */
    public function getConfig(): array
    {
        return AdminSetting::getStripeConfig();
    }

    /**
     * Set Stripe configuration and validate API keys.
     */
    public function setConfig(array $config): bool
    {
        // Validate required fields
        if (empty($config['public_key']) || empty($config['secret_key'])) {
            throw new \InvalidArgumentException('Public key and secret key are required');
        }

        // Test the API key before saving
        if (!$this->testApiKey($config['secret_key'])) {
            throw new \RuntimeException('Invalid Stripe API key or connection failed');
        }

        // Save configuration
        AdminSetting::setStripeConfig($config);

        // Clear cached client
        $this->stripeClient = null;

        Log::info('Stripe configuration updated', [
            'test_mode' => $config['test_mode'] ?? false,
            'has_webhook_secret' => !empty($config['webhook_secret']),
        ]);

        return true;
    }

    /**
     * Test a Stripe API key for validity.
     */
    public function testApiKey(string $secretKey): bool
    {
        try {
            $stripe = new StripeClient($secretKey);

            // Make a simple API call to test the key
            $account = $stripe->account->retrieve();

            Log::info('Stripe API key test successful', [
                'account_id' => $account->id,
                'country' => $account->country,
                'currency' => $account->default_currency,
            ]);

            return true;

        } catch (AuthenticationException $e) {
            Log::warning('Invalid Stripe API key', [
                'error' => $e->getMessage(),
            ]);
            return false;

        } catch (InvalidRequestException $e) {
            Log::warning('Stripe API request error during key test', [
                'error' => $e->getMessage(),
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('Unexpected error testing Stripe API key', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get a configured Stripe client instance.
     */
    public function getStripeClient(): StripeClient
    {
        if ($this->stripeClient === null) {
            $config = $this->getConfig();

            if (empty($config['secret_key'])) {
                throw new \RuntimeException('Stripe secret key not configured');
            }

            $this->stripeClient = new StripeClient($config['secret_key']);
        }

        return $this->stripeClient;
    }

    /**
     * Set the global Stripe API key.
     */
    public function setGlobalApiKey(): void
    {
        $config = $this->getConfig();

        if (!empty($config['secret_key'])) {
            Stripe::setApiKey($config['secret_key']);
        }
    }

    /**
     * Check if Stripe is configured and ready to use.
     */
    public function isConfigured(): bool
    {
        return AdminSetting::hasStripeConfig();
    }

    /**
     * Get configuration status for admin dashboard.
     */
    public function getConfigurationStatus(): array
    {
        $config = $this->getConfig();

        return [
            'configured' => $this->isConfigured(),
            'has_public_key' => !empty($config['public_key']),
            'has_secret_key' => !empty($config['secret_key']),
            'has_webhook_secret' => !empty($config['webhook_secret']),
            'test_mode' => $config['test_mode'],
            'public_key_preview' => $this->maskKey($config['public_key'] ?? ''),
            'secret_key_preview' => $this->maskKey($config['secret_key'] ?? ''),
        ];
    }

    /**
     * Clear all Stripe configuration.
     */
    public function clearConfig(): bool
    {
        try {
            AdminSetting::whereIn('key', [
                'stripe_public_key',
                'stripe_secret_key',
                'stripe_webhook_secret',
                'stripe_test_mode',
            ])->delete();

            // Clear cached client
            $this->stripeClient = null;

            Log::info('Stripe configuration cleared');
            return true;

        } catch (\Exception $e) {
            Log::error('Failed to clear Stripe configuration', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Mask an API key for display purposes.
     */
    private function maskKey(string $key): string
    {
        if (strlen($key) < 8) {
            return str_repeat('*', strlen($key));
        }

        $start = substr($key, 0, 8);
        $end = substr($key, -4);
        $middle = str_repeat('*', max(0, strlen($key) - 12));

        return $start . $middle . $end;
    }

    /**
     * Get webhook endpoint URL for configuration.
     */
    public function getWebhookUrl(): string
    {
        return url('/stripe/webhook');
    }

    /**
     * Get recommended webhook events.
     */
    public function getRecommendedWebhookEvents(): array
    {
        return [
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted',
            'invoice.payment_succeeded',
            'invoice.payment_failed',
        ];
    }

    /**
     * Get Stripe price IDs configuration.
     */
    public function getPriceIds(): array
    {
        return [
            'basic' => AdminSetting::getValue('stripe_price_basic', ''),
            'professional' => AdminSetting::getValue('stripe_price_professional', ''),
            'enterprise' => AdminSetting::getValue('stripe_price_enterprise', ''),
        ];
    }

    /**
     * Set Stripe price IDs configuration.
     */
    public function setPriceIds(array $priceIds): bool
    {
        try {
            AdminSetting::setValue('stripe_price_basic', $priceIds['basic'] ?? '', false, 'Stripe Price ID for Basic Plan');
            AdminSetting::setValue('stripe_price_professional', $priceIds['professional'] ?? '', false, 'Stripe Price ID for Professional Plan');
            AdminSetting::setValue('stripe_price_enterprise', $priceIds['enterprise'] ?? '', false, 'Stripe Price ID for Enterprise Plan');

            Log::info('Stripe price IDs updated', [
                'has_basic' => !empty($priceIds['basic']),
                'has_professional' => !empty($priceIds['professional']),
                'has_enterprise' => !empty($priceIds['enterprise']),
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to save Stripe price IDs', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Clear Stripe price IDs configuration.
     */
    public function clearPriceIds(): bool
    {
        try {
            AdminSetting::whereIn('key', [
                'stripe_price_basic',
                'stripe_price_professional',
                'stripe_price_enterprise',
            ])->delete();

            Log::info('Stripe price IDs cleared');
            return true;

        } catch (\Exception $e) {
            Log::error('Failed to clear Stripe price IDs', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get price IDs configuration status.
     */
    public function getPriceIdsStatus(): array
    {
        $priceIds = $this->getPriceIds();

        return [
            'configured' => !empty($priceIds['basic']) || !empty($priceIds['professional']) || !empty($priceIds['enterprise']),
            'has_basic' => !empty($priceIds['basic']),
            'has_professional' => !empty($priceIds['professional']),
            'has_enterprise' => !empty($priceIds['enterprise']),
            'count' => count(array_filter($priceIds)),
        ];
    }
}
