<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CreditTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CreditTransaction>
 */
class CreditTransactionFactory extends Factory
{
    protected $model = CreditTransaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement(['purchased', 'consumed', 'refunded', 'expired']),
            'amount' => $this->faker->numberBetween(100, 5000), // $1.00 to $50.00 in cents
            'description' => $this->faker->randomElement([
                'Credit purchase',
                'Monthly subscription',
                'One-time payment',
                'Bonus credits',
                'Usage deduction',
                'Refund processing',
            ]),
            'subscription_id' => null,
            'upload_id' => null,
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }

    /**
     * Indicate that the transaction is a purchase.
     */
    public function purchase(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'purchased',
            'amount' => $this->faker->numberBetween(500, 5000), // $5.00 to $50.00
            'description' => $this->faker->randomElement([
                'Credit purchase',
                'Monthly subscription payment',
                'One-time credit purchase',
                'Subscription renewal',
            ]),
        ]);
    }

    /**
     * Indicate that the transaction is usage deduction.
     */
    public function usage(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'consumed',
            'amount' => -$this->faker->numberBetween(10, 100), // Negative for usage
            'description' => $this->faker->randomElement([
                'CSV processing usage',
                'Line processing fee',
                'Usage deduction',
                'Processing charge',
            ]),
        ]);
    }

    /**
     * Indicate that the transaction is a refund.
     */
    public function refund(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'refunded',
            'amount' => -$this->faker->numberBetween(100, 2000), // Negative for refunds
            'description' => $this->faker->randomElement([
                'Refund processed',
                'Subscription refund',
                'Partial refund',
                'Full refund',
            ]),
        ]);
    }

    /**
     * Indicate that the transaction is expired credits.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'expired',
            'amount' => $this->faker->numberBetween(100, 1000), // $1.00 to $10.00
            'description' => $this->faker->randomElement([
                'Welcome bonus',
                'Referral bonus',
                'Promotional credits',
                'Customer appreciation bonus',
            ]),
        ]);
    }

    /**
     * Indicate that the transaction is a test transaction.
     */
    public function test(): static
    {
        return $this->state(fn (array $attributes) => [
            'description' => 'TEST ' . $attributes['description'],
            'amount' => $this->faker->numberBetween(1, 100), // Small test amounts
        ]);
    }

    /**
     * Create transaction with specific amount in dollars.
     */
    public function withAmount(float $dollars): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => (int) ($dollars * 100), // Convert to cents
        ]);
    }

    /**
     * Create transaction for current month.
     */
    public function currentMonth(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => $this->faker->dateTimeBetween(now()->startOfMonth(), now()),
        ]);
    }

    /**
     * Create transaction for last month.
     */
    public function lastMonth(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => $this->faker->dateTimeBetween(
                now()->subMonth()->startOfMonth(),
                now()->subMonth()->endOfMonth()
            ),
        ]);
    }

    /**
     * Create transaction for specific date.
     */
    public function forDate(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => $date,
        ]);
    }
}
