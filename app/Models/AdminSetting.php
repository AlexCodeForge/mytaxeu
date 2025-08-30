<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminSetting extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'value',
        'encrypted',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'encrypted' => 'boolean',
        ];
    }

    /**
     * Get the value attribute, decrypting if necessary.
     */
    public function getValueAttribute(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($this->encrypted) {
            try {
                return decrypt($value);
            } catch (\Exception $e) {
                \Log::error('Failed to decrypt admin setting', [
                    'key' => $this->key,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        }

        return $value;
    }

    /**
     * Set the value attribute, encrypting if necessary.
     */
    public function setValueAttribute(?string $value): void
    {
        if ($value === null) {
            $this->attributes['value'] = null;
            return;
        }

        if ($this->encrypted) {
            $this->attributes['value'] = encrypt($value);
        } else {
            $this->attributes['value'] = $value;
        }
    }

    /**
     * Get a setting value by key.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Set a setting value by key.
     */
    public static function setValue(string $key, ?string $value, bool $encrypted = false, ?string $description = null): self
    {
        return self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'encrypted' => $encrypted,
                'description' => $description,
            ]
        );
    }

    /**
     * Get Stripe configuration from admin settings with .env fallback.
     */
    public static function getStripeConfig(): array
    {
        return [
            'public_key' => self::getValue('stripe_public_key') ?: config('cashier.key'),
            'secret_key' => self::getValue('stripe_secret_key') ?: config('cashier.secret'),
            'webhook_secret' => self::getValue('stripe_webhook_secret') ?: config('cashier.webhook.secret'),
            'test_mode' => (bool) self::getValue('stripe_test_mode', false),
        ];
    }

    /**
     * Set Stripe configuration in admin settings.
     */
    public static function setStripeConfig(array $config): void
    {
        if (isset($config['public_key'])) {
            self::setValue('stripe_public_key', $config['public_key'], false, 'Stripe Publishable Key');
        }

        if (isset($config['secret_key'])) {
            self::setValue('stripe_secret_key', $config['secret_key'], true, 'Stripe Secret Key (Encrypted)');
        }

        if (isset($config['webhook_secret'])) {
            self::setValue('stripe_webhook_secret', $config['webhook_secret'], true, 'Stripe Webhook Secret (Encrypted)');
        }

        if (isset($config['test_mode'])) {
            self::setValue('stripe_test_mode', $config['test_mode'] ? '1' : '0', false, 'Stripe Test Mode');
        }
    }

    /**
     * Check if Stripe configuration is available.
     */
    public static function hasStripeConfig(): bool
    {
        $config = self::getStripeConfig();
        return !empty($config['public_key']) && !empty($config['secret_key']);
    }
}
