<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Services\StripeConfigurationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class StripeConfiguration extends Component
{
    use AuthorizesRequests;

    public string $publicKey = '';
    public string $secretKey = '';
    public string $webhookSecret = '';
    public bool $testMode = false;
    public bool $showSecretKey = false;
    public bool $showWebhookSecret = false;
    public array $configStatus = [];
    public bool $saving = false;
    public bool $testing = false;

    // Price IDs configuration - COMMENTED OUT (no longer needed)
    // public string $basicPriceId = '';
    // public string $professionalPriceId = '';
    // public string $enterprisePriceId = '';
    // public array $priceIdsStatus = [];
    // public bool $savingPrices = false;

    protected array $rules = [
        'publicKey' => 'required|string|starts_with:pk_',
        'secretKey' => 'required|string|starts_with:sk_',
        'webhookSecret' => 'nullable|string|starts_with:whsec_',
        'testMode' => 'boolean',
        // 'basicPriceId' => 'nullable|string|starts_with:price_',
        // 'professionalPriceId' => 'nullable|string|starts_with:price_',
        // 'enterprisePriceId' => 'nullable|string|starts_with:price_',
    ];

    protected array $messages = [
        'publicKey.required' => 'La clave pública de Stripe es obligatoria.',
        'publicKey.starts_with' => 'La clave pública debe comenzar con "pk_".',
        'secretKey.required' => 'La clave secreta de Stripe es obligatoria.',
        'secretKey.starts_with' => 'La clave secreta debe comenzar con "sk_".',
        'webhookSecret.starts_with' => 'El secreto del webhook debe comenzar con "whsec_".',
        // 'basicPriceId.starts_with' => 'El ID de precio básico debe comenzar con "price_".',
        // 'professionalPriceId.starts_with' => 'El ID de precio profesional debe comenzar con "price_".',
        // 'enterprisePriceId.starts_with' => 'El ID de precio empresarial debe comenzar con "price_".'
    ];

    public function mount(): void
    {
        try {
            $this->authorize('viewAny', \App\Models\AdminSetting::class);

            $service = app(StripeConfigurationService::class);
            $config = $service->getConfig();
            $this->configStatus = $service->getConfigurationStatus();

            // Log debug information
            \Log::info('Stripe configuration loaded in mount()', [
                'config' => [
                    'has_public_key' => !empty($config['public_key']),
                    'has_secret_key' => !empty($config['secret_key']),
                    'has_webhook_secret' => !empty($config['webhook_secret']),
                    'test_mode' => $config['test_mode'],
                    'public_key_length' => strlen($config['public_key'] ?? ''),
                    'secret_key_length' => strlen($config['secret_key'] ?? ''),
                ],
                'config_status' => $this->configStatus,
            ]);

            // Only show masked versions for security
            $this->publicKey = $config['public_key'] ?: '';
            $this->testMode = $config['test_mode'];

            // Don't populate secret fields for security
            $this->secretKey = '';
            $this->webhookSecret = '';

            // Load price IDs configuration - COMMENTED OUT (no longer needed)
            // $priceIds = $service->getPriceIds();
            // $this->priceIdsStatus = $service->getPriceIdsStatus();
            // $this->basicPriceId = $priceIds['basic'];
            // $this->professionalPriceId = $priceIds['professional'];
            // $this->enterprisePriceId = $priceIds['enterprise'];

        } catch (\Exception $e) {
            \Log::error('Error in StripeConfiguration mount()', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Add user-friendly error message
            $this->addError('general', 'Error al cargar la configuración de Stripe: ' . $e->getMessage());

            // Set safe defaults
            $this->publicKey = '';
            $this->secretKey = '';
            $this->webhookSecret = '';
            $this->testMode = false;
            $this->configStatus = [
                'configured' => false,
                'has_public_key' => false,
                'has_secret_key' => false,
                'has_webhook_secret' => false,
                'test_mode' => false,
                'public_key_preview' => '',
                'secret_key_preview' => '',
            ];
        }
    }

    public function saveConfiguration(): void
    {
        $this->authorize('create', \App\Models\AdminSetting::class);

        $this->saving = true;
        $this->resetErrorBag();

        \Log::info('Livewire saveConfiguration started', [
            'has_public_key' => !empty($this->publicKey),
            'has_secret_key' => !empty($this->secretKey),
            'has_webhook_secret' => !empty($this->webhookSecret),
            'test_mode' => $this->testMode,
            'public_key_length' => strlen($this->publicKey),
            'secret_key_length' => strlen($this->secretKey),
        ]);

        try {
            \Log::info('Starting validation');
            $this->validate();
            \Log::info('Validation passed');

            $service = app(StripeConfigurationService::class);

            $config = [
                'public_key' => $this->publicKey,
                'secret_key' => $this->secretKey,
                'webhook_secret' => $this->webhookSecret ?: null,
                'test_mode' => $this->testMode,
            ];

            \Log::info('Calling StripeConfigurationService::setConfig');
            $service->setConfig($config);
            \Log::info('StripeConfigurationService::setConfig completed successfully');

            // Refresh status
            $this->configStatus = $service->getConfigurationStatus();
            \Log::info('Configuration status refreshed', $this->configStatus);

            // Clear secret fields after saving for security
            $this->secretKey = '';
            $this->webhookSecret = '';

            \Log::info('Stripe configuration saved successfully via Livewire');
            session()->flash('message', 'Configuración de Stripe guardada exitosamente.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::warning('Stripe configuration validation failed', [
                'errors' => $e->errors(),
            ]);
            // Validation errors are automatically handled by Livewire
        } catch (\InvalidArgumentException $e) {
            \Log::error('Stripe configuration invalid argument', [
                'error' => $e->getMessage(),
            ]);
            $this->addError('general', $e->getMessage());
        } catch (\RuntimeException $e) {
            \Log::error('Stripe configuration runtime error', [
                'error' => $e->getMessage(),
            ]);
            $this->addError('secretKey', 'Clave API inválida o error de conexión con Stripe: ' . $e->getMessage());
        } catch (\Exception $e) {
            \Log::error('Stripe configuration unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->addError('general', 'Error inesperado: ' . $e->getMessage());
        } finally {
            $this->saving = false;
            \Log::info('Livewire saveConfiguration finished', ['saving' => false]);
        }
    }

    public function testConnection(): void
    {
        $this->authorize('create', \App\Models\AdminSetting::class);

        $this->testing = true;
        $this->resetErrorBag(['secretKey', 'test']);

        \Log::info('Livewire testConnection started', [
            'has_secret_key' => !empty($this->secretKey),
            'secret_key_length' => strlen($this->secretKey),
            'secret_key_prefix' => substr($this->secretKey, 0, 10),
        ]);

        try {
            \Log::info('Validating secret key field');
            $this->validateOnly('secretKey');
            \Log::info('Secret key validation passed');

            $service = app(StripeConfigurationService::class);

            \Log::info('Testing API key connection');
            if ($service->testApiKey($this->secretKey)) {
                \Log::info('API key test successful');
                session()->flash('test-success', 'Conexión con Stripe exitosa. La clave API es válida.');
            } else {
                \Log::warning('API key test failed');
                $this->addError('test', 'No se pudo conectar con Stripe. Verifica la clave API.');
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::warning('Secret key validation failed', [
                'errors' => $e->errors(),
            ]);
            // Validation errors are automatically handled by Livewire
        } catch (\Exception $e) {
            \Log::error('Unexpected error during connection test', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->addError('test', 'Error al probar la conexión: ' . $e->getMessage());
        } finally {
            $this->testing = false;
            \Log::info('Livewire testConnection finished', ['testing' => false]);
        }
    }

    public function clearConfiguration(): void
    {
        $this->authorize('delete', \App\Models\AdminSetting::class);

        try {
            $service = app(StripeConfigurationService::class);

            if ($service->clearConfig()) {
                $this->publicKey = '';
                $this->secretKey = '';
                $this->webhookSecret = '';
                $this->testMode = false;
                $this->configStatus = $service->getConfigurationStatus();

                session()->flash('message', 'Configuración de Stripe eliminada exitosamente.');
            } else {
                $this->addError('general', 'Error al eliminar la configuración.');
            }

        } catch (\Exception $e) {
            $this->addError('general', 'Error inesperado: ' . $e->getMessage());
        }
    }

    /**
     * Test the current saved Stripe configuration
     */
    public function testCurrentConfiguration(): void
    {
        $this->authorize('create', \App\Models\AdminSetting::class);

        $this->testing = true;
        $this->resetErrorBag(['test', 'general']);

        \Log::info('Testing current saved Stripe configuration');

        try {
            $service = app(StripeConfigurationService::class);

            // Get the current configuration from database
            $config = $service->getConfig();

            \Log::info('Current configuration retrieved for testing', [
                'has_public_key' => !empty($config['public_key']),
                'has_secret_key' => !empty($config['secret_key']),
                'has_webhook_secret' => !empty($config['webhook_secret']),
                'test_mode' => $config['test_mode'],
            ]);

            if (empty($config['secret_key'])) {
                $this->addError('test', 'No hay configuración de Stripe guardada para probar.');
                return;
            }

            \Log::info('Testing stored API key');
            if ($service->testApiKey($config['secret_key'])) {
                \Log::info('Stored configuration test successful');
                session()->flash('test-success', 'La configuración de Stripe guardada funciona correctamente. Conexión exitosa.');
            } else {
                \Log::warning('Stored configuration test failed');
                $this->addError('test', 'La configuración guardada no es válida. La clave API no funciona.');
            }

        } catch (\Exception $e) {
            \Log::error('Unexpected error testing current configuration', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->addError('test', 'Error al probar la configuración: ' . $e->getMessage());
        } finally {
            $this->testing = false;
            \Log::info('Configuration test finished');
        }
    }

    public function toggleSecretKeyVisibility(): void
    {
        $this->showSecretKey = !$this->showSecretKey;
    }

    public function toggleWebhookSecretVisibility(): void
    {
        $this->showWebhookSecret = !$this->showWebhookSecret;
    }

    // COMMENTED OUT - savePriceIds method no longer needed
    // public function savePriceIds(): void
    // {
    //     $this->authorize('create', \App\Models\AdminSetting::class);

    //     $this->savingPrices = true;
    //     $this->resetErrorBag();

    //     try {
    //         // Validate price IDs
    //         $this->validate([
    //             'basicPriceId' => 'nullable|string|starts_with:price_',
    //             'professionalPriceId' => 'nullable|string|starts_with:price_',
    //             'enterprisePriceId' => 'nullable|string|starts_with:price_',
    //         ]);

    //         $service = app(StripeConfigurationService::class);

    //         $priceIds = [
    //             'basic' => $this->basicPriceId,
    //             'professional' => $this->professionalPriceId,
    //             'enterprise' => $this->enterprisePriceId,
    //         ];

    //         if ($service->setPriceIds($priceIds)) {
    //             $this->priceIdsStatus = $service->getPriceIdsStatus();
    //             session()->flash('message', 'IDs de precios guardados exitosamente.');
    //         } else {
    //             $this->addError('general', 'Error al guardar los IDs de precios.');
    //         }

    //     } catch (\Illuminate\Validation\ValidationException $e) {
    //         // Validation errors are automatically handled by Livewire
    //     } catch (\Exception $e) {
    //         $this->addError('general', 'Error inesperado: ' . $e->getMessage());
    //     } finally {
    //         $this->savingPrices = false;
    //     }
    // }

    // COMMENTED OUT - clearPriceIds method no longer needed
    // public function clearPriceIds(): void
    // {
    //     $this->authorize('delete', \App\Models\AdminSetting::class);

    //     try {
    //         $service = app(StripeConfigurationService::class);

    //         if ($service->clearPriceIds()) {
    //             $this->basicPriceId = '';
    //             $this->professionalPriceId = '';
    //             $this->enterprisePriceId = '';
    //             $this->priceIdsStatus = $service->getPriceIdsStatus();

    //             session()->flash('message', 'IDs de precios eliminados exitosamente.');
    //         } else {
    //             $this->addError('general', 'Error al eliminar los IDs de precios.');
    //         }

    //     } catch (\Exception $e) {
    //         $this->addError('general', 'Error inesperado: ' . $e->getMessage());
    //     }
    // }

    // COMMENTED OUT - loadCurrentPriceIds method no longer needed
    // public function loadCurrentPriceIds(): void
    // {
    //     $service = app(StripeConfigurationService::class);

    //     // Set the current price IDs from our Stripe setup
    //     $this->basicPriceId = 'price_1S1couBBlYDJOOlgpefIx2gu';
    //     $this->professionalPriceId = 'price_1S1covBBlYDJOOlguPu91kOL';
    //     $this->enterprisePriceId = 'price_1S1cowBBlYDJOOlgDacMEp1a';

    //     session()->flash('message', 'IDs de precios cargados desde la configuración actual.');
    // }

    public function getWebhookUrlProperty(): string
    {
        $service = app(StripeConfigurationService::class);
        return $service->getWebhookUrl();
    }

    public function getRecommendedEventsProperty(): array
    {
        $service = app(StripeConfigurationService::class);
        return $service->getRecommendedWebhookEvents();
    }

    public function render()
    {
        return view('livewire.admin.stripe-configuration')->layout('layouts.panel');
    }
}
