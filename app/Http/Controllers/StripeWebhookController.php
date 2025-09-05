<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CreditService;
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
        WebhookReceived::dispatch($event);

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
        }

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

        Log::info('Subscription updated', [
            'subscription_id' => $subscription->id,
            'status' => $subscription->status,
        ]);

        // Handle plan changes, status updates, etc.
        // Additional logic can be added here for handling plan upgrades/downgrades
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

        // Allocate monthly credits for recurring payments
        $creditService = app(CreditService::class);
        $creditsToAllocate = $this->getCreditsForInvoice($invoice);

        $success = $creditService->allocateCredits(
            $user,
            $creditsToAllocate,
            "Créditos mensuales por pago exitoso: {$invoice->id}",
            $localSubscription
        );

        Log::info('Payment succeeded - credits allocated', [
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'subscription_id' => $invoice->subscription,
            'billing_reason' => $invoice->billing_reason,
            'credits_allocated' => $creditsToAllocate,
            'success' => $success,
        ]);
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

        // Default: 10 credits per payment
        Log::info('Using default credits for invoice', [
            'invoice_id' => $invoice->id,
            'credits' => 10,
        ]);

        return 10;
    }
}
