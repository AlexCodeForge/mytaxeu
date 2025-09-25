<?php

declare(strict_types=1);

namespace App\Services\SpanishTaxForms;

use App\Services\SpanishTaxForms\Config\SpanishTaxConfig;
use App\Services\SpanishTaxForms\Config\Form349Config;
use App\Services\SpanishTaxForms\Config\Form369Config;
use App\Services\SpanishTaxForms\Generators\Form349Generator;
use App\Services\SpanishTaxForms\Generators\Form349CsvGenerator;
use App\Services\SpanishTaxForms\Generators\Form369Generator;
use App\Services\RateService;
use App\Exceptions\SpanishTaxForms\FormGenerationException;
use App\Exceptions\SpanishTaxForms\ValidationException;
// use Illuminate\Support\Facades\Log;
// use Illuminate\Support\Facades\Storage;

/**
 * Main service for generating Spanish tax forms (Form 349 and Form 369)
 */
class SpanishTaxFormsService
{
    private SpanishTaxDataProcessor $dataProcessor;
    private Form349Generator $form349Generator;
    private Form349CsvGenerator $form349CsvGenerator;
    private Form369Generator $form369Generator;

    public function __construct(
        RateService $rateService,
        Form349Generator $form349Generator,
        Form369Generator $form369Generator
    ) {
        $this->dataProcessor = new SpanishTaxDataProcessor($rateService);
        $this->form349Generator = $form349Generator;
        $this->form349CsvGenerator = new Form349CsvGenerator();
        $this->form369Generator = $form369Generator;
    }

