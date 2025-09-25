<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RateSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupDuplicateRates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rates:cleanup-duplicates
                           {--dry-run : Show what would be deleted without actually deleting}
                           {--force : Force deletion without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up duplicate rate entries and keep only the most recent ones';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('🧹 Starting cleanup of duplicate rate entries...');

        try {
            // Process exchange rates
            $this->info('📊 Processing exchange rates...');
            $exchangeDeleted = $this->cleanupExchangeRates($dryRun, $force);

            // Process VAT rates
            $this->info('📊 Processing VAT rates...');
            $vatDeleted = $this->cleanupVatRates($dryRun, $force);

            $this->newLine();
            $this->info('📋 Cleanup Summary:');
            $this->info("   💱 Exchange rate duplicates " . ($dryRun ? "would be" : "") . " deleted: {$exchangeDeleted}");
            $this->info("   📊 VAT rate duplicates " . ($dryRun ? "would be" : "") . " deleted: {$vatDeleted}");

            if ($dryRun) {
                $this->newLine();
                $this->warn('🏃 This was a dry run. No data was actually deleted.');
                $this->info('Run without --dry-run to perform the actual cleanup.');
            } else {
                // Clear caches after cleanup
                RateSetting::clearRateCaches();
                $this->info('🧹 Rate caches cleared');
            }

            $this->newLine();
            $this->info('✅ Cleanup process completed successfully!');

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->newLine();
            $this->error('❌ Cleanup failed: ' . $e->getMessage());

            \Log::error('Rate cleanup command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Clean up duplicate exchange rates
     */
    private function cleanupExchangeRates(bool $dryRun, bool $force): int
    {
        $deleted = 0;

        // Get all currencies that have duplicates
        $currencies = RateSetting::exchangeRates()
            ->active()
            ->selectRaw('currency, COUNT(*) as count')
            ->groupBy('currency')
            ->having('count', '>', 1)
            ->pluck('currency');

        foreach ($currencies as $currency) {
            // Get all rates for this currency, ordered by last_updated_at desc
            $rates = RateSetting::exchangeRates()
                ->where('currency', $currency)
                ->where('is_active', true)
                ->orderBy('last_updated_at', 'desc')
                ->get();

            if ($rates->count() > 1) {
                $keepRate = $rates->first(); // Keep the most recent one
                $duplicates = $rates->skip(1); // Mark others as duplicates

                $this->line("   Currency {$currency}: Found {$duplicates->count()} duplicates");
                $this->line("     → Keeping: ID #{$keepRate->id} (updated: {$keepRate->last_updated_at->format('Y-m-d H:i:s')}, mode: {$keepRate->update_mode})");

                foreach ($duplicates as $duplicate) {
                    $this->line("     → " . ($dryRun ? "Would delete" : "Deleting") . ": ID #{$duplicate->id} (updated: {$duplicate->last_updated_at->format('Y-m-d H:i:s')}, mode: {$duplicate->update_mode})");

                    if (!$dryRun) {
                        if ($force || $this->confirm("Delete duplicate rate for {$currency}?", true)) {
                            $duplicate->delete();
                            $deleted++;
                        }
                    } else {
                        $deleted++;
                    }
                }
            }
        }

        return $deleted;
    }

    /**
     * Clean up duplicate VAT rates
     */
    private function cleanupVatRates(bool $dryRun, bool $force): int
    {
        $deleted = 0;

        // Get all countries that have duplicates
        $countries = RateSetting::vatRates()
            ->active()
            ->selectRaw('country, COUNT(*) as count')
            ->groupBy('country')
            ->having('count', '>', 1)
            ->pluck('country');

        foreach ($countries as $country) {
            // Get all rates for this country, ordered by last_updated_at desc
            $rates = RateSetting::vatRates()
                ->where('country', $country)
                ->where('is_active', true)
                ->orderBy('last_updated_at', 'desc')
                ->get();

            if ($rates->count() > 1) {
                $keepRate = $rates->first(); // Keep the most recent one
                $duplicates = $rates->skip(1); // Mark others as duplicates

                $this->line("   Country {$country}: Found {$duplicates->count()} duplicates");
                $this->line("     → Keeping: ID #{$keepRate->id} (updated: {$keepRate->last_updated_at->format('Y-m-d H:i:s')})");

                foreach ($duplicates as $duplicate) {
                    $this->line("     → " . ($dryRun ? "Would delete" : "Deleting") . ": ID #{$duplicate->id} (updated: {$duplicate->last_updated_at->format('Y-m-d H:i:s')})");

                    if (!$dryRun) {
                        if ($force || $this->confirm("Delete duplicate rate for {$country}?", true)) {
                            $duplicate->delete();
                            $deleted++;
                        }
                    } else {
                        $deleted++;
                    }
                }
            }
        }

        return $deleted;
    }
}
