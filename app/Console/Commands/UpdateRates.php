<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RateService;
use Illuminate\Console\Command;
use Exception;

class UpdateRates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rates:update
                           {--type=auto : Type of update (auto, exchange, vat, all)}
                           {--force : Force update even if recently updated}
                           {--test : Test API connectivity before updating}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update exchange rates and VAT rates from external APIs';

    /**
     * Execute the console command.
     */
    public function handle(RateService $rateService): int
    {
        $type = $this->option('type');
        $force = $this->option('force');
        $test = $this->option('test');

        $this->info('🔄 Starting rate update process...');

        try {
            // Test API connectivity if requested
            if ($test) {
                $this->info('🔍 Testing API connectivity...');
                $connectivity = $rateService->validateApiConnectivity();

                if ($connectivity['vatcomply_exchange_rates']) {
                    $this->info('✅ VATComply exchange rates API: Connected');
                } else {
                    $this->error('❌ VATComply exchange rates API: Failed');
                }

                if ($connectivity['vatcomply_vat_validation']) {
                    $this->info('✅ VATComply VAT validation API: Connected');
                } else {
                    $this->error('❌ VATComply VAT validation API: Failed');
                }

                if (!empty($connectivity['errors'])) {
                    foreach ($connectivity['errors'] as $error) {
                        $this->error("⚠️  {$error}");
                    }
                }

                $this->newLine();
            }

            // Perform updates based on type
            switch ($type) {
                case 'auto':
                    $this->info('🤖 Updating automatic rates only...');
                    $summary = $rateService->updateAutomaticRates();
                    $this->displayUpdateSummary($summary);
                    break;

                case 'exchange':
                    $this->info('💱 Updating exchange rates from VATComply API...');
                    $rates = $rateService->updateExchangeRatesFromApi($force);
                    $this->info("✅ Updated " . count($rates) . " exchange rates:");
                    foreach ($rates as $currency => $rate) {
                        $this->line("   {$currency}: " . number_format($rate, 6));
                    }
                    break;

                case 'vat':
                    $this->warn('ℹ️  VAT rates update not implemented yet (no API available)');
                    $this->info('VAT rates are currently managed manually in the admin interface');
                    break;

                case 'all':
                    $this->info('🌐 Updating all rates...');

                    // Exchange rates
                    try {
                        $rates = $rateService->updateExchangeRatesFromApi($force);
                        $this->info("✅ Exchange rates updated: " . count($rates) . " currencies");
                    } catch (Exception $e) {
                        $this->error("❌ Exchange rates update failed: " . $e->getMessage());
                    }

                    // VAT rates (placeholder)
                    $this->warn('ℹ️  VAT rates are managed manually');
                    break;

                default:
                    $this->error("❌ Invalid update type: {$type}");
                    $this->info('Valid types: auto, exchange, vat, all');
                    return self::FAILURE;
            }

            $this->newLine();
            $this->info('✅ Rate update process completed successfully!');
            return self::SUCCESS;

        } catch (Exception $e) {
            $this->newLine();
            $this->error('❌ Rate update failed: ' . $e->getMessage());

            // Log the error for debugging
            \Log::error('Rate update command failed', [
                'type' => $type,
                'force' => $force,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Display update summary in a formatted way
     */
    private function displayUpdateSummary(array $summary): void
    {
        $this->info('📊 Update Summary:');

        if ($summary['exchange_rates_updated'] > 0) {
            $this->info("   ✅ Exchange rates updated: {$summary['exchange_rates_updated']}");
        }

        if ($summary['exchange_rates_failed'] > 0) {
            $this->error("   ❌ Exchange rates failed: {$summary['exchange_rates_failed']}");
        }

        if ($summary['vat_rates_updated'] > 0) {
            $this->info("   ✅ VAT rates updated: {$summary['vat_rates_updated']}");
        }

        if ($summary['vat_rates_failed'] > 0) {
            $this->error("   ❌ VAT rates failed: {$summary['vat_rates_failed']}");
        }

        if (!empty($summary['errors'])) {
            $this->newLine();
            $this->error('🚨 Errors encountered:');
            foreach ($summary['errors'] as $error) {
                $this->error("   • {$error}");
            }
        }
    }
}
