<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RateService;
use App\Models\RateSetting;
use Illuminate\Console\Command;
use Exception;

class InitializeRates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rates:initialize
                           {--force : Force initialization even if rates already exist}
                           {--from-api : Initialize exchange rates from API instead of hardcoded values}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize the rates database with default exchange and VAT rates';

    /**
     * Execute the console command.
     */
    public function handle(RateService $rateService): int
    {
        $force = $this->option('force');
        $fromApi = $this->option('from-api');

        $this->info('🚀 Initializing rates database...');

        try {
            // Check if rates already exist
            $existingExchangeRates = RateSetting::exchangeRates()->count();
            $existingVatRates = RateSetting::vatRates()->count();

            if (($existingExchangeRates > 0 || $existingVatRates > 0) && !$force) {
                $this->warn('⚠️  Rates already exist in database!');
                $this->info("   Exchange rates: {$existingExchangeRates}");
                $this->info("   VAT rates: {$existingVatRates}");
                $this->newLine();

                if (!$this->confirm('Do you want to continue anyway? (Use --force to skip this prompt)')) {
                    $this->info('❌ Initialization cancelled');
                    return self::SUCCESS;
                }
            }

            $this->newLine();

            // Initialize exchange rates
            $this->info('💱 Initializing exchange rates...');

            if ($fromApi) {
                $this->info('📡 Fetching latest rates from VATComply API...');
                try {
                    $exchangeRates = $rateService->fetchExchangeRatesFromApi();
                    $this->info('✅ Successfully fetched rates from API');
                } catch (Exception $e) {
                    $this->error('❌ API fetch failed, using fallback rates: ' . $e->getMessage());
                    $exchangeRates = $rateService->getFallbackExchangeRates();
                }
            } else {
                $this->info('📋 Using hardcoded fallback rates...');
                $exchangeRates = $rateService->getFallbackExchangeRates();
            }

            $exchangeRatesCreated = 0;
            foreach ($exchangeRates as $currency => $rate) {
                $existing = RateSetting::exchangeRates()
                    ->where('currency', $currency)
                    ->where('is_active', true)
                    ->first();

                if (!$existing || $force) {
                    RateSetting::updateRate(
                        type: 'exchange_rate',
                        rate: $rate,
                        currency: $currency,
                        source: $fromApi ? 'api_vatcomply' : 'manual',
                        updateMode: $fromApi ? 'automatic' : 'manual',
                        metadata: [
                            'initialized_at' => now()->toISOString(),
                            'initialized_from' => $fromApi ? 'vatcomply_api' : 'hardcoded_fallback'
                        ]
                    );
                    $exchangeRatesCreated++;
                    $this->line("   ✅ {$currency}: " . number_format($rate, 6));
                } else {
                    $this->line("   ⏭️  {$currency}: Already exists, skipping");
                }
            }

            $this->newLine();

            // Initialize VAT rates
            $this->info('📊 Initializing VAT rates...');
            $vatRates = $rateService->getFallbackVatRates();

            $vatRatesCreated = 0;
            foreach ($vatRates as $country => $rate) {
                $existing = RateSetting::vatRates()
                    ->where('country', $country)
                    ->where('is_active', true)
                    ->first();

                if (!$existing || $force) {
                    RateSetting::updateRate(
                        type: 'vat_rate',
                        rate: $rate,
                        country: $country,
                        source: 'manual',
                        updateMode: 'manual',
                        metadata: [
                            'initialized_at' => now()->toISOString(),
                            'initialized_from' => 'hardcoded_oss_rates'
                        ]
                    );
                    $vatRatesCreated++;
                    $this->line("   ✅ {$country}: " . number_format($rate * 100, 2) . '%');
                } else {
                    $this->line("   ⏭️  {$country}: Already exists, skipping");
                }
            }

            $this->newLine();

            // Summary
            $this->info('📋 Initialization Summary:');
            $this->info("   💱 Exchange rates created/updated: {$exchangeRatesCreated}");
            $this->info("   📊 VAT rates created/updated: {$vatRatesCreated}");

            // Clear caches
            RateSetting::clearRateCaches();
            $this->info('🧹 Rate caches cleared');

            $this->newLine();
            $this->info('✅ Rates database initialization completed successfully!');

            // Show next steps
            $this->newLine();
            $this->info('🔧 Next steps:');
            $this->info('   • Run "php artisan rates:update --test" to test API connectivity');
            $this->info('   • Set up daily cron job: "php artisan rates:update"');
            $this->info('   • Access admin interface to manage rates manually');

            return self::SUCCESS;

        } catch (Exception $e) {
            $this->newLine();
            $this->error('❌ Initialization failed: ' . $e->getMessage());

            // Log the error for debugging
            \Log::error('Rate initialization failed', [
                'force' => $force,
                'from_api' => $fromApi,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return self::FAILURE;
        }
    }
}
