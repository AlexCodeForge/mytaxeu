<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\CreditTransaction;
use App\Notifications\SubscriptionPaymentConfirmation;
use App\Notifications\SaleNotification;
use App\Services\CreditService;
use App\Services\DiscountCodeUsageService;
use App\Services\EmailService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\WebhookReceived;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends WebhookController
{
    /**
     * Handle a Stripe webhook.
     */
    public function handleWebhook(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $secret = config('cashier.webhook.secret');

        if (empty($secret)) {
            Log::error('Stripe webhook secret not configured');
            return response('Webhook secret not configured', 500);
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (SignatureVerificationException $e) {
            Log::warning('Invalid Stripe webhook signature', [
                'error' => $e->getMessage(),
                'payload_excerpt' => substr($payload, 0, 100),
            ]);
            return response('Invalid signature', 400);
        } catch (\Exception $e) {
            Log::error('Error parsing Stripe webhook', [
                'error' => $e->getMessage(),
                'payload_excerpt' => substr($payload, 0, 100),
            ]);
            return response('Invalid payload', 400);
        }

        // Fire the webhook received event
        WebhookReceived::dispatch($event->toArray());

        // Handle the event
        try {
            $this->handleEvent($event);
        } catch (\Exception $e) {
            Log::error('Error processing Stripe webhook', [
                'event_type' => $event->type,
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
            return response('Error processing webhook', 500);
        }

        return response('Webhook handled', 200);
    }

    /**
     * Handle a specific Stripe event.
     */
    protected function handleEvent(Event $event): void
    {
        Log::info('Processing Stripe webhook', [
            'event_type' => $event->type,
            'event_id' => $event->id,
        ]);

        switch ($event->type) {
            case 'customer.subscription.created':
                $this->handleSubscriptionCreated($event);
                break;

            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdated($event);
                break;

            case 'customer.subscription.deleted':
                $this->handleSubscriptionDeleted($event);
                break;

            case 'invoice.payment_succeeded':
                $this->handlePaymentSucceeded($event);
                break;

            case 'invoice.payment_failed':
                $this->handlePaymentFailed($event);
                break;

            default:
                Log::debug('Unhandled Stripe webhook event', [
                    'event_type' => $event->type,
                    'event_id' => $event->id,
                ]);
                break;
        }
    }

    /**
     * Handle subscription created event - allocate initial credits.
     */
    protected function handleSubscriptionCreated(Event $event): void
    {
        $subscription = $event->data->object;
        $stripeCustomerId = $subscription->customer;

        $user = User::where('stripe_id', $stripeCustomerId)->first();
        if (!$user) {
            Log::warning('User not found for subscription created event', [
                'stripe_customer_id' => $stripeCustomerId,
                'subscription_id' => $subscription->id,
            ]);
            return;
        }

        // Create local subscription record if it doesn't exist
        $localSubscription = $user->subscriptions()->where('stripe_id', $subscription->id)->first();

        if (!$localSubscription) {
            $localSubscription = $user->subscriptions()->create([
                'type' => 'default', // or extract from metadata if available
                'stripe_id' => $subscription->id,
                'stripe_status' => $subscription->status,
                'stripe_price' => $subscription->items->data[0]->price->id ?? null,
                'quantity' => $subscription->items->data[0]->quantity ?? 1,
                'trial_ends_at' => $subscription->trial_end ? \Carbon\Carbon::createFromTimestamp($subscription->trial_end) : null,
                'ends_at' => null,
            ]);

            Log::info('Local subscription record created', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'local_subscription_id' => $localSubscription->id,
            ]);
        } else {
            // Update existing subscription if it was created by subscription.updated webhook
            // But only if the new status is better or equal (don't downgrade from active to incomplete)
            $previousStatus = $localSubscription->stripe_status;
            $newStatus = $subscription->status;

            // Define status hierarchy (higher number = better status)
            $statusPriority = [
                'incomplete' => 1,
                'incomplete_expired' => 1,
                'canceled' => 2,
                'unpaid' => 3,
                'past_due' => 4,
                'trialing' => 5,
                'active' => 6,
            ];

            $currentPriority = $statusPriority[$previousStatus] ?? 0;
            $newPriority = $statusPriority[$newStatus] ?? 0;

            if ($newPriority >= $currentPriority) {
                $localSubscription->stripe_status = $newStatus;
                $statusUpdated = true;
            } else {
                $statusUpdated = false;
                Log::info('🚫 Preventing status downgrade', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'current_status' => $previousStatus,
                    'attempted_new_status' => $newStatus,
                    'reason' => 'New status has lower priority',
                ]);
            }

            // Always update price and quantity regardless of status
            $localSubscription->stripe_price = $subscription->items->data[0]->price->id ?? $localSubscription->stripe_price;
            $localSubscription->quantity = $subscription->items->data[0]->quantity ?? $localSubscription->quantity;
            $localSubscription->save();

            Log::info('✅ Existing subscription record processed during creation', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'local_subscription_id' => $localSubscription->id,
                'previous_status' => $previousStatus,
                'new_status' => $statusUpdated ? $newStatus : $previousStatus,
                'status_updated' => $statusUpdated,
            ]);
        }

        // Handle discount code usage if present
        $this->handleDiscountCodeUsage($subscription, $user);

        // Allocate initial credits based on subscription
        $creditService = app(CreditService::class);
        $creditsToAllocate = $this->getCreditsForSubscription($subscription);

        $success = $creditService->allocateCredits(
            $user,
            $creditsToAllocate,
            "Créditos iniciales por suscripción: {$subscription->id}",
            $localSubscription
        );

        Log::info('Subscription created - credits allocated', [
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'credits_allocated' => $creditsToAllocate,
            'success' => $success,
        ]);
    }

    /**
     * Handle subscription updated event.
     */
    protected function handleSubscriptionUpdated(Event $event): void
    {
        $subscription = $event->data->object;

        Log::info('🔄 Subscription updated', [
            'subscription_id' => $subscription->id,
            'status' => $subscription->status,
            'customer_id' => $subscription->customer,
        ]);

        // Find the user by Stripe customer ID
        $user = User::where('stripe_id', $subscription->customer)->first();
        if (!$user) {
            Log::warning('❌ User not found for subscription update', [
                'customer_id' => $subscription->customer,
                'subscription_id' => $subscription->id,
            ]);
            return;
        }

        // Find the local subscription
        $localSubscription = $user->subscriptions()
            ->where('stripe_id', $subscription->id)
            ->first();

        if (!$localSubscription) {
            Log::warning('❌ Local subscription not found for update, creating it now', [
                'user_id' => $user->id,
                'stripe_subscription_id' => $subscription->id,
            ]);

            // Create the local subscription record since it doesn't exist yet
            // This handles the case where subscription.updated webhook arrives before subscription.created
            $localSubscription = $user->subscriptions()->create([
                'type' => 'default',
                'stripe_id' => $subscription->id,
                'stripe_status' => $subscription->status,
                'stripe_price' => $subscription->items->data[0]->price->id ?? null,
                'quantity' => $subscription->items->data[0]->quantity ?? 1,
                'trial_ends_at' => $subscription->trial_end ? \Carbon\Carbon::createFromTimestamp($subscription->trial_end) : null,
                'ends_at' => null,
            ]);

            Log::info('✅ Local subscription record created during update', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'local_subscription_id' => $localSubscription->id,
                'initial_status' => $subscription->status,
            ]);
        }

        // Track what changed
        $previousStatus = $localSubscription->stripe_status;
        $newStatus = $subscription->status;

        // Define status hierarchy (higher number = better status)
        $statusPriority = [
            'incomplete' => 1,
            'incomplete_expired' => 1,
            'canceled' => 2,
            'unpaid' => 3,
            'past_due' => 4,
            'trialing' => 5,
            'active' => 6,
        ];

        $currentPriority = $statusPriority[$previousStatus] ?? 0;
        $newPriority = $statusPriority[$newStatus] ?? 0;

        // Only update status if it's better or equal (prevent downgrades)
        if ($newPriority >= $currentPriority) {
            $localSubscription->stripe_status = $newStatus;
            $statusUpdated = true;
        } else {
            $statusUpdated = false;
            Log::info('🚫 Preventing status downgrade in update', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'current_status' => $previousStatus,
                'attempted_new_status' => $newStatus,
                'reason' => 'New status has lower priority',
            ]);
        }

        $localSubscription->quantity = $subscription->quantity ?? 1;

        // Update period information
        if (isset($subscription->current_period_start)) {
            $localSubscription->trial_ends_at = $subscription->current_period_start
                ? Carbon::createFromTimestamp($subscription->current_period_start)
                : null;
        }

        if (isset($subscription->current_period_end)) {
            $localSubscription->ends_at = $subscription->cancel_at_period_end && $subscription->current_period_end
                ? Carbon::createFromTimestamp($subscription->current_period_end)
                : null;
        }

        $localSubscription->save();

        Log::info('✅ Local subscription status processed', [
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'previous_status' => $previousStatus,
            'attempted_status' => $newStatus,
            'final_status' => $localSubscription->stripe_status,
            'status_updated' => $statusUpdated,
            'status_changed' => $statusUpdated && ($previousStatus !== $newStatus),
        ]);

        // If subscription became active from incomplete, log special success message
        if ($statusUpdated && $previousStatus === 'incomplete' && $newStatus === 'active') {
            Log::info('🎉 Subscription activated successfully', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'subscription_id' => $subscription->id,
            ]);
        }
    }

    /**
     * Handle subscription deleted event.
     */
    protected function handleSubscriptionDeleted(Event $event): void
    {
        $subscription = $event->data->object;

        Log::info('Subscription cancelled', [
            'subscription_id' => $subscription->id,
            'customer_id' => $subscription->customer,
        ]);

        // Additional logic for handling subscription cancellation
        // Could include sending notification emails, updating user status, etc.
    }

    /**
     * Handle successful payment event - allocate monthly credits.
     */
    protected function handlePaymentSucceeded(Event $event): void
    {
        $invoice = $event->data->object;
        $stripeCustomerId = $invoice->customer;

        // Skip if this is not a subscription invoice
        if (!$invoice->subscription) {
            Log::debug('Skipping non-subscription invoice', [
                'invoice_id' => $invoice->id,
                'customer_id' => $stripeCustomerId,
            ]);
            return;
        }

        $user = User::where('stripe_id', $stripeCustomerId)->first();
        if (!$user) {
            Log::warning('User not found for payment succeeded event', [
                'stripe_customer_id' => $stripeCustomerId,
                'invoice_id' => $invoice->id,
            ]);
            return;
        }

        // Find or create local subscription record
        $localSubscription = $user->subscriptions()->where('stripe_id', $invoice->subscription)->first();

        if (!$localSubscription) {
            Log::warning('Local subscription not found for payment, creating it', [
                'user_id' => $user->id,
                'subscription_id' => $invoice->subscription,
                'invoice_id' => $invoice->id,
            ]);

            // Try to fetch subscription from Stripe to create local record
            try {
                $stripeSubscription = \Stripe\Subscription::retrieve($invoice->subscription);
                $localSubscription = $user->subscriptions()->create([
                    'type' => 'default',
                    'stripe_id' => $stripeSubscription->id,
                    'stripe_status' => $stripeSubscription->status,
                    'stripe_price' => $stripeSubscription->items->data[0]->price->id ?? null,
                    'quantity' => $stripeSubscription->items->data[0]->quantity ?? 1,
                    'trial_ends_at' => $stripeSubscription->trial_end ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->trial_end) : null,
                    'ends_at' => null,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to create local subscription record from Stripe', [
                    'error' => $e->getMessage(),
                    'subscription_id' => $invoice->subscription,
                ]);
                return;
            }
        }

        // Skip initial invoice for new subscriptions (credits are allocated in handleSubscriptionCreated)
        if ($invoice->billing_reason === 'subscription_create') {
            Log::info('Skipping initial subscription invoice - credits already allocated', [
                'user_id' => $user->id,
                'invoice_id' => $invoice->id,
                'subscription_id' => $invoice->subscription,
            ]);
            return;
        }

        // Record the actual revenue transaction for financial reporting
        $revenueAmount = $invoice->amount_paid; // This is in cents

        // Create revenue transaction for financial dashboard
        CreditTransaction::create([
            'user_id' => $user->id,
            'type' => 'purchased',
            'amount' => $revenueAmount, // Store actual payment amount in cents
            'description' => "Pago de suscripción: {$invoice->id} (€" . number_format($revenueAmount / 100, 2) . ")",
            'subscription_id' => $localSubscription->id,
        ]);

        // Allocate monthly credits for recurring payments
        $creditService = app(CreditService::class);
        $creditsToAllocate = $this->getCreditsForInvoice($invoice);

        $success = $creditService->allocateCredits(
            $user,
            $creditsToAllocate,
            "Créditos mensuales por pago exitoso: {$invoice->id}",
            $localSubscription
        );

        Log::info('Payment succeeded - revenue recorded and credits allocated', [
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'subscription_id' => $invoice->subscription,
            'billing_reason' => $invoice->billing_reason,
            'revenue_amount_cents' => $revenueAmount,
            'revenue_amount_eur' => $revenueAmount / 100,
            'credits_allocated' => $creditsToAllocate,
            'success' => $success,
        ]);

        // Send payment confirmation email if credits were successfully allocated
        if ($success) {
            $this->sendPaymentConfirmationEmail($user, $invoice, $localSubscription, $creditsToAllocate);
            $this->sendSaleNotificationToAdmins($user, $invoice, $localSubscription);
        }
    }

    /**
     * Handle failed payment event.
     */
    protected function handlePaymentFailed(Event $event): void
    {
        $invoice = $event->data->object;

        Log::warning('Payment failed', [
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer,
            'amount' => $invoice->amount_due,
        ]);

        // Additional logic for handling failed payments
        // Could include sending notification emails, suspending accounts, etc.
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
                Log::warning('Failed to retrieve product metadata for credits', [
                    'subscription_id' => $subscription->id,
                    'product_id' => $firstItem->price->product ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Default: 10 credits for new subscriptions
        Log::info('Using default credits for subscription', [
            'subscription_id' => $subscription->id,
            'credits' => 10,
        ]);

        return 10;
    }

    /**
     * Get the number of credits to allocate for an invoice.
     */
    protected function getCreditsForInvoice($invoice): int
    {
        // Try to get credits from the subscription items in the invoice
        if (!empty($invoice->lines->data)) {
            foreach ($invoice->lines->data as $line) {
                if ($line->type === 'subscription') {
                    try {
                        // Retrieve the product to get metadata
                        $product = \Stripe\Product::retrieve($line->price->product);

                        if (isset($product->metadata['credits'])) {
                            return (int) $product->metadata['credits'];
                        }

                        // Check price metadata as fallback
                        $price = \Stripe\Price::retrieve($line->price->id);
                        if (isset($price->metadata['credits'])) {
                            return (int) $price->metadata['credits'];
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to retrieve product metadata for invoice credits', [
                            'invoice_id' => $invoice->id,
                            'product_id' => $line->price->product ?? 'unknown',
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        // Default: Calculate credits based on payment amount
        // For example: 1 EUR = 10 credits, so 500 EUR = 5000 credits
        $paymentAmount = $invoice->amount_paid / 100; // Convert cents to euros
        $defaultCredits = (int) ($paymentAmount * 10); // 10 credits per euro

        Log::info('Using default credits calculation based on payment amount', [
            'invoice_id' => $invoice->id,
            'payment_amount_eur' => $paymentAmount,
            'credits' => $defaultCredits,
        ]);

        return $defaultCredits;
    }

    /**
     * Send payment confirmation email to user
     */
    protected function sendPaymentConfirmationEmail($user, $invoice, $subscription, int $creditsAllocated): void
    {
        try {
            // Check if subscription payment emails are enabled
            if (!config('emails.features.subscription_emails', true)) {
                Log::debug('Subscription payment emails are disabled, skipping notification', [
                    'user_id' => $user->id,
                    'invoice_id' => $invoice->id,
                ]);
                return;
            }

            // Prepare payment data
            $paymentData = [
                'amount' => $invoice->amount_paid / 100, // Convert from cents
                'currency' => strtoupper($invoice->currency),
                'paid_at' => Carbon::createFromTimestamp($invoice->status_transitions->paid_at ?? time()),
                'transaction_id' => $invoice->id,
                'stripe_payment_intent_id' => $invoice->payment_intent ?? null,
                'billing_reason' => $invoice->billing_reason,
            ];

            // Prepare subscription data
            $subscriptionData = [
                'plan_name' => $this->determinePlanName($invoice),
                'subscription_id' => $subscription->stripe_id,
                'next_billing_date' => $this->calculateNextBillingDate($subscription),
            ];

            // Prepare credits data
            $creditService = app(CreditService::class);
            $currentBalance = $creditService->getUserCredits($user);

            $creditsData = [
                'amount' => $creditsAllocated,
                'total_balance' => $currentBalance,
                'files_processable' => intval($currentBalance / 10), // Assuming 10 credits per file
            ];

            // Send the notification
            $user->notify(new SubscriptionPaymentConfirmation(
                $paymentData,
                $subscriptionData,
                $creditsData
            ));

            Log::info('Payment confirmation email sent', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'invoice_id' => $invoice->id,
                'credits_allocated' => $creditsAllocated,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send payment confirmation email', [
                'user_id' => $user->id,
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Determine plan name from invoice
     */
    protected function determinePlanName($invoice): string
    {
        try {
            if (!empty($invoice->lines->data)) {
                foreach ($invoice->lines->data as $line) {
                    if ($line->type === 'subscription' && isset($line->price->nickname)) {
                        return $line->price->nickname;
                    }
                }
            }

            // Fallback based on amount
            $amount = $invoice->amount_paid / 100;
            if ($amount <= 30) {
                return 'Plan Starter';
            } elseif ($amount <= 150) {
                return 'Plan Business';
            } else {
                return 'Plan Enterprise';
            }
        } catch (\Exception $e) {
            Log::warning('Could not determine plan name from invoice', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
        }

        return 'Plan MyTaxEU';
    }

    /**
     * Calculate next billing date for subscription
     */
    protected function calculateNextBillingDate($subscription): string
    {
        try {
            // For active subscriptions, add one month to current period end
            if ($subscription->trial_ends_at && $subscription->trial_ends_at->isFuture()) {
                return $subscription->trial_ends_at->format('Y-m-d');
            }

            // Estimate next billing date (in production, you'd fetch from Stripe)
            return Carbon::now()->addMonth()->format('Y-m-d');
        } catch (\Exception $e) {
            Log::debug('Could not calculate next billing date', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
            return Carbon::now()->addMonth()->format('Y-m-d');
        }
    }

    /**
     * Send sale notification to admin users
     */
    protected function sendSaleNotificationToAdmins($user, $invoice, $subscription): void
    {
        try {
            // Check if admin sale notifications are enabled
            if (!config('emails.features.admin_notifications', true)) {
                Log::debug('Admin notifications are disabled, skipping sale notification', [
                    'user_id' => $user->id,
                    'invoice_id' => $invoice->id,
                ]);
                return;
            }

            // Prepare customer data
            $customerData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
                'country' => $user->country ?? null,
            ];

            // Prepare sale data
            $saleData = [
                'amount' => $invoice->amount_paid / 100, // Convert from cents
                'currency' => strtoupper($invoice->currency),
                'plan_name' => $this->determinePlanName($invoice),
                'transaction_id' => $invoice->id,
                'stripe_payment_intent_id' => $invoice->payment_intent ?? null,
                'billing_cycle' => $invoice->billing_reason === 'subscription_create' ? 'Nuevo' : 'Renovación',
                'created_at' => Carbon::createFromTimestamp($invoice->created),
                'is_first_purchase' => $this->isFirstPurchase($user),
                'discount_applied' => $invoice->discount ? true : false,
                'discount_amount' => $invoice->discount ? ($invoice->discount->amount ?? 0) / 100 : 0,
                'coupon_code' => $invoice->discount->coupon->id ?? null,
            ];

            // Prepare revenue data (simplified for now)
            $revenueData = [
                'today_sales' => $this->getTodaySalesCount(),
                'today_revenue' => $this->getTodayRevenue(),
                'month_sales' => $this->getMonthSalesCount(),
                'month_revenue' => $this->getMonthRevenue(),
                'avg_transaction_value' => $this->getAverageTransactionValue(),
            ];

            // Send to admin email service
            $emailService = app(EmailService::class);
            $emailService->sendToAdmins(
                '💰 Nueva Venta Realizada - MyTaxEU',
                'emails.admin.sale-notification',
                [
                    'customer' => $customerData,
                    'sale' => $saleData,
                    'revenue' => $revenueData,
                ]
            );

            Log::info('Sale notification sent to admins', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'invoice_id' => $invoice->id,
                'sale_amount' => $saleData['amount'],
                'plan_name' => $saleData['plan_name'],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send sale notification to admins', [
                'user_id' => $user->id,
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Check if this is the user's first purchase
     */
    protected function isFirstPurchase($user): bool
    {
        try {
            // Count previous successful payments
            $previousPayments = $user->subscriptions()
                ->where('stripe_status', 'active')
                ->orWhere('stripe_status', 'canceled')
                ->count();

            return $previousPayments <= 1; // Current subscription is first
        } catch (\Exception $e) {
            Log::debug('Could not determine if first purchase', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get today's sales count (simplified implementation)
     */
    protected function getTodaySalesCount(): int
    {
        try {
            return User::whereHas('subscriptions', function ($query) {
                $query->where('created_at', '>=', Carbon::today());
            })->count();
        } catch (\Exception $e) {
            Log::debug('Could not get today sales count', ['error' => $e->getMessage()]);
            return 1;
        }
    }

    /**
     * Get today's revenue (simplified implementation)
     */
    protected function getTodayRevenue(): float
    {
        // This would integrate with your actual revenue tracking
        // For now, return a placeholder
        return 125.0;
    }

    /**
     * Get month's sales count (simplified implementation)
     */
    protected function getMonthSalesCount(): int
    {
        try {
            return User::whereHas('subscriptions', function ($query) {
                $query->where('created_at', '>=', Carbon::now()->startOfMonth());
            })->count();
        } catch (\Exception $e) {
            Log::debug('Could not get month sales count', ['error' => $e->getMessage()]);
            return 1;
        }
    }

    /**
     * Get month's revenue (simplified implementation)
     */
    protected function getMonthRevenue(): float
    {
        // This would integrate with your actual revenue tracking
        // For now, return a placeholder
        return 2500.0;
    }

    /**
     * Get average transaction value (simplified implementation)
     */
    protected function getAverageTransactionValue(): float
    {
        // This would calculate actual average from your data
        // For now, return a placeholder
        return 125.0;
    }

    /**
     * Handle discount code usage when a subscription is created.
     */
    protected function handleDiscountCodeUsage($subscription, User $user): void
    {
        try {
            // Check if there's a discount on the subscription
            if (empty($subscription->discount)) {
                return;
            }

            $discount = $subscription->discount;
            $coupon = $discount->coupon;

            if (!$coupon) {
                return;
            }

            // Try to find our local discount code by Stripe coupon ID
            $discountCode = \App\Models\DiscountCode::where('stripe_coupon_id', $coupon->id)->first();

            if (!$discountCode) {
                Log::warning('Discount code not found for Stripe coupon', [
                    'stripe_coupon_id' => $coupon->id,
                    'subscription_id' => $subscription->id,
                ]);
                return;
            }

            // Get subscription plan info
            $priceId = $subscription->items->data[0]->price->id ?? null;
            $amount = ($subscription->items->data[0]->price->unit_amount ?? 0) / 100; // Convert from cents

            // Try to find the subscription plan
            $plan = SubscriptionPlan::where('stripe_monthly_price_id', $priceId)->first();

            if (!$plan) {
                Log::warning('Subscription plan not found for price ID', [
                    'price_id' => $priceId,
                    'subscription_id' => $subscription->id,
                ]);
                return;
            }

            // Calculate discount amount
            $discountAmount = 0;
            if ($coupon->percent_off) {
                $discountAmount = $amount * ($coupon->percent_off / 100);
            } elseif ($coupon->amount_off) {
                $discountAmount = ($coupon->amount_off / 100); // Convert from cents
            }

            // Record the usage
            $usageService = app(DiscountCodeUsageService::class);
            $usage = $usageService->recordUsage(
                $discountCode->code,
                $user,
                $plan,
                $amount,
                $discountAmount,
                $subscription->id,
                $coupon->id
            );

            if ($usage) {
                Log::info('Discount code usage recorded from webhook', [
                    'usage_id' => $usage->id,
                    'code' => $discountCode->code,
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'discount_amount' => $discountAmount,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error handling discount code usage from webhook', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscription->id ?? null,
                'user_id' => $user->id,
            ]);
        }
    }
}
