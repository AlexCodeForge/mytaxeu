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

    protected array $availablePlans = [];

    public function mount(): void
    {
        $this->loadPlans();
        $this->loadSubscriptionData();
    }

    /**
     * Load subscription plans from database
     */
    protected function loadPlans(): void
    {
        $plans = SubscriptionPlan::getActivePlans();

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

            // Get current plan from database
            $currentPlan = SubscriptionPlan::where('slug', $subscription->type)->first();
            if ($currentPlan) {
                $this->currentPlanName = $currentPlan->name;
                $this->planFeatures = $currentPlan->features ?? [];
            } else {
                // Fallback for plans not in database
                $this->currentPlanName = $this->getCurrentPlanName($subscription->type);
                $this->planFeatures = ['Funciones del plan suscrito'];
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
            'basic' => 'Plan Básico',
            'professional' => 'Plan Profesional',
            'enterprise' => 'Plan Empresarial',
        ];
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

    #[Layout('layouts.panel')]
    public function render(): View
    {
        return view('livewire.billing.billing-page', [
            'availablePlans' => $this->availablePlans,
        ]);
    }
}
