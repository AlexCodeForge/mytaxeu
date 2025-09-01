<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Upload;
use App\Models\UploadMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsageControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function authenticated_user_can_get_current_usage_statistics(): void
    {
        $user = User::factory()->create([
            'current_month_usage' => 1500,
            'total_lines_processed' => 5000,
        ]);

        // Create some upload metrics for the user
        UploadMetric::factory()->completed()->count(3)->create([
            'user_id' => $user->id,
            'line_count' => 500,
            'processing_duration_seconds' => 120,
            'credits_consumed' => 1,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/usage/current');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'current_month_usage',
                    'total_lines_processed',
                    'monthly_limit',
                    'remaining_limit',
                    'usage_percentage',
                    'total_uploads',
                    'successful_uploads',
                    'failed_uploads',
                    'average_processing_time',
                    'total_credits_consumed',
                ]
            ]);

        $this->assertEquals(1500, $response->json('data.current_month_usage'));
        $this->assertEquals(5000, $response->json('data.total_lines_processed'));
    }

    /** @test */
    public function authenticated_user_can_get_usage_history_with_pagination(): void
    {
        $user = User::factory()->create();

        // Create upload metrics for different dates
        UploadMetric::factory()->completed()->count(15)->create([
            'user_id' => $user->id,
            'created_at' => now()->subDays(rand(1, 30)),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/usage/history?page=1&per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'file_name',
                        'line_count',
                        'file_size_bytes',
                        'processing_duration_seconds',
                        'credits_consumed',
                        'status',
                        'created_at',
                        'processing_started_at',
                        'processing_completed_at',
                    ]
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ]
            ]);

        // Should return 10 items per page
        $this->assertLessThanOrEqual(10, count($response->json('data')));
    }

    /** @test */
    public function user_can_filter_usage_history_by_date_range(): void
    {
        $user = User::factory()->create();

        // Create metrics for different time periods
        $oldMetric = UploadMetric::factory()->completed()->create([
            'user_id' => $user->id,
            'created_at' => now()->subWeeks(3),
        ]);

        $recentMetric = UploadMetric::factory()->completed()->create([
            'user_id' => $user->id,
            'created_at' => now()->subDays(5),
        ]);

        // Filter for last week only
        $startDate = now()->subWeek()->toDateString();
        $endDate = now()->toDateString();

        $response = $this->actingAs($user)
            ->getJson("/api/usage/history?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($recentMetric->id, $data[0]['id']);
    }

    /** @test */
    public function user_can_filter_usage_history_by_status(): void
    {
        $user = User::factory()->create();

        $completedMetric = UploadMetric::factory()->completed()->create([
            'user_id' => $user->id,
        ]);

        $failedMetric = UploadMetric::factory()->failed()->create([
            'user_id' => $user->id,
        ]);

        // Filter for completed uploads only
        $response = $this->actingAs($user)
            ->getJson('/api/usage/history?status=completed');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($completedMetric->id, $data[0]['id']);
        $this->assertEquals('completed', $data[0]['status']);
    }

    /** @test */
    public function user_cannot_access_other_users_usage_data(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        UploadMetric::factory()->completed()->count(5)->create([
            'user_id' => $user2->id,
        ]);

        $response = $this->actingAs($user1)
            ->getJson('/api/usage/current');

        $response->assertStatus(200);

        // User1 should not see User2's data
        $this->assertEquals(0, $response->json('data.total_uploads'));
    }

    /** @test */
    public function unauthenticated_user_cannot_access_usage_endpoints(): void
    {
        $this->getJson('/api/usage/current')
            ->assertStatus(401);

        $this->getJson('/api/usage/history')
            ->assertStatus(401);
    }

    /** @test */
    public function user_can_get_usage_trends_data(): void
    {
        $user = User::factory()->create();

        // Create metrics over several days
        for ($i = 0; $i < 7; $i++) {
            UploadMetric::factory()->completed()->create([
                'user_id' => $user->id,
                'line_count' => 100 + $i * 10,
                'created_at' => now()->subDays($i),
            ]);
        }

        $response = $this->actingAs($user)
            ->getJson('/api/usage/trends?days=7');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'date',
                        'line_count',
                        'upload_count',
                    ]
                ]
            ]);

        $this->assertGreaterThan(0, count($response->json('data')));
    }

    /** @test */
    public function usage_history_returns_properly_formatted_data(): void
    {
        $user = User::factory()->create();
        $upload = Upload::factory()->completed()->create(['user_id' => $user->id]);

        $metric = UploadMetric::factory()->completed()->create([
            'user_id' => $user->id,
            'upload_id' => $upload->id,
            'file_name' => 'test-file.csv',
            'line_count' => 250,
            'file_size_bytes' => 1024000,
            'processing_duration_seconds' => 45,
            'credits_consumed' => 1,
            'status' => UploadMetric::STATUS_COMPLETED,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/usage/history');

        $response->assertStatus(200);

        $data = $response->json('data')[0];
        $this->assertEquals($metric->id, $data['id']);
        $this->assertEquals('test-file.csv', $data['file_name']);
        $this->assertEquals(250, $data['line_count']);
        $this->assertEquals(1024000, $data['file_size_bytes']);
        $this->assertEquals(45, $data['processing_duration_seconds']);
        $this->assertEquals(1, $data['credits_consumed']);
        $this->assertEquals('completed', $data['status']);
    }

    /** @test */
    public function usage_current_endpoint_calculates_correct_percentages(): void
    {
        $user = User::factory()->create([
            'current_month_usage' => 750, // 75% of 1000 limit
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/usage/current');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals(750, $data['current_month_usage']);
        $this->assertEquals(1000, $data['monthly_limit']); // Free tier limit
        $this->assertEquals(250, $data['remaining_limit']);
        $this->assertEquals(75.0, $data['usage_percentage']);
    }

    /** @test */
    public function usage_history_can_be_sorted_by_different_fields(): void
    {
        $user = User::factory()->create();

        // Create metrics with different line counts
        UploadMetric::factory()->completed()->create([
            'user_id' => $user->id,
            'line_count' => 100,
            'created_at' => now()->subDays(1),
        ]);

        UploadMetric::factory()->completed()->create([
            'user_id' => $user->id,
            'line_count' => 300,
            'created_at' => now()->subDays(2),
        ]);

        // Sort by line_count descending
        $response = $this->actingAs($user)
            ->getJson('/api/usage/history?sort_by=line_count&sort_direction=desc');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertGreaterThan($data[1]['line_count'], $data[0]['line_count']);
    }
}
