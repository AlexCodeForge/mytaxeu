<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RateSetting;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class RateService
{
    private const VATCOMPLY_BASE_URL = 'https://api.vatcomply.com';
    private const REQUEST_TIMEOUT = 30;
    private const CACHE_DURATION = 3600; // 1 hour

    // All currencies we support (VATComply API supports most of these - HRK uses fallback)
    private const ALL_REQUIRED_CURRENCIES = [
        'EUR', 'GBP', 'PLN', 'SEK', 'DKK', 'CZK', 'HUF', 'RON', 'BGN', 'HRK',
        'NOK', 'CHF', 'USD', 'CAD', 'AUD', 'JPY', 'CNY', 'INR', 'BRL', 'MXN'
    ];

    // Currencies supported by VATComply API (19 out of 20 - HRK not supported)
    private const VATCOMPLY_SUPPORTED_CURRENCIES = [
        'EUR', 'GBP', 'PLN', 'SEK', 'DKK', 'CZK', 'HUF', 'RON', 'BGN',
        'NOK', 'CHF', 'USD', 'CAD', 'AUD', 'JPY', 'CNY', 'INR', 'BRL', 'MXN'
    ];

    // EU countries we need for VAT rates (from StreamingCsvTransformer)
    private const EU_COUNTRIES = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR',
        'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL',
        'PT', 'RO', 'SE', 'SI', 'SK'
    ];

    /**
     * Fetch latest exchange rates from VATComply API
     *
     * @return array Array of currency => rate mappings
     * @throws Exception If API request fails
     */
    public function fetchExchangeRatesFromApi(): array
    {
        try {
            Log::info('Fetching exchange rates from VATComply API');

            // Get ALL rates from VATComply (no symbol limitation - they support way more than we thought!)
            $url = self::VATCOMPLY_BASE_URL . "/rates?base=EUR";

            $response = Http::timeout(self::REQUEST_TIMEOUT)->get($url);

            if (!$response->successful()) {
                throw new Exception("VATComply API request failed with status: {$response->status()}");
            }

            $data = $response->json();

            if (!isset($data['rates']) || !is_array($data['rates'])) {
                throw new Exception('Invalid response format from VATComply API');
            }

            // Ensure EUR is always 1.0
            $rates = $data['rates'];
            $rates['EUR'] = 1.0;

            // Convert to our format (to EUR rates, not from EUR) - filter to only our required currencies
            $convertedRates = [];
            foreach (self::ALL_REQUIRED_CURRENCIES as $currency) {
                if ($currency === 'EUR') {
                    $convertedRates[$currency] = 1.0;
                } elseif (isset($rates[$currency])) {
                    // Convert from EUR to currency rate to currency to EUR rate
                    $convertedRates[$currency] = 1.0 / $rates[$currency];
                }
            }

            Log::info('Successfully fetched exchange rates from VATComply API', [
                'rates' => $convertedRates,
                'date' => $data['date'] ?? 'unknown'
            ]);

            return $convertedRates;

        } catch (Exception $e) {
            Log::error('Failed to fetch exchange rates from VATComply API', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Update exchange rates from API
     *
     * @param bool $forceUpdate Force update even if recently updated
     * @return array Updated rates
     * @throws Exception If update fails and no fallback available
     */
    public function updateExchangeRatesFromApi(bool $forceUpdate = false): array
    {
        try {
            $rates = $this->fetchExchangeRatesFromApi();

            foreach ($rates as $currency => $rate) {
                // Only update automatic rates or if force update
                $existingRate = RateSetting::exchangeRates()
                    ->where('currency', $currency)
                    ->where('is_active', true)
                    ->first();

                if (!$existingRate || $existingRate->update_mode === 'automatic' || $forceUpdate) {
                    RateSetting::updateRate(
                        type: 'exchange_rate',
                        rate: $rate,
                        currency: $currency,
                        source: 'api_vatcomply',
                        updateMode: $existingRate?->update_mode ?? 'automatic',
                        metadata: [
                            'api_response_date' => now()->toISOString(),
                            'api_url' => self::VATCOMPLY_BASE_URL . '/rates'
                        ]
                    );
                }
            }

            Log::info('Successfully updated exchange rates from API', [
                'updated_currencies' => array_keys($rates),
                'force_update' => $forceUpdate
            ]);

            return $rates;

        } catch (Exception $e) {
            Log::error('Failed to update exchange rates from API', [
                'error' => $e->getMessage(),
                'force_update' => $forceUpdate
            ]);

            // If we have existing rates, return them as fallback
            $fallbackRates = $this->getFallbackExchangeRates();
            if (!empty($fallbackRates)) {
                Log::warning('Using fallback exchange rates');
                return $fallbackRates;
            }

            throw $e;
        }
    }

    /**
     * Get fallback exchange rates (current hardcoded rates from StreamingCsvTransformer)
     *
     * @return array
     */
    public function getFallbackExchangeRates(): array
    {
        return [
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
    }

    /**
     * Get fallback VAT rates (current hardcoded rates from StreamingCsvTransformer)
     *
     * @return array
     */
    public function getFallbackVatRates(): array
    {
        return [
            'AT' => 0.20, 'BE' => 0.21, 'BG' => 0.20, 'CY' => 0.19, 'CZ' => 0.21,
            'DE' => 0.19, 'DK' => 0.25, 'EE' => 0.20, 'ES' => 0.21, 'FI' => 0.24,
            'FR' => 0.20, 'GR' => 0.24, 'HR' => 0.25, 'HU' => 0.27, 'IE' => 0.23,
            'IT' => 0.22, 'LT' => 0.21, 'LU' => 0.17, 'LV' => 0.21, 'MT' => 0.18,
            'NL' => 0.21, 'PL' => 0.23, 'PT' => 0.23, 'RO' => 0.19, 'SE' => 0.25, 'SI' => 0.22, 'SK' => 0.20,
        ];
    }

    /**
     * Initialize default rates in database (seed with current hardcoded values)
     *
     * @return void
     */
    public function initializeDefaultRates(): void
    {
        Log::info('Initializing default rates in database');

        // Initialize exchange rates
        $exchangeRates = $this->getFallbackExchangeRates();
        foreach ($exchangeRates as $currency => $rate) {
            $existing = RateSetting::exchangeRates()
                ->where('currency', $currency)
                ->where('is_active', true)
                ->first();

            if (!$existing) {
                RateSetting::updateRate(
                    type: 'exchange_rate',
                    rate: $rate,
                    currency: $currency,
                    source: 'manual',
                    updateMode: 'manual',
                    metadata: ['initialized_from' => 'StreamingCsvTransformer_hardcoded']
                );
            }
        }

        // Initialize VAT rates
        $vatRates = $this->getFallbackVatRates();
        foreach ($vatRates as $country => $rate) {
            $existing = RateSetting::vatRates()
                ->where('country', $country)
                ->where('is_active', true)
                ->first();

            if (!$existing) {
                RateSetting::updateRate(
                    type: 'vat_rate',
                    rate: $rate,
                    country: $country,
                    source: 'manual',
                    updateMode: 'manual',
                    metadata: ['initialized_from' => 'StreamingCsvTransformer_hardcoded']
                );
            }
        }

        Log::info('Default rates initialization completed');
    }

    /**
     * Get current exchange rates for StreamingCsvTransformer compatibility
     *
     * @return array
     */
    public function getExchangeRatesForTransformer(): array
    {
        try {
            $rates = RateSetting::getCurrentExchangeRates();

            // If no rates in database, use fallback
            if (empty($rates)) {
                Log::warning('No exchange rates found in database, using fallback');
                return $this->getFallbackExchangeRates();
            }

            // Ensure all required currencies are present
            foreach (self::ALL_REQUIRED_CURRENCIES as $currency) {
                if (!isset($rates[$currency])) {
                    Log::warning("Missing exchange rate for {$currency}, using fallback");
                    $fallback = $this->getFallbackExchangeRates();
                    $rates[$currency] = $fallback[$currency] ?? 1.0;
                }
            }

            return $rates;

        } catch (Exception $e) {
            Log::error('Failed to get exchange rates, using fallback', [
                'error' => $e->getMessage()
            ]);
            return $this->getFallbackExchangeRates();
        }
    }

    /**
     * Get current VAT rates for StreamingCsvTransformer compatibility
     *
     * @return array
     */
    public function getVatRatesForTransformer(): array
    {
        try {
            $rates = RateSetting::getCurrentVatRates();

            // If no rates in database, use fallback
            if (empty($rates)) {
                Log::warning('No VAT rates found in database, using fallback');
                return $this->getFallbackVatRates();
            }

            // Ensure all required countries are present
            foreach (self::EU_COUNTRIES as $country) {
                if (!isset($rates[$country])) {
                    Log::warning("Missing VAT rate for {$country}, using fallback");
                    $fallback = $this->getFallbackVatRates();
                    $rates[$country] = $fallback[$country] ?? 0.0;
                }
            }

            return $rates;

        } catch (Exception $e) {
            Log::error('Failed to get VAT rates, using fallback', [
                'error' => $e->getMessage()
            ]);
            return $this->getFallbackVatRates();
        }
    }

    /**
     * Check which rates need updating (for automatic updates)
     *
     * @return array Array with 'exchange_rates' and 'vat_rates' that need updating
     */
    public function getRatesThatNeedUpdating(): array
    {
        $exchangeRates = RateSetting::exchangeRates()
            ->automatic()
            ->active()
            ->get()
            ->filter(fn($rate) => $rate->needsUpdate());

        // VAT rates don't have API source yet, so they don't need automatic updating
        $vatRates = collect();

        return [
            'exchange_rates' => $exchangeRates,
            'vat_rates' => $vatRates
        ];
    }

    /**
     * Update all rates that are set to automatic update
     *
     * @return array Summary of updates
     */
    public function updateAutomaticRates(): array
    {
        $summary = [
            'exchange_rates_updated' => 0,
            'exchange_rates_failed' => 0,
            'vat_rates_updated' => 0,
            'vat_rates_failed' => 0,
            'errors' => []
        ];

        try {
            // Update exchange rates from API
            $rates = $this->updateExchangeRatesFromApi();
            $summary['exchange_rates_updated'] = count($rates);

        } catch (Exception $e) {
            $summary['exchange_rates_failed'] = count(self::ALL_REQUIRED_CURRENCIES);
            $summary['errors'][] = 'Exchange rates update failed: ' . $e->getMessage();
        }

        // VAT rates don't have automatic updates yet (no API)

        Log::info('Automatic rates update completed', $summary);

        return $summary;
    }

    /**
     * Validate API connectivity
     *
     * @return array Validation results
     */
    public function validateApiConnectivity(): array
    {
        $results = [
            'vatcomply_exchange_rates' => false,
            'vatcomply_vat_validation' => false,
            'errors' => []
        ];

        try {
            // Test exchange rates endpoint
            $response = Http::timeout(10)->get(self::VATCOMPLY_BASE_URL . '/rates?symbols=USD');
            $results['vatcomply_exchange_rates'] = $response->successful();

            if (!$results['vatcomply_exchange_rates']) {
                $results['errors'][] = 'VATComply exchange rates API not accessible';
            }

        } catch (Exception $e) {
            $results['errors'][] = 'VATComply exchange rates test failed: ' . $e->getMessage();
        }

        try {
            // Test VAT validation endpoint
            $response = Http::timeout(10)->get(self::VATCOMPLY_BASE_URL . '/vat?vat_number=DE123456789');
            $results['vatcomply_vat_validation'] = $response->successful();

            if (!$results['vatcomply_vat_validation']) {
                $results['errors'][] = 'VATComply VAT validation API not accessible';
            }

        } catch (Exception $e) {
            $results['errors'][] = 'VATComply VAT validation test failed: ' . $e->getMessage();
        }

        return $results;
    }
}
