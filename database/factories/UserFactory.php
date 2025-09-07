<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'credits' => fake()->numberBetween(0, 10000),
            'is_admin' => false,
            'total_lines_processed' => fake()->numberBetween(0, 50000),
            'current_month_usage' => fake()->numberBetween(0, 5000),
            'usage_reset_date' => now()->addMonth(),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Create an admin user.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
            'credits' => fake()->numberBetween(5000, 20000),
        ]);
    }

    /**
     * Create a user with high credits.
     */
    public function withHighCredits(): static
    {
        return $this->state(fn (array $attributes) => [
            'credits' => fake()->numberBetween(10000, 50000),
        ]);
    }

    /**
     * Create a user with no credits.
     */
    public function withNoCredits(): static
    {
        return $this->state(fn (array $attributes) => [
            'credits' => 0,
        ]);
    }

    /**
     * Create a user with high usage.
     */
    public function withHighUsage(): static
    {
        return $this->state(fn (array $attributes) => [
            'total_lines_processed' => fake()->numberBetween(50000, 200000),
            'current_month_usage' => fake()->numberBetween(5000, 15000),
        ]);
    }
}
