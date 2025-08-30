<?php

declare(strict_types=1);

namespace App\Services;

use DomainException;
use RuntimeException;

class CsvTransformer
{
    private const NUMERIC_COLUMNS = [
        'TOTAL_ACTIVITY_VALUE_VAT_INCL_AMT',
        'TOTAL_ACTIVITY_VALUE_VAT_EXCL_AMT',
        'TOTAL_ACTIVITY_VALUE_VAT_AMT',
        'PRICE_OF_ITEMS_VAT_INCL_AMT',
        'PRICE_OF_ITEMS_VAT_EXCL_AMT',
        'PRICE_OF_ITEMS_VAT_AMT',
        'PRICE_OF_ITEMS_VAT_RATE_PERCENT',
    ];

    // Tax categories in order of rule application (first match wins)
    private const TAX_CATEGORIES = [
        'B2C/B2B Local',
        'Local Sin IVA',
        'Intracomunitarias B2B',
        'OSS',
        'IOSS',
        'Marketplace VAT',
        'Amazon Compras',
        'Exportaciones',
    ];

    // EU countries (excluding GB which is treated separately)
    private const EU_COUNTRIES = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
        'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'
    ];

    // Export departure countries
    private const EXPORT_DEPARTURE_COUNTRIES = ['ES', 'DE', 'FR', 'IT', 'PL'];

    // Fixed exchange rates to EUR
    private const EXCHANGE_RATES = [
        'EUR' => 1.0,
        'PLN' => 0.23,
        'SEK' => 0.087,
    ];

    // Categories that require currency conversion
    private const CURRENCY_CONVERSION_CATEGORIES = [
        'OSS',
        'IOSS',
        'Marketplace VAT',
        'Amazon Compras',
        'Intracomunitarias B2B',
    ];

    /**
     * Transform CSV file from input path to output path.
     * 
     * Main entry point that orchestrates the transformation pipeline:
     * validation, parsing, classification, aggregation, and output generation.
     */
    public function transform(string $inputPath, string $outputPath): void
    {
        // Validate input file exists and output is writable
        if (!file_exists($inputPath)) {
            throw new RuntimeException('Input file not found: ' . $inputPath);
        }

        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir) || !is_writable($outputDir)) {
            throw new RuntimeException('Cannot write to output path: ' . $outputPath);
        }

        // Validate file format and requirements
        $this->validate($inputPath);

        // Parse and normalize the CSV data
        $rows = $this->parseRows($inputPath);
        
        // Classify transactions into tax categories
        $classifiedRows = $this->classifyTransactions($rows);
        
        // Convert currencies and compute totals
        $processedRows = $this->convertCurrenciesAndComputeTotals($classifiedRows);
        
        // Aggregate data by category
        $aggregatedData = $this->aggregatePerCategory($processedRows);
        
        // Write final output with proper sections and formatting
        $this->writeOutputCsv($aggregatedData, $outputPath);
    }

    /**
     * Parse CSV rows with automatic delimiter detection and encoding normalization.
     * 
     * Detects comma vs semicolon delimiter by analyzing first non-empty line.
     * Handles UTF-8/ISO-8859-1 encoding fallback.
     */
    private function parseRows(string $filePath): array
    {
        // Normalize encoding first
        $content = $this->normalizeEncoding($filePath);
        
        // Detect delimiter from first non-empty line
        $delimiter = $this->detectDelimiter($content);
        
        // Parse CSV content
        $lines = explode("\n", $content);
        $rows = [];
        $headers = null;
        
        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            $columns = str_getcsv($line, $delimiter);
            
            if ($headers === null) {
                // First line contains headers
                $headers = array_map('trim', $columns);
                continue;
            }
            
            // Create associative array for data row
            $row = [];
            foreach ($headers as $index => $header) {
                $value = isset($columns[$index]) ? trim($columns[$index]) : '';
                
                // Normalize numeric columns
                if (in_array($header, self::NUMERIC_COLUMNS)) {
                    $row[$header] = $this->normalizeNumericValue($value);
                } else {
                    $row[$header] = $value;
                }
            }
            
            $rows[] = $row;
        }
        
        return $rows;
    }

    /**
     * Normalize file encoding with UTF-8/ISO-8859-1 fallback handling.
     * 
     * Attempts UTF-8 read first; on failure, loads as ISO-8859-1 and converts to UTF-8.
     */
    private function normalizeEncoding(string $filePath): string
    {
        $content = file_get_contents($filePath);
        
        if ($content === false) {
            throw new RuntimeException('Unable to read file: ' . $filePath);
        }
        
        // Check if content is valid UTF-8
        if (mb_check_encoding($content, 'UTF-8')) {
            return $content;
        }
        
        // Fallback: assume ISO-8859-1 and convert to UTF-8
        $converted = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
        
        if ($converted === false) {
            throw new RuntimeException('Unable to normalize encoding for file: ' . $filePath);
        }
        
        return $converted;
    }

    /**
     * Validate file extension, required columns, and business constraints.
     * 
     * - Accept only .csv and .txt extensions
     * - Require ACTIVITY_PERIOD column
     * - Enforce max 3 distinct ACTIVITY_PERIOD values
     */
    private function validate(string $filePath): void
    {
        // Validate file extension
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'txt'])) {
            throw new DomainException('Invalid file extension. Only .csv and .txt files are supported.');
        }

        // Read and parse file to validate content
        $content = $this->normalizeEncoding($filePath);
        $delimiter = $this->detectDelimiter($content);
        
        $lines = explode("\n", $content);
        $headers = null;
        $activityPeriods = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            $columns = str_getcsv($line, $delimiter);
            
            if ($headers === null) {
                // First line contains headers
                $headers = array_map('trim', $columns);
                
                // Check for required ACTIVITY_PERIOD column
                if (!in_array('ACTIVITY_PERIOD', $headers)) {
                    throw new DomainException('Missing required ACTIVITY_PERIOD column');
                }
                
                continue;
            }
            
            // Track distinct ACTIVITY_PERIOD values
            $activityPeriodIndex = array_search('ACTIVITY_PERIOD', $headers);
            if ($activityPeriodIndex !== false && isset($columns[$activityPeriodIndex])) {
                $period = trim($columns[$activityPeriodIndex]);
                if (!empty($period) && !in_array($period, $activityPeriods)) {
                    $activityPeriods[] = $period;
                    
                    // Enforce max 3 distinct periods
                    if (count($activityPeriods) > 3) {
                        throw new DomainException('Maximum 3 distinct ACTIVITY_PERIOD values allowed, found more than 3');
                    }
                }
            }
        }
    }

    /**
     * Detect CSV delimiter by analyzing first non-empty line.
     * 
     * Uses comma if more commas than semicolons, otherwise uses semicolon.
     */
    private function detectDelimiter(string $content): string
    {
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            $commaCount = substr_count($line, ',');
            $semicolonCount = substr_count($line, ';');
            
            // Use comma if more commas than semicolons, otherwise use semicolon
            return $commaCount >= $semicolonCount ? ',' : ';';
        }
        
        // Default to comma if no delimiters found
        return ',';
    }

    /**
     * Normalize numeric value by coercing to float (empty values become 0).
     */
    private function normalizeNumericValue(string $value): float
    {
        if (empty($value)) {
            return 0.0;
        }
        
        // Replace European decimal separator if needed
        $normalized = str_replace(',', '.', $value);
        
        return (float) $normalized;
    }

    /**
     * Write the aggregated data to output CSV file with proper sections.
     * 
     * Organizes output into REGULAR and INTERNATIONAL sections with category headers.
     */
    private function writeOutputCsv(array $aggregatedData, string $outputPath): void
    {
        if (empty($aggregatedData)) {
            file_put_contents($outputPath, '');
            return;
        }
        
        $csvContent = [];
        
        // Define section organization
        $regularCategories = ['B2C/B2B Local', 'Local Sin IVA'];
        $internationalCategories = ['Intracomunitarias B2B', 'OSS', 'IOSS', 'Marketplace VAT', 'Amazon Compras', 'Exportaciones'];
        
        // REGULAR Section
        if ($this->hasAnyCategory($aggregatedData, $regularCategories)) {
            $csvContent[] = ''; // Blank line
            $csvContent[] = 'REGULAR SECTION';
            $csvContent[] = ''; // Blank line
            
            foreach ($regularCategories as $category) {
                if (isset($aggregatedData[$category])) {
                    $csvContent = array_merge($csvContent, $this->writeCategorySection($category, $aggregatedData[$category]));
                }
            }
        }
        
        // INTERNATIONAL Section
        if ($this->hasAnyCategory($aggregatedData, $internationalCategories)) {
            $csvContent[] = ''; // Blank line
            $csvContent[] = 'INTERNATIONAL SECTION';
            $csvContent[] = ''; // Blank line
            
            foreach ($internationalCategories as $category) {
                if (isset($aggregatedData[$category])) {
                    $csvContent = array_merge($csvContent, $this->writeCategorySection($category, $aggregatedData[$category]));
                }
            }
        }
        
        // Handle any remaining categories (like Unclassified)
        $handledCategories = array_merge($regularCategories, $internationalCategories);
        foreach ($aggregatedData as $category => $categoryData) {
            if (!in_array($category, $handledCategories)) {
                $csvContent[] = ''; // Blank line
                $csvContent = array_merge($csvContent, $this->writeCategorySection($category, $categoryData));
            }
        }
        
        // Remove leading empty lines
        while (!empty($csvContent) && $csvContent[0] === '') {
            array_shift($csvContent);
        }
        
        file_put_contents($outputPath, implode("\n", $csvContent));
    }

    /**
     * Check if aggregated data has any of the specified categories.
     */
    private function hasAnyCategory(array $aggregatedData, array $categories): bool
    {
        foreach ($categories as $category) {
            if (isset($aggregatedData[$category]) && !empty($aggregatedData[$category])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Write a single category section with header and data.
     */
    private function writeCategorySection(string $category, array $categoryData): array
    {
        if (empty($categoryData)) {
            return [];
        }
        
        $section = [];
        
        // Category header
        $section[] = $category;
        
        // Column headers from first row
        $headers = array_keys($categoryData[0]);
        $section[] = implode(',', $headers);
        
        // Data rows
        foreach ($categoryData as $row) {
            $values = [];
            foreach ($headers as $header) {
                $value = $row[$header] ?? '';
                
                // Format numeric values
                if (is_float($value)) {
                    $value = number_format($value, 2);
                }
                
                // Quote values that contain commas, quotes, or newlines
                if (is_string($value) && (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false)) {
                    $value = '"' . str_replace('"', '""', $value) . '"';
                }
                
                $values[] = $value;
            }
            
            $section[] = implode(',', $values);
        }
        
        // Add blank line after section
        $section[] = '';
        
        return $section;
    }

    /**
     * Convert currencies and compute totals for all rows.
     * 
     * Applies currency conversion to specific categories and computes row totals.
     */
    private function convertCurrenciesAndComputeTotals(array $rows): array
    {
        $processedRows = [];
        
        foreach ($rows as $row) {
            // Convert currencies if needed
            $row = $this->convertCurrencies($row);
            
            // Compute row totals
            $row = $this->computeRowTotals($row);
            
            $processedRows[] = $row;
        }
        
        return $processedRows;
    }

    /**
     * Convert currencies using fixed exchange rates for specific categories.
     * 
     * Only applies to OSS, IOSS, Marketplace, Compras, and Intracom B2B categories.
     */
    private function convertCurrencies(array $row): array
    {
        $category = $row['TAX_CATEGORY'] ?? '';
        $currency = $row['TRANSACTION_CURRENCY_CODE'] ?? 'EUR';
        
        // Only convert for specific categories
        if (!in_array($category, self::CURRENCY_CONVERSION_CATEGORIES)) {
            return $row;
        }
        
        // Only convert if we have an exchange rate and it's not already EUR
        if ($currency === 'EUR' || !isset(self::EXCHANGE_RATES[$currency])) {
            return $row;
        }
        
        $exchangeRate = self::EXCHANGE_RATES[$currency];
        
        // Convert all numeric amount columns
        foreach (self::NUMERIC_COLUMNS as $column) {
            if (isset($row[$column]) && is_numeric($row[$column])) {
                $row[$column] = (float) $row[$column] * $exchangeRate;
            }
        }
        
        // Update currency code to EUR
        $row['TRANSACTION_CURRENCY_CODE'] = 'EUR';
        
        return $row;
    }

    /**
     * Compute row totals: Base (€), IVA (€), Total (€), and Calculated Base (€).
     * 
     * Calculates totals from VAT-excluded, VAT, and VAT-included amounts.
     */
    private function computeRowTotals(array $row): array
    {
        // Base (€): Sum of VAT-excluded amounts
        $totalBase = (float) ($row['TOTAL_ACTIVITY_VALUE_VAT_EXCL_AMT'] ?? 0);
        $priceBase = (float) ($row['PRICE_OF_ITEMS_VAT_EXCL_AMT'] ?? 0);
        $row['Base (€)'] = $totalBase + $priceBase;
        
        // IVA (€): Sum of VAT amounts
        $totalVat = (float) ($row['TOTAL_ACTIVITY_VALUE_VAT_AMT'] ?? 0);
        $priceVat = (float) ($row['PRICE_OF_ITEMS_VAT_AMT'] ?? 0);
        $row['IVA (€)'] = $totalVat + $priceVat;
        
        // Total (€): Sum of VAT-included amounts
        $totalInclusive = (float) ($row['TOTAL_ACTIVITY_VALUE_VAT_INCL_AMT'] ?? 0);
        $priceInclusive = (float) ($row['PRICE_OF_ITEMS_VAT_INCL_AMT'] ?? 0);
        $row['Total (€)'] = $totalInclusive + $priceInclusive;
        
        // Calculated Base (€): Special calculation for B2C/B2B Local category
        $category = $row['TAX_CATEGORY'] ?? '';
        if ($category === 'B2C/B2B Local') {
            $vatAmount = $row['IVA (€)'];
            $vatRate = (float) ($row['PRICE_OF_ITEMS_VAT_RATE_PERCENT'] ?? 0);
            
            if ($vatAmount > 0 && $vatRate > 0) {
                // If VAT > 0: Calculated Base = VAT / (rate / 100)
                $row['Calculated Base (€)'] = $vatAmount / ($vatRate / 100);
            } else {
                // If VAT = 0: Calculated Base = Base
                $row['Calculated Base (€)'] = $row['Base (€)'];
            }
        }
        
        return $row;
    }

    /**
     * Aggregate processed rows by category with category-specific grouping rules.
     * 
     * Each category has different aggregation logic and output columns.
     */
    private function aggregatePerCategory(array $rows): array
    {
        // Group rows by category
        $categorizedRows = [];
        foreach ($rows as $row) {
            $category = $row['TAX_CATEGORY'] ?? 'Unclassified';
            $categorizedRows[$category][] = $row;
        }
        
        $aggregatedSections = [];
        
        // Process each category with its specific aggregation logic
        foreach ($categorizedRows as $category => $categoryRows) {
            $aggregatedRows = $this->aggregateCategory($category, $categoryRows);
            if (!empty($aggregatedRows)) {
                $aggregatedSections[$category] = $aggregatedRows;
            }
        }
        
        return $aggregatedSections;
    }

    /**
     * Aggregate a specific category using category-specific rules.
     */
    private function aggregateCategory(string $category, array $rows): array
    {
        switch ($category) {
            case 'B2C/B2B Local':
                return $this->aggregateB2cB2bLocal($rows);
            case 'Local Sin IVA':
                return $this->aggregateLocalSinIva($rows);
            case 'Intracomunitarias B2B':
                return $this->aggregateIntracomunitariasB2b($rows);
            case 'OSS':
                return $this->aggregateOss($rows);
            case 'IOSS':
                return $this->aggregateIoss($rows);
            case 'Marketplace VAT':
                return $this->aggregateMarketplaceVat($rows);
            case 'Amazon Compras':
                return $this->aggregateAmazonCompras($rows);
            case 'Exportaciones':
                return $this->aggregateExportaciones($rows);
            default:
                return $this->aggregateDefault($rows);
        }
    }

    /**
     * Aggregate B2C/B2B Local transactions by TAXABLE_JURISDICTION.
     */
    private function aggregateB2cB2bLocal(array $rows): array
    {
        $grouped = [];
        
        foreach ($rows as $row) {
            $jurisdiction = $row['TAXABLE_JURISDICTION'] ?? 'Unknown';
            $key = $jurisdiction;
            
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'TAXABLE_JURISDICTION' => $jurisdiction,
                    'Base (€)' => 0,
                    'IVA (€)' => 0,
                    'Total (€)' => 0,
                    'Calculated Base (€)' => 0,
                    'Count' => 0,
                ];
            }
            
            $grouped[$key]['Base (€)'] += (float) ($row['Base (€)'] ?? 0);
            $grouped[$key]['IVA (€)'] += (float) ($row['IVA (€)'] ?? 0);
            $grouped[$key]['Total (€)'] += (float) ($row['Total (€)'] ?? 0);
            $grouped[$key]['Calculated Base (€)'] += (float) ($row['Calculated Base (€)'] ?? 0);
            $grouped[$key]['Count']++;
        }
        
        $result = array_values($grouped);
        $result[] = $this->generateTotalsRow($result, 'B2C/B2B Local Total');
        
        return $result;
    }

    /**
     * Aggregate Local Sin IVA transactions with buyer data and detail fields.
     */
    private function aggregateLocalSinIva(array $rows): array
    {
        $result = [];
        
        foreach ($rows as $row) {
            $result[] = [
                'BUYER_NAME' => $row['BUYER_NAME'] ?? '',
                'TRANSACTION_EVENT_CODE' => $row['TRANSACTION_EVENT_CODE'] ?? '',
                'SALE_DEPART_COUNTRY' => $row['SALE_DEPART_COUNTRY'] ?? '',
                'Base (€)' => (float) ($row['Base (€)'] ?? 0),
                'IVA (€)' => (float) ($row['IVA (€)'] ?? 0),
                'Total (€)' => (float) ($row['Total (€)'] ?? 0),
            ];
        }
        
        $result[] = $this->generateTotalsRow($result, 'Local Sin IVA Total');
        
        return $result;
    }

    /**
     * Aggregate Intracomunitarias B2B transactions with buyer name and VAT number.
     */
    private function aggregateIntracomunitariasB2b(array $rows): array
    {
        $grouped = [];
        
        foreach ($rows as $row) {
            $buyerName = $row['BUYER_NAME'] ?? '';
            $buyerVat = $row['BUYER_VAT_NUMBER'] ?? '';
            $country = $row['SALE_ARRIVAL_COUNTRY'] ?? '';
            $key = $buyerName . '|' . $buyerVat . '|' . $country;
            
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'BUYER_NAME' => $buyerName,
                    'BUYER_VAT_NUMBER' => $buyerVat,
                    'SALE_ARRIVAL_COUNTRY' => $country,
                    'Base (€)' => 0,
                    'IVA (€)' => 0,
                    'Total (€)' => 0,
                    'Count' => 0,
                ];
            }
            
            $grouped[$key]['Base (€)'] += (float) ($row['Base (€)'] ?? 0);
            $grouped[$key]['IVA (€)'] += (float) ($row['IVA (€)'] ?? 0);
            $grouped[$key]['Total (€)'] += (float) ($row['Total (€)'] ?? 0);
            $grouped[$key]['Count']++;
        }
        
        $result = array_values($grouped);
        $result[] = $this->generateTotalsRow($result, 'Intracomunitarias B2B Total');
        
        return $result;
    }

    /**
     * Aggregate OSS transactions with destination country breakdown.
     */
    private function aggregateOss(array $rows): array
    {
        $grouped = [];
        
        foreach ($rows as $row) {
            $country = $row['SALE_ARRIVAL_COUNTRY'] ?? '';
            $key = $country;
            
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'SALE_ARRIVAL_COUNTRY' => $country,
                    'Base (€)' => 0,
                    'IVA (€)' => 0,
                    'Total (€)' => 0,
                    'Count' => 0,
                ];
            }
            
            $grouped[$key]['Base (€)'] += (float) ($row['Base (€)'] ?? 0);
            $grouped[$key]['IVA (€)'] += (float) ($row['IVA (€)'] ?? 0);
            $grouped[$key]['Total (€)'] += (float) ($row['Total (€)'] ?? 0);
            $grouped[$key]['Count']++;
        }
        
        $result = array_values($grouped);
        $result[] = $this->generateTotalsRow($result, 'OSS Total');
        
        return $result;
    }

    /**
     * Aggregate IOSS transactions.
     */
    private function aggregateIoss(array $rows): array
    {
        $grouped = [];
        
        foreach ($rows as $row) {
            $country = $row['SALE_ARRIVAL_COUNTRY'] ?? '';
            $key = $country;
            
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'SALE_ARRIVAL_COUNTRY' => $country,
                    'Base (€)' => 0,
                    'IVA (€)' => 0,
                    'Total (€)' => 0,
                    'Count' => 0,
                ];
            }
            
            $grouped[$key]['Base (€)'] += (float) ($row['Base (€)'] ?? 0);
            $grouped[$key]['IVA (€)'] += (float) ($row['IVA (€)'] ?? 0);
            $grouped[$key]['Total (€)'] += (float) ($row['Total (€)'] ?? 0);
            $grouped[$key]['Count']++;
        }
        
        $result = array_values($grouped);
        $result[] = $this->generateTotalsRow($result, 'IOSS Total');
        
        return $result;
    }

    /**
     * Aggregate Marketplace VAT transactions.
     */
    private function aggregateMarketplaceVat(array $rows): array
    {
        $grouped = [];
        
        foreach ($rows as $row) {
            $country = $row['SALE_ARRIVAL_COUNTRY'] ?? '';
            $key = $country;
            
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'SALE_ARRIVAL_COUNTRY' => $country,
                    'Base (€)' => 0,
                    'IVA (€)' => 0,
                    'Total (€)' => 0,
                    'Count' => 0,
                ];
            }
            
            $grouped[$key]['Base (€)'] += (float) ($row['Base (€)'] ?? 0);
            $grouped[$key]['IVA (€)'] += (float) ($row['IVA (€)'] ?? 0);
            $grouped[$key]['Total (€)'] += (float) ($row['Total (€)'] ?? 0);
            $grouped[$key]['Count']++;
        }
        
        $result = array_values($grouped);
        $result[] = $this->generateTotalsRow($result, 'Marketplace VAT Total');
        
        return $result;
    }

    /**
     * Aggregate Amazon Compras transactions.
     */
    private function aggregateAmazonCompras(array $rows): array
    {
        $result = [];
        
        foreach ($rows as $row) {
            $result[] = [
                'SUPPLIER_NAME' => $row['SUPPLIER_NAME'] ?? '',
                'Base (€)' => (float) ($row['Base (€)'] ?? 0),
                'IVA (€)' => (float) ($row['IVA (€)'] ?? 0),
                'Total (€)' => (float) ($row['Total (€)'] ?? 0),
            ];
        }
        
        $result[] = $this->generateTotalsRow($result, 'Amazon Compras Total');
        
        return $result;
    }

    /**
     * Aggregate Exportaciones transactions.
     */
    private function aggregateExportaciones(array $rows): array
    {
        $grouped = [];
        
        foreach ($rows as $row) {
            $departCountry = $row['SALE_DEPART_COUNTRY'] ?? '';
            $arrivalCountry = $row['SALE_ARRIVAL_COUNTRY'] ?? '';
            $key = $departCountry . '|' . $arrivalCountry;
            
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'SALE_DEPART_COUNTRY' => $departCountry,
                    'SALE_ARRIVAL_COUNTRY' => $arrivalCountry,
                    'Base (€)' => 0,
                    'IVA (€)' => 0,
                    'Total (€)' => 0,
                    'Count' => 0,
                ];
            }
            
            $grouped[$key]['Base (€)'] += (float) ($row['Base (€)'] ?? 0);
            $grouped[$key]['IVA (€)'] += (float) ($row['IVA (€)'] ?? 0);
            $grouped[$key]['Total (€)'] += (float) ($row['Total (€)'] ?? 0);
            $grouped[$key]['Count']++;
        }
        
        $result = array_values($grouped);
        $result[] = $this->generateTotalsRow($result, 'Exportaciones Total');
        
        return $result;
    }

    /**
     * Default aggregation for unclassified transactions.
     */
    private function aggregateDefault(array $rows): array
    {
        $result = [];
        
        foreach ($rows as $row) {
            $result[] = [
                'Original Data' => json_encode($row),
                'Base (€)' => (float) ($row['Base (€)'] ?? 0),
                'IVA (€)' => (float) ($row['IVA (€)'] ?? 0),
                'Total (€)' => (float) ($row['Total (€)'] ?? 0),
            ];
        }
        
        if (!empty($result)) {
            $result[] = $this->generateTotalsRow($result, 'Unclassified Total');
        }
        
        return $result;
    }

    /**
     * Generate totals row for a category.
     */
    private function generateTotalsRow(array $rows, string $label): array
    {
        $totals = [
            'Label' => $label,
            'Base (€)' => 0,
            'IVA (€)' => 0,
            'Total (€)' => 0,
        ];
        
        foreach ($rows as $row) {
            if (isset($row['Label'])) continue; // Skip existing totals rows
            
            $totals['Base (€)'] += (float) ($row['Base (€)'] ?? 0);
            $totals['IVA (€)'] += (float) ($row['IVA (€)'] ?? 0);
            $totals['Total (€)'] += (float) ($row['Total (€)'] ?? 0);
        }
        
        return $totals;
    }

    /**
     * Classify transactions into tax categories using business rules.
     * 
     * Applies classification rules in order - first match wins.
     */
    private function classifyTransactions(array $rows): array
    {
        $classifiedRows = [];
        
        foreach ($rows as $row) {
            $category = $this->classify($row);
            $row['TAX_CATEGORY'] = $category;
            $classifiedRows[] = $row;
        }
        
        return $classifiedRows;
    }

    /**
     * Classify a single transaction row using 8 tax category rules.
     * 
     * Rules are applied in order; first match wins.
     */
    private function classify(array $row): string
    {
        // Rule 1: B2C/B2B Local
        if ($this->isB2cB2bLocal($row)) {
            return 'B2C/B2B Local';
        }

        // Rule 2: Local Sin IVA
        if ($this->isLocalSinIva($row)) {
            return 'Local Sin IVA';
        }

        // Rule 3: Intracomunitarias B2B
        if ($this->isIntracomunitariasB2b($row)) {
            return 'Intracomunitarias B2B';
        }

        // Rule 4: OSS
        if ($this->isOss($row)) {
            return 'OSS';
        }

        // Rule 5: IOSS
        if ($this->isIoss($row)) {
            return 'IOSS';
        }

        // Rule 6: Marketplace VAT
        if ($this->isMarketplaceVat($row)) {
            return 'Marketplace VAT';
        }

        // Rule 7: Amazon Compras
        if ($this->isAmazonCompras($row)) {
            return 'Amazon Compras';
        }

        // Rule 8: Exportaciones
        if ($this->isExportaciones($row)) {
            return 'Exportaciones';
        }

        // Default category if no rules match
        return 'Unclassified';
    }

    /**
     * Rule 1: B2C/B2B Local
     * (TAX_REPORTING_SCHEME in {REGULAR, UK_VOEC-DOMESTIC}) && TAX_COLLECTION_RESPONSIBILITY == SELLER
     */
    private function isB2cB2bLocal(array $row): bool
    {
        $scheme = $row['TAX_REPORTING_SCHEME'] ?? '';
        $responsibility = $row['TAX_COLLECTION_RESPONSIBILITY'] ?? '';

        return in_array($scheme, ['REGULAR', 'UK_VOEC-DOMESTIC']) && $responsibility === 'SELLER';
    }

    /**
     * Rule 2: Local Sin IVA
     * SALE_DEPART_COUNTRY == SALE_ARRIVAL_COUNTRY && TOTAL_ACTIVITY_VALUE_VAT_AMT == 0 && 
     * empty(BUYER_VAT_NUMBER) && TAX_COLLECTION_RESPONSIBILITY == SELLER
     */
    private function isLocalSinIva(array $row): bool
    {
        $departCountry = $row['SALE_DEPART_COUNTRY'] ?? '';
        $arrivalCountry = $row['SALE_ARRIVAL_COUNTRY'] ?? '';
        $vatAmount = (float) ($row['TOTAL_ACTIVITY_VALUE_VAT_AMT'] ?? 0);
        $buyerVatNumber = $row['BUYER_VAT_NUMBER'] ?? '';
        $responsibility = $row['TAX_COLLECTION_RESPONSIBILITY'] ?? '';

        return $departCountry === $arrivalCountry &&
               $vatAmount == 0 &&
               empty($buyerVatNumber) &&
               $responsibility === 'SELLER';
    }

    /**
     * Rule 3: Intracomunitarias B2B
     * depart != arrival && both EU (not GB) && BUYER_VAT_NUMBER_COUNTRY present && 
     * RESP == SELLER && SCHEME == REGULAR
     */
    private function isIntracomunitariasB2b(array $row): bool
    {
        $departCountry = $row['SALE_DEPART_COUNTRY'] ?? '';
        $arrivalCountry = $row['SALE_ARRIVAL_COUNTRY'] ?? '';
        $buyerVatCountry = $row['BUYER_VAT_NUMBER_COUNTRY'] ?? '';
        $responsibility = $row['TAX_COLLECTION_RESPONSIBILITY'] ?? '';
        $scheme = $row['TAX_REPORTING_SCHEME'] ?? '';

        return $departCountry !== $arrivalCountry &&
               in_array($departCountry, self::EU_COUNTRIES) &&
               in_array($arrivalCountry, self::EU_COUNTRIES) &&
               !empty($buyerVatCountry) &&
               $responsibility === 'SELLER' &&
               $scheme === 'REGULAR';
    }

    /**
     * Rule 4: OSS
     * SCHEME == UNION-OSS
     */
    private function isOss(array $row): bool
    {
        $scheme = $row['TAX_REPORTING_SCHEME'] ?? '';
        return $scheme === 'UNION-OSS';
    }

    /**
     * Rule 5: IOSS
     * SCHEME == DEEMED_RESELLER-IOSS && depart, arrival in EU and distinct
     */
    private function isIoss(array $row): bool
    {
        $scheme = $row['TAX_REPORTING_SCHEME'] ?? '';
        $departCountry = $row['SALE_DEPART_COUNTRY'] ?? '';
        $arrivalCountry = $row['SALE_ARRIVAL_COUNTRY'] ?? '';

        return $scheme === 'DEEMED_RESELLER-IOSS' &&
               in_array($departCountry, self::EU_COUNTRIES) &&
               in_array($arrivalCountry, self::EU_COUNTRIES) &&
               $departCountry !== $arrivalCountry;
    }

    /**
     * Rule 6: Marketplace VAT
     * RESP == MARKETPLACE
     */
    private function isMarketplaceVat(array $row): bool
    {
        $responsibility = $row['TAX_COLLECTION_RESPONSIBILITY'] ?? '';
        return $responsibility === 'MARKETPLACE';
    }

    /**
     * Rule 7: Amazon Compras
     * SUPPLIER_NAME == 'Amazon Services Europe Sarl'
     */
    private function isAmazonCompras(array $row): bool
    {
        $supplierName = $row['SUPPLIER_NAME'] ?? '';
        return $supplierName === 'Amazon Services Europe Sarl';
    }

    /**
     * Rule 8: Exportaciones
     * depart in {ES,DE,FR,IT,PL} && arrival not in that set
     */
    private function isExportaciones(array $row): bool
    {
        $departCountry = $row['SALE_DEPART_COUNTRY'] ?? '';
        $arrivalCountry = $row['SALE_ARRIVAL_COUNTRY'] ?? '';

        return in_array($departCountry, self::EXPORT_DEPARTURE_COUNTRIES) &&
               !in_array($arrivalCountry, self::EXPORT_DEPARTURE_COUNTRIES);
    }
}
