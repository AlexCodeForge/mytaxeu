<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AdminSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AdminSetting>
 */
class AdminSettingFactory extends Factory
{
    protected $model = AdminSetting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->slug(2, '_'),
            'value' => $this->faker->sentence(),
            'encrypted' => false,
            'description' => $this->faker->sentence(),
        ];
    }

    /**
     * Create an encrypted setting.
     */
    public function encrypted(): static
    {
        return $this->state(fn (array $attributes) => [
            'encrypted' => true,
            'value' => $this->faker->uuid(),
            'description' => 'Encrypted setting: ' . $this->faker->sentence(),
        ]);
    }

    /**
     * Create Stripe configuration settings.
     */
    public function stripeConfig(): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => $this->faker->randomElement([
                'stripe_public_key',
                'stripe_secret_key',
                'stripe_webhook_secret',
                'stripe_test_mode',
            ]),
            'value' => $this->faker->randomElement([
                'pk_test_' . $this->faker->uuid(),
                'sk_test_' . $this->faker->uuid(),
                'whsec_' . $this->faker->uuid(),
                '1',
            ]),
            'encrypted' => $this->faker->boolean(70),
            'description' => 'Stripe configuration setting',
        ]);
    }

    /**
     * Create system settings.
     */
    public function systemSetting(): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => $this->faker->randomElement([
                'maintenance_mode',
                'max_upload_size',
                'default_credits',
                'credit_rate',
                'notification_email',
                'system_timezone',
            ]),
            'value' => $this->faker->randomElement([
                '0',
                '10485760', // 10MB
                '1000',
                '0.01',
                'admin@example.com',
                'UTC',
            ]),
            'encrypted' => false,
            'description' => 'System configuration setting',
        ]);
    }
}
