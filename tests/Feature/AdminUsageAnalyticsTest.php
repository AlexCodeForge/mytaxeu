<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Upload;
use App\Models\UploadMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUsageAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create(['is_admin' => true]);
        $this->regularUser = User::factory()->create(['is_admin' => false]);
    }

    /** @test */
    public function admin_can_get_system_wide_usage_overview(): void
    {
        // Create test data for multiple users
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        UploadMetric::factory()->completed()->count(3)->create([
            'user_id' => $user1->id,
            'line_count' => 500,
        ]);

        UploadMetric::factory()->completed()->count(2)->create([
            'user_id' => $user2->id,
            'line_count' => 300,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/usage/overview');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_users',
                    'active_users',
                    'total_uploads',
                    'total_lines_processed',
                    'total_processing_time',
                    'total_credits_consumed',
                    'average_file_size',
                    'success_rate',
                    'top_users',
                    'usage_trends',
                ]
            ]);

        $data = $response->json('data');
        $this->assertGreaterThan(0, $data['total_uploads']);
        $this->assertGreaterThan(0, $data['total_lines_processed']);
    }

    /** @test */
    public function admin_can_get_user_usage_statistics_with_pagination(): void
    {
        // Create multiple users with usage data
        $users = User::factory()->count(15)->create();

        foreach ($users as $user) {
            UploadMetric::factory()->completed()->count(rand(1, 5))->create([
                'user_id' => $user->id,
            ]);
        }

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/usage/users?page=1&per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'total_uploads',
                        'successful_uploads',
                        'failed_uploads',
                        'total_lines_processed',
                        'total_credits_consumed',
                        'current_month_usage',
                        'last_upload_at',
                        'avg_processing_time',
                    ]
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ]
            ]);

        $this->assertLessThanOrEqual(10, count($response->json('data')));
    }

    /** @test */
    public function admin_can_search_users_by_name_or_email(): void
    {
        $targetUser = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
        ]);

        User::factory()->count(5)->create(); // Other users

        UploadMetric::factory()->completed()->create([
            'user_id' => $targetUser->id,
        ]);

        // Search by name
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/usage/users?search=John');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($targetUser->id, $data[0]['id']);

        // Search by email
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/usage/users?search=john.doe@example.com');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($targetUser->id, $data[0]['id']);
    }

    /** @test */
    public function admin_can_filter_users_by_usage_criteria(): void
    {
        $heavyUser = User::factory()->create();
        $lightUser = User::factory()->create();

        // Heavy user with many uploads
        UploadMetric::factory()->completed()->count(10)->create([
            'user_id' => $heavyUser->id,
            'line_count' => 1000,
        ]);

        // Light user with few uploads
        UploadMetric::factory()->completed()->count(2)->create([
            'user_id' => $lightUser->id,
            'line_count' => 100,
        ]);

        // Filter by minimum uploads
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/usage/users?min_uploads=5');

        $response->assertStatus(200);
        $data = $response->json('data');

        $userIds = array_column($data, 'id');
        $this->assertContains($heavyUser->id, $userIds);
        $this->assertNotContains($lightUser->id, $userIds);
    }

    /** @test */
    public function admin_can_sort_users_by_different_metrics(): void
    {
        $user1 = User::factory()->create(['name' => 'Alice']);
        $user2 = User::factory()->create(['name' => 'Bob']);

        UploadMetric::factory()->completed()->count(5)->create([
            'user_id' => $user1->id,
            'line_count' => 100,
        ]);

        UploadMetric::factory()->completed()->count(10)->create([
            'user_id' => $user2->id,
            'line_count' => 200,
        ]);

        // Sort by total uploads descending
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/usage/users?sort_by=total_uploads&sort_direction=desc');

        $response->assertStatus(200);
        $data = $response->json('data');

        // Bob should be first (10 uploads) followed by Alice (5 uploads)
        $this->assertEquals($user2->id, $data[0]['id']);
        $this->assertEquals($user1->id, $data[1]['id']);
    }

    /** @test */
    public function admin_can_export_system_usage_data(): void
    {
        $users = User::factory()->count(3)->create();

        foreach ($users as $user) {
            UploadMetric::factory()->completed()->count(2)->create([
                'user_id' => $user->id,
            ]);
        }

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/usage/export');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'csv_content',
                    'filename',
                    'total_records',
                ]
            ]);

        $data = $response->json('data');
        $this->assertStringContainsString('Usuario', $data['csv_content']); // Spanish header
        $this->assertGreaterThan(0, $data['total_records']);
    }

    /** @test */
    public function admin_can_export_filtered_usage_data(): void
    {
        $targetUser = User::factory()->create();
        $otherUser = User::factory()->create();

        UploadMetric::factory()->completed()->create([
            'user_id' => $targetUser->id,
            'created_at' => now()->subDays(5),
        ]);

        UploadMetric::factory()->completed()->create([
            'user_id' => $otherUser->id,
            'created_at' => now()->subDays(15),
        ]);

        // Export with date filter
        $startDate = now()->subWeek()->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/admin/usage/export?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals(1, $data['total_records']); // Only one record in date range
    }

    /** @test */
    public function admin_can_get_usage_trends_over_time(): void
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

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/usage/trends?days=7');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'date',
                        'total_uploads',
                        'total_lines',
                        'unique_users',
                        'success_rate',
                    ]
                ]
            ]);

        $this->assertGreaterThan(0, count($response->json('data')));
    }

    /** @test */
    public function admin_can_get_detailed_user_metrics(): void
    {
        $user = User::factory()->create();

        UploadMetric::factory()->completed()->count(3)->create([
            'user_id' => $user->id,
            'processing_duration_seconds' => 45,
        ]);

        UploadMetric::factory()->failed()->count(1)->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/admin/usage/users/{$user->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'created_at',
                    ],
                    'metrics' => [
                        'total_uploads',
                        'successful_uploads',
                        'failed_uploads',
                        'total_lines_processed',
                        'avg_processing_time',
                        'total_credits_consumed',
                        'current_month_usage',
                    ],
                    'recent_uploads' => [
                        '*' => [
                            'id',
                            'file_name',
                            'status',
                            'line_count',
                            'created_at',
                        ]
                    ]
                ]
            ]);

        $metrics = $response->json('data.metrics');
        $this->assertEquals(4, $metrics['total_uploads']);
        $this->assertEquals(3, $metrics['successful_uploads']);
        $this->assertEquals(1, $metrics['failed_uploads']);
    }

    /** @test */
    public function regular_user_cannot_access_admin_analytics(): void
    {
        $this->actingAs($this->regularUser)
            ->getJson('/api/admin/usage/overview')
            ->assertStatus(403);

        $this->actingAs($this->regularUser)
            ->getJson('/api/admin/usage/users')
            ->assertStatus(403);

        $this->actingAs($this->regularUser)
            ->getJson('/api/admin/usage/export')
            ->assertStatus(403);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_admin_analytics(): void
    {
        $this->getJson('/api/admin/usage/overview')
            ->assertStatus(401);

        $this->getJson('/api/admin/usage/users')
            ->assertStatus(401);
    }

    /** @test */
    public function admin_can_get_top_users_by_usage(): void
    {
        $heavyUser = User::factory()->create(['name' => 'Heavy User']);
        $lightUser = User::factory()->create(['name' => 'Light User']);

        UploadMetric::factory()->completed()->count(10)->create([
            'user_id' => $heavyUser->id,
            'line_count' => 1000,
        ]);

        UploadMetric::factory()->completed()->count(2)->create([
            'user_id' => $lightUser->id,
            'line_count' => 100,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/usage/top-users?limit=5&metric=total_lines');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'user_id',
                        'name',
                        'email',
                        'metric_value',
                        'rank',
                    ]
                ]
            ]);

        $data = $response->json('data');
        $this->assertEquals($heavyUser->id, $data[0]['user_id']); // Heavy user should be first
    }

    /** @test */
    public function admin_analytics_includes_performance_metrics(): void
    {
        $user = User::factory()->create();

        UploadMetric::factory()->completed()->create([
            'user_id' => $user->id,
            'processing_duration_seconds' => 120,
            'file_size_bytes' => 1024000, // 1MB
        ]);

        UploadMetric::factory()->completed()->create([
            'user_id' => $user->id,
            'processing_duration_seconds' => 60,
            'file_size_bytes' => 512000, // 0.5MB
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/usage/performance');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'avg_processing_time',
                    'avg_file_size',
                    'total_processing_time',
                    'efficiency_metrics',
                ]
            ]);

        $data = $response->json('data');
        $this->assertEquals(90, $data['avg_processing_time']); // (120 + 60) / 2
    }
}
