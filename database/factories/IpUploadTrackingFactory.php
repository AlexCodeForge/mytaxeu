<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\IpUploadTracking;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\IpUploadTracking>
 */
class IpUploadTrackingFactory extends Factory
{
    protected $model = IpUploadTracking::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ip_address' => $this->faker->ipv4(),
            'upload_count' => $this->faker->numberBetween(1, 50),
            'total_lines_attempted' => $this->faker->numberBetween(100, 10000),
            'last_upload_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Create a high activity IP.
     */
    public function highActivity(): static
    {
        return $this->state(fn (array $attributes) => [
            'upload_count' => $this->faker->numberBetween(100, 500),
            'total_lines_attempted' => $this->faker->numberBetween(50000, 200000),
        ]);
    }

    /**
     * Create a recent activity IP.
     */
    public function recentActivity(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_upload_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
        ]);
    }

    /**
     * Create an old activity IP.
     */
    public function oldActivity(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_upload_at' => $this->faker->dateTimeBetween('-6 months', '-1 month'),
        ]);
    }
}
