<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\ResetMonthlyUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResetMonthlyUsageCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function console_command_can_reset_monthly_usage(): void
    {
        // Create users with old reset dates who need reset
        $user1 = User::factory()->create([
            'current_month_usage' => 1000,
            'usage_reset_date' => now()->subMonth(),
        ]);

        $user2 = User::factory()->create([
            'current_month_usage' => 500,
            'usage_reset_date' => null,
        ]);

        // Create user who doesn't need reset
        $user3 = User::factory()->create([
            'current_month_usage' => 300,
            'usage_reset_date' => now()->subDays(5),
        ]);

        $this->artisan('usage:reset-monthly')
            ->expectsOutput('Starting monthly usage reset process...')
            ->expectsOutput('Successfully reset monthly usage for 2 users.')
            ->assertExitCode(0);

        $user1->refresh();
        $user2->refresh();
        $user3->refresh();

        $this->assertEquals(0, $user1->current_month_usage);
        $this->assertEquals(0, $user2->current_month_usage);
        $this->assertEquals(300, $user3->current_month_usage); // Shouldn't be reset
    }

    /** @test */
    public function console_command_can_run_in_dry_run_mode(): void
    {
        $user = User::factory()->create([
            'current_month_usage' => 1000,
            'usage_reset_date' => now()->subMonth(),
        ]);

        $this->artisan('usage:reset-monthly --dry-run')
            ->expectsOutput('Starting monthly usage reset process...')
            ->expectsOutput('DRY RUN MODE - No changes will be made')
            ->assertExitCode(0);

        $user->refresh();

        // Usage should not be reset in dry run mode
        $this->assertEquals(1000, $user->current_month_usage);
    }

    /** @test */
    public function console_command_can_force_reset_all_users(): void
    {
        // Create users with various reset dates
        $user1 = User::factory()->create([
            'current_month_usage' => 1000,
            'usage_reset_date' => now()->subDays(5), // Recent reset
        ]);

        $user2 = User::factory()->create([
            'current_month_usage' => 500,
            'usage_reset_date' => now()->subMonth(), // Old reset
        ]);

        $user3 = User::factory()->create([
            'current_month_usage' => 0, // No usage
            'usage_reset_date' => now()->subMonth(),
        ]);

        $this->artisan('usage:reset-monthly --force')
            ->expectsOutput('Starting monthly usage reset process...')
            ->expectsOutput('FORCE MODE - Resetting all users regardless of reset dates')
            ->expectsOutput('Successfully reset monthly usage for 2 users.')
            ->assertExitCode(0);

        $user1->refresh();
        $user2->refresh();
        $user3->refresh();

        // Users with usage should be reset
        $this->assertEquals(0, $user1->current_month_usage);
        $this->assertEquals(0, $user2->current_month_usage);

        // User with no usage should remain unchanged
        $this->assertEquals(0, $user3->current_month_usage);
    }

    /** @test */
    public function console_command_handles_no_users_to_reset(): void
    {
        // Create user who doesn't need reset
        User::factory()->create([
            'current_month_usage' => 300,
            'usage_reset_date' => now()->subDays(5),
        ]);

        $this->artisan('usage:reset-monthly')
            ->expectsOutput('Starting monthly usage reset process...')
            ->expectsOutput('No users required usage reset at this time.')
            ->assertExitCode(0);
    }

    /** @test */
    public function console_command_shows_table_in_dry_run_with_users(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'current_month_usage' => 1000,
            'usage_reset_date' => now()->subMonth(),
        ]);

        $this->artisan('usage:reset-monthly --dry-run')
            ->expectsOutput('Starting monthly usage reset process...')
            ->expectsOutput('DRY RUN MODE - No changes will be made')
            ->expectsOutput('Total users to reset: 1')
            ->assertExitCode(0);
    }
}
