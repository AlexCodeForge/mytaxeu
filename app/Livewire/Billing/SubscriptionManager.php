<?php

declare(strict_types=1);

namespace App\Livewire\Billing;

use App\Models\AdminSetting;
use App\Models\DiscountCode;
use App\Models\DiscountCodeUsage;
use App\Models\SubscriptionPlan;
use App\Services\CreditService;
use App\Services\StripeConfigurationService;
use App\Services\StripeDiscountService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

    // Discount code properties
    public string $discountCode = '';
    public ?array $appliedDiscount = null;
    public bool $showDiscountField = false;
    public bool $discountLoading = false;

    public array $plans = [];

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

        Log::info('Loading plans in SubscriptionManager', [
            'plans_count' => $plans->count(),
            'plan_slugs' => $plans->pluck('slug')->toArray()
        ]);

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

        Log::info('Plans loaded in SubscriptionManager', [
            'transformed_plans_count' => count($this->plans),
            'plan_ids' => collect($this->plans)->pluck('id')->toArray()
        ]);
    }

    /**
     * Reload plans from database - useful for debugging
     */
    public function reloadPlans(): void
    {
        $this->loadPlans();
        session()->flash('message', 'Planes recargados. Total: ' . count($this->plans));
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
            // Safety check: Reload plans if empty
            if (empty($this->plans)) {
                Log::warning('Plans array was empty in subscribe method, reloading...');
                $this->loadPlans();
            }

            $plan = collect($this->plans)->firstWhere('id', $planId);

            if (!$plan) {
                // Debug: Let's see what plans are available and what planId was requested
                $availablePlanIds = collect($this->plans)->pluck('id')->toArray();
                Log::error('Plan not found', [
                    'requested_plan_id' => $planId,
                    'available_plan_ids' => $availablePlanIds,
                    'total_plans' => count($this->plans)
                ]);

                if (empty($availablePlanIds)) {
                    session()->flash('error', 'No hay planes disponibles. Por favor, contacta al administrador.');
                } else {
                    session()->flash('error', 'Plan no encontrado. Plan solicitado: ' . $planId . '. Planes disponibles: ' . implode(', ', $availablePlanIds));
                }
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

            // Prepare checkout session data
            $sessionData = [
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
            ];

            // Apply discount if available and compatible with plan
            if ($this->appliedDiscount && $this->appliedDiscount['valid']) {
                $discountCodeModel = DiscountCode::find($this->appliedDiscount['discount_code_id']);
                $selectedPlan = SubscriptionPlan::where('slug', $planId)->first();

                if ($discountCodeModel && $selectedPlan && $discountCodeModel->canBeAppliedToPlan($selectedPlan)) {
                    // Calculate discount for this specific plan
                    $originalAmount = (float) $selectedPlan->monthly_price;
                    $discountAmount = $discountCodeModel->calculateDiscount($originalAmount);

                    // Update applied discount with plan-specific calculations
                    $this->appliedDiscount['plan_id'] = $planId;
                    $this->appliedDiscount['original_amount'] = $originalAmount;
                    $this->appliedDiscount['discount_amount'] = $discountAmount;
                    $this->appliedDiscount['final_amount'] = max(0, $originalAmount - $discountAmount);

                    $stripeDiscountService = app(StripeDiscountService::class);
                    $sessionData = $stripeDiscountService->applyCouponToCheckoutSession(
                        $sessionData,
                        $this->discountCode
                    );

                    // Add discount code to metadata
                    $sessionData['metadata']['discount_code'] = $this->discountCode;
                }
            }

            $checkoutSession = Session::create($sessionData);

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

    public function toggleDiscountField(): void
    {
        $this->showDiscountField = !$this->showDiscountField;

        if (!$this->showDiscountField) {
            $this->clearDiscountCode();
        }
    }

    public function applyDiscountCode(): void
    {
        $this->discountLoading = true;

        try {
            $this->validate([
                'discountCode' => ['required', 'string', 'min:3'],
            ], [
                'discountCode.required' => 'Ingresa un código de descuento',
                'discountCode.min' => 'El código debe tener al menos 3 caracteres',
            ]);

            $discountCode = DiscountCode::byCode($this->discountCode)->first();

            if (!$discountCode) {
                session()->flash('discount_error', 'Código de descuento no encontrado');
                return;
            }

            if (!$discountCode->isValid()) {
                session()->flash('discount_error', 'Este código de descuento ha expirado o no está disponible');
                return;
            }

            $user = auth()->user();

            if (!$discountCode->canBeUsedByUser($user)) {
                if ($discountCode->usages()->where('user_id', $user->id)->exists()) {
                    session()->flash('discount_error', 'Ya has usado este código de descuento');
                } else {
                    session()->flash('discount_error', 'Este código de descuento no es válido');
                }
                return;
            }

            // Store the validated discount code for later use
            $this->appliedDiscount = [
                'code' => $discountCode->code,
                'name' => $discountCode->name,
                'type' => $discountCode->type,
                'value' => (float) $discountCode->value,
                'discount_code_id' => $discountCode->id,
                'is_global' => $discountCode->is_global,
                'valid' => true,
            ];

            session()->flash('discount_success', "¡Código válido! Se aplicará automáticamente cuando selecciones un plan compatible.");

        } catch (\Exception $e) {
            session()->flash('discount_error', 'Error al validar el código: ' . $e->getMessage());
        } finally {
            $this->discountLoading = false;
        }
    }

    public function removeDiscountCode(): void
    {
        $this->clearDiscountCode();
        session()->flash('discount_success', 'Código de descuento removido');
    }

    private function clearDiscountCode(): void
    {
        $this->discountCode = '';
        $this->appliedDiscount = null;
        $this->resetValidation('discountCode');
    }

    /**
     * Calculate discount for a specific plan if applicable
     */
    public function calculateDiscountForPlan(string $planId): array
    {
        if (!$this->appliedDiscount || !$this->appliedDiscount['valid']) {
            return [
                'applicable' => false,
                'original_amount' => 0,
                'discount_amount' => 0,
                'final_amount' => 0,
            ];
        }

        // Safety check: Reload plans if empty
        if (empty($this->plans)) {
            $this->loadPlans();
        }

        $discountCodeModel = DiscountCode::find($this->appliedDiscount['discount_code_id']);
        $plan = collect($this->plans)->firstWhere('id', $planId);

        if (!$discountCodeModel || !$plan) {
            return [
                'applicable' => false,
                'original_amount' => 0,
                'discount_amount' => 0,
                'final_amount' => 0,
            ];
        }

        // Check if code is global or plan-specific
        if (!$discountCodeModel->is_global) {
            $subscriptionPlan = SubscriptionPlan::where('slug', $planId)->first();
            if (!$subscriptionPlan || !$discountCodeModel->canBeAppliedToPlan($subscriptionPlan)) {
                return [
                    'applicable' => false,
                    'original_amount' => $plan['price'],
                    'discount_amount' => 0,
                    'final_amount' => $plan['price'],
                ];
            }
        }

        $originalAmount = (float) $plan['price'];
        $discountAmount = $discountCodeModel->calculateDiscount($originalAmount);
        $finalAmount = max(0, $originalAmount - $discountAmount);

        return [
            'applicable' => true,
            'original_amount' => $originalAmount,
            'discount_amount' => $discountAmount,
            'final_amount' => $finalAmount,
        ];
    }

    public function render()
    {
        return view('livewire.billing.subscription-manager', [
            'appliedDiscount' => $this->appliedDiscount,
        ])->layout('layouts.panel');
    }
}
