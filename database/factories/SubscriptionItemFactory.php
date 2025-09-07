<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\SubscriptionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SubscriptionItem>
 */
class SubscriptionItemFactory extends Factory
{
    protected $model = SubscriptionItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'stripe_id' => 'si_' . $this->faker->unique()->regexify('[A-Za-z0-9]{24}'),
            'stripe_product' => 'prod_' . $this->faker->regexify('[A-Za-z0-9]{24}'),
            'stripe_price' => 'price_' . $this->faker->regexify('[A-Za-z0-9]{24}'),
            'quantity' => $this->faker->numberBetween(1, 5),
        ];
    }

    /**
     * Create a basic plan item.
     */
    public function basicPlan(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_product' => 'prod_basic_plan',
            'stripe_price' => 'price_basic_monthly',
            'quantity' => 1,
        ]);
    }

    /**
     * Create a premium plan item.
     */
    public function premiumPlan(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_product' => 'prod_premium_plan',
            'stripe_price' => 'price_premium_monthly',
            'quantity' => 1,
        ]);
    }

    /**
     * Create an enterprise plan item.
     */
    public function enterprisePlan(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_product' => 'prod_enterprise_plan',
            'stripe_price' => 'price_enterprise_monthly',
            'quantity' => $this->faker->numberBetween(1, 10),
        ]);
    }
}
