<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CreditService;
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

        // Allocate initial credits based on subscription
        $creditService = app(CreditService::class);
        $creditsToAllocate = $this->getCreditsForSubscription($subscription);

        $success = $creditService->allocateCredits(
            $user,
            $creditsToAllocate,
            "Créditos iniciales por suscripción: {$subscription->id}",
            $user->subscriptions()->where('stripe_id', $subscription->id)->first()
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

        // Allocate monthly credits
        $creditService = app(CreditService::class);
        $creditsToAllocate = $this->getCreditsForInvoice($invoice);

        $success = $creditService->allocateCredits(
            $user,
            $creditsToAllocate,
            "Créditos mensuales por pago exitoso: {$invoice->id}",
            $user->subscriptions()->where('stripe_id', $invoice->subscription)->first()
        );

        Log::info('Payment succeeded - credits allocated', [
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
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
        // Default: 10 credits for new subscriptions
        // This can be made configurable based on plan or price ID
        return 10;
    }

    /**
     * Get the number of credits to allocate for an invoice.
     */
    protected function getCreditsForInvoice($invoice): int
    {
        // Default: 10 credits per monthly payment
        // This can be made configurable based on plan or price ID
        return 10;
    }
}
