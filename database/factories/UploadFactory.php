<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Upload;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Upload>
 */
class UploadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'original_name' => $this->faker->words(3, true) . '.csv',
            'disk' => 'local',
            'path' => 'uploads/' . $this->faker->uuid() . '.csv',
            'transformed_path' => null,
            'size_bytes' => $this->faker->numberBetween(1000, 10000000),
            'csv_line_count' => $this->faker->numberBetween(10, 1000),
            'rows_count' => $this->faker->numberBetween(10, 1000),
            'status' => $this->faker->randomElement(Upload::STATUSES),
            'failure_reason' => null,
            'processed_at' => null,
        ];
    }

    /**
     * Configure the model to be completed.
     */
    public function completed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => Upload::STATUS_COMPLETED,
            'processed_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'transformed_path' => 'uploads/1/output/test_' . $this->faker->uuid() . '_transformado.csv',
        ]);
    }

    /**
     * Configure the model to be failed.
     */
    public function failed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => Upload::STATUS_FAILED,
            'failure_reason' => $this->faker->sentence(),
            'processed_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Configure the model to be processing.
     */
    public function processing(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => Upload::STATUS_PROCESSING,
        ]);
    }
}
