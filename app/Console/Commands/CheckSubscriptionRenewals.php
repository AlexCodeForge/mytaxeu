<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\SubscriptionRenewalReminder;
use App\Services\CreditService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Subscription;

class CheckSubscriptionRenewals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:check-renewals
                           {--days=7 : Number of days before renewal to send reminder}
                           {--dry-run : Show what would be done without sending emails}
                           {--force : Force send even if feature is disabled}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for upcoming subscription renewals and send reminder emails';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!config('emails.features.subscription_emails', true) && !$this->option('force')) {
            $this->warn('Subscription emails are disabled. Use --force to override.');
            return 1;
        }

        $daysUntilRenewal = (int) $this->option('days');
        $isDryRun = $this->option('dry-run');

        $this->info("Checking for subscriptions renewing in {$daysUntilRenewal} days...");

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No emails will be sent');
        }

        $targetDate = Carbon::now()->addDays($daysUntilRenewal)->startOfDay();
        $endDate = $targetDate->copy()->endOfDay();

        // Get active subscriptions that renew on the target date
        $subscriptions = Subscription::query()
            ->where('stripe_status', 'active')
            ->whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [$targetDate, $endDate])
            ->orWhere(function ($query) use ($targetDate, $endDate) {
                // For recurring subscriptions, calculate next billing date
                $query->whereNull('trial_ends_at')
                      ->whereNotNull('created_at');
                // This is a simplified approach - in production you'd want to store next_billing_date
            })
            ->with('user')
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->info('No subscriptions found renewing on the target date.');
            return 0;
        }

        $this->info("Found {$subscriptions->count()} subscription(s) to process");

        $successCount = 0;
        $errorCount = 0;
        $creditService = app(CreditService::class);

        foreach ($subscriptions as $subscription) {
            $user = $subscription->user;

            if (!$user) {
                $this->error("Subscription {$subscription->id} has no associated user");
                $errorCount++;
                continue;
            }

            try {
                // Gather subscription data
                $subscriptionData = $this->getSubscriptionData($subscription);

                // Gather usage statistics
                $usageStats = $this->getUsageStats($user, $creditService);

                if ($isDryRun) {
                    $this->line("Would send renewal reminder to: {$user->email} ({$user->name})");
                    $this->line("  Plan: {$subscriptionData['plan_name']}");
                    $this->line("  Amount: {$subscriptionData['amount']} {$subscriptionData['currency']}");
                    $this->line("  Files processed: {$usageStats['files_processed']}");
                } else {
                    // Send the notification
                    $user->notify(new SubscriptionRenewalReminder(
                        $subscriptionData,
                        $usageStats,
                        $daysUntilRenewal
                    ));

                    $this->info("✓ Sent renewal reminder to {$user->email}");

                    Log::info('Subscription renewal reminder sent', [
                        'user_id' => $user->id,
                        'user_email' => $user->email,
                        'subscription_id' => $subscription->id,
                        'days_until_renewal' => $daysUntilRenewal,
                    ]);
                }

                $successCount++;

            } catch (\Exception $e) {
                $this->error("✗ Failed to process renewal reminder for {$user->email}: {$e->getMessage()}");

                Log::error('Failed to send subscription renewal reminder', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $errorCount++;
            }
        }

        // Summary
        $this->newLine();
        if ($isDryRun) {
            $this->info("DRY RUN COMPLETE:");
        } else {
            $this->info("RENEWAL REMINDERS COMPLETE:");
        }
        $this->line("  ✓ Success: {$successCount}");
        if ($errorCount > 0) {
            $this->line("  ✗ Errors: {$errorCount}");
        }

        return $errorCount > 0 ? 1 : 0;
    }

    /**
     * Get subscription data for email template
     */
    protected function getSubscriptionData(Subscription $subscription): array
    {
        // Get plan details from Stripe
        $stripePlan = null;
        try {
            if ($subscription->stripe_price) {
                $stripePlan = $subscription->asStripeSubscription()->items->data[0]->price ?? null;
            }
        } catch (\Exception $e) {
            Log::warning('Could not fetch Stripe plan details', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'plan_name' => $this->determinePlanName($subscription, $stripePlan),
            'amount' => $this->determinePlanAmount($subscription, $stripePlan),
            'currency' => $stripePlan->currency ?? 'EUR',
            'next_billing_date' => $this->calculateNextBillingDate($subscription),
            'payment_method' => $this->getPaymentMethodInfo($subscription),
        ];
    }

    /**
     * Get usage statistics for the current billing period
     */
    protected function getUsageStats(User $user, CreditService $creditService): array
    {
        // Calculate current billing period (approximation)
        $billingPeriodStart = Carbon::now()->startOfMonth();
        $billingPeriodEnd = Carbon::now()->endOfMonth();

        // Get upload/processing statistics
        $uploadsThisPeriod = $user->uploads()
            ->whereBetween('created_at', [$billingPeriodStart, $billingPeriodEnd])
            ->count();

        // Calculate credits used this period
        $creditsUsedThisPeriod = $user->creditTransactions()
            ->where('type', 'deduction')
            ->whereBetween('created_at', [$billingPeriodStart, $billingPeriodEnd])
            ->sum('amount');

        // Estimate time saved (8 hours per file processed is mentioned in landing page)
        $timeSavedHours = $uploadsThisPeriod * 8;

        // Estimate cost savings (600€ per month mentioned in landing page)
        $costSavings = $uploadsThisPeriod * 75; // Rough estimate per file

        return [
            'files_processed' => $uploadsThisPeriod,
            'credits_used' => abs($creditsUsedThisPeriod), // Make positive for display
            'time_saved_hours' => $timeSavedHours,
            'cost_savings' => $costSavings,
        ];
    }

    /**
     * Determine plan name from subscription
     */
    protected function determinePlanName(Subscription $subscription, $stripePlan = null): string
    {
        if ($stripePlan && isset($stripePlan->nickname)) {
            return $stripePlan->nickname;
        }

        // Fallback based on price/amount
        if ($stripePlan && isset($stripePlan->unit_amount)) {
            $amount = $stripePlan->unit_amount / 100; // Convert from cents

            if ($amount <= 30) {
                return 'Plan Starter';
            } elseif ($amount <= 150) {
                return 'Plan Business';
            } else {
                return 'Plan Enterprise';
            }
        }

        return 'Plan MyTaxEU';
    }

    /**
     * Determine plan amount from subscription
     */
    protected function determinePlanAmount(Subscription $subscription, $stripePlan = null): float
    {
        if ($stripePlan && isset($stripePlan->unit_amount)) {
            return $stripePlan->unit_amount / 100; // Convert from cents
        }

        return 0.0;
    }

    /**
     * Calculate next billing date
     */
    protected function calculateNextBillingDate(Subscription $subscription): string
    {
        if ($subscription->trial_ends_at) {
            return $subscription->trial_ends_at->format('Y-m-d');
        }

        // For active subscriptions, approximate next billing date
        // In production, you'd store this or fetch from Stripe
        return Carbon::now()->addMonth()->format('Y-m-d');
    }

    /**
     * Get payment method information
     */
    protected function getPaymentMethodInfo(Subscription $subscription): string
    {
        try {
            $user = $subscription->user;
            $paymentMethods = $user->paymentMethods();

            if ($paymentMethods->isNotEmpty()) {
                $pm = $paymentMethods->first();
                if ($pm->card) {
                    return "Tarjeta terminada en {$pm->card->last4}";
                }
            }
        } catch (\Exception $e) {
            Log::debug('Could not fetch payment method info', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }

        return 'Método de pago configurado';
    }
}


