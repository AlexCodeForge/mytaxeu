<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Admin\UsageAnalytics;
use App\Livewire\Admin\UserUsageManager;
use App\Models\Upload;
use App\Models\UploadMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminUsageComponentTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = User::factory()->create(['is_admin' => true]);
    }

    /** @test */
    public function usage_analytics_component_displays_system_overview(): void
    {
        $users = User::factory()->count(3)->create();

        foreach ($users as $user) {
            UploadMetric::factory()->completed()->count(2)->create([
                'user_id' => $user->id,
                'line_count' => 500,
            ]);
        }

        Livewire::actingAs($this->adminUser)
            ->test(UsageAnalytics::class)
            ->assertStatus(200)
            ->assertSee('3') // Total active users
            ->assertSee('6') // Total uploads (3 users * 2 uploads)
            ->assertSee('3,000') // Total lines (6 uploads * 500 lines)
            ->assertSee('Análisis de Uso del Sistema'); // Spanish title
    }

    /** @test */
    public function usage_analytics_shows_trends_chart(): void
    {
        $user = User::factory()->create();

        // Create metrics over several days
        for ($i = 0; $i < 5; $i++) {
            UploadMetric::factory()->completed()->create([
                'user_id' => $user->id,
                'created_at' => now()->subDays($i),
            ]);
        }

        Livewire::actingAs($this->adminUser)
            ->test(UsageAnalytics::class)
            ->assertSee('Tendencias de Uso')
            ->assertSee('últimos 7 días');
    }

    /** @test */
    public function usage_analytics_can_filter_by_date_range(): void
    {
        $user = User::factory()->create();

        UploadMetric::factory()->completed()->create([
            'user_id' => $user->id,
            'created_at' => now()->subDays(2),
        ]);

        UploadMetric::factory()->completed()->create([
            'user_id' => $user->id,
            'created_at' => now()->subDays(10),
        ]);

        $component = Livewire::actingAs($this->adminUser)
            ->test(UsageAnalytics::class);

        // Apply date filter for last week only
        $component->set('startDate', now()->subWeek()->format('Y-m-d'))
            ->set('endDate', now()->format('Y-m-d'))
            ->call('applyFilters')
            ->assertSee('1'); // Should only show 1 recent upload
    }

    /** @test */
    public function user_usage_manager_displays_users_with_pagination(): void
    {
        User::factory()->count(25)->create()->each(function ($user) {
            UploadMetric::factory()->completed()->count(rand(1, 3))->create([
                'user_id' => $user->id,
            ]);
        });

        Livewire::actingAs($this->adminUser)
            ->test(UserUsageManager::class)
            ->assertStatus(200)
            ->assertSee('Gestión de Usuarios')
            ->assertSee('siguiente') // Next page link
            ->call('gotoPage', 2)
            ->assertSee('Página 2');
    }

    /** @test */
    public function user_usage_manager_can_search_users(): void
    {
        $targetUser = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        User::factory()->count(5)->create(); // Other users

        UploadMetric::factory()->completed()->create([
            'user_id' => $targetUser->id,
        ]);

        $component = Livewire::actingAs($this->adminUser)
            ->test(UserUsageManager::class);

        // Search by name
        $component->set('search', 'John')
            ->call('applySearch')
            ->assertSee('John Doe')
            ->assertSee('john@example.com');

        // Search should filter out other users
        $otherUsers = User::where('id', '!=', $targetUser->id)->limit(3)->get();
        foreach ($otherUsers as $user) {
            $component->assertDontSee($user->name);
        }
    }

    /** @test */
    public function user_usage_manager_can_sort_by_different_columns(): void
    {
        $user1 = User::factory()->create(['name' => 'Alice']);
        $user2 = User::factory()->create(['name' => 'Bob']);

        // Give Alice more uploads
        UploadMetric::factory()->completed()->count(5)->create([
            'user_id' => $user1->id,
        ]);

        UploadMetric::factory()->completed()->count(2)->create([
            'user_id' => $user2->id,
        ]);

        $component = Livewire::actingAs($this->adminUser)
            ->test(UserUsageManager::class);

        // Sort by total uploads descending
        $component->call('sortBy', 'total_uploads', 'desc');

        // Alice should appear before Bob
        $html = $component->get('html');
        $alicePos = strpos($html, 'Alice');
        $bobPos = strpos($html, 'Bob');

        $this->assertLessThan($bobPos, $alicePos);
    }

    /** @test */
    public function user_usage_manager_can_filter_by_usage_criteria(): void
    {
        $heavyUser = User::factory()->create(['name' => 'Heavy User']);
        $lightUser = User::factory()->create(['name' => 'Light User']);

        UploadMetric::factory()->completed()->count(10)->create([
            'user_id' => $heavyUser->id,
        ]);

        UploadMetric::factory()->completed()->count(1)->create([
            'user_id' => $lightUser->id,
        ]);

        $component = Livewire::actingAs($this->adminUser)
            ->test(UserUsageManager::class);

        // Filter by minimum uploads
        $component->set('minUploads', 5)
            ->call('applyFilters')
            ->assertSee('Heavy User')
            ->assertDontSee('Light User');
    }

    /** @test */
    public function usage_analytics_can_export_data(): void
    {
        $users = User::factory()->count(3)->create();

        foreach ($users as $user) {
            UploadMetric::factory()->completed()->create([
                'user_id' => $user->id,
            ]);
        }

        Livewire::actingAs($this->adminUser)
            ->test(UsageAnalytics::class)
            ->call('exportData')
            ->assertDispatched('download-ready'); // Event should be dispatched
    }

    /** @test */
    public function user_usage_manager_shows_user_details_modal(): void
    {
        $user = User::factory()->create(['name' => 'Test User']);

        UploadMetric::factory()->completed()->count(3)->create([
            'user_id' => $user->id,
            'processing_duration_seconds' => 60,
        ]);

        $component = Livewire::actingAs($this->adminUser)
            ->test(UserUsageManager::class)
            ->call('showUserDetails', $user->id)
            ->assertSet('selectedUserId', $user->id)
            ->assertSet('showDetailsModal', true)
            ->assertSee('Detalles del Usuario')
            ->assertSee('Test User')
            ->assertSee('3'); // Number of uploads
    }

    /** @test */
    public function usage_analytics_shows_top_users_section(): void
    {
        $topUser = User::factory()->create(['name' => 'Top User']);
        $regularUser = User::factory()->create(['name' => 'Regular User']);

        UploadMetric::factory()->completed()->count(10)->create([
            'user_id' => $topUser->id,
            'line_count' => 1000,
        ]);

        UploadMetric::factory()->completed()->count(2)->create([
            'user_id' => $regularUser->id,
            'line_count' => 100,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(UsageAnalytics::class)
            ->assertSee('Usuarios Más Activos')
            ->assertSee('Top User')
            ->assertSee('10,000'); // Total lines for top user
    }

    /** @test */
    public function usage_analytics_displays_performance_metrics(): void
    {
        $user = User::factory()->create();

        UploadMetric::factory()->completed()->create([
            'user_id' => $user->id,
            'processing_duration_seconds' => 120,
            'file_size_bytes' => 1024000,
        ]);

        UploadMetric::factory()->completed()->create([
            'user_id' => $user->id,
            'processing_duration_seconds' => 60,
            'file_size_bytes' => 512000,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(UsageAnalytics::class)
            ->assertSee('Métricas de Rendimiento')
            ->assertSee('1.5m') // Average processing time (90 seconds)
            ->assertSee('768 KB'); // Average file size
    }

    /** @test */
    public function user_usage_manager_can_bulk_reset_usage(): void
    {
        $users = User::factory()->count(3)->create([
            'current_month_usage' => 500,
        ]);

        foreach ($users as $user) {
            UploadMetric::factory()->completed()->create([
                'user_id' => $user->id,
            ]);
        }

        $userIds = $users->pluck('id')->toArray();

        Livewire::actingAs($this->adminUser)
            ->test(UserUsageManager::class)
            ->set('selectedUsers', $userIds)
            ->call('bulkResetUsage')
            ->assertSee('Uso reiniciado exitosamente');

        // Verify users' usage was reset
        foreach ($users as $user) {
            $user->refresh();
            $this->assertEquals(0, $user->current_month_usage);
        }
    }

    /** @test */
    public function regular_user_cannot_access_admin_components(): void
    {
        $regularUser = User::factory()->create(['is_admin' => false]);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        Livewire::actingAs($regularUser)
            ->test(UsageAnalytics::class);
    }

    /** @test */
    public function usage_analytics_shows_empty_state_with_no_data(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(UsageAnalytics::class)
            ->assertSee('Sin datos de uso')
            ->assertSee('No hay métricas disponibles');
    }

    /** @test */
    public function user_usage_manager_shows_empty_state_with_no_users(): void
    {
        // Don't create any users with upload metrics
        User::factory()->count(2)->create(); // Users without uploads

        Livewire::actingAs($this->adminUser)
            ->test(UserUsageManager::class)
            ->set('onlyWithUploads', true)
            ->call('applyFilters')
            ->assertSee('No se encontraron usuarios')
            ->assertSee('con los filtros aplicados');
    }

    /** @test */
    public function usage_analytics_can_refresh_data(): void
    {
        $component = Livewire::actingAs($this->adminUser)
            ->test(UsageAnalytics::class);

        // Create new data after component is loaded
        $user = User::factory()->create();
        UploadMetric::factory()->completed()->create([
            'user_id' => $user->id,
        ]);

        $component->call('refreshData')
            ->assertSee('1'); // Should now show the new upload
    }

    /** @test */
    public function user_usage_manager_can_view_user_upload_history(): void
    {
        $user = User::factory()->create(['name' => 'Test User']);
        $upload = Upload::factory()->completed()->create(['user_id' => $user->id]);

        UploadMetric::factory()->completed()->create([
            'user_id' => $user->id,
            'upload_id' => $upload->id,
            'file_name' => 'test-file.csv',
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(UserUsageManager::class)
            ->call('showUserHistory', $user->id)
            ->assertSet('selectedUserId', $user->id)
            ->assertSet('showHistoryModal', true)
            ->assertSee('Historial de')
            ->assertSee('test-file.csv');
    }
}
