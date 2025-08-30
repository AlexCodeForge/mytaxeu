<?php

declare(strict_types=1);

namespace App\Livewire\Billing;

use App\Models\AdminSetting;
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

    // Hardcoded plan configuration (in a real app, this would come from database/config)
    protected array $plans = [
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
            'stripe_price_id' => 'price_1S1couBBlYDJOOlgpefIx2gu', // Real Stripe price ID
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
            'stripe_price_id' => 'price_1S1covBBlYDJOOlguPu91kOL', // Real Stripe price ID
        ],
        [
            'id' => 'enterprise',
            'name' => 'Plan Empresarial',
            'description' => 'Para grandes consultorías',
            'credits' => 50,
            'price' => 99.00,
            'currency' => 'EUR',
            'interval' => 'month',
            'features' => [
                '50 créditos mensuales',
                'Procesamiento de archivos CSV',
                'Notificaciones por email',
                'Soporte prioritario 24/7',
                'Análisis y reportes avanzados',
                'Gestión de múltiples usuarios'
            ],
            'stripe_price_id' => 'price_1S1cowBBlYDJOOlgDacMEp1a', // Real Stripe price ID
        ],
    ];

    public function mount(): void
    {
        $this->loadCurrentSubscription();
        $this->availablePlans = $this->plans;
    }

    public function loadCurrentSubscription(): void
    {
        $user = auth()->user();

        if ($user->subscribed()) {
            $subscription = $user->subscription();
            $this->currentSubscription = [
                'name' => $subscription->type ?? 'Suscripción Activa',
                'status' => $subscription->stripe_status,
                'current_period_end' => $subscription->asStripeSubscription()->current_period_end,
                'cancel_at_period_end' => $subscription->asStripeSubscription()->cancel_at_period_end ?? false,
                'stripe_id' => $subscription->stripe_id,
            ];

            $this->willRenew = !$this->currentSubscription['cancel_at_period_end'];
        } else {
            $this->currentSubscription = null;
        }
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

    public function render()
    {
        return view('livewire.billing.subscription-manager')->layout('layouts.panel');
    }
}
