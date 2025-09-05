<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AdminSetting;
use App\Models\User;
use App\Services\CreditService;
use App\Services\StripeConfigurationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Stripe\Customer;
use Stripe\Stripe;
use Stripe\Subscription;

class FixMissingSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'stripe:fix-missing-subscriptions {--dry-run : Run without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Fix users who have Stripe customer IDs but missing local subscription records';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Configure Stripe API key
        $stripeService = app(StripeConfigurationService::class);
        if (!$stripeService->isConfigured()) {
            $this->error('Stripe is not configured. Please configure Stripe settings first.');
            return Command::FAILURE;
        }

        $stripeConfig = AdminSetting::getStripeConfig();
        Stripe::setApiKey($stripeConfig['secret_key']);

        $this->info('Looking for users with Stripe customer IDs but missing subscriptions...');

        $usersWithStripeIds = User::whereNotNull('stripe_id')
            ->whereDoesntHave('subscriptions')
            ->get();

        if ($usersWithStripeIds->isEmpty()) {
            $this->info('No users found with missing subscriptions.');
            return Command::SUCCESS;
        }

        $this->info("Found {$usersWithStripeIds->count()} users with potential missing subscriptions.");

        $creditService = app(CreditService::class);
        $fixed = 0;
        $errors = 0;

        foreach ($usersWithStripeIds as $user) {
            $this->info("Checking user {$user->id} ({$user->email}) with Stripe ID: {$user->stripe_id}");

            try {
                // Fetch customer from Stripe
                $customer = Customer::retrieve($user->stripe_id);

                // Get active subscriptions
                $subscriptions = Subscription::all([
                    'customer' => $user->stripe_id,
                    'status' => 'active',
                    'limit' => 10,
                ]);

                if ($subscriptions->data) {
                    foreach ($subscriptions->data as $stripeSubscription) {
                        $this->info("  Found active subscription: {$stripeSubscription->id}");

                        if ($this->option('dry-run')) {
                            $this->info("  [DRY RUN] Would create local subscription record");
                            $this->info("  [DRY RUN] Would allocate credits based on plan");
                            continue;
                        }

                        // Create local subscription record
                        $localSubscription = $user->subscriptions()->create([
                            'type' => 'default',
                            'stripe_id' => $stripeSubscription->id,
                            'stripe_status' => $stripeSubscription->status,
                            'stripe_price' => $stripeSubscription->items->data[0]->price->id ?? null,
                            'quantity' => $stripeSubscription->items->data[0]->quantity ?? 1,
                            'trial_ends_at' => $stripeSubscription->trial_end ? Carbon::createFromTimestamp($stripeSubscription->trial_end) : null,
                            'ends_at' => null,
                        ]);

                        $this->info("  ✅ Created local subscription record (ID: {$localSubscription->id})");

                        // Calculate and allocate credits
                        $creditsToAllocate = $this->getCreditsForSubscription($stripeSubscription);

                        if ($creditsToAllocate > 0) {
                            $success = $creditService->allocateCredits(
                                $user,
                                $creditsToAllocate,
                                "Créditos retroactivos por suscripción: {$stripeSubscription->id}",
                                $localSubscription
                            );

                            if ($success) {
                                $this->info("  ✅ Allocated {$creditsToAllocate} credits");
                            } else {
                                $this->error("  ❌ Failed to allocate credits");
                            }
                        }

                        Log::info('Retroactively fixed missing subscription', [
                            'user_id' => $user->id,
                            'stripe_customer_id' => $user->stripe_id,
                            'subscription_id' => $stripeSubscription->id,
                            'credits_allocated' => $creditsToAllocate,
                        ]);

                        $fixed++;
                    }
                } else {
                    $this->info("  No active subscriptions found for customer");
                }

            } catch (\Exception $e) {
                $this->error("  ❌ Error processing user {$user->id}: {$e->getMessage()}");
                Log::error('Error fixing missing subscription', [
                    'user_id' => $user->id,
                    'stripe_customer_id' => $user->stripe_id,
                    'error' => $e->getMessage(),
                ]);
                $errors++;
            }
        }

        if ($this->option('dry-run')) {
            $this->info("Dry run completed. Found {$usersWithStripeIds->count()} users that would be processed.");
        } else {
            $this->info("Completed! Fixed {$fixed} subscriptions with {$errors} errors.");
        }

        return Command::SUCCESS;
    }

    /**
     * Get the number of credits to allocate for a subscription.
     */
    protected function getCreditsForSubscription($subscription): int
    {
        // Try to get credits from product metadata
        if (!empty($subscription->items->data)) {
            $firstItem = $subscription->items->data[0];

            try {
                // Retrieve the product to get metadata
                $product = \Stripe\Product::retrieve($firstItem->price->product);

                if (isset($product->metadata['credits'])) {
                    return (int) $product->metadata['credits'];
                }

                // Check price metadata as fallback
                $price = \Stripe\Price::retrieve($firstItem->price->id);
                if (isset($price->metadata['credits'])) {
                    return (int) $price->metadata['credits'];
                }
            } catch (\Exception $e) {
                Log::warning('Failed to retrieve product metadata for credits in fix command', [
                    'subscription_id' => $subscription->id,
                    'product_id' => $firstItem->price->product ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Default: 10 credits for subscriptions
        return 10;
    }
}
