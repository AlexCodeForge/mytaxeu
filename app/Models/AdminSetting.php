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
                    'value_length' => strlen($value),
                    'value_preview' => substr($value, 0, 50),
                    'app_key_set' => !empty(config('app.key')),
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
            try {
                $encrypted = encrypt($value);
                $this->attributes['value'] = $encrypted;
                \Log::info('Successfully encrypted admin setting', [
                    'key' => $this->key,
                    'original_length' => strlen($value),
                    'encrypted_length' => strlen($encrypted),
                    'app_key_set' => !empty(config('app.key')),
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to encrypt admin setting', [
                    'key' => $this->key,
                    'error' => $e->getMessage(),
                    'value_length' => strlen($value),
                    'app_key_set' => !empty(config('app.key')),
                ]);
                throw $e;
            }
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
        \Log::info('setValue called', [
            'key' => $key,
            'has_value' => !empty($value),
            'value_length' => strlen($value ?? ''),
            'encrypted' => $encrypted,
            'has_description' => !empty($description),
        ]);

        // Find existing setting or create new one
        $setting = self::firstOrNew(['key' => $key]);

        // Set the encrypted flag first so the mutator knows what to do
        $setting->encrypted = $encrypted;
        $setting->description = $description;

        // Now set the value - this will trigger the mutator if needed
        $setting->value = $value;

        // Save the model
        $setting->save();

        \Log::info('setValue completed', [
            'key' => $key,
            'setting_id' => $setting->id,
            'setting_encrypted' => $setting->encrypted,
            'raw_value_length' => strlen($setting->attributes['value'] ?? ''),
            'mutator_used' => $encrypted,
        ]);

        return $setting;
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
        \Log::info('setStripeConfig called', [
            'has_public_key' => isset($config['public_key']),
            'has_secret_key' => isset($config['secret_key']),
            'has_webhook_secret' => isset($config['webhook_secret']),
            'has_test_mode' => isset($config['test_mode']),
        ]);

        if (isset($config['public_key'])) {
            \Log::info('Setting public key');
            self::setValue('stripe_public_key', $config['public_key'], false, 'Stripe Publishable Key');
        }

        if (isset($config['secret_key'])) {
            \Log::info('Setting secret key with encryption');
            self::setValue('stripe_secret_key', $config['secret_key'], true, 'Stripe Secret Key (Encrypted)');
        }

        if (isset($config['webhook_secret'])) {
            \Log::info('Setting webhook secret with encryption');
            self::setValue('stripe_webhook_secret', $config['webhook_secret'], true, 'Stripe Webhook Secret (Encrypted)');
        }

        if (isset($config['test_mode'])) {
            \Log::info('Setting test mode');
            self::setValue('stripe_test_mode', $config['test_mode'] ? '1' : '0', false, 'Stripe Test Mode');
        }

        \Log::info('setStripeConfig completed');
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
