<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class RateSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'currency',
        'country',
        'rate',
        'effective_date',
        'source',
        'update_mode',
        'is_active',
        'metadata',
        'last_updated_at',
    ];

    protected $casts = [
        'rate' => 'decimal:6',
        'effective_date' => 'date',
        'is_active' => 'boolean',
        'metadata' => 'array',
        'last_updated_at' => 'datetime',
    ];

    protected $dates = [
        'effective_date',
        'last_updated_at',
    ];

    // Scopes
    public function scopeExchangeRates($query)
    {
        return $query->where('type', 'exchange_rate');
    }

    public function scopeVatRates($query)
    {
        return $query->where('type', 'vat_rate');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAutomatic($query)
    {
        return $query->where('update_mode', 'automatic');
    }

    public function scopeManual($query)
    {
        return $query->where('update_mode', 'manual');
    }

    public function scopeCurrent($query)
    {
        return $query->where('effective_date', '<=', now())
                    ->orderBy('effective_date', 'desc');
    }

    // Static methods for getting rates with caching
    public static function getExchangeRate(string $currency): float
    {
        return Cache::remember("exchange_rate_{$currency}", 3600, function () use ($currency) {
            $rate = self::exchangeRates()
                ->active()
                ->where('currency', $currency)
                ->orderBy('last_updated_at', 'desc')
                ->first();

            return $rate ? (float) $rate->rate : 1.0;
        });
    }

    public static function getVatRate(string $country): float
    {
        return Cache::remember("vat_rate_{$country}", 3600, function () use ($country) {
            $rate = self::vatRates()
                ->active()
                ->where('country', $country)
                ->orderBy('last_updated_at', 'desc')
                ->first();

            return $rate ? (float) $rate->rate : 0.0;
        });
    }

    // Get all current exchange rates as array (for StreamingCsvTransformer compatibility)
    public static function getCurrentExchangeRates(): array
    {
        return Cache::remember('all_exchange_rates', 3600, function () {
            // Get the most recent rate for each currency to handle duplicates
            $rates = [];
            $currencies = self::exchangeRates()
                ->active()
                ->distinct()
                ->pluck('currency');

            foreach ($currencies as $currency) {
                $rate = self::exchangeRates()
                    ->active()
                    ->where('currency', $currency)
                    ->orderBy('last_updated_at', 'desc')
                    ->first();

                if ($rate) {
                    $rates[$currency] = (float) $rate->rate;
                }
            }

            // Ensure EUR is always 1.0
            $rates['EUR'] = 1.0;

            return $rates;
        });
    }

    // Get all current VAT rates as array (for StreamingCsvTransformer compatibility)
    public static function getCurrentVatRates(): array
    {
        return Cache::remember('all_vat_rates', 3600, function () {
            // Get the most recent rate for each country to handle duplicates
            $rates = [];
            $countries = self::vatRates()
                ->active()
                ->distinct()
                ->pluck('country');

            foreach ($countries as $country) {
                $rate = self::vatRates()
                    ->active()
                    ->where('country', $country)
                    ->orderBy('last_updated_at', 'desc')
                    ->first();

                if ($rate) {
                    $rates[$country] = (float) $rate->rate;
                }
            }

            return $rates;
        });
    }

    // Update or create rate with proper handling of manual/automatic modes
    public static function updateRate(
        string $type,
        float $rate,
        ?string $currency = null,
        ?string $country = null,
        string $source = 'manual',
        string $updateMode = 'manual',
        ?array $metadata = null
    ): self {
        // For proper unique handling, find and update the most recent active rate
        // instead of relying on effective_date which can cause duplicates

        $query = self::where('type', $type)->where('is_active', true);

        if ($currency) {
            $query->where('currency', $currency);
        }

        if ($country) {
            $query->where('country', $country);
        }

        $existingRate = $query->orderBy('last_updated_at', 'desc')->first();

        // If updating an existing rate and it's set to manual mode, preserve manual mode
        // unless explicitly requested to change it
        if ($existingRate && $existingRate->update_mode === 'manual' && $source === 'api_vatcomply') {
            // Skip API updates for manual rates
            return $existingRate;
        }

        $data = [
            'type' => $type,
            'rate' => $rate,
            'effective_date' => now()->startOfDay(),
            'source' => $source,
            'update_mode' => $updateMode,
            'is_active' => true,
            'metadata' => $metadata,
            'last_updated_at' => now(),
        ];

        if ($currency) {
            $data['currency'] = $currency;
        }

        if ($country) {
            $data['country'] = $country;
        }

        if ($existingRate) {
            // Update existing rate
            $existingRate->update($data);
            $rateSetting = $existingRate;
        } else {
            // Create new rate
            $rateSetting = self::create($data);
        }

        // Clear relevant cache
        if ($type === 'exchange_rate') {
            Cache::forget("exchange_rate_{$currency}");
            Cache::forget('all_exchange_rates');
        } else {
            Cache::forget("vat_rate_{$country}");
            Cache::forget('all_vat_rates');
        }

        return $rateSetting;
    }

    // Clear all rate caches
    public static function clearRateCaches(): void
    {
        $currencies = ['PLN', 'EUR', 'SEK', 'GBP'];
        $countries = [
            'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR',
            'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL',
            'PT', 'RO', 'SE', 'SI', 'SK'
        ];

        foreach ($currencies as $currency) {
            Cache::forget("exchange_rate_{$currency}");
        }

        foreach ($countries as $country) {
            Cache::forget("vat_rate_{$country}");
        }

        Cache::forget('all_exchange_rates');
        Cache::forget('all_vat_rates');
    }

    // Check if rate needs updating (for automatic updates)
    public function needsUpdate(): bool
    {
        if ($this->update_mode !== 'automatic') {
            return false;
        }

        // Update if last updated more than 24 hours ago
        return !$this->last_updated_at ||
               $this->last_updated_at->diffInHours(now()) >= 24;
    }

    // Get formatted rate for display
    public function getFormattedRateAttribute(): string
    {
        if ($this->type === 'exchange_rate') {
            return number_format($this->rate, 6);
        }

        return number_format($this->rate * 100, 2) . '%';
    }

    // Get display name
    public function getDisplayNameAttribute(): string
    {
        if ($this->type === 'exchange_rate') {
            return "{$this->currency} to EUR";
        }

        return "{$this->country} VAT Rate";
    }
}
