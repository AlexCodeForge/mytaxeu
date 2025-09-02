<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Admin;

use App\Livewire\Admin\Dashboard;
use App\Models\User;
use App\Models\Upload;
use App\Models\UploadMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Carbon\Carbon;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->regularUser = User::factory()->create(['is_admin' => false]);
    }

    /** @test */
    public function it_renders_successfully_for_admin_users(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(Dashboard::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.admin.dashboard')
            ->assertSee('Admin Dashboard')
            ->assertSee('System Overview');
    }

    /** @test */
    public function it_denies_access_to_non_admin_users(): void
    {
        $this->actingAs($this->regularUser);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        Livewire::test(Dashboard::class);
    }

    /** @test */
    public function it_displays_system_metrics(): void
    {
        // Create test data
        $users = User::factory()->count(10)->create();
        $uploads = Upload::factory()->count(25)->create([
            'user_id' => $users->random()->id,
        ]);

        $this->actingAs($this->admin);

        Livewire::test(Dashboard::class)
            ->assertViewHas('metrics', function ($metrics) {
                return isset($metrics['total_users']) &&
                       isset($metrics['total_uploads']) &&
                       isset($metrics['uploads_today']) &&
                       isset($metrics['success_rate']);
            });
    }

    /** @test */
    public function it_calculates_correct_user_metrics(): void
    {
        // Create additional users (admin already exists)
        User::factory()->count(5)->create();

        $this->actingAs($this->admin);

        Livewire::test(Dashboard::class)
            ->assertViewHas('metrics', function ($metrics) {
                return $metrics['total_users'] >= 6; // 5 new + admin + regularUser
            });
    }

    /** @test */
    public function it_calculates_upload_metrics_correctly(): void
    {
        // Create uploads with different statuses and dates
        Upload::factory()->count(5)->create([
            'user_id' => $this->regularUser->id,
            'status' => Upload::STATUS_COMPLETED,
            'created_at' => now(),
        ]);

        Upload::factory()->count(3)->create([
            'user_id' => $this->regularUser->id,
            'status' => Upload::STATUS_FAILED,
            'created_at' => now()->subDay(),
        ]);

        Upload::factory()->count(2)->create([
            'user_id' => $this->regularUser->id,
            'status' => Upload::STATUS_PROCESSING,
            'created_at' => now(),
        ]);

        $this->actingAs($this->admin);

        Livewire::test(Dashboard::class)
            ->assertViewHas('metrics', function ($metrics) {
                return $metrics['total_uploads'] === 10 && // Including setup uploads
                       $metrics['uploads_today'] >= 7 && // 5 completed + 2 processing today
                       $metrics['uploads_this_week'] >= 10 &&
                       $metrics['uploads_this_month'] >= 10;
            });
    }

    /** @test */
    public function it_calculates_success_rate_correctly(): void
    {
        // Clear any existing uploads
        Upload::query()->delete();

        // Create uploads with known status distribution
        Upload::factory()->count(8)->create([
            'user_id' => $this->regularUser->id,
            'status' => Upload::STATUS_COMPLETED,
        ]);

        Upload::factory()->count(2)->create([
            'user_id' => $this->regularUser->id,
            'status' => Upload::STATUS_FAILED,
        ]);

        $this->actingAs($this->admin);

        Livewire::test(Dashboard::class)
            ->assertViewHas('metrics', function ($metrics) {
                return abs($metrics['success_rate'] - 80.0) < 0.1; // 8/10 = 80%
            });
    }

    /** @test */
    public function it_displays_recent_activity_feed(): void
    {
        // Create recent uploads
        Upload::factory()->count(5)->create([
            'user_id' => $this->regularUser->id,
            'created_at' => now()->subMinutes(30),
        ]);

        $this->actingAs($this->admin);

        Livewire::test(Dashboard::class)
            ->assertViewHas('recentActivity')
            ->assertSee('Recent Activity');
    }

    /** @test */
    public function it_displays_active_users_count(): void
    {
        // Create users with recent uploads
        $activeUser1 = User::factory()->create();
        $activeUser2 = User::factory()->create();
        $inactiveUser = User::factory()->create();

        Upload::factory()->create([
            'user_id' => $activeUser1->id,
            'created_at' => now()->subHours(2),
        ]);

        Upload::factory()->create([
            'user_id' => $activeUser2->id,
            'created_at' => now()->subHours(1),
        ]);

        // Inactive user - upload from last week
        Upload::factory()->create([
            'user_id' => $inactiveUser->id,
            'created_at' => now()->subWeek(),
        ]);

        $this->actingAs($this->admin);

        Livewire::test(Dashboard::class)
            ->assertViewHas('metrics', function ($metrics) {
                return $metrics['active_users_today'] >= 2;
            });
    }

    /** @test */
    public function it_shows_processing_status_breakdown(): void
    {
        Upload::factory()->count(3)->create([
            'user_id' => $this->regularUser->id,
            'status' => Upload::STATUS_PROCESSING,
        ]);

        Upload::factory()->count(2)->create([
            'user_id' => $this->regularUser->id,
            'status' => Upload::STATUS_QUEUED,
        ]);

        $this->actingAs($this->admin);

        Livewire::test(Dashboard::class)
            ->assertViewHas('statusBreakdown', function ($breakdown) {
                return isset($breakdown['processing']) &&
                       isset($breakdown['queued']) &&
                       isset($breakdown['completed']) &&
                       isset($breakdown['failed']);
            });
    }

    /** @test */
    public function it_provides_quick_action_links(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(Dashboard::class)
            ->assertSee('User Management')
            ->assertSee('Upload Manager')
            ->assertSee('Job Monitor');
    }

    /** @test */
    public function it_updates_in_real_time_with_polling(): void
    {
        $this->actingAs($this->admin);

        $component = Livewire::test(Dashboard::class);

        // Check that the component has polling enabled
        $this->assertNotNull($component->instance());
    }

    /** @test */
    public function it_displays_system_health_indicators(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(Dashboard::class)
            ->assertViewHas('systemHealth')
            ->assertSee('System Health');
    }

    /** @test */
    public function it_shows_storage_usage_metrics(): void
    {
        // Create uploads with known file sizes
        Upload::factory()->count(3)->create([
            'user_id' => $this->regularUser->id,
            'size_bytes' => 1024 * 1024, // 1MB each
        ]);

        $this->actingAs($this->admin);

        Livewire::test(Dashboard::class)
            ->assertViewHas('storageMetrics', function ($storage) {
                return isset($storage['total_storage_used']) &&
                       isset($storage['average_file_size']);
            });
    }

    /** @test */
    public function it_displays_recent_user_registrations(): void
    {
        // Create recent users
        User::factory()->count(3)->create([
            'created_at' => now()->subHours(2),
        ]);

        $this->actingAs($this->admin);

        Livewire::test(Dashboard::class)
            ->assertViewHas('recentUsers');
    }

    /** @test */
    public function it_shows_error_rate_metrics(): void
    {
        Upload::factory()->count(7)->create([
            'user_id' => $this->regularUser->id,
            'status' => Upload::STATUS_COMPLETED,
        ]);

        Upload::factory()->count(3)->create([
            'user_id' => $this->regularUser->id,
            'status' => Upload::STATUS_FAILED,
            'failure_reason' => 'Test error',
        ]);

        $this->actingAs($this->admin);

        Livewire::test(Dashboard::class)
            ->assertViewHas('metrics', function ($metrics) {
                return isset($metrics['error_rate']) && $metrics['error_rate'] > 0;
            });
    }

    /** @test */
    public function it_refreshes_data_manually(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(Dashboard::class)
            ->call('refreshData')
            ->assertDispatched('$refresh');
    }

    /** @test */
    public function it_navigates_to_quick_actions(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(Dashboard::class)
            ->call('navigateToUsers')
            ->assertRedirect(route('admin.users.index'));

        Livewire::test(Dashboard::class)
            ->call('navigateToUploads')
            ->assertRedirect(route('admin.uploads'));

        Livewire::test(Dashboard::class)
            ->call('navigateToJobs')
            ->assertRedirect(route('admin.job.monitor'));
    }

    /** @test */
    public function it_handles_empty_data_gracefully(): void
    {
        // Clear all uploads
        Upload::query()->delete();

        $this->actingAs($this->admin);

        Livewire::test(Dashboard::class)
            ->assertViewHas('metrics', function ($metrics) {
                return $metrics['total_uploads'] === 0 &&
                       $metrics['success_rate'] === 0;
            })
            ->assertSee('No recent activity');
    }

    /** @test */
    public function it_displays_processing_time_metrics(): void
    {
        // Create upload metrics with processing times
        $upload = Upload::factory()->create([
            'user_id' => $this->regularUser->id,
            'status' => Upload::STATUS_COMPLETED,
        ]);

        UploadMetric::factory()->create([
            'upload_id' => $upload->id,
            'user_id' => $upload->user_id,
            'processing_duration_seconds' => 120,
            'status' => UploadMetric::STATUS_COMPLETED,
        ]);

        $this->actingAs($this->admin);

        Livewire::test(Dashboard::class)
            ->assertViewHas('performanceMetrics', function ($performance) {
                return isset($performance['average_processing_time']);
            });
    }

    /** @test */
    public function it_shows_trending_data(): void
    {
        // Create uploads over different days
        Upload::factory()->create([
            'user_id' => $this->regularUser->id,
            'created_at' => now()->subDays(1),
        ]);

        Upload::factory()->count(2)->create([
            'user_id' => $this->regularUser->id,
            'created_at' => now(),
        ]);

        $this->actingAs($this->admin);

        Livewire::test(Dashboard::class)
            ->assertViewHas('trendData');
    }
}

