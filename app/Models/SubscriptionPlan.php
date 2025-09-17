<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionPlan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'monthly_price',
        'stripe_monthly_price_id',
        'is_monthly_enabled',
        'features',
        'max_alerts_per_month',
        'is_active',
        'is_featured',
        'sort_order',
    ];

    protected $casts = [
        'monthly_price' => 'decimal:2',
        'is_monthly_enabled' => 'boolean',
        'features' => 'array',
        'max_alerts_per_month' => 'integer',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Relationships
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_slug', 'slug');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('monthly_price');
    }

    // Accessor for monthly price
    public function getDiscountedMonthlyPriceAttribute(): ?float
    {
        return $this->monthly_price ? (float) $this->monthly_price : null;
    }

    // Helper methods
    public function getFormattedPrice(): string
    {
        return $this->monthly_price ? '€' . number_format((float) $this->monthly_price, 2) : 'Gratis';
    }

    public function getStripePriceId(): ?string
    {
        return $this->stripe_monthly_price_id;
    }

    // API Serialization
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'monthly_price' => $this->monthly_price,
            'discounted_monthly_price' => $this->discounted_monthly_price,
            'features' => $this->features,
            'limits' => [
                'max_alerts_per_month' => $this->max_alerts_per_month,
            ],
            'meta' => [
                'is_active' => $this->is_active,
                'is_featured' => $this->is_featured,
                'sort_order' => $this->sort_order,
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
            ],
            'stripe_price_id' => $this->stripe_monthly_price_id,
        ];
    }

    // Static methods
    public static function getActivePlans()
    {
        return self::active()->ordered()->get();
    }

    public static function getFeaturedPlans()
    {
        return self::active()->ordered()->limit(3)->get();
    }


    // Boot method for model events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($plan) {
            if (empty($plan->slug)) {
                $plan->slug = \Str::slug($plan->name);
            }
        });

        static::updating(function ($plan) {
            if ($plan->isDirty('name') && empty($plan->slug)) {
                $plan->slug = \Str::slug($plan->name);
            }
        });
    }
}
