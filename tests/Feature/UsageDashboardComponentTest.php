<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Dashboard\UsageDashboard;
use App\Livewire\Dashboard\UsageHistory;
use App\Livewire\Dashboard\UsageStats;
use App\Models\Upload;
use App\Models\UploadMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UsageDashboardComponentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function usage_dashboard_component_loads_with_user_data(): void
    {
        $user = User::factory()->create([
            'current_month_usage' => 750,
            'total_lines_processed' => 2500,
        ]);

        // Create some metrics for the user
        UploadMetric::factory()->completed()->count(3)->create([
            'user_id' => $user->id,
            'line_count' => 250,
        ]);

        Livewire::actingAs($user)
            ->test(UsageDashboard::class)
            ->assertStatus(200)
            ->assertSee('750') // Current month usage
            ->assertSee('2,500') // Total lines processed (formatted)
            ->assertSee('75%'); // Usage percentage
    }

    /** @test */
    public function usage_dashboard_shows_correct_tier_warnings(): void
    {
        // User approaching limit (90%)
        $user = User::factory()->create([
            'current_month_usage' => 900,
        ]);

        Livewire::actingAs($user)
            ->test(UsageDashboard::class)
            ->assertSee('90%')
            ->assertSee('warning') // Should show warning styling
            ->assertSee('Límite mensual'); // Spanish text for monthly limit
    }

    /** @test */
    public function usage_dashboard_shows_critical_warning_when_over_limit(): void
    {
        // User over limit
        $user = User::factory()->create([
            'current_month_usage' => 1050, // Over 1000 limit
        ]);

        Livewire::actingAs($user)
            ->test(UsageDashboard::class)
            ->assertSee('105%')
            ->assertSee('bg-red') // Should show critical styling
            ->assertSee('Límite excedido'); // Spanish text for limit exceeded
    }

    /** @test */
    public function usage_stats_component_displays_processing_metrics(): void
    {
        $user = User::factory()->create();

        // Create completed metrics with processing times
        UploadMetric::factory()->completed()->create([
            'user_id' => $user->id,
            'processing_duration_seconds' => 45,
            'credits_consumed' => 1,
        ]);

        UploadMetric::factory()->completed()->create([
            'user_id' => $user->id,
            'processing_duration_seconds' => 75,
            'credits_consumed' => 2,
        ]);

        Livewire::actingAs($user)
            ->test(UsageStats::class)
            ->assertStatus(200)
            ->assertSee('60s') // Average processing time (45+75)/2 = 60
            ->assertSee('3') // Total credits consumed
            ->assertSee('2'); // Total successful uploads
    }

    /** @test */
    public function usage_history_component_displays_paginated_results(): void
    {
        $user = User::factory()->create();

        // Create more metrics than fit on one page
        UploadMetric::factory()->completed()->count(25)->create([
            'user_id' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(UsageHistory::class)
            ->assertStatus(200)
            ->assertSee('Página 1') // Spanish pagination text
            ->assertSee('siguiente') // Next page link
            ->call('gotoPage', 2)
            ->assertSee('Página 2');
    }

    /** @test */
    public function usage_history_can_filter_by_date_range(): void
    {
        $user = User::factory()->create();

        // Create metrics for different dates
        $oldMetric = UploadMetric::factory()->completed()->create([
            'user_id' => $user->id,
            'file_name' => 'old-file.csv',
            'created_at' => now()->subMonth(),
        ]);

        $recentMetric = UploadMetric::factory()->completed()->create([
            'user_id' => $user->id,
            'file_name' => 'recent-file.csv',
            'created_at' => now()->subDays(3),
        ]);

        $component = Livewire::actingAs($user)
            ->test(UsageHistory::class)
            ->assertSee('old-file.csv')
            ->assertSee('recent-file.csv');

        // Apply date filter for last week only
        $component->set('startDate', now()->subWeek()->format('Y-m-d'))
            ->set('endDate', now()->format('Y-m-d'))
            ->call('applyFilters')
            ->assertDontSee('old-file.csv')
            ->assertSee('recent-file.csv');
    }

    /** @test */
    public function usage_history_can_filter_by_status(): void
    {
        $user = User::factory()->create();

        $completedMetric = UploadMetric::factory()->completed()->create([
            'user_id' => $user->id,
            'file_name' => 'completed-file.csv',
        ]);

        $failedMetric = UploadMetric::factory()->failed()->create([
            'user_id' => $user->id,
            'file_name' => 'failed-file.csv',
        ]);

        $component = Livewire::actingAs($user)
            ->test(UsageHistory::class)
            ->assertSee('completed-file.csv')
            ->assertSee('failed-file.csv');

        // Filter for completed only
        $component->set('statusFilter', 'completed')
            ->call('applyFilters')
            ->assertSee('completed-file.csv')
            ->assertDontSee('failed-file.csv');
    }

    /** @test */
    public function usage_history_can_sort_by_different_columns(): void
    {
        $user = User::factory()->create();

        UploadMetric::factory()->completed()->create([
            'user_id' => $user->id,
            'file_name' => 'small-file.csv',
            'line_count' => 100,
        ]);

        UploadMetric::factory()->completed()->create([
            'user_id' => $user->id,
            'file_name' => 'large-file.csv',
            'line_count' => 500,
        ]);

        $component = Livewire::actingAs($user)
            ->test(UsageHistory::class);

        // Sort by line count ascending
        $component->call('sortBy', 'line_count', 'asc');

        // Get the displayed data order
        $html = $component->get('html');
        $smallFilePos = strpos($html, 'small-file.csv');
        $largeFilePos = strpos($html, 'large-file.csv');

        $this->assertLessThan($largeFilePos, $smallFilePos);

        // Sort by line count descending
        $component->call('sortBy', 'line_count', 'desc');

        $html = $component->get('html');
        $smallFilePos = strpos($html, 'small-file.csv');
        $largeFilePos = strpos($html, 'large-file.csv');

        $this->assertGreaterThan($largeFilePos, $smallFilePos);
    }

    /** @test */
    public function usage_dashboard_refreshes_data_on_upload_completion(): void
    {
        $user = User::factory()->create([
            'current_month_usage' => 500,
        ]);

        $component = Livewire::actingAs($user)
            ->test(UsageDashboard::class)
            ->assertSee('500');

        // Simulate upload completion event
        $component->call('refreshUsageData');

        // Update user's usage
        $user->update(['current_month_usage' => 750]);

        $component->call('refreshUsageData')
            ->assertSee('750');
    }

    /** @test */
    public function usage_stats_shows_empty_state_for_new_users(): void
    {
        $user = User::factory()->create();
        // No upload metrics created

        Livewire::actingAs($user)
            ->test(UsageStats::class)
            ->assertSee('Sin datos') // Spanish for "No data"
            ->assertSee('0'); // Zero values for all metrics
    }

    /** @test */
    public function usage_history_shows_empty_state_with_no_uploads(): void
    {
        $user = User::factory()->create();
        // No upload metrics created

        Livewire::actingAs($user)
            ->test(UsageHistory::class)
            ->assertSee('No hay archivos procesados') // Spanish for "No processed files"
            ->assertSee('historial de uso'); // Usage history text
    }

    /** @test */
    public function usage_components_format_numbers_correctly_for_spanish_locale(): void
    {
        $user = User::factory()->create([
            'current_month_usage' => 1234,
            'total_lines_processed' => 5678,
        ]);

        UploadMetric::factory()->completed()->create([
            'user_id' => $user->id,
            'line_count' => 1500,
            'file_size_bytes' => 2048576, // 2MB
        ]);

        Livewire::actingAs($user)
            ->test(UsageDashboard::class)
            ->assertSee('1.234') // Thousand separator for Spanish locale
            ->assertSee('5.678')
            ->assertSee('2 MB'); // Formatted file size
    }

    /** @test */
    public function usage_dashboard_shows_progress_bars_with_correct_colors(): void
    {
        // Test different usage levels
        $testCases = [
            ['usage' => 200, 'color' => 'bg-blue'], // Low usage - blue
            ['usage' => 750, 'color' => 'bg-yellow'], // Medium usage - yellow
            ['usage' => 950, 'color' => 'bg-red'], // High usage - red
        ];

        foreach ($testCases as $case) {
            $user = User::factory()->create([
                'current_month_usage' => $case['usage'],
            ]);

            Livewire::actingAs($user)
                ->test(UsageDashboard::class)
                ->assertSee($case['color']);
        }
    }

    /** @test */
    public function usage_history_export_functionality_works(): void
    {
        $user = User::factory()->create();

        UploadMetric::factory()->completed()->count(5)->create([
            'user_id' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(UsageHistory::class)
            ->call('exportToCsv')
            ->assertDispatched('download-ready'); // Event should be dispatched
    }
}
