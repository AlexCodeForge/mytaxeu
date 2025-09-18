<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Price;
use Stripe\Product;
use Stripe\Stripe;

class SubscriptionPlanObserver
{
    public function __construct()
    {
        $this->configureStripe();
    }

    private function configureStripe(): void
    {
        try {
            $stripeConfig = \App\Models\AdminSetting::getStripeConfig();
            if (!empty($stripeConfig['secret_key'])) {
                Stripe::setApiKey($stripeConfig['secret_key']);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to configure Stripe in SubscriptionPlanObserver', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle the SubscriptionPlan "created" event.
     */
    public function created(SubscriptionPlan $plan): void
    {
        $this->syncToStripe($plan);
    }

    /**
     * Handle the SubscriptionPlan "updated" event.
     */
    public function updated(SubscriptionPlan $plan): void
    {
        $this->syncToStripe($plan);
    }

    /**
     * Handle the SubscriptionPlan "deleted" event.
     */
    public function deleted(SubscriptionPlan $plan): void
    {
        $this->archiveStripeResources($plan);
    }

    /**
     * Synchronize plan to Stripe
     */
    protected function syncToStripe(SubscriptionPlan $plan): void
    {
        $stripeConfig = \App\Models\AdminSetting::getStripeConfig();
        if (empty($stripeConfig['secret_key'])) {
            Log::info('Stripe secret key not configured, skipping sync', ['plan_id' => $plan->id]);
            return;
        }

        // Ensure Stripe is configured with current key
        Stripe::setApiKey($stripeConfig['secret_key']);

        try {
            // Create or update Stripe product
            $stripeProduct = $this->createOrUpdateStripeProduct($plan);

            // Handle pricing for each frequency
            $this->syncPricingToStripe($plan, $stripeProduct);

            Log::info('Successfully synced plan to Stripe', [
                'plan_id' => $plan->id,
                'plan_slug' => $plan->slug,
                'stripe_product_id' => $stripeProduct->id
            ]);

        } catch (ApiErrorException $e) {
            Log::error('Failed to sync plan to Stripe', [
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
                'stripe_error_code' => $e->getStripeCode(),
            ]);
        } catch (\Exception $e) {
            Log::error('Unexpected error syncing plan to Stripe', [
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create or update Stripe product
     */
    protected function createOrUpdateStripeProduct(SubscriptionPlan $plan): Product
    {
        $productData = [
            'name' => $plan->name,
            'description' => !empty($plan->description) ? $plan->description : "Subscription plan: {$plan->name}",
            'metadata' => [
                'plan_id' => (string) $plan->id,
                'plan_slug' => $plan->slug,
                'created_at' => $plan->created_at->toISOString(),
            ],
        ];

        // Try to find existing product by metadata
        try {
            $products = Product::all(['limit' => 100]);

            // Filter by metadata locally since Stripe API doesn't support metadata filtering in list
            foreach ($products->data as $product) {
                if (isset($product->metadata['plan_slug']) && $product->metadata['plan_slug'] === $plan->slug) {
                    return Product::update($product->id, $productData);
                }
            }
        } catch (ApiErrorException $e) {
            Log::warning('Error searching for existing Stripe product', [
                'plan_slug' => $plan->slug,
                'error' => $e->getMessage(),
            ]);
        }

        // Create new product
        return Product::create($productData);
    }

    /**
     * Sync pricing for all frequencies to Stripe
     */
    protected function syncPricingToStripe(SubscriptionPlan $plan, Product $stripeProduct): void
    {
        $frequencies = [
            'weekly' => ['interval' => 'week', 'interval_count' => 1],
            'monthly' => ['interval' => 'month', 'interval_count' => 1],
            'yearly' => ['interval' => 'year', 'interval_count' => 1],
        ];

        foreach ($frequencies as $frequency => $stripeInterval) {
            // Only handle monthly frequency since we simplified the system
            if ($frequency !== 'monthly') {
                continue;
            }

            $isEnabled = $plan->is_monthly_enabled;
            $price = $plan->monthly_price;
            $discountedPrice = $plan->monthly_price; // No discount system anymore
            $currentStripeId = $plan->stripe_monthly_price_id;

            if (!$isEnabled || !$price) {
                // Archive existing price if frequency is disabled
                if ($currentStripeId) {
                    $this->archiveStripePrice($currentStripeId);
                    $plan->update(["stripe_monthly_price_id" => null]);
                }
                continue;
            }

            // Determine the price to use (discounted if available)
            $finalPrice = $discountedPrice ?? $price;
            $unitAmount = (int) round($finalPrice * 100); // Convert to cents

            // Check if we need to create a new price
            $needsNewPrice = !$currentStripeId || $this->priceChanged($currentStripeId, $unitAmount);

            if ($needsNewPrice) {
                // Archive old price if it exists
                if ($currentStripeId) {
                    $this->archiveStripePrice($currentStripeId);
                }

                // Create new price
                $newPrice = $this->createStripePrice($stripeProduct, $unitAmount, $stripeInterval, $plan);
                $plan->update(["stripe_monthly_price_id" => $newPrice->id]);
            }
        }
    }

    /**
     * Create a new Stripe price
     */
    protected function createStripePrice(Product $product, int $unitAmount, array $interval, SubscriptionPlan $plan): Price
    {
        $priceData = [
            'product' => $product->id,
            'unit_amount' => $unitAmount,
            'currency' => 'eur',
            'recurring' => $interval,
            'metadata' => [
                'plan_id' => (string) $plan->id,
                'plan_slug' => $plan->slug,
                'frequency' => 'monthly',
                'original_price' => (string) $plan->monthly_price,
                'sync_source' => 'observer',
            ],
        ];

        return Price::create($priceData);
    }

    /**
     * Check if price amount has changed
     */
    protected function priceChanged(string $stripePriceId, int $newUnitAmount): bool
    {
        try {
            $price = Price::retrieve($stripePriceId);
            return $price->unit_amount !== $newUnitAmount;
        } catch (ApiErrorException $e) {
            Log::warning('Error retrieving Stripe price for comparison', [
                'stripe_price_id' => $stripePriceId,
                'error' => $e->getMessage(),
            ]);
            return true; // Assume it changed if we can't retrieve it
        }
    }

    /**
     * Archive a Stripe price
     */
    protected function archiveStripePrice(string $stripePriceId): void
    {
        try {
            Price::update($stripePriceId, ['active' => false]);
            Log::info('Archived Stripe price', ['stripe_price_id' => $stripePriceId]);
        } catch (ApiErrorException $e) {
            Log::warning('Failed to archive Stripe price', [
                'stripe_price_id' => $stripePriceId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Archive Stripe resources when plan is deleted
     */
    protected function archiveStripeResources(SubscriptionPlan $plan): void
    {
        $stripeConfig = \App\Models\AdminSetting::getStripeConfig();
        if (empty($stripeConfig['secret_key'])) {
            return;
        }

        // Ensure Stripe is configured with current key
        Stripe::setApiKey($stripeConfig['secret_key']);

        try {
            // Archive all prices
            $priceIds = array_filter([
                $plan->stripe_weekly_price_id,
                $plan->stripe_monthly_price_id,
                $plan->stripe_yearly_price_id,
            ]);

            foreach ($priceIds as $priceId) {
                $this->archiveStripePrice($priceId);
            }

            // Find and archive the product
            $products = Product::all([
                'limit' => 100,
                'metadata' => ['plan_slug' => $plan->slug],
            ]);

            if ($products->data && count($products->data) > 0) {
                $product = $products->data[0];
                Product::update($product->id, ['active' => false]);
                Log::info('Archived Stripe product', ['stripe_product_id' => $product->id]);
            }

        } catch (ApiErrorException $e) {
            Log::error('Failed to archive Stripe resources', [
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
