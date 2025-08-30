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

    // Price IDs configuration
    public string $basicPriceId = '';
    public string $professionalPriceId = '';
    public string $enterprisePriceId = '';
    public array $priceIdsStatus = [];
    public bool $savingPrices = false;

    protected array $rules = [
        'publicKey' => 'required|string|starts_with:pk_',
        'secretKey' => 'required|string|starts_with:sk_',
        'webhookSecret' => 'nullable|string|starts_with:whsec_',
        'testMode' => 'boolean',
        'basicPriceId' => 'nullable|string|starts_with:price_',
        'professionalPriceId' => 'nullable|string|starts_with:price_',
        'enterprisePriceId' => 'nullable|string|starts_with:price_',
    ];

    protected array $messages = [
        'publicKey.required' => 'La clave pública de Stripe es obligatoria.',
        'publicKey.starts_with' => 'La clave pública debe comenzar con "pk_".',
        'secretKey.required' => 'La clave secreta de Stripe es obligatoria.',
        'secretKey.starts_with' => 'La clave secreta debe comenzar con "sk_".',
        'webhookSecret.starts_with' => 'El secreto del webhook debe comenzar con "whsec_".',
        'basicPriceId.starts_with' => 'El ID de precio básico debe comenzar con "price_".',
        'professionalPriceId.starts_with' => 'El ID de precio profesional debe comenzar con "price_".',
        'enterprisePriceId.starts_with' => 'El ID de precio empresarial debe comenzar con "price_".',
    ];

    public function mount(): void
    {
        $this->authorize('viewAny', \App\Models\AdminSetting::class);

        $service = app(StripeConfigurationService::class);
        $config = $service->getConfig();
        $this->configStatus = $service->getConfigurationStatus();

        // Only show masked versions for security
        $this->publicKey = $config['public_key'] ?: '';
        $this->testMode = $config['test_mode'];

        // Don't populate secret fields for security
        $this->secretKey = '';
        $this->webhookSecret = '';

        // Load price IDs configuration
        $priceIds = $service->getPriceIds();
        $this->priceIdsStatus = $service->getPriceIdsStatus();
        $this->basicPriceId = $priceIds['basic'];
        $this->professionalPriceId = $priceIds['professional'];
        $this->enterprisePriceId = $priceIds['enterprise'];
    }

    public function saveConfiguration(): void
    {
        $this->authorize('create', \App\Models\AdminSetting::class);

        $this->saving = true;
        $this->resetErrorBag();

        try {
            $this->validate();

            $service = app(StripeConfigurationService::class);

            $config = [
                'public_key' => $this->publicKey,
                'secret_key' => $this->secretKey,
                'webhook_secret' => $this->webhookSecret ?: null,
                'test_mode' => $this->testMode,
            ];

            $service->setConfig($config);

            // Refresh status
            $this->configStatus = $service->getConfigurationStatus();

            // Clear secret fields after saving for security
            $this->secretKey = '';
            $this->webhookSecret = '';

            session()->flash('message', 'Configuración de Stripe guardada exitosamente.');

        } catch (\InvalidArgumentException $e) {
            $this->addError('general', $e->getMessage());
        } catch (\RuntimeException $e) {
            $this->addError('secretKey', 'Clave API inválida o error de conexión con Stripe.');
        } catch (\Exception $e) {
            $this->addError('general', 'Error inesperado: ' . $e->getMessage());
        } finally {
            $this->saving = false;
        }
    }

    public function testConnection(): void
    {
        $this->authorize('create', \App\Models\AdminSetting::class);

        $this->testing = true;
        $this->resetErrorBag(['secretKey', 'test']);

        try {
            $this->validateOnly('secretKey');

            $service = app(StripeConfigurationService::class);

            if ($service->testApiKey($this->secretKey)) {
                session()->flash('test-success', 'Conexión con Stripe exitosa. La clave API es válida.');
            } else {
                $this->addError('test', 'No se pudo conectar con Stripe. Verifica la clave API.');
            }

        } catch (\Exception $e) {
            $this->addError('test', 'Error al probar la conexión: ' . $e->getMessage());
        } finally {
            $this->testing = false;
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

    public function toggleSecretKeyVisibility(): void
    {
        $this->showSecretKey = !$this->showSecretKey;
    }

    public function toggleWebhookSecretVisibility(): void
    {
        $this->showWebhookSecret = !$this->showWebhookSecret;
    }

    public function savePriceIds(): void
    {
        $this->authorize('create', \App\Models\AdminSetting::class);

        $this->savingPrices = true;
        $this->resetErrorBag();

        try {
            // Validate price IDs
            $this->validate([
                'basicPriceId' => 'nullable|string|starts_with:price_',
                'professionalPriceId' => 'nullable|string|starts_with:price_',
                'enterprisePriceId' => 'nullable|string|starts_with:price_',
            ]);

            $service = app(StripeConfigurationService::class);
            
            $priceIds = [
                'basic' => $this->basicPriceId,
                'professional' => $this->professionalPriceId,
                'enterprise' => $this->enterprisePriceId,
            ];

            if ($service->setPriceIds($priceIds)) {
                $this->priceIdsStatus = $service->getPriceIdsStatus();
                session()->flash('message', 'IDs de precios guardados exitosamente.');
            } else {
                $this->addError('general', 'Error al guardar los IDs de precios.');
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Validation errors are automatically handled by Livewire
        } catch (\Exception $e) {
            $this->addError('general', 'Error inesperado: ' . $e->getMessage());
        } finally {
            $this->savingPrices = false;
        }
    }

    public function clearPriceIds(): void
    {
        $this->authorize('delete', \App\Models\AdminSetting::class);

        try {
            $service = app(StripeConfigurationService::class);
            
            if ($service->clearPriceIds()) {
                $this->basicPriceId = '';
                $this->professionalPriceId = '';
                $this->enterprisePriceId = '';
                $this->priceIdsStatus = $service->getPriceIdsStatus();

                session()->flash('message', 'IDs de precios eliminados exitosamente.');
            } else {
                $this->addError('general', 'Error al eliminar los IDs de precios.');
            }

        } catch (\Exception $e) {
            $this->addError('general', 'Error inesperado: ' . $e->getMessage());
        }
    }

    public function loadCurrentPriceIds(): void
    {
        $service = app(StripeConfigurationService::class);
        
        // Set the current price IDs from our Stripe setup
        $this->basicPriceId = 'price_1S1couBBlYDJOOlgpefIx2gu';
        $this->professionalPriceId = 'price_1S1covBBlYDJOOlguPu91kOL';
        $this->enterprisePriceId = 'price_1S1cowBBlYDJOOlgDacMEp1a';

        session()->flash('message', 'IDs de precios cargados desde la configuración actual.');
    }

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
