<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\UsageMeteringService;
use Illuminate\Console\Command;

class ResetMonthlyUsage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'usage:reset-monthly
                            {--force : Force reset even if already done this month}
                            {--dry-run : Show what would be reset without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset monthly usage counters for users based on their reset dates';

    /**
     * Execute the console command.
     */
    public function handle(UsageMeteringService $usageMeteringService): int
    {
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Starting monthly usage reset process...');

        try {
            if ($dryRun) {
                $this->warn('DRY RUN MODE - No changes will be made');
                $this->showUsersToReset($usageMeteringService);
                return Command::SUCCESS;
            }

            if ($force) {
                $this->warn('FORCE MODE - Resetting all users regardless of reset dates');
            }

            $resetCount = $force
                ? $this->forceResetAllUsers($usageMeteringService)
                : $usageMeteringService->processMonthlyResets();

            if ($resetCount > 0) {
                $this->info("Successfully reset monthly usage for {$resetCount} users.");
            } else {
                $this->info('No users required usage reset at this time.');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to reset monthly usage: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Show users that would be reset without actually resetting them.
     */
    private function showUsersToReset(UsageMeteringService $usageMeteringService): void
    {
        $cutoffDate = now()->subMonth()->toDateString();

        $usersToReset = \App\Models\User::where(function ($query) use ($cutoffDate) {
            $query->whereNull('usage_reset_date')
                  ->orWhere('usage_reset_date', '<=', $cutoffDate);
        })->where('current_month_usage', '>', 0)->get();

        if ($usersToReset->isEmpty()) {
            $this->info('No users would be reset.');
            return;
        }

        $this->table(
            ['ID', 'Name', 'Email', 'Current Usage', 'Last Reset Date'],
            $usersToReset->map(function ($user) {
                return [
                    $user->id,
                    $user->name,
                    $user->email,
                    number_format($user->current_month_usage),
                    $user->usage_reset_date?->format('Y-m-d') ?? 'Never',
                ];
            })
        );

        $this->info("Total users to reset: {$usersToReset->count()}");
    }

    /**
     * Force reset all users with current usage > 0.
     */
    private function forceResetAllUsers(UsageMeteringService $usageMeteringService): int
    {
        $users = \App\Models\User::where('current_month_usage', '>', 0)->get();
        $resetCount = 0;

        foreach ($users as $user) {
            $usageMeteringService->resetMonthlyUsage($user);
            $resetCount++;
        }

        return $resetCount;
    }
}
