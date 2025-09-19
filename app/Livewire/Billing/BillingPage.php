<?php

declare(strict_types=1);

namespace App\Livewire\Billing;

use App\Models\SubscriptionPlan;
use App\Services\StripePortalService;
use Illuminate\View\View;
use Laravel\Cashier\Subscription;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BillingPage extends Component
{
    public bool $hasActiveSubscription = false;
    public ?array $subscriptionDetails = null;
    public int $currentCredits = 0;
    public ?int $trialDaysRemaining = null;
    public bool $loading = false;
    public string $subscriptionStatus = 'none';
    public string $currentPlanName = 'Plan Gratuito';
    public string $statusMessage = '';
    public ?string $nextBillingDate = null;
    public array $planFeatures = [];
    public bool $showManageButton = false;
    public bool $showUpgradeCta = true;

    // New properties from SubscriptionManager
    public ?array $currentSubscription = null;
    public array $plans = [];
    public ?string $currentPlanId = null;
    public bool $willRenew = true;

    protected array $availablePlans = [];

    public function mount(): void
    {
        $this->loadPlans();
        $this->loadCurrentSubscription(); // Load this FIRST to get Stripe data
        $this->loadSubscriptionData();    // Then use the Stripe data here
        $this->getCurrentCredits();
    }

    /**
     * Load subscription plans from database
     */
    protected function loadPlans(): void
    {
        $plans = SubscriptionPlan::getActivePlans();

        // Load both formats for compatibility
        $this->availablePlans = $plans->map(function ($plan) {
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

        // Also load in SubscriptionManager format
        $this->plans = $this->availablePlans;
    }

    public function loadSubscriptionData(): void
    {
        $user = auth()->user();
        $this->currentCredits = $user->credits ?? 0;

        // Get the most recent subscription
        $subscription = $user->subscriptions()
            ->latest()
            ->first();

        if ($subscription) {
            $this->subscriptionDetails = [
                'id' => $subscription->id,
                'stripe_id' => $subscription->stripe_id,
                'status' => $subscription->stripe_status,
                'stripe_price' => $subscription->stripe_price,
                'quantity' => $subscription->quantity,
                'trial_ends_at' => $subscription->trial_ends_at,
                'ends_at' => $subscription->ends_at,
                'created_at' => $subscription->created_at,
            ];

            $this->subscriptionStatus = $subscription->stripe_status;
            $this->hasActiveSubscription = in_array($subscription->stripe_status, ['active', 'trialing']);

            // Use the same plan detection logic as SubscriptionManager
            if ($this->currentSubscription && $this->currentPlanId) {
                $this->currentPlanName = $this->currentSubscription['name'];

                // Get plan features from our plans data
                $currentPlan = collect($this->plans)->firstWhere('id', $this->currentPlanId);
                if ($currentPlan) {
                    $this->planFeatures = $currentPlan['features'] ?? ['Funciones del plan suscrito'];
                } else {
                    $this->planFeatures = ['Funciones del plan suscrito'];
                }
            } else {
                // Fallback for when Stripe plan detection fails
                $this->currentPlanName = 'Plan Desconocido';
                $this->planFeatures = ['Funciones del plan suscrito'];

                Log::warning('🚨 BillingPage: Could not detect plan from Stripe data', [
                    'user_id' => auth()->id(),
                    'subscription_type' => $subscription->type,
                    'current_subscription_exists' => !!$this->currentSubscription,
                    'current_plan_id' => $this->currentPlanId,
                ]);
            }

            // Set status message and UI flags
            $this->statusMessage = $this->getStatusMessage($subscription->stripe_status);
            $this->showManageButton = in_array($subscription->stripe_status, ['active', 'trialing', 'past_due']);
            $this->showUpgradeCta = false; // Hide upgrade CTA if user has subscription

            // Calculate next billing date
            if ($subscription->ends_at && !$subscription->ends_at->isPast()) {
                $this->nextBillingDate = $subscription->ends_at->format('d/m/Y');
            }

            // Calculate trial days remaining
            if ($subscription->trial_ends_at && $subscription->stripe_status === 'trialing') {
                $this->trialDaysRemaining = Carbon::parse($subscription->trial_ends_at)->diffInDays(now(), false);
                $this->trialDaysRemaining = max(0, $this->trialDaysRemaining);
            }
        } else {
            $this->hasActiveSubscription = false;
            $this->subscriptionDetails = null;
            $this->subscriptionStatus = 'none';
            $this->trialDaysRemaining = null;
            $this->currentPlanName = 'Plan Gratuito';
            $this->statusMessage = 'Sin suscripción activa';
            $this->nextBillingDate = null;
            $this->planFeatures = ['Acceso básico', 'Funciones limitadas'];
            $this->showManageButton = false;
            $this->showUpgradeCta = true;
        }
    }

    public function refreshSubscriptionStatus(): void
    {
        $this->loadSubscriptionData();
        session()->flash('info', 'Estado de suscripción actualizado.');
    }

    public function redirectToPortal(): void
    {
        $this->loading = true;

        try {
            $user = auth()->user();

            if (!$user->hasStripeId()) {
                $user->createAsStripeCustomer();
            }

            $portalSession = $user->billingPortalSession(route('billing'));
            $this->redirect($portalSession->url);
        } catch (\Exception $e) {
            $this->loading = false;
            session()->flash('error', 'Error al acceder al portal de facturación: ' . $e->getMessage());
        }
    }

    public function getCurrentCredits(): void
    {
        $user = auth()->user();
        $this->currentCredits = $user->credits ?? 0;
    }

    /**
     * Get the current plan name from the subscription type (slug)
     */
    protected function getCurrentPlanName(string $planSlug): string
    {
        $planMap = [
            'free' => 'Plan Gratuito',
            'starter' => 'Plan Starter',
            'business' => 'Plan Business',
            'professional' => 'Plan Profesional', // Keep for backward compatibility
            'enterprise' => 'Plan Enterprise', // Fixed: now matches SubscriptionManager
        ];

        Log::info('🏷️ BillingPage getPlanName called', [
            'plan_slug' => $planSlug,
            'mapped_name' => $planMap[$planSlug] ?? 'Plan Desconocido',
            'available_plans' => array_keys($planMap),
        ]);

        return $planMap[$planSlug] ?? 'Plan Desconocido';
    }

    /**
     * Get status message based on subscription status
     */
    protected function getStatusMessage(string $status): string
    {
        return match ($status) {
            'active' => 'Activa',
            'trialing' => 'En período de prueba',
            'past_due' => 'Pago pendiente',
            'canceled' => 'Cancelada',
            'incomplete' => 'Incompleta',
            'incomplete_expired' => 'Expirada',
            'unpaid' => 'Sin pagar',
            default => 'Inactiva',
        };
    }

    /**
     * Load current subscription details (from SubscriptionManager)
     */
    public function loadCurrentSubscription(): void
    {
        $user = auth()->user();

        Log::info('🔍 LoadCurrentSubscription called in BillingPage', [
            'user_id' => $user->id,
            'user_subscribed' => $user->subscribed(),
            'total_plans_loaded' => count($this->plans),
        ]);

        if ($user->subscribed()) {
            $subscription = $user->subscription();
            $stripeSubscription = $subscription->asStripeSubscription();

            Log::info('📋 Subscription details', [
                'subscription_id' => $subscription->stripe_id,
                'subscription_status' => $subscription->stripe_status,
                'subscription_type' => $subscription->type,
                'stripe_subscription_status' => $stripeSubscription->status,
            ]);

            // Determine current plan ID by matching Stripe price ID or credits
            $this->currentPlanId = $this->getCurrentPlanId($stripeSubscription);

            Log::info('🎯 Plan detection result', [
                'detected_plan_id' => $this->currentPlanId,
            ]);

            $currentPlan = collect($this->plans)->firstWhere('id', $this->currentPlanId);

            Log::info('📦 Current plan lookup', [
                'current_plan_id' => $this->currentPlanId,
                'current_plan_found' => $currentPlan ? 'YES' : 'NO',
                'current_plan_name' => $currentPlan['name'] ?? 'NOT_FOUND',
                'fallback_name' => $subscription->type ?? 'Suscripción Activa',
            ]);

            $this->currentSubscription = [
                'name' => $currentPlan['name'] ?? $subscription->type ?? 'Suscripción Activa',
                'status' => $subscription->stripe_status,
                'current_period_end' => $stripeSubscription->current_period_end,
                'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end ?? false,
                'stripe_id' => $subscription->stripe_id,
                'plan_id' => $this->currentPlanId,
            ];

            $this->willRenew = !$this->currentSubscription['cancel_at_period_end'];

            Log::info('✅ Final subscription data', [
                'subscription_name' => $this->currentSubscription['name'],
                'subscription_plan_id' => $this->currentSubscription['plan_id'],
                'subscription_status' => $this->currentSubscription['status'],
            ]);
        } else {
            $this->currentSubscription = null;
            $this->currentPlanId = null;
            Log::info('❌ User not subscribed');
        }
    }

    /**
     * Determine which plan the user currently has based on their Stripe subscription.
     */
    protected function getCurrentPlanId($stripeSubscription): ?string
    {
        Log::info('🔬 Starting plan detection', [
            'subscription_id' => $stripeSubscription->id,
            'items_count' => count($stripeSubscription->items->data ?? []),
        ]);

        if (empty($stripeSubscription->items->data)) {
            Log::warning('❌ No subscription items found');
            return null;
        }

        $subscriptionItem = $stripeSubscription->items->data[0];
        $priceId = $subscriptionItem->price->id;
        $amount = $subscriptionItem->price->unit_amount; // Amount in cents

        Log::info('💰 Subscription item details', [
            'price_id' => $priceId,
            'amount_cents' => $amount,
            'amount_eur' => $amount / 100,
            'product_id' => $subscriptionItem->price->product,
        ]);

        Log::info('📊 Available plans for matching', [
            'plans_count' => count($this->plans),
            'plans_details' => collect($this->plans)->map(function($plan) {
                return [
                    'id' => $plan['id'],
                    'name' => $plan['name'],
                    'price' => $plan['price'],
                    'price_cents' => (int)($plan['price'] * 100),
                    'stripe_price_id' => $plan['stripe_price_id'] ?? 'NULL',
                ];
            })->toArray(),
        ]);

        // First, try to match by Stripe price ID (if we have real Stripe price IDs)
        Log::info('🔍 Step 1: Checking Stripe price ID matches');
        foreach ($this->plans as $plan) {
            if (isset($plan['stripe_price_id']) && $plan['stripe_price_id'] === $priceId) {
                Log::info('✅ Found plan by Stripe price ID match', [
                    'matched_plan' => $plan['id'],
                    'plan_name' => $plan['name'],
                    'stripe_price_id' => $plan['stripe_price_id'],
                ]);
                return $plan['id'];
            }
        }
        Log::info('❌ No Stripe price ID matches found');

        // Fallback: match by price amount (convert our price to cents)
        Log::info('🔍 Step 2: Checking price amount matches');
        foreach ($this->plans as $plan) {
            $planCents = (int)($plan['price'] * 100);
            Log::debug('Comparing plan price', [
                'plan_id' => $plan['id'],
                'plan_price_eur' => $plan['price'],
                'plan_price_cents' => $planCents,
                'subscription_amount_cents' => $amount,
                'match' => $planCents === $amount ? 'YES' : 'NO',
            ]);

            if ($planCents === $amount) {
                Log::info('✅ Found plan by price amount match', [
                    'matched_plan' => $plan['id'],
                    'plan_name' => $plan['name'],
                    'plan_price' => $plan['price'],
                    'amount_cents' => $amount,
                ]);
                return $plan['id'];
            }
        }
        Log::info('❌ No price amount matches found');

        return null; // Couldn't determine plan
    }

    /**
     * Update renewal preference (from SubscriptionManager)
     */
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
                session()->flash('info', 'Tu suscripción se renovará automáticamente.');
            } else {
                // Cancel at period end
                $subscription->cancel();
                session()->flash('info', 'Tu suscripción se cancelará al final del período actual.');
            }

            $this->loadCurrentSubscription();

        } catch (\Exception $e) {
            session()->flash('error', 'Error al actualizar preferencias: ' . $e->getMessage());
        } finally {
            $this->loading = false;
        }
    }

    /**
     * View billing portal (enhanced from existing redirectToPortal)
     */
    public function viewBillingPortal(): void
    {
        $this->loading = true;

        try {
            $user = auth()->user();

            if (!$user->hasStripeId()) {
                $user->createAsStripeCustomer([
                    'name' => $user->name,
                    'email' => $user->email,
                ]);
            }

            $this->redirect($user->billingPortalUrl(route('billing')));

        } catch (\Exception $e) {
            session()->flash('error', 'Error al acceder al portal de facturación: ' . $e->getMessage());
        } finally {
            $this->loading = false;
        }
    }

    /**
     * Get current credits property for the view
     */
    public function getCurrentCreditsProperty(): int
    {
        $user = auth()->user();
        return $user->credits ?? 0;
    }

    #[Layout('layouts.panel')]
    public function render(): View
    {
        return view('livewire.billing.billing-page', [
            'availablePlans' => $this->availablePlans,
        ]);
    }
}
