<?php

declare(strict_types=1);

namespace App\Livewire\Billing;

use App\Models\AdminSetting;
use App\Models\SubscriptionPlan;
use App\Services\CreditService;
use App\Services\StripeConfigurationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class SubscriptionManager extends Component
{
    use AuthorizesRequests;

    public bool $loading = false;
    public ?array $currentSubscription = null;
    public array $availablePlans = [];
    public bool $willRenew = true;
    public ?string $currentPlanId = null;

    protected array $plans = [];

    public function mount(): void
    {
        $this->loadPlans();
        $this->loadCurrentSubscription();
    }

    /**
     * Load subscription plans from database
     */
    protected function loadPlans(): void
    {
        $plans = SubscriptionPlan::getActivePlans();

        $this->plans = $plans->map(function ($plan) {
            return [
                'id' => $plan->slug,
                'name' => $plan->name,
                'description' => $plan->description ?? '',
                'credits' => $plan->max_alerts_per_month,
                'price' => $plan->monthly_price ?? 0,
                'currency' => 'EUR',
                'interval' => 'month',
                'features' => $plan->features ?? [],
                'stripe_price_id' => $plan->stripe_monthly_price_id,
                'is_featured' => $plan->is_featured,
            ];
        })->toArray();

        $this->availablePlans = $this->plans;
    }

    public function loadCurrentSubscription(): void
    {
        $user = auth()->user();

        if ($user->subscribed()) {
            $subscription = $user->subscription();
            $stripeSubscription = $subscription->asStripeSubscription();

            // Determine current plan ID by matching Stripe price ID or credits
            $this->currentPlanId = $this->getCurrentPlanId($stripeSubscription);

            $currentPlan = collect($this->plans)->firstWhere('id', $this->currentPlanId);

            $this->currentSubscription = [
                'name' => $currentPlan['name'] ?? $subscription->type ?? 'Suscripción Activa',
                'status' => $subscription->stripe_status,
                'current_period_end' => $stripeSubscription->current_period_end,
                'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end ?? false,
                'stripe_id' => $subscription->stripe_id,
                'plan_id' => $this->currentPlanId,
            ];

            $this->willRenew = !$this->currentSubscription['cancel_at_period_end'];
        } else {
            $this->currentSubscription = null;
            $this->currentPlanId = null;
        }
    }

    /**
     * Determine which plan the user currently has based on their Stripe subscription.
     */
    protected function getCurrentPlanId($stripeSubscription): ?string
    {
        if (empty($stripeSubscription->items->data)) {
            return null;
        }

        $subscriptionItem = $stripeSubscription->items->data[0];
        $priceId = $subscriptionItem->price->id;
        $amount = $subscriptionItem->price->unit_amount; // Amount in cents

        // First, try to match by Stripe price ID (if we have real Stripe price IDs)
        foreach ($this->plans as $plan) {
            if (isset($plan['stripe_price_id']) && $plan['stripe_price_id'] === $priceId) {
                return $plan['id'];
            }
        }

        // Fallback: match by price amount (convert our price to cents)
        foreach ($this->plans as $plan) {
            if ((int)($plan['price'] * 100) === $amount) {
                return $plan['id'];
            }
        }

        // Fallback: try to get plan_id from product metadata
        try {
            $product = \Stripe\Product::retrieve($subscriptionItem->price->product);
            if (isset($product->metadata['plan_id'])) {
                return $product->metadata['plan_id'];
            }
        } catch (\Exception $e) {
            // Silently continue if we can't retrieve product metadata
        }

        // Default fallback based on credit amount if available in metadata
        try {
            $product = \Stripe\Product::retrieve($subscriptionItem->price->product);
            if (isset($product->metadata['credits'])) {
                $credits = (int) $product->metadata['credits'];
                foreach ($this->plans as $plan) {
                    if ($plan['credits'] === $credits) {
                        return $plan['id'];
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently continue if we can't retrieve product metadata
        }

        return null; // Couldn't determine plan
    }

    public function subscribe(string $planId): void
    {
        $this->loading = true;

        try {
            $plan = collect($this->plans)->firstWhere('id', $planId);

            if (!$plan) {
                session()->flash('error', 'Plan no encontrado.');
                return;
            }

            // Check if user is trying to subscribe to the same plan they already have
            if ($this->currentPlanId === $planId) {
                session()->flash('error', 'Ya tienes este plan activo. No puedes suscribirte al mismo plan nuevamente.');
                return;
            }

            // Check if Stripe is configured
            $stripeService = app(StripeConfigurationService::class);
            if (!$stripeService->isConfigured()) {
                session()->flash('error', 'El sistema de pagos no está configurado. Contacte al administrador.');
                return;
            }

            $stripeConfig = AdminSetting::getStripeConfig();
            Stripe::setApiKey($stripeConfig['secret_key']);

            $user = auth()->user();

            // Create Stripe customer if not exists
            if (!$user->hasStripeId()) {
                $user->createAsStripeCustomer([
                    'name' => $user->name,
                    'email' => $user->email,
                ]);
            }

            // For demo purposes, we'll create a checkout session with a test product
            // In production, you would use the actual Stripe price IDs from your products
            $checkoutSession = Session::create([
                'customer' => $user->stripe_id,
                'payment_method_types' => ['card'],
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => 'eur',
                            'unit_amount' => (int)($plan['price'] * 100), // Convert to cents
                            'recurring' => [
                                'interval' => $plan['interval'],
                            ],
                            'product_data' => [
                                'name' => $plan['name'],
                                'description' => $plan['description'],
                                'metadata' => [
                                    'credits' => $plan['credits'],
                                    'plan_id' => $plan['id'],
                                ],
                            ],
                        ],
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'subscription',
                'success_url' => route('dashboard') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('billing.subscriptions'),
                'metadata' => [
                    'user_id' => $user->id,
                    'plan_id' => $planId,
                ],
            ]);

            // Redirect to Stripe Checkout
            $this->redirect($checkoutSession->url);

        } catch (ApiErrorException $e) {
            session()->flash('error', 'Error de Stripe: ' . $e->getMessage());
        } catch (\Exception $e) {
            session()->flash('error', 'Error inesperado: ' . $e->getMessage());
        } finally {
            $this->loading = false;
        }
    }

    public function updateRenewalPreference(): void
    {
        $this->loading = true;

        try {
            $user = auth()->user();

            if (!$user->subscribed()) {
                session()->flash('error', 'No tienes una suscripción activa.');
                return;
            }

            $subscription = $user->subscription();

            if ($this->willRenew) {
                // Resume subscription
                $subscription->resume();
                session()->flash('message', 'Tu suscripción se renovará automáticamente.');
            } else {
                // Cancel at period end
                $subscription->cancelAt($subscription->asStripeSubscription()->current_period_end);
                session()->flash('message', 'Tu suscripción se cancelará al final del período actual.');
            }

            $this->loadCurrentSubscription();

        } catch (\Exception $e) {
            session()->flash('error', 'Error al actualizar preferencias: ' . $e->getMessage());
        } finally {
            $this->loading = false;
        }
    }

    public function cancelSubscription(): void
    {
        $this->loading = true;

        try {
            $user = auth()->user();

            if (!$user->subscribed()) {
                session()->flash('error', 'No tienes una suscripción activa.');
                return;
            }

            $user->subscription()->cancelNow();
            $this->loadCurrentSubscription();

            session()->flash('message', 'Tu suscripción ha sido cancelada.');

        } catch (\Exception $e) {
            session()->flash('error', 'Error al cancelar la suscripción: ' . $e->getMessage());
        } finally {
            $this->loading = false;
        }
    }

    public function viewBillingPortal(): void
    {
        try {
            $user = auth()->user();

            if (!$user->hasStripeId()) {
                session()->flash('error', 'No hay información de facturación disponible.');
                return;
            }

            $this->redirect($user->billingPortalUrl(route('billing.subscriptions')));

        } catch (\Exception $e) {
            session()->flash('error', 'Error al acceder al portal de facturación: ' . $e->getMessage());
        }
    }

    public function getCurrentCreditsProperty(): int
    {
        $creditService = app(CreditService::class);
        return $creditService->getCreditBalance(auth()->user());
    }

    /**
     * Check if a plan is the user's current plan.
     */
    public function isCurrentPlan(string $planId): bool
    {
        return $this->currentPlanId === $planId;
    }

    /**
     * Get button text for a plan based on whether it's current or not.
     */
    public function getPlanButtonText(string $planId): string
    {
        if ($this->isCurrentPlan($planId)) {
            return 'Plan Actual';
        }

        return 'Cambiar a este plan';
    }

    /**
     * Check if a plan button should be disabled.
     */
    public function isPlanButtonDisabled(string $planId): bool
    {
        return $this->isCurrentPlan($planId) || $this->loading;
    }

    public function render()
    {
        return view('livewire.billing.subscription-manager')->layout('layouts.panel');
    }
}