    /**
     * Generate Spanish tax forms from CSV input
     */
    public function generateForms(
        string $inputPath,
        SpanishTaxConfig $baseConfig,
        array $formTypes = ['form349', 'form369']
    ): array {
        // Log::info('Starting Spanish tax forms generation', [
        //     'input' => $inputPath,
        //     'form_types' => $formTypes,
        //     'declarant_nif' => $baseConfig->declarantNif
        // ]);
        echo "Starting Spanish tax forms generation for: " . implode(', ', $formTypes) . "\n";

        try {
            // Process the CSV file
            $processedData = $this->dataProcessor->processFile($inputPath);

            // Extract period information
            $periodInfo = $this->dataProcessor->extractPeriodInfo();

            $results = [];

            // Generate Form 349 if requested
            if (in_array('form349', $formTypes) && !empty($processedData['intracomunitarias'])) {
                $results['form349'] = $this->generateForm349(
                    $processedData['intracomunitarias'],
                    $baseConfig,
                    $periodInfo
                );
            }

            // Generate Form 369 if requested
            if (in_array('form369', $formTypes) &&
                (!empty($processedData['oss']) || !empty($processedData['ioss']))) {
                $results['form369'] = $this->generateForm369(
                    $processedData['oss'],
                    $processedData['ioss'],
                    $baseConfig,
                    $periodInfo
                );
            }

            // Log::info('Spanish tax forms generation completed', [
            //     'forms_generated' => array_keys($results),
            //     'period_info' => $periodInfo
            // ]);
            echo "Spanish tax forms generation completed: " . implode(', ', array_keys($results)) . "\n";

            return $results;

        } catch (\Exception $e) {
            // Log::error('Spanish tax forms generation failed', [
            //     'error' => $e->getMessage(),
            //     'trace' => $e->getTraceAsString()
            // ]);
            echo "Spanish tax forms generation failed: " . $e->getMessage() . "\n";
            throw new FormGenerationException('Failed to generate Spanish tax forms: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Generate Form 349 (Intracomunitarias)
     */
    public function generateForm349(
        array $intracomunitariasData,
        SpanishTaxConfig $baseConfig,
        array $periodInfo
    ): array {
        // Filter out invalid records instead of failing
        $filteredData = [];
        $skippedRecords = 0;

        foreach ($intracomunitariasData as $key => $transaction) {
            // Skip records with negative or zero amounts
            if (($transaction['Base (€)'] ?? 0) <= 0) {
                $skippedRecords++;
                echo "Skipping record with invalid amount: {$key} (amount: " . ($transaction['Base (€)'] ?? 0) . ")\n";
                continue;
            }

            // Skip records with missing data
            if (!str_contains($key, '|')) {
                $skippedRecords++;
                continue;
            }

            [$country, $buyerName, $buyerVat] = explode('|', $key);
            if (empty($buyerName) || empty($buyerVat)) {
                $skippedRecords++;
                continue;
            }

            $filteredData[$key] = $transaction;
        }

        echo "Filtered Form 349 data: " . count($filteredData) . " valid records, {$skippedRecords} skipped\n";

        if (empty($filteredData)) {
            throw new FormGenerationException('No valid data for Form 349 after filtering');
        }

        // Create Form 349 configuration
        $form349Config = new Form349Config(
            $baseConfig,
            $periodInfo['year'],
            $periodInfo['period']
        );

        // Generate BOTH formats

        // 1. Generate fixed-width format
        $contentFixedWidth = $this->form349Generator->generate($filteredData, $form349Config);
        if (empty($contentFixedWidth)) {
            throw new FormGenerationException('Form 349 fixed-width generation resulted in empty content');
        }

        // 2. Generate CSV format
        $contentCsv = $this->form349CsvGenerator->generate($filteredData, $form349Config);
        if (empty($contentCsv)) {
            throw new FormGenerationException('Form 349 CSV generation resulted in empty content');
        }

        // Save both files
        $filenameFixedWidth = $this->generateFilename('349', $periodInfo);
        $filenameCsv = $this->generateFilename('349_csv', $periodInfo);

        $filepathFixedWidth = $this->saveToTempFile($contentFixedWidth, $filenameFixedWidth);
        $filepathCsv = $this->saveToTempFile($contentCsv, $filenameCsv);

        return [
            'fixed_width' => [
                'type' => 'form349_fixed_width',
                'filename' => $filenameFixedWidth,
                'filepath' => $filepathFixedWidth,
                'content_length' => strlen($contentFixedWidth),
                'records_count' => count($filteredData),
                'description' => 'Official fixed-width format (500 chars per line)'
            ],
            'csv' => [
                'type' => 'form349_csv',
                'filename' => $filenameCsv,
                'filepath' => $filepathCsv,
                'content_length' => strlen($contentCsv),
                'records_count' => count($filteredData),
                'description' => 'Semicolon-delimited CSV format'
            ],
            'summary' => [
                'original_records_count' => count($intracomunitariasData),
                'valid_records_count' => count($filteredData),
                'skipped_records_count' => $skippedRecords,
                'period' => $periodInfo['year'] . '-' . $periodInfo['period']
            ]
        ];
    }

    /**
     * Generate Form 369 (OSS/IOSS)
     */
    public function generateForm369(
        array $ossData,
        array $iossData,
        SpanishTaxConfig $baseConfig,
        array $periodInfo
    ): array {
        // Determine regime based on available data
        $regime = 'MOSS'; // Default
        if (!empty($iossData)) {
            $regime = 'IMPO';
        } elseif (!empty($ossData)) {
            $regime = 'MOSS';
        }

        // Validate data
        $validationErrors = $this->form369Generator->validateData($ossData, $iossData,
            new Form369Config($baseConfig, $periodInfo['year'] . ' ' . $periodInfo['period'], $regime, $periodInfo['is_quarterly']));

        if (!empty($validationErrors)) {
            throw new ValidationException('Form 369 validation failed: ' . implode(', ', $validationErrors));
        }

        // Create Form 369 configuration
        $form369Config = new Form369Config(
            $baseConfig,
            $periodInfo['year'] . ' ' . $periodInfo['period'],
            $regime,
            $periodInfo['is_quarterly']
        );

        // Generate the form content
        $content = $this->form369Generator->generate($ossData, $iossData, $form369Config);

        if (empty($content)) {
            throw new FormGenerationException('Form 369 generation resulted in empty content');
        }

        // Save to temporary file
        $filename = $this->generateFilename('369', $periodInfo);
        $filepath = $this->saveToTempFile($content, $filename);

        return [
            'type' => 'form369',
            'filename' => $filename,
            'filepath' => $filepath,
            'content_length' => strlen($content),
            'oss_records_count' => count($ossData),
            'ioss_records_count' => count($iossData),
            'regime' => $regime,
            'period' => $periodInfo['year'] . '-' . $periodInfo['period']
        ];
    }

    /**
     * Generate filename for Spanish tax forms
     */
    private function generateFilename(string $formType, array $periodInfo): string
    {
        $periodStr = str_replace(' ', '', $periodInfo['period']);
        return "modelo_{$formType}_{$periodInfo['year']}_{$periodStr}.txt";
    }

    /**
     * Save content to temporary file
     */
    private function saveToTempFile(string $content, string $filename): string
    {
        $tempPath = '/var/www/mytaxeu/docs/jorditest/';

        // Ensure directory exists
        if (!is_dir($tempPath)) {
            mkdir($tempPath, 0755, true);
        }

        $filepath = $tempPath . $filename;

        // Save the file
        file_put_contents($filepath, $content);

        return $filepath;
    }

    /**
     * Get summary of processed data
     */
    public function getDataSummary(string $inputPath): array
    {
        $processedData = $this->dataProcessor->processFile($inputPath);
        $periodInfo = $this->dataProcessor->extractPeriodInfo();

        return [
            'activity_periods' => $this->dataProcessor->getActivityPeriods(),
            'period_info' => $periodInfo,
            'intracomunitarias_count' => count($processedData['intracomunitarias']),
            'oss_count' => count($processedData['oss']),
            'ioss_count' => count($processedData['ioss']),
            'forms_applicable' => [
                'form349' => !empty($processedData['intracomunitarias']),
                'form369' => !empty($processedData['oss']) || !empty($processedData['ioss'])
            ]
        ];
    }
}
