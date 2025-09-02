<?php

declare(strict_types=1);

namespace App\Livewire\Billing;

use App\Services\StripePortalService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\View\View;
use Laravel\Cashier\Subscription;
use Livewire\Component;
use Carbon\Carbon;

class BillingPage extends Component
{
    use AuthorizesRequests;

    public bool $hasActiveSubscription = false;
    public ?array $subscriptionDetails = null;
    public int $currentCredits = 0;
    public ?int $trialDaysRemaining = null;
    public bool $loading = false;
    public string $subscriptionStatus = 'none';

    // Plan configuration matching existing SubscriptionManager
    protected array $availablePlans = [
        [
            'id' => 'basic',
            'name' => 'Plan Básico',
            'description' => 'Ideal para consultorías pequeñas',
            'credits' => 10,
            'price' => 29.00,
            'currency' => 'EUR',
            'interval' => 'month',
            'features' => [
                '10 créditos mensuales',
                'Procesamiento de archivos CSV',
                'Notificaciones por email',
                'Soporte por email'
            ],
            'stripe_price_id' => 'price_1S1couBBlYDJOOlgpefIx2gu',
        ],
        [
            'id' => 'professional',
            'name' => 'Plan Profesional',
            'description' => 'Para consultorías medianas',
            'credits' => 25,
            'price' => 59.00,
            'currency' => 'EUR',
            'interval' => 'month',
            'features' => [
                '25 créditos mensuales',
                'Procesamiento de archivos CSV',
                'Notificaciones por email',
                'Soporte prioritario',
                'Historial de transacciones extendido'
            ],
            'stripe_price_id' => 'price_1S1covBBlYDJOOlguPu91kOL',
        ],
        [
            'id' => 'enterprise',
            'name' => 'Plan Empresarial',
            'description' => 'Para consultorías grandes',
            'credits' => 50,
            'price' => 99.00,
            'currency' => 'EUR',
            'interval' => 'month',
            'features' => [
                '50 créditos mensuales',
                'Procesamiento de archivos CSV',
                'Notificaciones por email',
                'Soporte prioritario 24/7',
                'Historial completo de transacciones',
                'API de integración',
                'Gestor de cuenta dedicado'
            ],
            'stripe_price_id' => 'price_1S1coxBBlYDJOOlgTQZNPZyL',
        ],
    ];

    public function mount(): void
    {
        $this->authorize('view', auth()->user());
        $this->loadSubscriptionData();
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
            $portalService = app(StripePortalService::class);
            $user = auth()->user();

            $session = $portalService->createPortalSession($user, url('/billing?portal_return=true'));

            $this->redirect($session->url);
        } catch (\Exception $e) {
            $this->loading = false;
            session()->flash('error', 'Error al acceder al portal de facturación: ' . $e->getMessage());
        }
    }

    public function getCurrentPlanName(): string
    {
        if (!$this->subscriptionDetails) {
            return 'Plan Gratuito';
        }

        $stripePriceId = $this->subscriptionDetails['stripe_price'];
        
        foreach ($this->availablePlans as $plan) {
            if ($plan['stripe_price_id'] === $stripePriceId) {
                return $plan['name'];
            }
        }

        // Fallback based on price ID patterns
        if (str_contains($stripePriceId, 'basic')) {
            return 'Plan Básico';
        } elseif (str_contains($stripePriceId, 'professional')) {
            return 'Plan Profesional';
        } elseif (str_contains($stripePriceId, 'enterprise')) {
            return 'Plan Empresarial';
        }

        return 'Plan Personalizado';
    }

    public function getCurrentPlanFeatures(): array
    {
        if (!$this->subscriptionDetails) {
            return [
                'Hasta 100 líneas CSV gratuitas',
                'Procesamiento básico de archivos',
                'Notificaciones por email limitadas'
            ];
        }

        $stripePriceId = $this->subscriptionDetails['stripe_price'];
        
        foreach ($this->availablePlans as $plan) {
            if ($plan['stripe_price_id'] === $stripePriceId) {
                return $plan['features'];
            }
        }

        return ['Funciones del plan personalizado'];
    }

    public function getAvailablePlans(): array
    {
        return $this->availablePlans;
    }

    public function getSubscriptionStatusMessage(): string
    {
        switch ($this->subscriptionStatus) {
            case 'active':
                return 'Estado: Activo';
            case 'trialing':
                if ($this->trialDaysRemaining !== null) {
                    return "Período de Prueba - {$this->trialDaysRemaining} días restantes";
                }
                return 'Período de Prueba';
            case 'canceled':
                if ($this->subscriptionDetails && $this->subscriptionDetails['ends_at']) {
                    $endsAt = Carbon::parse($this->subscriptionDetails['ends_at'])->format('d/m/Y');
                    return "Suscripción Cancelada - Termina el {$endsAt}";
                }
                return 'Suscripción Cancelada';
            case 'past_due':
                return 'Pago Atrasado - Actualizar método de pago';
            case 'incomplete':
                return 'Pago Incompleto - Acción requerida';
            case 'incomplete_expired':
                return 'Pago Expirado - Renovar suscripción';
            case 'unpaid':
                return 'Sin Pagar - Actualizar pago';
            default:
                return 'Sin suscripción activa';
        }
    }

    public function getNextBillingDate(): ?string
    {
        if (!$this->hasActiveSubscription || !$this->subscriptionDetails) {
            return null;
        }

        // If trialing, next billing is when trial ends
        if ($this->subscriptionStatus === 'trialing' && $this->subscriptionDetails['trial_ends_at']) {
            return Carbon::parse($this->subscriptionDetails['trial_ends_at'])->format('d/m/Y');
        }

        // For active subscriptions, estimate next billing (usually monthly)
        if ($this->subscriptionStatus === 'active') {
            return Carbon::parse($this->subscriptionDetails['created_at'])
                ->addMonth()
                ->format('d/m/Y');
        }

        return null;
    }

    public function shouldShowUpgradeCta(): bool
    {
        return !$this->hasActiveSubscription || $this->subscriptionStatus === 'canceled';
    }

    public function shouldShowManageButton(): bool
    {
        return $this->hasActiveSubscription || 
               in_array($this->subscriptionStatus, ['canceled', 'past_due', 'incomplete']);
    }

    public function render(): View
    {
        return view('livewire.billing.billing-page', [
            'hasActiveSubscription' => $this->hasActiveSubscription,
            'subscriptionDetails' => $this->subscriptionDetails,
            'currentCredits' => $this->currentCredits,
            'planName' => $this->getCurrentPlanName(),
            'planFeatures' => $this->getCurrentPlanFeatures(),
            'statusMessage' => $this->getSubscriptionStatusMessage(),
            'nextBillingDate' => $this->getNextBillingDate(),
            'availablePlans' => $this->getAvailablePlans(),
            'showUpgradeCta' => $this->shouldShowUpgradeCta(),
            'showManageButton' => $this->shouldShowManageButton(),
        ]);
    }
}
