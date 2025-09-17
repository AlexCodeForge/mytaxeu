<?php

namespace Database\Factories;

use App\Models\DiscountCode;
use App\Models\DiscountCodeUsage;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DiscountCodeUsage>
 */
class DiscountCodeUsageFactory extends Factory
{
    protected $model = DiscountCodeUsage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $originalAmount = $this->faker->randomFloat(2, 10, 100);
        $discountAmount = $this->faker->randomFloat(2, 1, $originalAmount * 0.5);
        $finalAmount = max(0, $originalAmount - $discountAmount);

        return [
            'discount_code_id' => DiscountCode::factory(),
            'user_id' => User::factory(),
            'subscription_plan_id' => SubscriptionPlan::factory(),
            'original_amount' => $originalAmount,
            'discount_amount' => $discountAmount,
            'final_amount' => $finalAmount,
            'stripe_coupon_id' => $this->faker->optional()->bothify('coupon_##########'),
            'stripe_subscription_id' => $this->faker->optional()->bothify('sub_##########'),
            'metadata' => [
                'user_agent' => $this->faker->userAgent(),
                'ip_address' => $this->faker->ipv4(),
                'usage_timestamp' => now()->toISOString(),
            ],
        ];
    }

    /**
     * Create a usage with a percentage discount.
     */
    public function withPercentageDiscount(float $percentage = 20): static
    {
        return $this->state(function (array $attributes) use ($percentage) {
            $originalAmount = $this->faker->randomFloat(2, 20, 100);
            $discountAmount = round($originalAmount * ($percentage / 100), 2);
            $finalAmount = $originalAmount - $discountAmount;

            return [
                'original_amount' => $originalAmount,
                'discount_amount' => $discountAmount,
                'final_amount' => $finalAmount,
            ];
        });
    }

    /**
     * Create a usage with a fixed discount.
     */
    public function withFixedDiscount(float $amount = 10): static
    {
        return $this->state(function (array $attributes) use ($amount) {
            $originalAmount = $this->faker->randomFloat(2, $amount + 5, 100);
            $discountAmount = min($amount, $originalAmount);
            $finalAmount = $originalAmount - $discountAmount;

            return [
                'original_amount' => $originalAmount,
                'discount_amount' => $discountAmount,
                'final_amount' => $finalAmount,
            ];
        });
    }

    /**
     * Create a usage with Stripe integration.
     */
    public function withStripe(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_coupon_id' => 'coupon_' . $this->faker->bothify('##########'),
            'stripe_subscription_id' => 'sub_' . $this->faker->bothify('##########'),
        ]);
    }

    /**
     * Create a usage for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Create a usage for a specific discount code.
     */
    public function forDiscountCode(DiscountCode $discountCode): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_code_id' => $discountCode->id,
        ]);
    }

    /**
     * Create a usage for a specific subscription plan.
     */
    public function forPlan(SubscriptionPlan $plan): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_plan_id' => $plan->id,
        ]);
    }
}
