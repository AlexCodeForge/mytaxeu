<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ExpireCreditTransaction;
use App\Models\CreditTransaction;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireCredits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'credits:expire {--dry-run : Show what would be expired without actually expiring}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch queue jobs to expire subscription credits that are 30 days old from subscription start date';

    /**
     * Credit expiration period in days.
     * Credits expire 30 days after subscription start date.
     */
    private int $expirationDays = 30;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('Starting credit expiration process...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No credits will actually be expired');
        }

        // Find subscription-based credits that should be expired
        // Credits expire 30 days after the subscription start date
        $expirationDate = now()->subDays($this->expirationDays);

        $expirableTransactions = CreditTransaction::where('type', 'purchased')
            ->whereNotNull('subscription_id') // Only subscription-based credits
            ->whereHas('subscription', function ($query) use ($expirationDate) {
                $query->where('created_at', '<', $expirationDate);
            })
            ->whereHas('user', function ($query) {
                $query->where('credits', '>', 0);
            })
            ->with(['user', 'subscription'])
            ->get();

        if ($expirableTransactions->isEmpty()) {
            $this->info('No credits found that need to be expired.');
            return 0;
        }

        $jobsDispatched = 0;
        $totalCreditsToExpire = 0;

        $this->info("Found {$expirableTransactions->count()} transactions to process");

        foreach ($expirableTransactions as $transaction) {
            $user = $transaction->user;
            $creditsToExpire = min($transaction->amount, $user->credits);

            if ($creditsToExpire <= 0) {
                continue;
            }

            $subscriptionDate = $transaction->subscription->created_at->format('Y-m-d');
            $this->line("User: {$user->name} ({$user->email}) - Processing {$creditsToExpire} credits from subscription {$transaction->subscription->stripe_id} (started: {$subscriptionDate})");

            if (!$dryRun) {
                // Dispatch job to process this credit expiration
                ExpireCreditTransaction::dispatch($transaction);
                $jobsDispatched++;

                Log::info('Credit expiration job dispatched', [
                    'transaction_id' => $transaction->id,
                    'user_id' => $user->id,
                    'credits_to_expire' => $creditsToExpire,
                ]);
            }

            $totalCreditsToExpire += $creditsToExpire;
        }

        if ($dryRun) {
            $this->info("DRY RUN COMPLETE:");
            $this->info("- {$totalCreditsToExpire} credits would be expired");
            $this->info("- {$expirableTransactions->count()} expiration jobs would be dispatched");
        } else {
            $this->info("EXPIRATION JOB DISPATCH COMPLETE:");
            $this->info("- {$jobsDispatched} expiration jobs dispatched to queue");
            $this->info("- {$totalCreditsToExpire} total credits will be processed");
            $this->info("- Jobs will be processed by queue workers");

            Log::info('Credit expiration jobs dispatched', [
                'jobs_dispatched' => $jobsDispatched,
                'total_credits_to_expire' => $totalCreditsToExpire,
            ]);
        }

        return 0;
    }
}
