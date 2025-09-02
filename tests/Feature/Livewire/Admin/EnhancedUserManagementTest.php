<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Admin;

use App\Livewire\Admin\EnhancedUserManagement;
use App\Models\User;
use App\Models\Upload;
use App\Models\UploadMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Carbon\Carbon;

class EnhancedUserManagementTest extends TestCase
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
        $response = $this->actingAs($this->admin)
            ->get(route('admin.users.enhanced'));

        $response->assertOk();
        $response->assertSeeLivewire(EnhancedUserManagement::class);
    }

    /** @test */
    public function it_denies_access_to_non_admin_users(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $this->actingAs($this->regularUser)
            ->get(route('admin.users.enhanced'));
    }

    /** @test */
    public function it_displays_users_with_activity_statistics(): void
    {
        $user = User::factory()->create();

        // Create uploads for activity stats
        Upload::factory()->count(3)->create([
            'user_id' => $user->id,
            'status' => Upload::STATUS_COMPLETED,
        ]);

        Upload::factory()->create([
            'user_id' => $user->id,
            'status' => Upload::STATUS_FAILED,
        ]);

        Livewire::actingAs($this->admin)
            ->test(EnhancedUserManagement::class)
            ->assertSee($user->name)
            ->assertSee($user->email)
            ->assertSee('4') // Total uploads
            ->assertSee('75%'); // Success rate (3/4)
    }

    /** @test */
    public function it_filters_users_by_activity_status(): void
    {
        $activeUser = User::factory()->create();
        $inactiveUser = User::factory()->create();

        // Only active user has uploads
        Upload::factory()->create(['user_id' => $activeUser->id]);

        Livewire::actingAs($this->admin)
            ->test(EnhancedUserManagement::class)
            ->set('activityFilter', 'active')
            ->assertSee($activeUser->email)
            ->assertDontSee($inactiveUser->email);

        Livewire::actingAs($this->admin)
            ->test(EnhancedUserManagement::class)
            ->set('activityFilter', 'inactive')
            ->assertSee($inactiveUser->email)
            ->assertDontSee($activeUser->email);
    }

    /** @test */
    public function it_searches_users_by_name_and_email(): void
    {
        $user1 = User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user2 = User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        Livewire::actingAs($this->admin)
            ->test(EnhancedUserManagement::class)
            ->set('search', 'John')
            ->assertSee($user1->email)
            ->assertDontSee($user2->email);

        Livewire::actingAs($this->admin)
            ->test(EnhancedUserManagement::class)
            ->set('search', 'jane@example.com')
            ->assertSee($user2->email)
            ->assertDontSee($user1->email);
    }

    /** @test */
    public function it_sorts_users_by_different_criteria(): void
    {
        $user1 = User::factory()->create(['created_at' => now()->subDays(2)]);
        $user2 = User::factory()->create(['created_at' => now()->subDays(1)]);

        // Create uploads to test upload count sorting
        Upload::factory()->count(5)->create(['user_id' => $user1->id]);
        Upload::factory()->count(2)->create(['user_id' => $user2->id]);

        Livewire::actingAs($this->admin)
            ->test(EnhancedUserManagement::class)
            ->set('sortBy', 'uploads_count')
            ->set('sortDirection', 'desc')
            ->assertSeeInOrder([$user1->email, $user2->email]);

        Livewire::actingAs($this->admin)
            ->test(EnhancedUserManagement::class)
            ->set('sortBy', 'created_at')
            ->set('sortDirection', 'desc')
            ->assertSeeInOrder([$user2->email, $user1->email]);
    }

    /** @test */
    public function it_opens_user_profile_modal(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(EnhancedUserManagement::class)
            ->call('openUserProfile', $user->id)
            ->assertSet('selectedUserId', $user->id)
            ->assertSet('showUserModal', true);
    }

    /** @test */
    public function it_displays_user_activity_timeline_in_modal(): void
    {
        $user = User::factory()->create();

        $upload1 = Upload::factory()->create([
            'user_id' => $user->id,
            'created_at' => now()->subDays(1),
            'status' => Upload::STATUS_COMPLETED,
        ]);

        $upload2 = Upload::factory()->create([
            'user_id' => $user->id,
            'created_at' => now(),
            'status' => Upload::STATUS_FAILED,
        ]);

        Livewire::actingAs($this->admin)
            ->test(EnhancedUserManagement::class)
            ->call('openUserProfile', $user->id)
            ->assertSee($upload1->original_name)
            ->assertSee($upload2->original_name)
            ->assertSee('Completed')
            ->assertSee('Failed');
    }

    /** @test */
    public function it_suspends_user_account(): void
    {
        $user = User::factory()->create(['is_suspended' => false]);

        Livewire::actingAs($this->admin)
            ->test(EnhancedUserManagement::class)
            ->call('suspendUser', $user->id)
            ->assertDispatched('show-notification', function ($data) {
                return $data['type'] === 'success' &&
                       str_contains($data['message'], 'suspended');
            });

        $this->assertTrue($user->fresh()->is_suspended);
    }

    /** @test */
    public function it_activates_suspended_user_account(): void
    {
        $user = User::factory()->create(['is_suspended' => true]);

        Livewire::actingAs($this->admin)
            ->test(EnhancedUserManagement::class)
            ->call('activateUser', $user->id)
            ->assertDispatched('show-notification', function ($data) {
                return $data['type'] === 'success' &&
                       str_contains($data['message'], 'activated');
            });

        $this->assertFalse($user->fresh()->is_suspended);
    }

    /** @test */
    public function it_prevents_admin_from_suspending_themselves(): void
    {
        Livewire::actingAs($this->admin)
            ->test(EnhancedUserManagement::class)
            ->call('suspendUser', $this->admin->id)
            ->assertDispatched('show-notification', function ($data) {
                return $data['type'] === 'error' &&
                       str_contains($data['message'], 'cannot suspend yourself');
            });

        $this->assertFalse($this->admin->fresh()->is_suspended);
    }

    /** @test */
    public function it_displays_user_statistics_summary(): void
    {
        $user = User::factory()->create();

        // Create uploads with metrics
        $upload1 = Upload::factory()->create([
            'user_id' => $user->id,
            'size_bytes' => 1024 * 1024, // 1MB
            'status' => Upload::STATUS_COMPLETED,
        ]);

        $upload2 = Upload::factory()->create([
            'user_id' => $user->id,
            'size_bytes' => 2 * 1024 * 1024, // 2MB
            'status' => Upload::STATUS_COMPLETED,
        ]);

        UploadMetric::factory()->create([
            'upload_id' => $upload1->id,
            'user_id' => $user->id,
            'processing_duration_seconds' => 120,
            'credits_consumed' => 5,
        ]);

        UploadMetric::factory()->create([
            'upload_id' => $upload2->id,
            'user_id' => $user->id,
            'processing_duration_seconds' => 180,
            'credits_consumed' => 8,
        ]);

        Livewire::actingAs($this->admin)
            ->test(EnhancedUserManagement::class)
            ->call('openUserProfile', $user->id)
            ->assertSee('3.00 MB') // Total storage
            ->assertSee('13') // Total credits consumed
            ->assertSee('2:30'); // Average processing time (150 seconds)
    }

    /** @test */
    public function it_filters_users_by_registration_date_range(): void
    {
        $oldUser = User::factory()->create(['created_at' => now()->subMonth()]);
        $newUser = User::factory()->create(['created_at' => now()]);

        Livewire::actingAs($this->admin)
            ->test(EnhancedUserManagement::class)
            ->set('dateFrom', now()->subWeeks(2)->format('Y-m-d'))
            ->set('dateTo', now()->format('Y-m-d'))
            ->assertSee($newUser->email)
            ->assertDontSee($oldUser->email);
    }

    /** @test */
    public function it_displays_recent_user_activity(): void
    {
        $user = User::factory()->create();

        $recentUpload = Upload::factory()->create([
            'user_id' => $user->id,
            'created_at' => now()->subHour(),
            'original_name' => 'recent_file.csv',
        ]);

        $oldUpload = Upload::factory()->create([
            'user_id' => $user->id,
            'created_at' => now()->subWeek(),
            'original_name' => 'old_file.csv',
        ]);

        Livewire::actingAs($this->admin)
            ->test(EnhancedUserManagement::class)
            ->call('openUserProfile', $user->id)
            ->assertSee('recent_file.csv')
            ->assertSee('old_file.csv');
    }

    /** @test */
    public function it_exports_user_activity_data(): void
    {
        $user = User::factory()->create();

        Upload::factory()->count(3)->create(['user_id' => $user->id]);

        Livewire::actingAs($this->admin)
            ->test(EnhancedUserManagement::class)
            ->call('exportUserActivity', $user->id)
            ->assertDispatched('start-export', function ($data) {
                return $data['type'] === 'users' &&
                       isset($data['filters']['user_id']);
            });
    }

    /** @test */
    public function it_tracks_admin_actions_for_audit_log(): void
    {
        $user = User::factory()->create(['is_suspended' => false]);

        Livewire::actingAs($this->admin)
            ->test(EnhancedUserManagement::class)
            ->call('suspendUser', $user->id);

        // Check that the action was logged
        $this->assertDatabaseHas('admin_action_logs', [
            'admin_user_id' => $this->admin->id,
            'action' => 'user_suspended',
            'target_user_id' => $user->id,
        ]);
    }

    /** @test */
    public function it_shows_user_login_activity(): void
    {
        $user = User::factory()->create();

        // Simulate login activity (this would normally be tracked by middleware)
        \DB::table('user_login_logs')->insert([
            'user_id' => $user->id,
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Test Browser',
            'logged_in_at' => now()->subDays(1),
            'created_at' => now()->subDays(1),
            'updated_at' => now()->subDays(1),
        ]);

        Livewire::actingAs($this->admin)
            ->test(EnhancedUserManagement::class)
            ->call('openUserProfile', $user->id)
            ->assertSee('192.168.1.1')
            ->assertSee('Test Browser');
    }

    /** @test */
    public function it_calculates_user_engagement_metrics(): void
    {
        $user = User::factory()->create(['created_at' => now()->subDays(30)]);

        // Create activity over time
        Upload::factory()->count(5)->create([
            'user_id' => $user->id,
            'created_at' => now()->subDays(5),
        ]);

        Upload::factory()->count(3)->create([
            'user_id' => $user->id,
            'created_at' => now()->subDays(15),
        ]);

        Livewire::actingAs($this->admin)
            ->test(EnhancedUserManagement::class)
            ->call('openUserProfile', $user->id)
            ->assertSee('8') // Total uploads
            ->assertSee('26.7%'); // Engagement rate (8 uploads / 30 days)
    }

    /** @test */
    public function it_handles_bulk_user_operations(): void
    {
        $users = User::factory()->count(3)->create(['is_suspended' => false]);

        Livewire::actingAs($this->admin)
            ->test(EnhancedUserManagement::class)
            ->set('selectedUsers', $users->pluck('id')->toArray())
            ->call('bulkSuspendUsers')
            ->assertDispatched('show-notification', function ($data) {
                return $data['type'] === 'success' &&
                       str_contains($data['message'], '3 users suspended');
            });

        foreach ($users as $user) {
            $this->assertTrue($user->fresh()->is_suspended);
        }
    }

    /** @test */
    public function it_prevents_bulk_suspension_of_admin_users(): void
    {
        $adminUser = User::factory()->create(['is_admin' => true, 'is_suspended' => false]);
        $regularUser = User::factory()->create(['is_admin' => false, 'is_suspended' => false]);

        Livewire::actingAs($this->admin)
            ->test(EnhancedUserManagement::class)
            ->set('selectedUsers', [$adminUser->id, $regularUser->id])
            ->call('bulkSuspendUsers')
            ->assertDispatched('show-notification', function ($data) {
                return $data['type'] === 'warning' &&
                       str_contains($data['message'], 'Admin users cannot be suspended');
            });

        $this->assertFalse($adminUser->fresh()->is_suspended);
        $this->assertTrue($regularUser->fresh()->is_suspended);
    }

    /** @test */
    public function it_closes_user_profile_modal(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(EnhancedUserManagement::class)
            ->call('openUserProfile', $user->id)
            ->assertSet('showUserModal', true)
            ->call('closeUserModal')
            ->assertSet('showUserModal', false)
            ->assertSet('selectedUserId', null);
    }

    /** @test */
    public function it_paginates_users_correctly(): void
    {
        User::factory()->count(25)->create();

        Livewire::actingAs($this->admin)
            ->test(EnhancedUserManagement::class)
            ->assertSee('Showing')
            ->assertSee('of');
    }

    /** @test */
    public function it_updates_page_when_filters_change(): void
    {
        User::factory()->count(25)->create();

        Livewire::actingAs($this->admin)
            ->test(EnhancedUserManagement::class)
            ->set('page', 2)
            ->assertSet('page', 2)
            ->set('search', 'test')
            ->assertSet('page', 1); // Should reset to page 1 when search changes
    }

    /** @test */
    public function it_shows_empty_state_when_no_users_match_filters(): void
    {
        Livewire::actingAs($this->admin)
            ->test(EnhancedUserManagement::class)
            ->set('search', 'nonexistent@example.com')
            ->assertSee('No users found')
            ->assertSee('Try adjusting your search criteria');
    }

    /** @test */
    public function it_displays_user_usage_trends(): void
    {
        $user = User::factory()->create();

        // Create uploads across different months
        Upload::factory()->create([
            'user_id' => $user->id,
            'created_at' => now()->subMonth(),
        ]);

        Upload::factory()->count(3)->create([
            'user_id' => $user->id,
            'created_at' => now(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(EnhancedUserManagement::class)
            ->call('openUserProfile', $user->id)
            ->assertSee('Monthly Usage Trend');
    }
}

