<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountCodeUsage extends Model
{
    use HasFactory;

    protected $table = 'discount_code_usages';

    protected $fillable = [
        'discount_code_id',
        'user_id',
        'subscription_plan_id',
        'original_amount',
        'discount_amount',
        'final_amount',
        'stripe_coupon_id',
        'stripe_subscription_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'original_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    // Relationships
    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    // Helper methods
    public function getDiscountPercentage(): float
    {
        if ($this->original_amount <= 0) {
            return 0;
        }

        return round(($this->discount_amount / $this->original_amount) * 100, 2);
    }

    public function getSavingsAmount(): float
    {
        return $this->discount_amount;
    }

    // API Serialization
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'discount_code' => $this->discountCode?->code,
            'user_id' => $this->user_id,
            'user_name' => $this->user?->name,
            'subscription_plan' => $this->subscriptionPlan?->name,
            'original_amount' => $this->original_amount,
            'discount_amount' => $this->discount_amount,
            'final_amount' => $this->final_amount,
            'discount_percentage' => $this->getDiscountPercentage(),
            'stripe_coupon_id' => $this->stripe_coupon_id,
            'stripe_subscription_id' => $this->stripe_subscription_id,
            'used_at' => $this->created_at?->toISOString(),
        ];
    }
}
