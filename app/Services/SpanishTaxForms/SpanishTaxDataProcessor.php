<?php

declare(strict_types=1);

namespace App\Services\SpanishTaxForms;

use App\Services\StreamingCsvTransformer;
use App\Services\RateService;
// use Illuminate\Support\Facades\Log;

/**
 * Data processor that extracts Spanish tax relevant data from CSV input
 * Uses the same aggregation logic as StreamingCsvTransformer
 */
class SpanishTaxDataProcessor
{
    private StreamingCsvTransformer $transformer;
    private array $categoryAggregates = [];
    private array $activityPeriods = [];

    public function __construct(RateService $rateService)
    {
        $this->transformer = new StreamingCsvTransformer($rateService);
    }

    /**
     * Process CSV file and return aggregated data for Spanish tax forms
     */
    public function processFile(string $inputPath): array
    {
        // Log::info('Processing CSV file for Spanish tax forms', ['input' => $inputPath]);
        echo "Processing CSV file for Spanish tax forms: {$inputPath}\n";

        // Use reflection to access private methods of StreamingCsvTransformer
        $reflection = new \ReflectionClass($this->transformer);
        
        // Load rates
        $loadRatesMethod = $reflection->getMethod('loadCurrentRates');
        $loadRatesMethod->setAccessible(true);
        $loadRatesMethod->invoke($this->transformer);

        // Initialize aggregates
        $initAggregatesMethod = $reflection->getMethod('initializeAggregates');
        $initAggregatesMethod->setAccessible(true);
        $initAggregatesMethod->invoke($this->transformer);

        // Initialize activity periods property
        $activityPeriodsProperty = $reflection->getProperty('activityPeriods');
        $activityPeriodsProperty->setAccessible(true);
        $activityPeriodsProperty->setValue($this->transformer, []);

        // Initialize processed rows
        $processedRowsProperty = $reflection->getProperty('processedRows');
        $processedRowsProperty->setAccessible(true);
        $processedRowsProperty->setValue($this->transformer, 0);

        // Get the category aggregates property
        $categoryAggregatesProperty = $reflection->getProperty('categoryAggregates');
        $categoryAggregatesProperty->setAccessible(true);
        
        $activityPeriodsProperty = $reflection->getProperty('activityPeriods');
        $activityPeriodsProperty->setAccessible(true);

        // Process file using streaming method
        $processMethod = $reflection->getMethod('processFileStreaming');
        $processMethod->setAccessible(true);
        $processMethod->invoke($this->transformer, $inputPath, microtime(true), 600);

        // Apply UK marketplace allocation
        $ukAllocationMethod = $reflection->getMethod('applyUkMarketplaceAllocation');
        $ukAllocationMethod->setAccessible(true);
        $ukAllocationMethod->invoke($this->transformer);

        // Get the aggregated data
        $this->categoryAggregates = $categoryAggregatesProperty->getValue($this->transformer);
        $this->activityPeriods = $activityPeriodsProperty->getValue($this->transformer);

        // Log::info('CSV processing completed for Spanish tax forms', [
        //     'intracomunitarias_records' => count($this->getIntracomunitariasData()),
        //     'oss_records' => count($this->getOssData()),
        //     'ioss_records' => count($this->getIossData()),
        //     'activity_periods' => $this->activityPeriods
        // ]);
        echo "CSV processing completed - Intracomunitarias: " . count($this->getIntracomunitariasData()) . 
             ", OSS: " . count($this->getOssData()) . 
             ", IOSS: " . count($this->getIossData()) . "\n";

        return [
            'intracomunitarias' => $this->getIntracomunitariasData(),
            'oss' => $this->getOssData(),
            'ioss' => $this->getIossData(),
            'activity_periods' => $this->activityPeriods
        ];
    }

    /**
     * Get Intracomunitarias data for Form 349
     */
    public function getIntracomunitariasData(): array
    {
        return $this->categoryAggregates['Ventas Intracomunitarias de bienes - B2B (EUR)'] ?? [];
    }

    /**
     * Get OSS data for Form 369
     */
    public function getOssData(): array
    {
        return $this->categoryAggregates['Ventanilla Única - OSS esquema europeo (EUR)'] ?? [];
    }

    /**
     * Get IOSS data for Form 369
     */
    public function getIossData(): array
    {
        return $this->categoryAggregates['Ventanilla Única - IOSS esquema de importación (EUR)'] ?? [];
    }

    /**
     * Get activity periods detected in the data
     */
    public function getActivityPeriods(): array
    {
        return $this->activityPeriods;
    }

    /**
     * Extract period information for Spanish tax forms
     */
    public function extractPeriodInfo(): array
    {
        if (empty($this->activityPeriods)) {
            return [
                'year' => date('Y'),
                'period' => 'Q1',
                'is_quarterly' => true
            ];
        }

        // Use the first activity period for form generation
        $firstPeriod = (string)array_keys($this->activityPeriods)[0];
        
        // Parse period format (e.g., "2023-JAN", "2023Q1")
        if (preg_match('/(\d{4})Q(\d)/', $firstPeriod, $matches)) {
            return [
                'year' => $matches[1],
                'period' => 'T ' . $matches[2], // Spanish quarterly format
                'is_quarterly' => true
            ];
        } elseif (preg_match('/(\d{4})-(\w{3})/', $firstPeriod, $matches)) {
            $monthMap = [
                'JAN' => '01', 'FEB' => '02', 'MAR' => '03', 'APR' => '04',
                'MAY' => '05', 'JUN' => '06', 'JUL' => '07', 'AUG' => '08',
                'SEP' => '09', 'OCT' => '10', 'NOV' => '11', 'DEC' => '12'
            ];
            
            $month = $monthMap[$matches[2]] ?? '01';
            
            return [
                'year' => $matches[1],
                'period' => 'M ' . ltrim($month, '0'), // Spanish monthly format
                'is_quarterly' => false
            ];
        }

        // Default fallback
        return [
            'year' => date('Y'),
            'period' => 'T 1',
            'is_quarterly' => true
        ];
    }
}
