<?php

declare(strict_types=1);

namespace App\Console\Commands;

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
    protected $description = 'Expire credits that are older than the expiration period';

    /**
     * Credit expiration period in days.
     */
    private int $expirationDays = 365; // 1 year

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

        // Find credits that should be expired
        $expirationDate = now()->subDays($this->expirationDays);

        $expirableTransactions = CreditTransaction::where('type', 'purchased')
            ->where('created_at', '<', $expirationDate)
            ->whereHas('user', function ($query) {
                $query->where('credits', '>', 0);
            })
            ->with('user')
            ->get();

        if ($expirableTransactions->isEmpty()) {
            $this->info('No credits found that need to be expired.');
            return 0;
        }

        $totalCreditsToExpire = 0;
        $affectedUsers = 0;

        $this->info("Found {$expirableTransactions->count()} transactions to process");

        foreach ($expirableTransactions as $transaction) {
            $user = $transaction->user;
            $creditsToExpire = min($transaction->amount, $user->credits);

            if ($creditsToExpire <= 0) {
                continue;
            }

            $this->line("User: {$user->name} ({$user->email}) - Expiring {$creditsToExpire} credits from transaction {$transaction->id}");

            if (!$dryRun) {
                try {
                    // Create expiration transaction
                    $success = CreditTransaction::create([
                        'user_id' => $user->id,
                        'type' => 'expired',
                        'amount' => -$creditsToExpire,
                        'description' => "Expiración automática de créditos antiguos (transacción #{$transaction->id})",
                    ]);

                    if ($success) {
                        // Reduce user credits
                        $user->decrement('credits', $creditsToExpire);

                        Log::info('Credits expired', [
                            'user_id' => $user->id,
                            'credits_expired' => $creditsToExpire,
                            'original_transaction_id' => $transaction->id,
                        ]);
                    }
                } catch (\Exception $e) {
                    $this->error("Failed to expire credits for user {$user->id}: " . $e->getMessage());
                    Log::error('Credit expiration failed', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
            }

            $totalCreditsToExpire += $creditsToExpire;
            $affectedUsers++;
        }

        if ($dryRun) {
            $this->info("DRY RUN COMPLETE:");
            $this->info("- {$totalCreditsToExpire} credits would be expired");
            $this->info("- {$affectedUsers} users would be affected");
        } else {
            $this->info("EXPIRATION COMPLETE:");
            $this->info("- {$totalCreditsToExpire} credits expired");
            $this->info("- {$affectedUsers} users affected");

            Log::info('Credit expiration completed', [
                'total_credits_expired' => $totalCreditsToExpire,
                'users_affected' => $affectedUsers,
            ]);
        }

        return 0;
    }
}
