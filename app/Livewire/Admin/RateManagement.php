<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\RateSetting;
use App\Services\RateService;
use Livewire\Component;
use Livewire\Attributes\On;
use Exception;

class RateManagement extends Component
{
    public $selectedTab = 'exchange';

    // Required currencies from StreamingCsvTransformer and CSV processing
    private const REQUIRED_EXCHANGE_CURRENCIES = [
        'EUR', 'GBP', 'PLN', 'SEK', 'DKK', 'CZK', 'HUF', 'RON', 'BGN', 'HRK',
        'NOK', 'CHF', 'USD', 'CAD', 'AUD', 'JPY', 'CNY', 'INR', 'BRL', 'MXN'
    ];
    private const REQUIRED_VAT_COUNTRIES = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR',
        'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL',
        'PT', 'RO', 'SE', 'SI', 'SK'
    ];

    // Edit mode
    public $editingExchangeRate = null;
    public $editingVatRate = null;

    // Edit forms
    public $editExchangeForm = [
        'rate' => '',
        'update_mode' => 'manual'
    ];

    public $editVatForm = [
        'rate' => '',
        'update_mode' => 'manual'
    ];

    // API status
    public $apiConnectivity = [];
    public $lastApiCheck = null;

    protected $rules = [
        'editExchangeForm.rate' => 'required|numeric|min:0',
        'editExchangeForm.update_mode' => 'required|in:manual,automatic',

        'editVatForm.rate' => 'required|numeric|min:0|max:1',
        'editVatForm.update_mode' => 'required|in:manual',
    ];

    public function mount()
    {
        $this->checkApiConnectivity();
    }

    public function render()
    {
        // Ensure all required rates exist
        $this->ensureRequiredRatesExist();

        // Get all required exchange rates (most recent per currency)
        $exchangeRates = collect();
        foreach (self::REQUIRED_EXCHANGE_CURRENCIES as $currency) {
            $rate = RateSetting::exchangeRates()
                ->where('currency', $currency)
                ->where('is_active', true)
                ->orderBy('last_updated_at', 'desc')
                ->first();

            if ($rate) {
                $exchangeRates->push($rate);
            }
        }
        // Sort alphabetically by currency code
        $exchangeRates = $exchangeRates->sortBy('currency')->values();

        // Get all required VAT rates (most recent per country)
        $vatRates = collect();
        foreach (self::REQUIRED_VAT_COUNTRIES as $country) {
            $rate = RateSetting::vatRates()
                ->where('country', $country)
                ->where('is_active', true)
                ->orderBy('last_updated_at', 'desc')
                ->first();

            if ($rate) {
                $vatRates->push($rate);
            }
        }
        // Sort alphabetically by country code
        $vatRates = $vatRates->sortBy('country')->values();

        return view('livewire.admin.rate-management', [
            'exchangeRates' => $exchangeRates,
            'vatRates' => $vatRates,
        ])->layout('layouts.panel');
    }

    private function ensureRequiredRatesExist(): void
    {
        $rateService = app(RateService::class);

        // Exchange rate fallbacks - ALL currencies to EUR
        $exchangeFallbacks = [
            'EUR' => 1.0,      // Base currency
            'GBP' => 1.169827, // British Pound
            'PLN' => 0.319033, // Polish Złoty
            'SEK' => 0.087,    // Swedish Krona
            'DKK' => 0.134,    // Danish Krone
            'CZK' => 0.041,    // Czech Koruna
            'HUF' => 0.0025,   // Hungarian Forint
            'RON' => 0.201,    // Romanian Leu
            'BGN' => 0.511,    // Bulgarian Lev
            'HRK' => 0.133,    // Croatian Kuna
            'NOK' => 0.087,    // Norwegian Krone
            'CHF' => 1.081,    // Swiss Franc
            'USD' => 0.926,    // US Dollar
            'CAD' => 0.679,    // Canadian Dollar
            'AUD' => 0.602,    // Australian Dollar
            'JPY' => 0.0062,   // Japanese Yen
            'CNY' => 0.129,    // Chinese Yuan
            'INR' => 0.011,    // Indian Rupee
            'BRL' => 0.162,    // Brazilian Real
            'MXN' => 0.047,    // Mexican Peso
        ];

        // VAT rate fallbacks from StreamingCsvTransformer
        $vatFallbacks = [
            'AT' => 0.20, 'BE' => 0.21, 'BG' => 0.20, 'CY' => 0.19, 'CZ' => 0.21,
            'DE' => 0.19, 'DK' => 0.25, 'EE' => 0.20, 'ES' => 0.21, 'FI' => 0.24,
            'FR' => 0.20, 'GR' => 0.24, 'HR' => 0.25, 'HU' => 0.27, 'IE' => 0.23,
            'IT' => 0.22, 'LT' => 0.21, 'LU' => 0.17, 'LV' => 0.21, 'MT' => 0.18,
            'NL' => 0.21, 'PL' => 0.23, 'PT' => 0.23, 'RO' => 0.19, 'SE' => 0.25, 'SI' => 0.22, 'SK' => 0.20,
        ];

        // Ensure all required exchange rates exist
        foreach (self::REQUIRED_EXCHANGE_CURRENCIES as $currency) {
            $existing = RateSetting::exchangeRates()
                ->where('currency', $currency)
                ->where('is_active', true)
                ->orderBy('last_updated_at', 'desc')
                ->first();

            if (!$existing) {
                // HRK is manual only (not supported by VATComply API), others are automatic
                $defaultMode = ($currency === 'HRK') ? 'manual' : 'automatic';

                RateSetting::updateRate(
                    type: 'exchange_rate',
                    rate: $exchangeFallbacks[$currency],
                    currency: $currency,
                    source: 'manual',
                    updateMode: $defaultMode,
                    metadata: ['auto_created_required' => true]
                );
            }
        }

        // Ensure all required VAT rates exist
        foreach (self::REQUIRED_VAT_COUNTRIES as $country) {
            $existing = RateSetting::vatRates()
                ->where('country', $country)
                ->where('is_active', true)
                ->orderBy('last_updated_at', 'desc')
                ->first();

            if (!$existing) {
                RateSetting::updateRate(
                    type: 'vat_rate',
                    rate: $vatFallbacks[$country],
                    country: $country,
                    source: 'manual',
                    updateMode: 'manual',
                    metadata: ['auto_created_required' => true]
                );
            }
        }
    }

    public function checkApiConnectivity()
    {
        try {
            $rateService = app(RateService::class);
            $this->apiConnectivity = $rateService->validateApiConnectivity();
            $this->lastApiCheck = now()->format('Y-m-d H:i:s');

            // Provide feedback based on connectivity results
            $exchangeConnected = $this->apiConnectivity['vatcomply_exchange_rates'] ?? false;
            $vatConnected = $this->apiConnectivity['vatcomply_vat_validation'] ?? false;

            if ($exchangeConnected && $vatConnected) {
                $this->dispatch('rate-updated', '✅ Estado verificado: Todas las APIs están conectadas y funcionando correctamente.');
            } elseif ($exchangeConnected) {
                $this->dispatch('rate-updated', '⚠️ Estado verificado: API de tarifas de cambio conectada. API de validación VAT desconectada.');
            } elseif ($vatConnected) {
                $this->dispatch('rate-updated', '⚠️ Estado verificado: API de validación VAT conectada. API de tarifas de cambio desconectada.');
            } else {
                $this->dispatch('rate-updated', '❌ Estado verificado: Todas las APIs están desconectadas. Revisa tu conexión a internet.');
            }

        } catch (Exception $e) {
            $this->apiConnectivity = ['errors' => ['Failed to check API connectivity: ' . $e->getMessage()]];
            $this->dispatch('rate-updated', '❌ Error al verificar estado: ' . $e->getMessage());
        }
    }

    public function setTab($tab)
    {
        $this->selectedTab = $tab;
        $this->resetErrorBag();
        $this->resetEditModes();
    }

    // Exchange Rate Management
    public function editExchangeRate($currency)
    {
        $rate = RateSetting::exchangeRates()
            ->where('currency', $currency)
            ->where('is_active', true)
            ->orderBy('last_updated_at', 'desc')
            ->first();

        if ($rate) {
            $this->editingExchangeRate = $currency;
            $this->editExchangeForm = [
                'rate' => $rate->rate,
                'update_mode' => $rate->update_mode
            ];
        }
    }

    public function updateExchangeRate($currency)
    {
        $this->validate([
            'editExchangeForm.rate' => 'required|numeric|min:0',
            'editExchangeForm.update_mode' => 'required|in:manual,automatic',
        ]);

        try {
            RateSetting::updateRate(
                type: 'exchange_rate',
                rate: (float) $this->editExchangeForm['rate'],
                currency: $currency,
                source: 'manual',
                updateMode: $this->editExchangeForm['update_mode'],
                metadata: [
                    'updated_by_admin' => true,
                    'updated_at' => now()->toISOString()
                ]
            );

            $this->resetEditModes();
            $this->dispatch('rate-updated', "Exchange rate for {$currency} updated successfully");

        } catch (Exception $e) {
            $this->addError('editExchangeForm.rate', 'Failed to update exchange rate: ' . $e->getMessage());
        }
    }

    public function cancelEditExchangeRate()
    {
        $this->resetEditModes();
    }

    // VAT Rate Management
    public function editVatRate($country)
    {
        $rate = RateSetting::vatRates()
            ->where('country', $country)
            ->where('is_active', true)
            ->orderBy('last_updated_at', 'desc')
            ->first();

        if ($rate) {
            $this->editingVatRate = $country;
            $this->editVatForm = [
                'rate' => $rate->rate,
                'update_mode' => 'manual'  // VAT rates are always manual
            ];
        }
    }

    public function updateVatRate($country)
    {
        $this->validate([
            'editVatForm.rate' => 'required|numeric|min:0|max:1',
            'editVatForm.update_mode' => 'required|in:manual',
        ]);

        try {
            RateSetting::updateRate(
                type: 'vat_rate',
                rate: (float) $this->editVatForm['rate'],
                country: $country,
                source: 'manual',
                updateMode: 'manual',  // VAT rates are always manual (no API support)
                metadata: [
                    'updated_by_admin' => true,
                    'updated_at' => now()->toISOString()
                ]
            );

            $this->resetEditModes();
            $this->dispatch('rate-updated', "VAT rate for {$country} updated successfully");

        } catch (Exception $e) {
            $this->addError('editVatForm.rate', 'Failed to update VAT rate: ' . $e->getMessage());
        }
    }

    public function cancelEditVatRate()
    {
        $this->resetEditModes();
    }

    // API Operations
    public function updateFromApi($type = 'exchange')
    {
        try {
            $rateService = app(RateService::class);

            if ($type === 'exchange') {
                $rates = $rateService->updateExchangeRatesFromApi(true);

                if (!empty($rates)) {
                    $apiCount = count($rates);
                    $totalCount = 20; // Total currencies in system
                    $fallbackCount = $totalCount - $apiCount;

                    $sampleRates = array_slice($rates, 0, 5, true);
                    $ratesList = [];
                    foreach ($sampleRates as $currency => $rate) {
                        $ratesList[] = "$currency: " . number_format($rate, 6);
                    }

                    $message = "🎉 API Update SUCCESS! Updated {$apiCount} currencies from VATComply API: " . implode(', ', $ratesList);
                    if (count($rates) > 5) {
                        $message .= "... (" . (count($rates) - 5) . " more)";
                    }
                    if ($fallbackCount > 0) {
                        $message .= ". {$fallbackCount} currency uses manual fallback (HRK - not supported by VATComply).";
                    }
                } else {
                    $message = 'API connected successfully, but no rates needed updating (all current rates are set to manual or already up to date)';
                }
            } else {
                $message = 'VAT rates API update not available yet';
            }

            $this->dispatch('rate-updated', $message);

        } catch (Exception $e) {
            $this->addError('general', 'API update failed: ' . $e->getMessage());
        }
    }

    // Helper methods
    private function resetEditModes()
    {
        $this->editingExchangeRate = null;
        $this->editingVatRate = null;
        $this->editExchangeForm = ['rate' => '', 'update_mode' => 'manual'];
        $this->editVatForm = ['rate' => '', 'update_mode' => 'manual'];
        $this->resetErrorBag();
    }

    #[On('rate-updated')]
    public function handleRateUpdated($message)
    {
        session()->flash('success', $message);
    }
}
