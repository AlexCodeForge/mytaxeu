<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdminSetting;
use Illuminate\Support\Facades\Log;
use Stripe\Coupon;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class StripeDiscountService
{
    public function __construct()
    {
        $this->configureStripe();
    }

    /**
     * Create a coupon in Stripe.
     */
    public function createCoupon(array $couponData): string
    {
        try {
            $coupon = Coupon::create($this->prepareCouponData($couponData));

            Log::info('Stripe coupon created', [
                'coupon_id' => $coupon->id,
                'type' => $couponData['amount_off'] ? 'fixed' : 'percentage',
            ]);

            return $coupon->id;
        } catch (ApiErrorException $e) {
            Log::error('Failed to create Stripe coupon', [
                'error' => $e->getMessage(),
                'coupon_data' => $couponData,
            ]);
            throw new \RuntimeException('Error creating Stripe coupon: ' . $e->getMessage());
        }
    }

    /**
     * Update a coupon in Stripe.
     */
    public function updateCoupon(string $couponId, array $updateData): Coupon
    {
        try {
            $coupon = Coupon::update($couponId, [
                'name' => $updateData['name'] ?? null,
                'metadata' => $updateData['metadata'] ?? null,
            ]);

            Log::info('Stripe coupon updated', [
                'coupon_id' => $couponId,
                'updated_fields' => array_keys($updateData),
            ]);

            return $coupon;
        } catch (ApiErrorException $e) {
            Log::error('Failed to update Stripe coupon', [
                'coupon_id' => $couponId,
                'error' => $e->getMessage(),
                'update_data' => $updateData,
            ]);
            throw new \RuntimeException('Error updating Stripe coupon: ' . $e->getMessage());
        }
    }

    /**
     * Archive (delete) a coupon in Stripe.
     */
    public function archiveCoupon(string $couponId): bool
    {
        try {
            $coupon = Coupon::retrieve($couponId);
            $coupon->delete();

            Log::info('Stripe coupon archived', [
                'coupon_id' => $couponId,
            ]);

            return true;
        } catch (ApiErrorException $e) {
            Log::error('Failed to archive Stripe coupon', [
                'coupon_id' => $couponId,
                'error' => $e->getMessage(),
            ]);
            // Don't throw here, as this is often called during cleanup
            return false;
        }
    }

    /**
     * Retrieve a coupon from Stripe.
     */
    public function getCoupon(string $couponId): ?Coupon
    {
        try {
            return Coupon::retrieve($couponId);
        } catch (ApiErrorException $e) {
            Log::error('Failed to retrieve Stripe coupon', [
                'coupon_id' => $couponId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Validate if a coupon code exists and is valid in Stripe.
     */
    public function validateCouponCode(string $couponCode): ?array
    {
        try {
            $coupon = Coupon::retrieve($couponCode);

            if (!$coupon->valid) {
                return null;
            }

            return [
                'id' => $coupon->id,
                'name' => $coupon->name,
                'amount_off' => $coupon->amount_off,
                'percent_off' => $coupon->percent_off,
                'currency' => $coupon->currency,
                'duration' => $coupon->duration,
                'duration_in_months' => $coupon->duration_in_months,
                'max_redemptions' => $coupon->max_redemptions,
                'times_redeemed' => $coupon->times_redeemed,
                'redeem_by' => $coupon->redeem_by,
                'valid' => $coupon->valid,
            ];
        } catch (ApiErrorException $e) {
            Log::debug('Coupon validation failed', [
                'coupon_code' => $couponCode,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get coupon information from Stripe regardless of validity status.
     * Used for syncing usage counts even for invalid/exhausted coupons.
     */
    public function getCouponInfo(string $couponCode): ?array
    {
        try {
            $coupon = Coupon::retrieve($couponCode);

            return [
                'id' => $coupon->id,
                'name' => $coupon->name,
                'amount_off' => $coupon->amount_off,
                'percent_off' => $coupon->percent_off,
                'currency' => $coupon->currency,
                'duration' => $coupon->duration,
                'duration_in_months' => $coupon->duration_in_months,
                'max_redemptions' => $coupon->max_redemptions,
                'times_redeemed' => $coupon->times_redeemed,
                'redeem_by' => $coupon->redeem_by,
                'valid' => $coupon->valid,
            ];
        } catch (ApiErrorException $e) {
            Log::debug('Coupon info retrieval failed', [
                'coupon_code' => $couponCode,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Create a promotion code in Stripe for a coupon.
     */
    public function createPromotionCode(string $couponId, array $promotionData = []): string
    {
        try {
            $promotionCode = \Stripe\PromotionCode::create([
                'coupon' => $couponId,
                'code' => $promotionData['code'] ?? null,
                'customer' => $promotionData['customer'] ?? null,
                'expires_at' => $promotionData['expires_at'] ?? null,
                'max_redemptions' => $promotionData['max_redemptions'] ?? null,
                'restrictions' => $promotionData['restrictions'] ?? null,
                'metadata' => $promotionData['metadata'] ?? [],
            ]);

            Log::info('Stripe promotion code created', [
                'promotion_code_id' => $promotionCode->id,
                'coupon_id' => $couponId,
            ]);

            return $promotionCode->id;
        } catch (ApiErrorException $e) {
            Log::error('Failed to create Stripe promotion code', [
                'coupon_id' => $couponId,
                'error' => $e->getMessage(),
                'promotion_data' => $promotionData,
            ]);
            throw new \RuntimeException('Error creating Stripe promotion code: ' . $e->getMessage());
        }
    }

    /**
     * Apply coupon to a Stripe checkout session.
     */
    public function applyCouponToCheckoutSession(array $sessionData, string $couponCode): array
    {
        // Validate the coupon first
        $couponInfo = $this->validateCouponCode($couponCode);

        if (!$couponInfo) {
            throw new \RuntimeException('Invalid or expired coupon code');
        }

        // Add discount to the session data
        $sessionData['discounts'] = [
            [
                'coupon' => $couponCode,
            ],
        ];

        return $sessionData;
    }

    /**
     * Calculate discount amount for preview.
     */
    public function calculateDiscount(string $couponCode, float $amount): array
    {
        $couponInfo = $this->validateCouponCode($couponCode);

        if (!$couponInfo) {
            return [
                'original_amount' => $amount,
                'discount_amount' => 0,
                'final_amount' => $amount,
                'discount_type' => null,
                'valid' => false,
            ];
        }

        $discountAmount = 0;
        $discountType = null;

        if ($couponInfo['percent_off']) {
            $discountAmount = round($amount * ($couponInfo['percent_off'] / 100), 2);
            $discountType = 'percentage';
        } elseif ($couponInfo['amount_off']) {
            // Convert from cents to euros
            $discountAmount = min($couponInfo['amount_off'] / 100, $amount);
            $discountType = 'fixed';
        }

        return [
            'original_amount' => $amount,
            'discount_amount' => $discountAmount,
            'final_amount' => max(0, $amount - $discountAmount),
            'discount_type' => $discountType,
            'coupon_info' => $couponInfo,
            'valid' => true,
        ];
    }

    /**
     * Configure Stripe with the current API key.
     */
    private function configureStripe(): void
    {
        $stripeConfig = AdminSetting::getStripeConfig();

        if (empty($stripeConfig['secret_key'])) {
            throw new \RuntimeException('Stripe secret key not configured');
        }

        Stripe::setApiKey($stripeConfig['secret_key']);
    }

    /**
     * Prepare coupon data for Stripe API.
     */
    private function prepareCouponData(array $couponData): array
    {
        $data = [
            'id' => $couponData['id'],
            'duration' => $couponData['duration'] ?? 'once',
            'name' => $couponData['name'] ?? null,
        ];

        // Add percentage or fixed amount
        if (isset($couponData['percent_off'])) {
            $data['percent_off'] = $couponData['percent_off'];
        } elseif (isset($couponData['amount_off'])) {
            $data['amount_off'] = $couponData['amount_off'];
            $data['currency'] = 'eur';
        }

        // Add optional fields
        if (isset($couponData['duration_in_months'])) {
            $data['duration_in_months'] = $couponData['duration_in_months'];
        }

        if (isset($couponData['max_redemptions'])) {
            $data['max_redemptions'] = $couponData['max_redemptions'];
        }

        if (isset($couponData['redeem_by'])) {
            $data['redeem_by'] = $couponData['redeem_by'];
        }

        return $data;
    }
}
