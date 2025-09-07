<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CreditTransaction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireCreditTransaction implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public CreditTransaction $transaction
    ) {
        // Set queue priority for credit expiration jobs
        $this->onQueue('credits');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            DB::transaction(function () {
                // Refresh the transaction and user to get latest data
                $this->transaction->refresh();
                $user = $this->transaction->user;

                if (!$user) {
                    Log::warning('User not found for credit transaction', [
                        'transaction_id' => $this->transaction->id,
                    ]);
                    return;
                }

                // Calculate credits to expire (can't expire more than user currently has)
                $creditsToExpire = min($this->transaction->amount, $user->credits);

                if ($creditsToExpire <= 0) {
                    Log::info('No credits to expire for transaction', [
                        'transaction_id' => $this->transaction->id,
                        'user_id' => $user->id,
                        'user_credits' => $user->credits,
                    ]);
                    return;
                }

                // Create expiration transaction record
                $subscriptionDate = $this->transaction->subscription->created_at->format('Y-m-d');

                $expirationTransaction = CreditTransaction::create([
                    'user_id' => $user->id,
                    'type' => 'expired',
                    'amount' => -$creditsToExpire,
                    'description' => "Expiración automática de créditos (30 días después de suscripción iniciada el {$subscriptionDate})",
                    'subscription_id' => $this->transaction->subscription_id,
                ]);

                // Reduce user credits
                $user->decrement('credits', $creditsToExpire);

                Log::info('Credits expired successfully via queue job', [
                    'user_id' => $user->id,
                    'credits_expired' => $creditsToExpire,
                    'original_transaction_id' => $this->transaction->id,
                    'expiration_transaction_id' => $expirationTransaction->id,
                    'subscription_date' => $subscriptionDate,
                    'new_balance' => $user->fresh()->credits,
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Failed to expire credits in queue job', [
                'transaction_id' => $this->transaction->id,
                'user_id' => $this->transaction->user_id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Credit expiration job failed permanently', [
            'transaction_id' => $this->transaction->id,
            'user_id' => $this->transaction->user_id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
}
