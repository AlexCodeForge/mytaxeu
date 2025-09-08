<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class EmailSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'category',
        'subcategory',
        'value',
        'type',
        'label',
        'description',
        'options',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'options' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'display_value',
    ];

    /**
     * Get a setting value by key with caching.
     */
    public static function getValue(string $key, $default = null)
    {
        // Check if we're in the config loading phase (facades not available)
        if (!app()->bound('cache') || !app('cache')->getStore()) {
            return self::getValueDirect($key, $default);
        }

        $cacheKey = "email_setting_{$key}";

        return Cache::remember($cacheKey, 3600, function () use ($key, $default) {
            return self::getValueDirect($key, $default);
        });
    }

    /**
     * Get a setting value directly from database without caching.
     */
    protected static function getValueDirect(string $key, $default = null)
    {
        try {
            $setting = self::where('key', $key)->where('is_active', true)->first();

            if (!$setting) {
                return $default;
            }

            return self::castValue($setting->value, $setting->type);
        } catch (\Exception $e) {
            // If database isn't ready or table doesn't exist, return default
            return $default;
        }
    }

    /**
     * Set a setting value and clear cache.
     */
    public static function setValue(string $key, $value): bool
    {
        try {
            $setting = self::where('key', $key)->first();

            if (!$setting) {
                return false;
            }

            $setting->value = $value;
            $setting->save();

            // Clear cache only if cache is available
            if (app()->bound('cache') && app('cache')->getStore()) {
                Cache::forget("email_setting_{$key}");
                Cache::forget('email_settings_all');
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get all settings grouped by category.
     */
    public static function getAllGrouped(): array
    {
        // Check if we're in the config loading phase (facades not available)
        if (!app()->bound('cache') || !app('cache')->getStore()) {
            return self::getAllGroupedDirect();
        }

        return Cache::remember('email_settings_all', 3600, function () {
            return self::getAllGroupedDirect();
        });
    }

    /**
     * Get all settings grouped by category directly from database.
     */
    protected static function getAllGroupedDirect(): array
    {
        try {
            $settings = self::where('is_active', true)
                ->orderBy('category')
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get();

            // Group by category and ensure appends are included
            $grouped = [];
            foreach ($settings as $setting) {
                $category = $setting->category;
                if (!isset($grouped[$category])) {
                    $grouped[$category] = [];
                }
                // Convert to array and ensure display_value is included
                $settingArray = $setting->toArray();
                $grouped[$category][] = $settingArray;
            }

            return $grouped;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get settings by category.
     */
    public static function getByCategory(string $category): array
    {
        try {
            $settings = self::where('category', $category)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get();

            // Convert to array and ensure appends (like display_value) are included
            return $settings->map(function ($setting) {
                return $setting->toArray();
            })->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Cast value to appropriate type.
     */
    protected static function castValue($value, string $type)
    {
        switch ($type) {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'integer':
                return (int) $value;
            case 'array':
                return is_string($value) ? json_decode($value, true) : $value;
            case 'email':
            case 'string':
            default:
                return (string) $value;
        }
    }

    /**
     * Clear all email settings cache.
     */
    public static function clearCache(): void
    {
        // Only clear cache if cache is available
        if (!app()->bound('cache') || !app('cache')->getStore()) {
            return;
        }

        Cache::forget('email_settings_all');

        // Clear individual setting caches
        try {
            $keys = self::pluck('key');
            foreach ($keys as $key) {
                Cache::forget("email_setting_{$key}");
            }
        } catch (\Exception $e) {
            // Ignore cache clearing errors during bootstrap
        }
    }

    /**
     * Boot method to clear cache on model events.
     */
    protected static function boot()
    {
        parent::boot();

        static::saved(function () {
            try {
                self::clearCache();
            } catch (\Exception $e) {
                // Ignore cache clearing errors during bootstrap
            }
        });

        static::deleted(function () {
            try {
                self::clearCache();
            } catch (\Exception $e) {
                // Ignore cache clearing errors during bootstrap
            }
        });
    }

    /**
     * Get the formatted value for display.
     */
    public function getDisplayValueAttribute(): string
    {
        $value = self::castValue($this->value, $this->type);

        switch ($this->type) {
            case 'boolean':
                return $value ? 'Habilitado' : 'Deshabilitado';
            case 'array':
                return is_array($value) ? implode(', ', $value) : (string) $value;
            default:
                return (string) $value;
        }
    }

    /**
     * Scope for active settings.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for settings by category.
     */
    public function scopeInCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
