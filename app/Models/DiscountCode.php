<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class DiscountCode extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'max_uses',
        'used_count',
        'expires_at',
        'is_active',
        'is_global',
        'stripe_coupon_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
            'is_global' => 'boolean',
            'metadata' => 'array',
        ];
    }

    // Relationships
    public function subscriptionPlans(): BelongsToMany
    {
        return $this->belongsToMany(SubscriptionPlan::class, 'discount_code_plans')
            ->withTimestamps();
    }

    public function usages(): HasMany
    {
        return $this->hasMany(DiscountCodeUsage::class);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->active()
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->where(function ($q) {
                $q->whereNull('max_uses')
                  ->orWhereRaw('used_count < max_uses');
            });
    }

    public function scopeByCode(Builder $query, string $code): Builder
    {
        return $query->where('code', strtoupper($code));
    }

    // Helper methods
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_uses && $this->used_count >= $this->max_uses) {
            return false;
        }

        return true;
    }

    public function canBeUsedByUser(User $user): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        // Check if user has already used this code
        return !$this->usages()->where('user_id', $user->id)->exists();
    }

    public function canBeAppliedToPlan(SubscriptionPlan $plan): bool
    {
        if ($this->is_global) {
            return true;
        }

        return $this->subscriptionPlans()->where('subscription_plans.id', $plan->id)->exists();
    }

    public function calculateDiscount(float $amount): float
    {
        $value = (float) $this->value;

        if ($this->type === 'percentage') {
            return round($amount * ($value / 100), 2);
        }

        if ($this->type === 'fixed') {
            return min($value, $amount);
        }

        return 0;
    }

    public function getFormattedValue(): string
    {
        $value = (float) $this->value;

        if ($this->type === 'percentage') {
            return number_format($value, 1) . '%';
        }

        return '€' . number_format($value, 2);
    }

    public function incrementUsage(): void
    {
        $this->increment('used_count');
    }

    public function getRemainingUses(): ?int
    {
        if (!$this->max_uses) {
            return null;
        }

        return max(0, $this->max_uses - $this->used_count);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isExhausted(): bool
    {
        return $this->max_uses && $this->used_count >= $this->max_uses;
    }

    // API Serialization
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'value' => $this->value,
            'formatted_value' => $this->getFormattedValue(),
            'max_uses' => $this->max_uses,
            'used_count' => $this->used_count,
            'remaining_uses' => $this->getRemainingUses(),
            'expires_at' => $this->expires_at?->toISOString(),
            'is_active' => $this->is_active,
            'is_global' => $this->is_global,
            'is_valid' => $this->isValid(),
            'is_expired' => $this->isExpired(),
            'is_exhausted' => $this->isExhausted(),
            'applicable_plans' => $this->is_global ? 'all' : $this->subscriptionPlans->pluck('slug'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    // Boot method for model events
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($discountCode) {
            // Ensure code is uppercase
            $discountCode->code = strtoupper($discountCode->code);

            // Initialize used_count if not set
            if (is_null($discountCode->used_count)) {
                $discountCode->used_count = 0;
            }
        });

        static::updating(function ($discountCode) {
            // Ensure code is uppercase
            if ($discountCode->isDirty('code')) {
                $discountCode->code = strtoupper($discountCode->code);
            }
        });
    }
}
