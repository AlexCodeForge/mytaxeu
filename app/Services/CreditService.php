<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CreditTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreditService
{
    /**
     * Allocate credits to a user and log the transaction.
     */
    public function allocateCredits(
        User $user,
        int $amount,
        string $description,
        ?Model $relatedModel = null
    ): bool {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Credit amount must be positive');
        }

        try {
            return DB::transaction(function () use ($user, $amount, $description, $relatedModel) {
                // Update user credits
                $user->increment('credits', $amount);

                // Create transaction record
                $transaction = CreditTransaction::create([
                    'user_id' => $user->id,
                    'type' => 'purchased',
                    'amount' => $amount,
                    'description' => $description,
                    'subscription_id' => $relatedModel instanceof \App\Models\Subscription ? $relatedModel->id : null,
                ]);

                Log::info('Credits allocated successfully', [
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'new_balance' => $user->fresh()->credits,
                    'transaction_id' => $transaction->id,
                ]);

                return true;
            });
        } catch (\Exception $e) {
            Log::error('Failed to allocate credits', [
                'user_id' => $user->id,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Unable to allocate credits: ' . $e->getMessage());
        }
    }

    /**
     * Consume credits from a user and log the transaction.
     */
    public function consumeCredits(
        User $user,
        int $amount,
        string $description,
        ?Model $relatedModel = null
    ): bool {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Credit amount must be positive');
        }

        if (!$this->hasEnoughCredits($user, $amount)) {
            Log::warning('Insufficient credits for consumption', [
                'user_id' => $user->id,
                'requested' => $amount,
                'available' => $user->credits,
            ]);

            return false;
        }

        try {
            return DB::transaction(function () use ($user, $amount, $description, $relatedModel) {
                // Update user credits
                $user->decrement('credits', $amount);

                // Create transaction record
                $transaction = CreditTransaction::create([
                    'user_id' => $user->id,
                    'type' => 'consumed',
                    'amount' => -$amount, // Negative for consumption
                    'description' => $description,
                    'upload_id' => $relatedModel instanceof \App\Models\Upload ? $relatedModel->id : null,
                ]);

                Log::info('Credits consumed successfully', [
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'new_balance' => $user->fresh()->credits,
                    'transaction_id' => $transaction->id,
                ]);

                return true;
            });
        } catch (\Exception $e) {
            Log::error('Failed to consume credits', [
                'user_id' => $user->id,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Unable to consume credits: ' . $e->getMessage());
        }
    }

    /**
     * Get the current credit balance for a user.
     */
    public function getCreditBalance(User $user): int
    {
        return (int) $user->credits;
    }

    /**
     * Check if user has enough credits for a transaction.
     */
    public function hasEnoughCredits(User $user, int $amount): bool
    {
        return $this->getCreditBalance($user) >= $amount;
    }

    /**
     * Refund credits to a user and log the transaction.
     */
    public function refundCredits(
        User $user,
        int $amount,
        string $description,
        ?Model $relatedModel = null
    ): bool {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Credit amount must be positive');
        }

        try {
            return DB::transaction(function () use ($user, $amount, $description, $relatedModel) {
                // Update user credits
                $user->increment('credits', $amount);

                // Create transaction record
                $transaction = CreditTransaction::create([
                    'user_id' => $user->id,
                    'type' => 'refunded',
                    'amount' => $amount,
                    'description' => $description,
                    'upload_id' => $relatedModel instanceof \App\Models\Upload ? $relatedModel->id : null,
                    'subscription_id' => $relatedModel instanceof \App\Models\Subscription ? $relatedModel->id : null,
                ]);

                Log::info('Credits refunded successfully', [
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'new_balance' => $user->fresh()->credits,
                    'transaction_id' => $transaction->id,
                ]);

                return true;
            });
        } catch (\Exception $e) {
            Log::error('Failed to refund credits', [
                'user_id' => $user->id,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Unable to refund credits: ' . $e->getMessage());
        }
    }

    /**
     * Get credit transaction history for a user.
     */
    public function getTransactionHistory(User $user, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return $user->creditTransactions()
            ->with(['upload', 'subscription'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
