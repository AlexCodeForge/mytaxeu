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

        Log::info('🔍 LoadCurrentSubscription called', [
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

        // Fallback: try to get plan_id from product metadata
        Log::info('🔍 Step 3: Checking product metadata for plan_id');
        try {
            $product = \Stripe\Product::retrieve($subscriptionItem->price->product);
            Log::info('📦 Product metadata retrieved', [
                'product_id' => $product->id,
                'metadata' => $product->metadata->toArray(),
            ]);

            if (isset($product->metadata['plan_id'])) {
                Log::info('✅ Found plan by product metadata plan_id', [
                    'matched_plan' => $product->metadata['plan_id'],
                ]);
                return $product->metadata['plan_id'];
            }
        } catch (\Exception $e) {
            Log::warning('❌ Failed to retrieve product metadata', [
                'error' => $e->getMessage(),
                'product_id' => $subscriptionItem->price->product,
            ]);
        }

        // Default fallback based on credit amount if available in metadata
        Log::info('🔍 Step 4: Checking product metadata for credits');
        try {
            $product = \Stripe\Product::retrieve($subscriptionItem->price->product);
            if (isset($product->metadata['credits'])) {
                $credits = (int) $product->metadata['credits'];
                Log::info('🪙 Product has credits metadata', [
                    'credits' => $credits,
                ]);

                foreach ($this->plans as $plan) {
                    if ($plan['credits'] === $credits) {
                        Log::info('✅ Found plan by credits match', [
                            'matched_plan' => $plan['id'],
                            'plan_name' => $plan['name'],
                            'credits' => $credits,
                        ]);
                        return $plan['id'];
                    }
                }
                Log::info('❌ No plans match the credits amount', ['credits' => $credits]);
            } else {
                Log::info('❌ No credits metadata found in product');
            }
        } catch (\Exception $e) {
            Log::warning('❌ Failed to retrieve product metadata for credits', [
                'error' => $e->getMessage(),
                'product_id' => $subscriptionItem->price->product,
            ]);
        }

        Log::error('🚨 Plan detection FAILED - no matches found', [
            'subscription_id' => $stripeSubscription->id,
            'price_id' => $priceId,
            'amount_cents' => $amount,
            'amount_eur' => $amount / 100,
            'product_id' => $subscriptionItem->price->product,
        ]);

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
                'billing_address_collection' => 'required',
                'tax_id_collection' => ['enabled' => true],
                'customer_update' => [
                    'name' => 'auto',
                    'address' => 'auto',
                ],
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
                'success_url' => route('thank-you') . '?session_id={CHECKOUT_SESSION_ID}',
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

                    Log::info('🎟️ Applying discount code to checkout session', [
                        'user_entered_code' => $this->discountCode,
                        'stripe_coupon_id' => $discountCodeModel->stripe_coupon_id,
                        'discount_amount' => $discountAmount,
                        'plan_id' => $planId,
                    ]);

                    $stripeDiscountService = app(StripeDiscountService::class);
                    $sessionData = $stripeDiscountService->applyCouponToCheckoutSession(
                        $sessionData,
                        $discountCodeModel->stripe_coupon_id // Use the actual Stripe coupon ID, not user input
                    );

                    // Add discount code to metadata
                    $sessionData['metadata']['discount_code'] = $this->discountCode;
                    $sessionData['metadata']['stripe_coupon_id'] = $discountCodeModel->stripe_coupon_id;
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

            // Additional validation: Check if the coupon exists in Stripe
            Log::info('🔍 Validating discount code in Stripe', [
                'user_entered_code' => $this->discountCode,
                'local_code' => $discountCode->code,
                'stripe_coupon_id' => $discountCode->stripe_coupon_id,
            ]);

            try {
                $stripeDiscountService = app(StripeDiscountService::class);

                // First, get coupon info regardless of validity to sync usage counts
                $stripeCouponInfo = $stripeDiscountService->getCouponInfo($discountCode->stripe_coupon_id);

                if (!$stripeCouponInfo) {
                    Log::warning('⚠️ Discount code exists locally but not in Stripe', [
                        'local_code' => $discountCode->code,
                        'stripe_coupon_id' => $discountCode->stripe_coupon_id,
                    ]);
                    session()->flash('discount_error', 'Este código de descuento no está disponible en este momento. Contacta al soporte.');
                    return;
                }

                // Check for usage count mismatch and sync if needed
                $localUsedCount = (int) $discountCode->used_count;
                $stripeTimesRedeemed = (int) ($stripeCouponInfo['times_redeemed'] ?? 0);

                if ($localUsedCount !== $stripeTimesRedeemed) {
                    Log::info('🔄 Syncing discount code usage count', [
                        'code' => $discountCode->code,
                        'local_count' => $localUsedCount,
                        'stripe_count' => $stripeTimesRedeemed,
                    ]);

                    // Update local count to match Stripe
                    $discountCode->used_count = $stripeTimesRedeemed;
                    $discountCode->save();

                    Log::info('✅ Usage count synchronized', [
                        'code' => $discountCode->code,
                        'updated_count' => $stripeTimesRedeemed,
                    ]);
                }

                // Check if the coupon is still valid after sync
                if (!($stripeCouponInfo['valid'] ?? false)) {
                    Log::warning('⚠️ Discount code is no longer valid in Stripe', [
                        'code' => $discountCode->code,
                        'stripe_valid' => false,
                        'times_redeemed' => $stripeTimesRedeemed,
                        'max_redemptions' => $stripeCouponInfo['max_redemptions'] ?? null,
                    ]);

                    // Determine specific reason for invalidity
                    $errorMessage = 'Este código de descuento ya no está disponible.';
                    if (isset($stripeCouponInfo['max_redemptions']) && $stripeTimesRedeemed >= $stripeCouponInfo['max_redemptions']) {
                        $errorMessage = 'Este código de descuento ya ha alcanzado su límite de usos.';
                    }

                    session()->flash('discount_error', $errorMessage);
                    return;
                }

                Log::info('✅ Discount code validated in both local and Stripe', [
                    'code' => $discountCode->code,
                    'stripe_valid' => $stripeCouponInfo['valid'] ?? false,
                    'stripe_times_redeemed' => $stripeTimesRedeemed,
                    'local_used_count' => $discountCode->used_count,
                ]);

            } catch (\Exception $e) {
                Log::error('❌ Error validating discount code in Stripe', [
                    'code' => $discountCode->code,
                    'stripe_coupon_id' => $discountCode->stripe_coupon_id,
                    'error' => $e->getMessage(),
                ]);
                session()->flash('discount_error', 'Error al validar el código de descuento. Inténtalo de nuevo.');
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
                'stripe_coupon_id' => $discountCode->stripe_coupon_id,
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
