<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SubscriptionPlan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Price;
use Stripe\Product;
use Stripe\Stripe;

class SyncSubscriptionPlansToStripe extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:sync-to-stripe
                            {--force : Force sync even if already synced}
                            {--plan= : Sync specific plan by slug}
                            {--dry-run : Show what would be synced without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync subscription plans to Stripe (products and prices)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!config('cashier.secret')) {
            $this->error('Stripe secret key not configured. Please set STRIPE_SECRET in your .env file.');
            return 1;
        }

        Stripe::setApiKey(config('cashier.secret'));

        $this->info('Starting Stripe synchronization...');

        // Get plans to sync
        $query = SubscriptionPlan::query();

        if ($planSlug = $this->option('plan')) {
            $query->where('slug', $planSlug);
            $this->info("Syncing specific plan: {$planSlug}");
        } else {
            $this->info('Syncing all subscription plans...');
        }

        $plans = $query->get();

        if ($plans->isEmpty()) {
            $this->warn('No plans found to sync.');
            return 0;
        }

        $this->info("Found {$plans->count()} plan(s) to sync.");

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No changes will be made to Stripe');
        }

        $progressBar = $this->output->createProgressBar($plans->count());
        $progressBar->start();

        $successCount = 0;
        $errorCount = 0;

        foreach ($plans as $plan) {
            try {
                $this->syncPlanToStripe($plan);
                $successCount++;
                $this->newLine();
                $this->info("✓ Synced: {$plan->name} ({$plan->slug})");
            } catch (\Exception $e) {
                $errorCount++;
                $this->newLine();
                $this->error("✗ Failed to sync: {$plan->name} ({$plan->slug}) - {$e->getMessage()}");
                Log::error('Stripe sync failed for plan', [
                    'plan_id' => $plan->id,
                    'error' => $e->getMessage()
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info("Synchronization complete!");
        $this->table(
            ['Result', 'Count'],
            [
                ['Successful', $successCount],
                ['Failed', $errorCount],
                ['Total', $plans->count()],
            ]
        );

        return $errorCount > 0 ? 1 : 0;
    }

    /**
     * Sync a single plan to Stripe
     */
    protected function syncPlanToStripe(SubscriptionPlan $plan): void
    {
        if ($this->option('dry-run')) {
            $this->line("Would sync plan: {$plan->name}");
            return;
        }

        // Create or update Stripe product
        $stripeProduct = $this->createOrUpdateStripeProduct($plan);

        // Handle pricing for each frequency
        $this->syncPricingToStripe($plan, $stripeProduct);
    }

    /**
     * Create or update Stripe product
     */
    protected function createOrUpdateStripeProduct(SubscriptionPlan $plan): Product
    {
        $productData = [
            'name' => $plan->name,
            'description' => $plan->description ?? "Subscription plan: {$plan->name}",
            'metadata' => [
                'plan_id' => (string) $plan->id,
                'plan_slug' => $plan->slug,
                'sync_source' => 'command',
                'synced_at' => now()->toISOString(),
            ],
        ];

        // Try to find existing product by metadata
        try {
            $products = Product::all(['limit' => 100]);

            // Filter by metadata locally since Stripe API doesn't support metadata filtering in list
            foreach ($products->data as $product) {
                if (isset($product->metadata['plan_slug']) && $product->metadata['plan_slug'] === $plan->slug) {
                    $this->line("  Updating existing Stripe product: {$product->id}");
                    return Product::update($product->id, $productData);
                }
            }
        } catch (ApiErrorException $e) {
            throw new \Exception("Error searching for existing Stripe product: {$e->getMessage()}");
        }

        // Create new product
        $this->line("  Creating new Stripe product...");
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
                if ($currentStripeId) {
                    $this->line("  Archiving {$frequency} price (disabled)...");
                    $this->archiveStripePrice($currentStripeId);
                    $plan->update(["stripe_monthly_price_id" => null]);
                }
                continue;
            }

            // Determine the price to use (discounted if available)
            $finalPrice = $discountedPrice ?? $price;
            $unitAmount = (int) round($finalPrice * 100); // Convert to cents

            // Check if we need to create a new price
            $needsNewPrice = $this->option('force') || !$currentStripeId || $this->priceChanged($currentStripeId, $unitAmount);

            if ($needsNewPrice) {
                // Archive old price if it exists
                if ($currentStripeId) {
                    $this->line("  Archiving old {$frequency} price...");
                    $this->archiveStripePrice($currentStripeId);
                }

                // Create new price
                $this->line("  Creating {$frequency} price (€{$finalPrice})...");
                $newPrice = $this->createStripePrice($stripeProduct, $unitAmount, $stripeInterval, $plan);
                $plan->update(["stripe_monthly_price_id" => $newPrice->id]);
            } else {
                $this->line("  {$frequency} price up to date");
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
                'sync_source' => 'command',
                'synced_at' => now()->toISOString(),
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
            // If we can't retrieve the price, assume it needs to be recreated
            return true;
        }
    }

    /**
     * Archive a Stripe price
     */
    protected function archiveStripePrice(string $stripePriceId): void
    {
        try {
            Price::update($stripePriceId, ['active' => false]);
        } catch (ApiErrorException $e) {
            throw new \Exception("Failed to archive Stripe price {$stripePriceId}: {$e->getMessage()}");
        }
    }
}
