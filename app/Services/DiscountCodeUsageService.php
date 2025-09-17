<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DiscountCode;
use App\Models\DiscountCodeUsage;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DiscountCodeUsageService
{
    /**
     * Record a discount code usage when a subscription is created.
     */
    public function recordUsage(
        string $discountCodeValue,
        User $user,
        SubscriptionPlan $plan,
        float $originalAmount,
        float $discountAmount,
        ?string $stripeSubscriptionId = null,
        ?string $stripeCouponId = null
    ): ?DiscountCodeUsage {
        return DB::transaction(function () use (
            $discountCodeValue,
            $user,
            $plan,
            $originalAmount,
            $discountAmount,
            $stripeSubscriptionId,
            $stripeCouponId
        ) {
            // Find the discount code
            $discountCode = DiscountCode::byCode($discountCodeValue)->first();

            if (!$discountCode) {
                Log::warning('Attempted to record usage for non-existent discount code', [
                    'code' => $discountCodeValue,
                    'user_id' => $user->id,
                ]);
                return null;
            }

            // Check if user already used this code
            $existingUsage = DiscountCodeUsage::where('discount_code_id', $discountCode->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existingUsage) {
                Log::warning('User attempted to use discount code multiple times', [
                    'code' => $discountCodeValue,
                    'user_id' => $user->id,
                    'existing_usage_id' => $existingUsage->id,
                ]);
                return $existingUsage;
            }

            // Create usage record
            $usage = DiscountCodeUsage::create([
                'discount_code_id' => $discountCode->id,
                'user_id' => $user->id,
                'subscription_plan_id' => $plan->id,
                'original_amount' => $originalAmount,
                'discount_amount' => $discountAmount,
                'final_amount' => max(0, $originalAmount - $discountAmount),
                'stripe_coupon_id' => $stripeCouponId,
                'stripe_subscription_id' => $stripeSubscriptionId,
                'metadata' => [
                    'plan_name' => $plan->name,
                    'user_email' => $user->email,
                    'usage_timestamp' => now()->toISOString(),
                ],
            ]);

            // Increment the discount code usage count
            $discountCode->incrementUsage();

            Log::info('Discount code usage recorded', [
                'usage_id' => $usage->id,
                'code' => $discountCodeValue,
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'discount_amount' => $discountAmount,
            ]);

            return $usage;
        });
    }

    /**
     * Record usage from Stripe webhook data.
     */
    public function recordUsageFromWebhook(array $webhookData): ?DiscountCodeUsage
    {
        try {
            // Extract data from webhook payload
            $stripeSubscriptionId = $webhookData['subscription_id'] ?? null;
            $stripeCouponId = $webhookData['coupon_id'] ?? null;
            $discountCodeValue = $webhookData['discount_code'] ?? null;
            $userEmail = $webhookData['customer_email'] ?? null;
            $planSlug = $webhookData['plan_id'] ?? null;
            $originalAmount = (float) ($webhookData['original_amount'] ?? 0);
            $discountAmount = (float) ($webhookData['discount_amount'] ?? 0);

            if (!$discountCodeValue || !$userEmail || !$planSlug) {
                Log::warning('Incomplete webhook data for discount code usage', $webhookData);
                return null;
            }

            // Find user and plan
            $user = User::where('email', $userEmail)->first();
            $plan = SubscriptionPlan::where('slug', $planSlug)->first();

            if (!$user || !$plan) {
                Log::warning('User or plan not found for discount code usage', [
                    'email' => $userEmail,
                    'plan_slug' => $planSlug,
                ]);
                return null;
            }

            return $this->recordUsage(
                $discountCodeValue,
                $user,
                $plan,
                $originalAmount,
                $discountAmount,
                $stripeSubscriptionId,
                $stripeCouponId
            );

        } catch (\Exception $e) {
            Log::error('Error recording discount code usage from webhook', [
                'error' => $e->getMessage(),
                'webhook_data' => $webhookData,
            ]);
            return null;
        }
    }

    /**
     * Get usage statistics for a discount code.
     */
    public function getUsageStats(DiscountCode $discountCode): array
    {
        $usages = $discountCode->usages()->with(['user', 'subscriptionPlan']);

        return [
            'total_uses' => $usages->count(),
            'total_discount_given' => $usages->sum('discount_amount'),
            'unique_users' => $usages->distinct('user_id')->count('user_id'),
            'plans_used' => $usages->with('subscriptionPlan')
                ->get()
                ->pluck('subscriptionPlan.name')
                ->unique()
                ->values()
                ->toArray(),
            'recent_usages' => $usages->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($usage) {
                    return [
                        'user_name' => $usage->user?->name,
                        'plan_name' => $usage->subscriptionPlan?->name,
                        'discount_amount' => $usage->discount_amount,
                        'used_at' => $usage->created_at->toISOString(),
                    ];
                })
                ->toArray(),
        ];
    }

    /**
     * Validate if a discount code can be used by a user for a plan.
     */
    public function validateUsage(string $discountCodeValue, User $user, SubscriptionPlan $plan): array
    {
        $discountCode = DiscountCode::byCode($discountCodeValue)->first();

        if (!$discountCode) {
            return [
                'valid' => false,
                'error' => 'Código de descuento no encontrado',
            ];
        }

        if (!$discountCode->isValid()) {
            return [
                'valid' => false,
                'error' => 'El código de descuento ha expirado o no está activo',
            ];
        }

        if (!$discountCode->canBeUsedByUser($user)) {
            return [
                'valid' => false,
                'error' => 'Ya has usado este código de descuento',
            ];
        }

        if (!$discountCode->canBeAppliedToPlan($plan)) {
            return [
                'valid' => false,
                'error' => 'Este código no aplica para el plan seleccionado',
            ];
        }

        $originalAmount = (float) $plan->monthly_price;
        $discountAmount = $discountCode->calculateDiscount($originalAmount);

        return [
            'valid' => true,
            'discount_code' => $discountCode,
            'original_amount' => $originalAmount,
            'discount_amount' => $discountAmount,
            'final_amount' => max(0, $originalAmount - $discountAmount),
            'savings_percentage' => $originalAmount > 0 ? round(($discountAmount / $originalAmount) * 100, 2) : 0,
        ];
    }

    /**
     * Preview discount calculation without applying it.
     */
    public function previewDiscount(string $discountCodeValue, float $amount): array
    {
        $discountCode = DiscountCode::byCode($discountCodeValue)->first();

        if (!$discountCode || !$discountCode->isValid()) {
            return [
                'valid' => false,
                'original_amount' => $amount,
                'discount_amount' => 0,
                'final_amount' => $amount,
            ];
        }

        $discountAmount = $discountCode->calculateDiscount($amount);

        return [
            'valid' => true,
            'code' => $discountCode->code,
            'name' => $discountCode->name,
            'type' => $discountCode->type,
            'value' => $discountCode->value,
            'original_amount' => $amount,
            'discount_amount' => $discountAmount,
            'final_amount' => max(0, $amount - $discountAmount),
            'savings_percentage' => $amount > 0 ? round(($discountAmount / $amount) * 100, 2) : 0,
        ];
    }
}
