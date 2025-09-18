<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'default',
            'stripe_id' => 'sub_' . $this->faker->unique()->regexify('[A-Za-z0-9]{24}'),
            'stripe_status' => $this->faker->randomElement([
                'active',
                'trialing',
                'past_due',
                'canceled',
                'unpaid',
                'incomplete',
                'incomplete_expired',
            ]),
            'stripe_price' => 'price_' . $this->faker->regexify('[A-Za-z0-9]{24}'),
            'quantity' => $this->faker->numberBetween(1, 5),
            'trial_ends_at' => null,
            'ends_at' => null,
        ];
    }

    /**
     * Create an active subscription.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_status' => 'active',
            'trial_ends_at' => null,
            'ends_at' => null,
        ]);
    }

    /**
     * Create a trialing subscription.
     */
    public function trialing(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_status' => 'trialing',
            'trial_ends_at' => $this->faker->dateTimeBetween('now', '+30 days'),
            'ends_at' => null,
        ]);
    }

    /**
     * Create a canceled subscription.
     */
    public function canceled(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_status' => 'canceled',
            'ends_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    /**
     * Create a past due subscription.
     */
    public function pastDue(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_status' => 'past_due',
            'trial_ends_at' => null,
            'ends_at' => null,
        ]);
    }

    /**
     * Create a subscription on grace period.
     */
    public function onGracePeriod(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_status' => 'canceled',
            'ends_at' => $this->faker->dateTimeBetween('now', '+7 days'),
        ]);
    }

    /**
     * Create a subscription with specific price.
     */
    public function withPrice(string $priceId): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_price' => $priceId,
        ]);
    }

    /**
     * Create a basic subscription.
     */
    public function basic(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'basic',
            'stripe_price' => 'price_basic_' . $this->faker->regexify('[A-Za-z0-9]{20}'),
            'quantity' => 1,
        ]);
    }

    /**
     * Create a premium subscription.
     */
    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'premium',
            'stripe_price' => 'price_premium_' . $this->faker->regexify('[A-Za-z0-9]{20}'),
            'quantity' => 1,
        ]);
    }

    /**
     * Create an enterprise subscription.
     */
    public function enterprise(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'enterprise',
            'stripe_price' => 'price_enterprise_' . $this->faker->regexify('[A-Za-z0-9]{20}'),
            'quantity' => $this->faker->numberBetween(1, 10),
        ]);
    }
}
