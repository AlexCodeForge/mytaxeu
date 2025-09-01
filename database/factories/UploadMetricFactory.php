<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Upload;
use App\Models\UploadMetric;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UploadMetric>
 */
class UploadMetricFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startTime = $this->faker->dateTimeBetween('-1 week', 'now');

        return [
            'user_id' => User::factory(),
            'upload_id' => Upload::factory(),
            'file_name' => $this->faker->words(3, true) . '.csv',
            'file_size_bytes' => $this->faker->numberBetween(1000, 10000000),
            'line_count' => $this->faker->numberBetween(10, 1000),
            'processing_started_at' => $startTime,
            'processing_completed_at' => null,
            'processing_duration_seconds' => null,
            'credits_consumed' => $this->faker->numberBetween(1, 10),
            'status' => $this->faker->randomElement(UploadMetric::STATUSES),
            'error_message' => null,
        ];
    }

    /**
     * Configure the model to be pending.
     */
    public function pending(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => UploadMetric::STATUS_PENDING,
            'processing_started_at' => null,
            'processing_completed_at' => null,
            'processing_duration_seconds' => null,
            'credits_consumed' => 0,
        ]);
    }

    /**
     * Configure the model to be processing.
     */
    public function processing(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => UploadMetric::STATUS_PROCESSING,
            'processing_started_at' => $this->faker->dateTimeBetween('-1 hour', 'now'),
            'processing_completed_at' => null,
            'processing_duration_seconds' => null,
        ]);
    }

    /**
     * Configure the model to be completed.
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $startTime = $this->faker->dateTimeBetween('-1 week', '-1 hour');

            // Use provided duration or generate one
            $processingDuration = $attributes['processing_duration_seconds'] ?? $this->faker->numberBetween(10, 600);
            $endTime = (clone $startTime)->modify("+{$processingDuration} seconds");

            return [
                'status' => UploadMetric::STATUS_COMPLETED,
                'processing_started_at' => $startTime,
                'processing_completed_at' => $endTime,
                'processing_duration_seconds' => $processingDuration,
                'credits_consumed' => $attributes['credits_consumed'] ?? $this->faker->numberBetween(1, 10),
            ];
        });
    }

    /**
     * Configure the model to be failed.
     */
    public function failed(): static
    {
        return $this->state(function (array $attributes) {
            $startTime = $this->faker->dateTimeBetween('-1 week', '-1 hour');

            // Use provided duration or generate one
            $processingDuration = $attributes['processing_duration_seconds'] ?? $this->faker->numberBetween(5, 300);
            $endTime = (clone $startTime)->modify("+{$processingDuration} seconds");

            return [
                'status' => UploadMetric::STATUS_FAILED,
                'processing_started_at' => $startTime,
                'processing_completed_at' => $endTime,
                'processing_duration_seconds' => $processingDuration,
                'error_message' => $attributes['error_message'] ?? $this->faker->sentence(),
                'credits_consumed' => 0,
            ];
        });
    }

    /**
     * Configure with specific line count.
     */
    public function withLineCount(int $lineCount): static
    {
        return $this->state(fn(array $attributes) => [
            'line_count' => $lineCount,
        ]);
    }

    /**
     * Configure with specific file size.
     */
    public function withFileSize(int $fileSizeBytes): static
    {
        return $this->state(fn(array $attributes) => [
            'file_size_bytes' => $fileSizeBytes,
        ]);
    }
}
