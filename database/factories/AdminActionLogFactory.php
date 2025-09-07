<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AdminActionLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AdminActionLog>
 */
class AdminActionLogFactory extends Factory
{
    protected $model = AdminActionLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admin_user_id' => User::factory()->admin(),
            'action' => $this->faker->randomElement([
                'user_updated',
                'user_suspended',
                'user_unsuspended',
                'credits_added',
                'credits_removed',
                'upload_deleted',
                'limit_override',
                'limit_reset',
                'usage_reset',
            ]),
            'target_user_id' => User::factory(),
            'target_upload_id' => null,
            'details' => $this->faker->sentence(),
            'metadata' => [
                'old_value' => $this->faker->numberBetween(0, 1000),
                'new_value' => $this->faker->numberBetween(1000, 5000),
            ],
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'created_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Action for limit override.
     */
    public function limitOverride(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'limit_override',
            'details' => 'Admin override for upload limit',
            'metadata' => [
                'old_limit' => $this->faker->numberBetween(1000, 5000),
                'new_limit' => $this->faker->numberBetween(10000, 50000),
            ],
        ]);
    }

    /**
     * Action for limit reset.
     */
    public function limitReset(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'limit_reset',
            'details' => 'Limit reset to default value',
            'metadata' => [
                'old_limit' => $this->faker->numberBetween(10000, 50000),
                'new_limit' => $this->faker->numberBetween(1000, 5000),
            ],
        ]);
    }

    /**
     * Action for usage reset.
     */
    public function usageReset(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'usage_reset',
            'details' => 'Usage reset by admin',
            'metadata' => [
                'old_usage' => $this->faker->numberBetween(1000, 10000),
                'new_usage' => 0,
            ],
        ]);
    }
}
