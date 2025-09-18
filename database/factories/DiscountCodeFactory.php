<?php

namespace Database\Factories;

use App\Models\DiscountCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DiscountCode>
 */
class DiscountCodeFactory extends Factory
{
    protected $model = DiscountCode::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('??###??')),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->optional()->sentence(),
            'type' => $this->faker->randomElement(['percentage', 'fixed']),
            'value' => $this->faker->randomFloat(2, 5, 50),
            'max_uses' => $this->faker->optional(0.7)->numberBetween(1, 100),
            'used_count' => 0,
            'expires_at' => $this->faker->optional(0.6)->dateTimeBetween('now', '+3 months'),
            'is_active' => $this->faker->boolean(80),
            'is_global' => $this->faker->boolean(30),
            'metadata' => null,
        ];
    }

    /**
     * Create a percentage discount code.
     */
    public function percentage(float $value = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'percentage',
            'value' => $value ?? $this->faker->randomFloat(1, 5, 50),
        ]);
    }

    /**
     * Create a fixed amount discount code.
     */
    public function fixed(float $value = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'fixed',
            'value' => $value ?? $this->faker->randomFloat(2, 5, 25),
        ]);
    }

    /**
     * Create an active discount code.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Create an inactive discount code.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create an expired discount code.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => $this->faker->dateTimeBetween('-3 months', '-1 day'),
        ]);
    }

    /**
     * Create a global discount code.
     */
    public function global(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_global' => true,
        ]);
    }

    /**
     * Create an exhausted discount code.
     */
    public function exhausted(): static
    {
        $maxUses = $this->faker->numberBetween(5, 20);
        return $this->state(fn (array $attributes) => [
            'max_uses' => $maxUses,
            'used_count' => $maxUses,
        ]);
    }

    /**
     * Create a discount code with Stripe integration.
     */
    public function withStripe(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_coupon_id' => 'coupon_' . $this->faker->unique()->bothify('??????????'),
        ]);
    }
}
