<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\AdminSetting;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Subscription as StripeSubscription;

class Subscription extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'stripe_id',
        'stripe_status',
        'stripe_price',
        'quantity',
        'trial_ends_at',
        'ends_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'trial_ends_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the subscription.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subscription items for the subscription.
     */
    public function items(): HasMany
    {
        return $this->hasMany(SubscriptionItem::class);
    }

    /**
     * Get the credit transactions for the subscription.
     */
    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    /**
     * Determine if the subscription is active.
     */
    public function active(): bool
    {
        return $this->stripe_status === 'active';
    }

    /**
     * Determine if the subscription is on trial.
     */
    public function onTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Determine if the subscription is cancelled.
     */
    public function cancelled(): bool
    {
        return $this->stripe_status === 'canceled';
    }

    /**
     * Determine if the subscription is on grace period.
     */
    public function onGracePeriod(): bool
    {
        return $this->ends_at && $this->ends_at->isFuture();
    }

    /**
     * Determine if the subscription is past due.
     */
    public function pastDue(): bool
    {
        return $this->stripe_status === 'past_due';
    }

    /**
     * Determine if the subscription is valid.
     */
    public function valid(): bool
    {
        return $this->active() || $this->onTrial() || $this->onGracePeriod();
    }

    /**
     * Get the Stripe subscription instance.
     */
    public function asStripeSubscription(): StripeSubscription
    {
        // Set Stripe API key
        $stripeConfig = AdminSetting::getStripeConfig();
        Stripe::setApiKey($stripeConfig['secret_key']);

        // Retrieve and return the Stripe subscription
        return StripeSubscription::retrieve($this->stripe_id);
    }

    /**
     * Cancel the subscription at the end of the current billing period.
     */
    public function cancel(): self
    {
        Log::info('🚫 Canceling subscription at period end', [
            'subscription_id' => $this->id,
            'stripe_id' => $this->stripe_id,
        ]);

        try {
            // Set Stripe API key
            $stripeConfig = AdminSetting::getStripeConfig();
            \Stripe\Stripe::setApiKey($stripeConfig['secret_key']);

            // Get current period end from Stripe
            $stripeSubscription = $this->asStripeSubscription();
            $periodEnd = Carbon::createFromTimestamp($stripeSubscription->current_period_end);

            // Cancel the subscription at period end in Stripe using API
            \Stripe\Subscription::update($this->stripe_id, [
                'cancel_at_period_end' => true,
            ]);

            // Update local database
            $this->update([
                'ends_at' => $periodEnd,
            ]);

            Log::info('✅ Subscription canceled at period end', [
                'subscription_id' => $this->id,
                'ends_at' => $periodEnd->toDateTimeString(),
            ]);

            return $this;

        } catch (\Exception $e) {
            Log::error('❌ Failed to cancel subscription', [
                'subscription_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Cancel the subscription at a specific date.
     */
    public function cancelAt(Carbon $date): self
    {
        Log::info('🚫 Canceling subscription at specific date', [
            'subscription_id' => $this->id,
            'stripe_id' => $this->stripe_id,
            'cancel_at' => $date->toDateTimeString(),
        ]);

        try {
            // Set Stripe API key
            $stripeConfig = AdminSetting::getStripeConfig();
            \Stripe\Stripe::setApiKey($stripeConfig['secret_key']);

            // Cancel the subscription at the specified time in Stripe using API
            \Stripe\Subscription::update($this->stripe_id, [
                'cancel_at' => $date->timestamp,
            ]);

            // Update local database
            $this->update([
                'ends_at' => $date,
            ]);

            Log::info('✅ Subscription scheduled to cancel', [
                'subscription_id' => $this->id,
                'ends_at' => $date->toDateTimeString(),
            ]);

            return $this;

        } catch (\Exception $e) {
            Log::error('❌ Failed to schedule subscription cancellation', [
                'subscription_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Resume a canceled subscription.
     */
    public function resume(): self
    {
        Log::info('🔄 Resuming subscription', [
            'subscription_id' => $this->id,
            'stripe_id' => $this->stripe_id,
        ]);

        try {
            // Set Stripe API key
            $stripeConfig = AdminSetting::getStripeConfig();
            \Stripe\Stripe::setApiKey($stripeConfig['secret_key']);

            // Resume the subscription in Stripe using API
            \Stripe\Subscription::update($this->stripe_id, [
                'cancel_at_period_end' => false,
            ]);

            // Update local database
            $this->update([
                'ends_at' => null,
            ]);

            Log::info('✅ Subscription resumed', [
                'subscription_id' => $this->id,
            ]);

            return $this;

        } catch (\Exception $e) {
            Log::error('❌ Failed to resume subscription', [
                'subscription_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Cancel the subscription immediately.
     */
    public function cancelNow(): self
    {
        Log::info('🚫 Canceling subscription immediately', [
            'subscription_id' => $this->id,
            'stripe_id' => $this->stripe_id,
        ]);

        try {
            // Set Stripe API key
            $stripeConfig = AdminSetting::getStripeConfig();
            \Stripe\Stripe::setApiKey($stripeConfig['secret_key']);

            // Cancel the subscription immediately in Stripe using API
            \Stripe\Subscription::cancel($this->stripe_id);

            // Update local database
            $this->update([
                'stripe_status' => 'canceled',
                'ends_at' => now(),
            ]);

            Log::info('✅ Subscription canceled immediately', [
                'subscription_id' => $this->id,
            ]);

            return $this;

        } catch (\Exception $e) {
            Log::error('❌ Failed to cancel subscription immediately', [
                'subscription_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
