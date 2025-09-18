<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UserUploadLimit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserUploadLimit>
 */
class UserUploadLimitFactory extends Factory
{
    protected $model = UserUploadLimit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'csv_line_limit' => $this->faker->randomElement([
                1000, 5000, 10000, 25000, 50000, 100000
            ]),
            'expires_at' => $this->faker->dateTimeBetween('now', '+6 months'),
            'created_by' => User::factory()->admin(),
        ];
    }

    /**
     * Create an expired limit.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => $this->faker->dateTimeBetween('-1 month', '-1 day'),
        ]);
    }

    /**
     * Create a high limit.
     */
    public function highLimit(): static
    {
        return $this->state(fn (array $attributes) => [
            'csv_line_limit' => $this->faker->numberBetween(100000, 500000),
        ]);
    }

    /**
     * Create a low limit.
     */
    public function lowLimit(): static
    {
        return $this->state(fn (array $attributes) => [
            'csv_line_limit' => $this->faker->numberBetween(100, 1000),
        ]);
    }

    /**
     * Create a permanent limit (no expiration).
     */
    public function permanent(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => null,
        ]);
    }
}
