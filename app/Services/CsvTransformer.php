<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Color;

/**
 * CSV Transformer Service
 *
 * Transforms Amazon transaction CSV files according to Spanish tax requirements.
 * Recreates the exact logic from transformadorImpl.py in PHP.
 */
class CsvTransformer
{
    // Column names for VAT-excluded amounts (Base calculations)
    private const VAT_EXCL_COLUMNS = [
        'PRICE_OF_ITEMS_AMT_VAT_EXCL',
        'PROMO_PRICE_OF_ITEMS_AMT_VAT_EXCL',
        'SHIP_CHARGE_AMT_VAT_EXCL',
        'PROMO_SHIP_CHARGE_AMT_VAT_EXCL',
        'GIFT_WRAP_AMT_VAT_EXCL',
        'PROMO_GIFT_WRAP_AMT_VAT_EXCL',
    ];

    // Column names for VAT amounts (IVA calculations)
    private const VAT_COLUMNS = [
        'PRICE_OF_ITEMS_VAT_AMT',
        'PROMO_PRICE_OF_ITEMS_VAT_AMT',
        'SHIP_CHARGE_VAT_AMT',
        'PROMO_SHIP_CHARGE_VAT_AMT',
        'GIFT_WRAP_VAT_AMT',
        'PROMO_GIFT_WRAP_VAT_AMT',
    ];

    // Column names for VAT-included amounts (Total calculations)
    private const VAT_INCL_COLUMNS = [
        'PRICE_OF_ITEMS_AMT_VAT_INCL',
        'PROMO_PRICE_OF_ITEMS_AMT_VAT_INCL',
        'SHIP_CHARGE_AMT_VAT_INCL',
        'PROMO_SHIP_CHARGE_AMT_VAT_INCL',
        'GIFT_WRAP_AMT_VAT_INCL',
        'PROMO_GIFT_WRAP_AMT_VAT_INCL',
    ];

    // All numeric columns for currency conversion
    private const NUMERIC_COLUMNS = [
        'COST_PRICE_OF_ITEMS', 'PRICE_OF_ITEMS_AMT_VAT_EXCL', 'PROMO_PRICE_OF_ITEMS_AMT_VAT_EXCL',
        'TOTAL_PRICE_OF_ITEMS_AMT_VAT_EXCL', 'SHIP_CHARGE_AMT_VAT_EXCL', 'PROMO_SHIP_CHARGE_AMT_VAT_EXCL',
        'TOTAL_SHIP_CHARGE_AMT_VAT_EXCL', 'GIFT_WRAP_AMT_VAT_EXCL', 'PROMO_GIFT_WRAP_AMT_VAT_EXCL',
        'TOTAL_GIFT_WRAP_AMT_VAT_EXCL', 'TOTAL_ACTIVITY_VALUE_AMT_VAT_EXCL',
        'PRICE_OF_ITEMS_VAT_RATE_PERCENT', 'PRICE_OF_ITEMS_VAT_AMT', 'PROMO_PRICE_OF_ITEMS_VAT_AMT',
        'TOTAL_PRICE_OF_ITEMS_VAT_AMT', 'SHIP_CHARGE_VAT_RATE_PERCENT', 'SHIP_CHARGE_VAT_AMT',
        'PROMO_SHIP_CHARGE_VAT_AMT', 'TOTAL_SHIP_CHARGE_VAT_AMT', 'GIFT_WRAP_VAT_RATE_PERCENT',
        'GIFT_WRAP_VAT_AMT', 'PROMO_GIFT_WRAP_VAT_AMT', 'TOTAL_GIFT_WRAP_VAT_AMT', 'TOTAL_ACTIVITY_VALUE_VAT_AMT',
        'PRICE_OF_ITEMS_AMT_VAT_INCL', 'PROMO_PRICE_OF_ITEMS_AMT_VAT_INCL', 'TOTAL_PRICE_OF_ITEMS_AMT_VAT_INCL',
        'SHIP_CHARGE_AMT_VAT_INCL', 'PROMO_SHIP_CHARGE_AMT_VAT_INCL', 'TOTAL_SHIP_CHARGE_AMT_VAT_INCL',
        'GIFT_WRAP_AMT_VAT_INCL', 'PROMO_GIFT_WRAP_AMT_VAT_INCL', 'TOTAL_GIFT_WRAP_AMT_VAT_INCL',
        'TOTAL_ACTIVITY_VALUE_AMT_VAT_INCL',
    ];

    // Tax categories exactly as in Python script
    private const TAX_CATEGORIES = [
        'Ventas locales al consumidor final - B2C y B2B (EUR)',
        'Ventas locales SIN IVA (EUR)',
        'Ventas Intracomunitarias de bienes - B2B (EUR)',
        'Ventanilla Única - OSS esquema europeo (EUR)',
        'Ventanilla Única - IOSS esquema de importación (EUR)',
        'IVA recaudado y remitido por Amazon Marketplace (EUR)',
        'Compras a Amazon (EUR)',
        'Exportaciones (EUR)',
    ];

    // EU countries list from Python script
    private const EU_COUNTRIES = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR',
        'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL',
        'PT', 'RO', 'SE', 'SI', 'SK'
    ];

    // Exchange rates to EUR from Python script
    private const EXCHANGE_RATES = [
        'PLN' => 0.23,
        'EUR' => 1.0,
        'SEK' => 0.087,
    ];

    // OSS VAT rates for each EU country from Python script
    private const OSS_VAT_RATES = [
        'AT' => 0.20, 'BE' => 0.21, 'BG' => 0.20, 'CY' => 0.19, 'CZ' => 0.21,
        'DE' => 0.19, 'DK' => 0.25, 'EE' => 0.20, 'ES' => 0.21, 'FI' => 0.24,
        'FR' => 0.20, 'GR' => 0.24, 'HR' => 0.25, 'HU' => 0.27, 'IE' => 0.23,
        'IT' => 0.22, 'LT' => 0.21, 'LU' => 0.17, 'LV' => 0.21, 'NL' => 0.21,
        'PL' => 0.23, 'PT' => 0.23, 'RO' => 0.19, 'SE' => 0.25, 'SI' => 0.22,
    ];

    /**
     * Transform CSV file according to Python transformadorImpl.py logic
     */
    public function transform(string $inputPath, string $outputPath): void
    {
        $this->log("Starting CSV transformation", ['input' => $inputPath, 'output' => $outputPath]);

        // Read and parse CSV
        $data = $this->readCsvFile($inputPath);

        // Validate ACTIVITY_PERIOD
        $this->validateActivityPeriods($data);

        // Initialize classifications
        $classifications = [];
        foreach (self::TAX_CATEGORIES as $category) {
            $classifications[$category] = [];
        }

        // Classify each transaction (exclude RETURN transactions)
        foreach ($data as $row) {
            // Skip RETURN transactions as they don't follow normal classification rules
            if (($row['TRANSACTION_TYPE'] ?? '') === 'RETURN') {
                continue;
            }

            // Python allows transactions to be in multiple categories!
            $categories = $this->classifyTransactionMultiple($row);
            foreach ($categories as $category) {
                $classifications[$category][] = $row;
            }
        }

        // Process each classification
        foreach ($classifications as $category => $transactions) {
            if (!empty($transactions)) {
                $classifications[$category] = $this->processCategory($category, $transactions);
            }
        }

        // Generate Excel output (saved as .csv like Python script)
        $this->generateExcelOutput($classifications, $outputPath);

        $this->log("CSV transformation completed", ['output' => $outputPath]);
    }

    /**
     * Log helper that works both in Laravel and standalone contexts
     */
    private function log(string $message, array $context = []): void
    {
        try {
            if (class_exists('Illuminate\Support\Facades\Log')) {
                Log::info($message, $context);
            }
        } catch (Exception $e) {
            // Fallback: just echo in standalone mode
            echo "$message\n";
        }
    }

    /**
     * Read CSV file and return array of rows
     */
    public function readCsvFile(string $inputPath): array
    {
        if (!file_exists($inputPath)) {
            throw new Exception("Input file does not exist: $inputPath");
        }

        $data = [];
        $headers = null;

        if (($handle = fopen($inputPath, 'r')) !== false) {
            while (($row = fgetcsv($handle)) !== false) {
                if ($headers === null) {
                    $headers = $row;
                continue;
            }

                $data[] = array_combine($headers, $row);
            }
            fclose($handle);
        }

        if (empty($data)) {
            throw new Exception("No data found in CSV file");
        }

        return $data;
    }

    /**
     * Validate activity periods (max 3 different periods)
     */
    private function validateActivityPeriods(array $data): void
    {
        $periods = array_unique(array_column($data, 'ACTIVITY_PERIOD'));

        if (count($periods) > 3) {
            throw new Exception("Las transacciones contienen más de tres periodos distintos: " . implode(', ', $periods));
        }
    }

    /**
     * Classify transaction based on Python script rules - ALLOWS MULTIPLE CATEGORIES
     */
    public function classifyTransactionMultiple(array $row): array
    {
        $departCountry = $row['SALE_DEPART_COUNTRY'] ?? '';
        $arrivalCountry = $row['SALE_ARRIVAL_COUNTRY'] ?? '';
        $buyerVat = $row['BUYER_VAT_NUMBER_COUNTRY'] ?? null;  // Python uses BUYER_VAT_NUMBER_COUNTRY
        $vatAmount = (float)($row['TOTAL_ACTIVITY_VALUE_VAT_AMT'] ?? 0);
        $reportingScheme = $row['TAX_REPORTING_SCHEME'] ?? null;
        $supplierName = $row['SUPPLIER_NAME'] ?? '';
        $taxResponsibility = $row['TAX_COLLECTION_RESPONSIBILITY'] ?? null;

        // Handle empty strings as null (like Python's pd.isna() / pd.notna())
        if ($buyerVat === '') $buyerVat = null;
        if ($reportingScheme === '') $reportingScheme = null;
        if ($taxResponsibility === '') $taxResponsibility = null;

                $categories = [];

        // 1. Classification B2C y B2B (Python lines 255-259) - uses "if" (ALWAYS CHECK INDEPENDENTLY)
        if (in_array($reportingScheme, ['REGULAR', 'UK_VOEC-DOMESTIC']) && $taxResponsibility === 'SELLER') {
            $categories[] = 'Ventas locales al consumidor final - B2C y B2B (EUR)';
        }

        // 2. Classification for "Ventas locales SIN IVA" + ELIF CHAIN (Python lines 262+)
        // Key insight: the elif chain is connected to SIN IVA, NOT to B2C!
        if ($departCountry === $arrivalCountry &&
            $vatAmount == 0 &&
            $buyerVat === null &&
            $taxResponsibility === 'SELLER') {
            $categories[] = 'Ventas locales SIN IVA (EUR)';
        }
        // 3. The ELIF chain - only runs if SIN IVA condition was FALSE
        elseif ($departCountry !== $arrivalCountry &&
            in_array($departCountry, self::EU_COUNTRIES) &&
            in_array($arrivalCountry, self::EU_COUNTRIES) &&
            $departCountry !== 'GB' &&
            $arrivalCountry !== 'GB' &&
            !empty($buyerVat) &&
            $taxResponsibility === 'SELLER' &&
            $reportingScheme === 'REGULAR') {
            $categories[] = 'Ventas Intracomunitarias de bienes - B2B (EUR)';
        }
        elseif ($reportingScheme === 'UNION-OSS') {
            $categories[] = 'Ventanilla Única - OSS esquema europeo (EUR)';
        }
        elseif ($reportingScheme === 'DEEMED_RESELLER-IOSS' &&
            in_array($departCountry, self::EU_COUNTRIES) &&
            in_array($arrivalCountry, self::EU_COUNTRIES) &&
            $departCountry !== $arrivalCountry) {
            $categories[] = 'Ventanilla Única - IOSS esquema de importación (EUR)';
        }
        elseif ($taxResponsibility === 'MARKETPLACE') {
            $categories[] = 'IVA recaudado y remitido por Amazon Marketplace (EUR)';
        }
        elseif ($supplierName === 'Amazon Services Europe Sarl') {
            $categories[] = 'Compras a Amazon (EUR)';
        }
        elseif (in_array($departCountry, ['ES', 'DE', 'FR', 'IT', 'PL']) &&
            !in_array($arrivalCountry, ['ES', 'DE', 'FR', 'IT', 'PL'])) {
            $categories[] = 'Exportaciones (EUR)';
        }

        return $categories;
    }

    /**
     * Classify transaction based on Python script rules - SINGLE CATEGORY (legacy method)
     */
    public function classifyTransaction(array $row): ?string
    {
        $departCountry = $row['SALE_DEPART_COUNTRY'] ?? '';
        $arrivalCountry = $row['SALE_ARRIVAL_COUNTRY'] ?? '';
        $buyerVat = $row['BUYER_VAT_NUMBER_COUNTRY'] ?? null;  // Python uses BUYER_VAT_NUMBER_COUNTRY
        $vatAmount = (float)($row['TOTAL_ACTIVITY_VALUE_VAT_AMT'] ?? 0);
        $reportingScheme = $row['TAX_REPORTING_SCHEME'] ?? null;
        $supplierName = $row['SUPPLIER_NAME'] ?? '';
        $taxResponsibility = $row['TAX_COLLECTION_RESPONSIBILITY'] ?? null;
        $exportOutsideEu = $row['EXPORT_OUTSIDE_EU'] ?? '';

        // Handle empty strings as null (like Python's pd.isna() / pd.notna())
        if ($buyerVat === '') $buyerVat = null;
        if ($reportingScheme === '') $reportingScheme = null;
        if ($taxResponsibility === '') $taxResponsibility = null;

        // EXACT PYTHON LOGIC: Mixed if/elif structure
        // Python lines 254-325 - must match exactly!

        // 1. Classification B2C y B2B (Python lines 255-259) - uses "if" (not elif)
        if (in_array($reportingScheme, ['REGULAR', 'UK_VOEC-DOMESTIC']) && $taxResponsibility === 'SELLER') {
            $b2cMatch = true;
        } else {
            $b2cMatch = false;
        }

        // 2. Classification for "Ventas locales SIN IVA" (Python lines 262-268) - uses "if" (not elif)
        if ($departCountry === $arrivalCountry &&
            $vatAmount == 0 &&
            $buyerVat === null &&
            $taxResponsibility === 'SELLER') {
            // SIN IVA has precedence over B2C in Python (later "if" overrides earlier)
            return 'Ventas locales SIN IVA (EUR)';
        }

        // 3. Classification Intracomunitario B2B (Python lines 272-280) - uses "elif"
        if ($departCountry !== $arrivalCountry &&
            in_array($departCountry, self::EU_COUNTRIES) &&
            in_array($arrivalCountry, self::EU_COUNTRIES) &&
            $departCountry !== 'GB' &&
            $arrivalCountry !== 'GB' &&
            !empty($buyerVat) &&
            $taxResponsibility === 'SELLER' &&
            $reportingScheme === 'REGULAR') {
            return 'Ventas Intracomunitarias de bienes - B2B (EUR)';
        }

        // 4. Classification OSS (Python line 302) - uses "elif"
        if ($reportingScheme === 'UNION-OSS') {
            return 'Ventanilla Única - OSS esquema europeo (EUR)';
        }

        // 5. Classification IOSS (Python line 306) - uses "elif"
        if ($reportingScheme === 'DEEMED_RESELLER-IOSS' &&
            in_array($departCountry, self::EU_COUNTRIES) &&
            in_array($arrivalCountry, self::EU_COUNTRIES) &&
            $departCountry !== $arrivalCountry) {
            return 'Ventanilla Única - IOSS esquema de importación (EUR)';
        }

        // 6. Classification IVA Amazon Marketplace (Python line 310) - uses "elif"
        if ($taxResponsibility === 'MARKETPLACE') {
            return 'IVA recaudado y remitido por Amazon Marketplace (EUR)';
        }

        // 7. Classification Compras a Amazon (Python line 314) - uses "elif"
        if ($supplierName === 'Amazon Services Europe Sarl') {
            return 'Compras a Amazon (EUR)';
        }

        // 8. Classification Exportaciones (Python line 318) - uses "elif" - LOWEST PRIORITY!
        if (in_array($departCountry, ['ES', 'DE', 'FR', 'IT', 'PL']) &&
            !in_array($arrivalCountry, ['ES', 'DE', 'FR', 'IT', 'PL'])) {
            return 'Exportaciones (EUR)';
        }

        // If we only matched B2C and nothing else applied, return B2C
        if ($b2cMatch) {
            return 'Ventas locales al consumidor final - B2C y B2B (EUR)';
        }

        return null; // Unclassified
    }

    /**
     * Process category according to Python script logic
     */
    private function processCategory(string $category, array $transactions): array
    {
        // Convert to numeric and apply currency conversion
        $transactions = $this->convertToNumericAndCurrency($transactions, $category);

        // Calculate Base, IVA, Total for each transaction
        $transactions = $this->calculateTotals($transactions);

        // Apply category-specific aggregation logic
        switch ($category) {
            case 'Ventas locales al consumidor final - B2C y B2B (EUR)':
                return $this->processB2cB2bLocal($transactions);

            case 'Ventas locales SIN IVA (EUR)':
                return $this->processLocalSinIva($transactions);

            case 'Ventas Intracomunitarias de bienes - B2B (EUR)':
                return $this->processIntracomunitariasB2b($transactions);

            case 'Ventanilla Única - OSS esquema europeo (EUR)':
                return $this->processOss($transactions);

            case 'Ventanilla Única - IOSS esquema de importación (EUR)':
                return $this->processIoss($transactions);

            case 'IVA recaudado y remitido por Amazon Marketplace (EUR)':
                return $this->processMarketplaceVat($transactions);

            case 'Compras a Amazon (EUR)':
                return $this->processAmazonCompras($transactions);

            case 'Exportaciones (EUR)':
                return $this->processExportaciones($transactions);

            default:
                return $transactions;
        }
    }

    /**
     * Convert numeric columns and apply currency conversion
     */
    private function convertToNumericAndCurrency(array $transactions, string $category): array
    {
        foreach ($transactions as &$transaction) {
            // Convert all numeric columns
            foreach (self::NUMERIC_COLUMNS as $column) {
                if (isset($transaction[$column])) {
                    $transaction[$column] = (float)$transaction[$column];
                }
            }

            // Apply currency conversion for specific categories
            if ($this->requiresCurrencyConversion($category)) {
                $currency = $transaction['TRANSACTION_CURRENCY_CODE'] ?? 'EUR';
                if ($currency !== 'EUR' && isset(self::EXCHANGE_RATES[$currency])) {
                    $rate = self::EXCHANGE_RATES[$currency];
        foreach (self::NUMERIC_COLUMNS as $column) {
                        if (isset($transaction[$column])) {
                            $transaction[$column] = round($transaction[$column] * $rate, 2);
                        }
                    }
                    $transaction['TRANSACTION_CURRENCY_CODE'] = 'EUR';
                }
            }
        }

        return $transactions;
    }

    /**
     * Check if category requires currency conversion
     */
    private function requiresCurrencyConversion(string $category): bool
    {
        return in_array($category, [
            'Ventanilla Única - OSS esquema europeo (EUR)',
            'Ventanilla Única - IOSS esquema de importación (EUR)',
            'IVA recaudado y remitido por Amazon Marketplace (EUR)',
            'Compras a Amazon (EUR)',
            'Ventas Intracomunitarias de bienes - B2B (EUR)',
        ]);
    }

    /**
     * Calculate Base, IVA, Total for each transaction
     */
    private function calculateTotals(array $transactions): array
    {
        foreach ($transactions as &$transaction) {
            // Calculate Base (€): Sum of VAT-excluded amounts
            $transaction['Base (€)'] = 0;
            foreach (self::VAT_EXCL_COLUMNS as $column) {
                $transaction['Base (€)'] += $transaction[$column] ?? 0;
            }

            // Calculate IVA (€): Sum of VAT amounts
            $transaction['IVA (€)'] = 0;
            foreach (self::VAT_COLUMNS as $column) {
                $transaction['IVA (€)'] += $transaction[$column] ?? 0;
            }

            // Calculate Total (€): Sum of VAT-included amounts
            $transaction['Total (€)'] = 0;
            foreach (self::VAT_INCL_COLUMNS as $column) {
                $transaction['Total (€)'] += $transaction[$column] ?? 0;
            }
        }

        return $transactions;
    }

    /**
     * Process B2C/B2B Local category (Python lines 395-420)
     */
    private function processB2cB2bLocal(array $transactions): array
    {
        // Calculate Calculated Base (€) for each transaction
        foreach ($transactions as &$transaction) {
            $vatRate = $transaction['PRICE_OF_ITEMS_VAT_RATE_PERCENT'] ?? 0;
            if ($transaction['IVA (€)'] > 0 && $vatRate > 0) {
                $transaction['Calculated Base (€)'] = $transaction['IVA (€)'] / $vatRate;
            } else {
                $transaction['Calculated Base (€)'] = $transaction['Base (€)'];
            }
        }

        // Group by TAXABLE_JURISDICTION
        $grouped = [];
        foreach ($transactions as $transaction) {
            $jurisdiction = $transaction['TAXABLE_JURISDICTION'] ?? '';

            // Skip transactions with empty jurisdiction or zero amounts
            if (empty($jurisdiction) || (!is_string($jurisdiction) && !is_numeric($jurisdiction))) {
                continue;
            }

            if (!isset($grouped[$jurisdiction])) {
                $grouped[$jurisdiction] = [
                    'TAXABLE_JURISDICTION' => $jurisdiction,
                    'Calculated Base (€)' => 0,
                    'IVA (€)' => 0,
                    'Total (€)' => 0,
                ];
            }

            $grouped[$jurisdiction]['Calculated Base (€)'] += $transaction['Calculated Base (€)'];
            $grouped[$jurisdiction]['IVA (€)'] += $transaction['IVA (€)'];
            $grouped[$jurisdiction]['Total (€)'] += $transaction['Total (€)'];
        }

        $result = array_values($grouped);

        // Sort by TAXABLE_JURISDICTION alphabetically
        usort($result, function($a, $b) {
            return strcmp($a['TAXABLE_JURISDICTION'], $b['TAXABLE_JURISDICTION']);
        });

        // Add total row
        $totalRow = [
            'TAXABLE_JURISDICTION' => 'Total',
            'Calculated Base (€)' => array_sum(array_column($result, 'Calculated Base (€)')),
            'IVA (€)' => array_sum(array_column($result, 'IVA (€)')),
            'Total (€)' => array_sum(array_column($result, 'Total (€)')),
        ];

        $result[] = $totalRow;
        return $result;
    }

    /**
     * Process Local Sin IVA category (Python lines 427-500)
     */
    private function processLocalSinIva(array $transactions): array
    {
        $result = [];

        foreach ($transactions as $transaction) {
            $buyerData = !empty($transaction['BUYER_VAT_NUMBER']) ?
                $transaction['BUYER_VAT_NUMBER'] : 'Sin identificación fiscal';

            $detail = $transaction['TRANSACTION_TYPE'] === 'SALE' ?
                'Envíos SIN IVA' : 'Productos SIN IVA';

            $result[] = [
                'País de origen / Datos del comprador' => $transaction['DEPARTURE_COUNTRY'] ?? '',
                'Datos del comprador' => $buyerData,
                'Detalle' => $detail,
                'Base (€)' => $transaction['Base (€)'],
                'IVA (€)' => $transaction['IVA (€)'],
                'Total (€)' => $transaction['Total (€)'],
            ];
        }

        // Add total row
        $totalRow = [
            'País de origen / Datos del comprador' => 'Total',
            'Datos del comprador' => '',
            'Detalle' => '',
            'Base (€)' => array_sum(array_column($result, 'Base (€)')),
            'IVA (€)' => array_sum(array_column($result, 'IVA (€)')),
            'Total (€)' => array_sum(array_column($result, 'Total (€)')),
        ];

        $result[] = $totalRow;
        return $result;
    }

    /**
     * Process Intracomunitarias B2B category (Python lines 502-578)
     * Aggregate transactions by buyer (country + name + VAT number)
     */
    public function processIntracomunitariasB2b(array $transactions): array
    {
        $grouped = [];

        foreach ($transactions as $transaction) {
            // Set VAT amounts to zero for intracomunitarias (Python lines 283-298)
            foreach (self::VAT_COLUMNS as $column) {
                $transaction[$column] = 0;
            }

            $country = $transaction['SALE_DEPART_COUNTRY'] ?? '';
            $buyerName = trim($transaction['BUYER_NAME'] ?? '');
            $buyerVat = trim($transaction['BUYER_VAT_NUMBER'] ?? ''); // Use full VAT number, not country

            // Use real buyer name or "Sin nombre" if empty
            if (empty($buyerName)) {
                $buyerName = 'Sin nombre';
            }

            // Create grouping key: for "Sin nombre" aggregate by country only,
            // for named buyers aggregate by country + name + VAT
            if ($buyerName === 'Sin nombre') {
                $key = $country . '|Sin nombre|';
                $buyerVat = ''; // Clear VAT for Sin nombre entries
            } else {
                $key = $country . '|' . $buyerName . '|' . $buyerVat;
            }

            // Calculate base amount from VAT-excluded columns
            $baseAmount = 0;
            foreach (self::VAT_EXCL_COLUMNS as $col) {
                $baseAmount += (float)($transaction[$col] ?? 0);
            }

            // Initialize group if not exists
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'País de origen' => $country,
                    'Nombre del comprador' => $buyerName,
                    'NIF del comprador' => !empty($buyerVat) ? $buyerVat : '',
                    'Base (€)' => 0,
                    'IVA (€)' => 0, // Always 0 for intracomunitarias B2B
                    'Total (€)' => 0,
                ];
            }

            // Add amounts to the group (with proper rounding)
            $grouped[$key]['Base (€)'] += $baseAmount;
            $grouped[$key]['Total (€)'] += $baseAmount; // Same as base since IVA is 0
        }

        $result = array_values($grouped);

        // Round values to fix floating point precision issues
        foreach ($result as &$row) {
            $row['Base (€)'] = round($row['Base (€)'], 2);
            $row['Total (€)'] = round($row['Total (€)'], 2);

            // Convert very small values to zero
            if (abs($row['Base (€)']) < 0.01) {
                $row['Base (€)'] = 0;
            }
            if (abs($row['Total (€)']) < 0.01) {
                $row['Total (€)'] = 0;
            }
        }

        // Sort by country, then by buyer name
        usort($result, function($a, $b) {
            if ($a['País de origen'] === $b['País de origen']) {
                return strcmp($a['Nombre del comprador'], $b['Nombre del comprador']);
            }
            return strcmp($a['País de origen'], $b['País de origen']);
        });

        // Add total row
        $totalRow = [
            'País de origen' => 'Total',
            'Nombre del comprador' => '',
            'NIF del comprador' => '',
            'Base (€)' => array_sum(array_column($result, 'Base (€)')),
            'IVA (€)' => 0,
            'Total (€)' => array_sum(array_column($result, 'Total (€)')),
        ];

        $result[] = $totalRow;
        return $result;
    }

    /**
     * Process OSS category (Python lines 646-743)
     */
    private function processOss(array $transactions): array
    {
        // Create destination country with VAT rate
        foreach ($transactions as &$transaction) {
            $arrivalCountry = $transaction['SALE_ARRIVAL_COUNTRY'] ?? '';
            $vatRate = self::OSS_VAT_RATES[$arrivalCountry] ?? 0;
            $transaction['País de destino / Tipo de IVA repercutido'] =
                $arrivalCountry . ' - ' . number_format($vatRate * 100, 2) . '%';
        }

        // Group by origin and destination with VAT rate
        $grouped = [];
        foreach ($transactions as $transaction) {
            $origin = $transaction['SALE_DEPART_COUNTRY'] ?? '';
            $destination = $transaction['País de destino / Tipo de IVA repercutido'];
            $key = $origin . '|' . $destination;

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'País de origen' => $origin,
                    'País de destino / Tipo de IVA repercutido' => $destination,
                    'Base (€)' => 0,
                    'IVA (€)' => 0,
                    'Total (€)' => 0,
                ];
            }

            $grouped[$key]['Base (€)'] += $transaction['Base (€)'];
            $grouped[$key]['IVA (€)'] += $transaction['IVA (€)'];
            $grouped[$key]['Total (€)'] += $transaction['Total (€)'];
        }

        $result = array_values($grouped);

        // Add total row
        $totalRow = [
            'País de origen' => 'Total',
            'País de destino / Tipo de IVA repercutido' => '',
            'Base (€)' => array_sum(array_column($result, 'Base (€)')),
            'IVA (€)' => array_sum(array_column($result, 'IVA (€)')),
            'Total (€)' => array_sum(array_column($result, 'Total (€)')),
        ];

        $result[] = $totalRow;
        return $result;
    }

    /**
     * Process IOSS category (Python lines 579-644)
     */
    private function processIoss(array $transactions): array
    {
        // Create destination country with VAT rate
        foreach ($transactions as &$transaction) {
            $arrivalCountry = $transaction['ARRIVAL_COUNTRY'] ?? '';
            $vatRate = $transaction['PRICE_OF_ITEMS_VAT_RATE_PERCENT'] ?? 0;
            $transaction['País de destino / Tipo de IVA repercutido'] =
                $arrivalCountry . ' - ' . $vatRate . '%';
        }

        // Group by origin and destination with VAT rate
        $grouped = [];
        foreach ($transactions as $transaction) {
            $origin = $transaction['SALE_DEPART_COUNTRY'] ?? '';
            $destination = $transaction['País de destino / Tipo de IVA repercutido'];
            $key = $origin . '|' . $destination;

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'País de origen' => $origin,
                    'País de destino / Tipo de IVA repercutido' => $destination,
                    'Base (€)' => 0,
                    'IVA (€)' => 0,
                    'Total (€)' => 0,
                ];
            }

            $grouped[$key]['Base (€)'] += $transaction['Base (€)'];
            $grouped[$key]['IVA (€)'] += $transaction['IVA (€)'];
            $grouped[$key]['Total (€)'] += $transaction['Total (€)'];
        }

        $result = array_values($grouped);

        // Add total row
        $totalRow = [
            'País de origen' => 'Total',
            'País de destino / Tipo de IVA repercutido' => '',
            'Base (€)' => array_sum(array_column($result, 'Base (€)')),
            'IVA (€)' => array_sum(array_column($result, 'IVA (€)')),
            'Total (€)' => array_sum(array_column($result, 'Total (€)')),
        ];

        $result[] = $totalRow;
        return $result;
    }

    /**
     * Process Marketplace VAT category (Python lines 807-867)
     */
    private function processMarketplaceVat(array $transactions): array
    {
        // Group by origin and destination countries
        $grouped = [];
        foreach ($transactions as $transaction) {
            $origin = $transaction['SALE_DEPART_COUNTRY'] ?? '';
            $destination = $transaction['SALE_ARRIVAL_COUNTRY'] ?? '';
            $key = $origin . '|' . $destination;

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'País de Origen' => $origin,
                    'País de Destino' => $destination,
                    'Base (€)' => 0,
                    'IVA (€)' => 0,
                    'Total (€)' => 0,
                ];
            }

            $grouped[$key]['Base (€)'] += $transaction['Base (€)'];
            $grouped[$key]['IVA (€)'] += $transaction['IVA (€)'];
            $grouped[$key]['Total (€)'] += $transaction['Total (€)'];
        }

        $result = array_values($grouped);

        // Add total row
        $totalRow = [
            'País de Origen' => 'Total',
            'País de Destino' => '',
            'Base (€)' => array_sum(array_column($result, 'Base (€)')),
            'IVA (€)' => array_sum(array_column($result, 'IVA (€)')),
            'Total (€)' => array_sum(array_column($result, 'Total (€)')),
        ];

        $result[] = $totalRow;
        return $result;
    }

    /**
     * Process Amazon Compras category (Python lines 745-805)
     */
    private function processAmazonCompras(array $transactions): array
    {
        // Group by origin and destination countries
        $grouped = [];
        foreach ($transactions as $transaction) {
            $origin = $transaction['SALE_DEPART_COUNTRY'] ?? '';
            $destination = $transaction['SALE_ARRIVAL_COUNTRY'] ?? '';
            $key = $origin . '|' . $destination;

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'País de Origen' => $origin,
                    'País de Destino' => $destination,
                    'Base (€)' => 0,
                    'IVA (€)' => 0,
                    'Total (€)' => 0,
                ];
            }

            $grouped[$key]['Base (€)'] += $transaction['Base (€)'];
            $grouped[$key]['IVA (€)'] += $transaction['IVA (€)'];
            $grouped[$key]['Total (€)'] += $transaction['Total (€)'];
        }

        $result = array_values($grouped);

        // Add total row
        $totalRow = [
            'País de Origen' => 'Total',
            'País de Destino' => '',
            'Base (€)' => array_sum(array_column($result, 'Base (€)')),
            'IVA (€)' => array_sum(array_column($result, 'IVA (€)')),
            'Total (€)' => array_sum(array_column($result, 'Total (€)')),
        ];

        $result[] = $totalRow;
        return $result;
    }

    /**
     * Process Exportaciones category (Python lines 869-900)
     */
    private function processExportaciones(array $transactions): array
    {
        // Group by origin, destination country and destination city
        $grouped = [];
        foreach ($transactions as $transaction) {
            $origin = $transaction['SALE_DEPART_COUNTRY'] ?? '';
            $destination = $transaction['SALE_ARRIVAL_COUNTRY'] ?? '';
            $city = $transaction['ARRIVAL_CITY'] ?? '';
            $key = $origin . '|' . $destination . '|' . $city;

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'País de Origen' => $origin,
                    'País de Destino' => $destination,
                    'Ciudad de Destino' => $city,
                    'Base (€)' => 0,
                    'IVA (€)' => 0,
                    'Total (€)' => 0,
                ];
            }

            // Use TOTAL_ACTIVITY_VALUE columns directly as in Python script
            $grouped[$key]['Base (€)'] += $transaction['TOTAL_ACTIVITY_VALUE_AMT_VAT_EXCL'] ?? 0;
            $grouped[$key]['IVA (€)'] += $transaction['TOTAL_ACTIVITY_VALUE_VAT_AMT'] ?? 0;
            $grouped[$key]['Total (€)'] += $transaction['TOTAL_ACTIVITY_VALUE_AMT_VAT_INCL'] ?? 0;
        }

        // Filter out rows where all amounts are 0
        $filtered = array_filter($grouped, function($row) {
            return $row['Base (€)'] != 0 || $row['IVA (€)'] != 0 || $row['Total (€)'] != 0;
        });

        $result = array_values($filtered);

        // Add total row
        $totalRow = [
            'País de Origen' => 'Total',
            'País de Destino' => '',
            'Ciudad de Destino' => '',
            'Base (€)' => array_sum(array_column($result, 'Base (€)')),
            'IVA (€)' => array_sum(array_column($result, 'IVA (€)')),
            'Total (€)' => array_sum(array_column($result, 'Total (€)')),
        ];

        $result[] = $totalRow;
        return $result;
    }

    /**
     * Generate Excel output like Python script (saved as .csv file)
     */
    private function generateExcelOutput(array $classifications, string $outputPath): void
    {
        $spreadsheet = new Spreadsheet();

        // Remove default sheet
        $spreadsheet->removeSheetByIndex(0);

        // Define sections as in Python script
        $regularCategories = [
            'Ventas locales al consumidor final - B2C y B2B (EUR)'
        ];

        // EXACT order as shown in expected output images
        $internationalCategories = [
            'Ventas Intracomunitarias de bienes - B2B (EUR)',
            'Ventanilla Única - OSS esquema europeo (EUR)',
            'Totales por país de destino en OSS',
            'IVA recaudado y remitido por Amazon Marketplace (EUR)',
            'Exportaciones (EUR)'
        ];

        // Generate OSS breakdown section
        if (!empty($classifications['Ventanilla Única - OSS esquema europeo (EUR)'])) {
            $classifications['Totales por país de destino en OSS'] = $this->generateOssBreakdown($classifications['Ventanilla Única - OSS esquema europeo (EUR)']);
        }

        // Create REGULAR sheet
        $hasRegularData = false;
        foreach ($regularCategories as $category) {
            if (!empty($classifications[$category])) {
                $hasRegularData = true;
                break;
            }
        }

        if ($hasRegularData) {
            $regularSheet = $spreadsheet->createSheet();
            $regularSheet->setTitle('REGULAR');
            $this->populateSheet($regularSheet, $regularCategories, $classifications);
        }

        // Create INTERNATIONAL sheet - ALWAYS include ALL categories like Python (lines 943-972)
        $internationalSheet = $spreadsheet->createSheet();
        $internationalSheet->setTitle('INTERNATIONAL');
        $this->populateSheet($internationalSheet, $internationalCategories, $classifications);

        // Set active sheet to first available
        if ($spreadsheet->getSheetCount() > 0) {
            $spreadsheet->setActiveSheetIndex(0);
        }

        // Write to Excel file but save with .csv extension (like Python script)
        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);
    }

    /**
     * Populate Excel sheet with category data (matching Python formatting)
     */
    private function populateSheet($sheet, array $categories, array $classifications): void
    {
        $currentRow = 1;

        foreach ($categories as $category) {
            $data = $classifications[$category] ?? [];

            // Always include category header like Python, even if no data
            if (empty($data)) {
                // Create proper empty data structure matching category format
                if ($category === 'Ventas Intracomunitarias de bienes - B2B (EUR)') {
                    $data = [['País de origen' => 'Total', 'Nombre del comprador' => '', 'NIF del comprador' => '', 'Base (€)' => 0, 'IVA (€)' => 0, 'Total (€)' => 0]];
                } elseif ($category === 'Exportaciones (EUR)') {
                    $data = [['País de Origen' => 'Total', 'País de Destino' => '', 'Ciudad de Destino' => '', 'Base (€)' => 0, 'IVA (€)' => 0, 'Total (€)' => 0]];
                } else {
                    // Generic empty structure
                    $data = [['Total' => 0]];
                }
            }

            // Add category header with blue background and white text (like Python)
            $sheet->setCellValue("A{$currentRow}", $category);

            // Style the header like Python script
            $headerRange = "A{$currentRow}:" . $this->getColumnLetter(count(array_keys($data[0]))) . "{$currentRow}";
            $sheet->mergeCells($headerRange);
            $sheet->getStyle($headerRange)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->setStartColor(new Color('4F81BD'));
            $sheet->getStyle($headerRange)->getFont()
                ->setBold(true)
                ->setColor(new Color(Color::COLOR_WHITE));

            $currentRow++; // Go to next row for column headers

            // Add column headers
            $headers = array_keys($data[0]);
            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue("{$col}{$currentRow}", $header);
                $col++;
            }
            $currentRow++;

            // Add data rows
            foreach ($data as $rowIndex => $row) {
                $col = 'A';
                foreach ($headers as $header) {
                    $value = $row[$header] ?? '';
                    $sheet->setCellValue("{$col}{$currentRow}", $value);
                    $col++;
                }

                // Style total row with yellow background (like Python)
                if ($rowIndex === count($data) - 1 && ($row[$headers[0]] ?? '') === 'Total') {
                    $totalRange = "A{$currentRow}:" . $this->getColumnLetter(count($headers)) . "{$currentRow}";
                    $sheet->getStyle($totalRange)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->setStartColor(new Color('FFFF00'));
                    $sheet->getStyle($totalRange)->getFont()->setBold(true);
                }

                $currentRow++;
            }

            $currentRow += 2; // Extra space between categories
        }
    }

    /**
     * Get Excel column letter for given column number
     */
    private function getColumnLetter(int $columnNumber): string
    {
        $letter = '';
        while ($columnNumber > 0) {
            $columnNumber--;
            $letter = chr(65 + ($columnNumber % 26)) . $letter;
            $columnNumber = intval($columnNumber / 26);
        }
        return $letter;
    }

    /**
     * Generate OSS breakdown by destination country (like in expected output)
     */
    private function generateOssBreakdown(array $ossData): array
    {
        $breakdown = [];
        $totals = ['Base (€)' => 0, 'IVA (€)' => 0, 'Total (€)' => 0];

        // Group by destination country (exclude total row to avoid double counting)
        $countryTotals = [];
        foreach ($ossData as $row) {
            // Skip the total row to avoid double counting
            if (($row['País de origen'] ?? '') === 'Total') {
                continue;
            }

            // Extract destination country from "País de destino / Tipo de IVA repercutido" field
            $destination = $row['País de destino / Tipo de IVA repercutido'] ?? '';
            $country = explode(' - ', $destination)[0] ?? '';

            if (!isset($countryTotals[$country])) {
                $countryTotals[$country] = ['Base (€)' => 0, 'IVA (€)' => 0, 'Total (€)' => 0];
            }

            $countryTotals[$country]['Base (€)'] += (float)($row['Base (€)'] ?? 0);
            $countryTotals[$country]['IVA (€)'] += (float)($row['IVA (€)'] ?? 0);
            $countryTotals[$country]['Total (€)'] += (float)($row['Total (€)'] ?? 0);
        }

        // Sort by country and create breakdown rows
        ksort($countryTotals);

        // Add total row first (like in expected output)
        $allTotals = ['Base (€)' => 0, 'IVA (€)' => 0, 'Total (€)' => 0];
        foreach ($countryTotals as $amounts) {
            $allTotals['Base (€)'] += $amounts['Base (€)'];
            $allTotals['IVA (€)'] += $amounts['IVA (€)'];
            $allTotals['Total (€)'] += $amounts['Total (€)'];
        }

        // Add total row first (no comma formatting like Python)
        $breakdown[] = [
            'País de destino' => '',
            'Base (€)' => round($allTotals['Base (€)'], 2),
            'IVA (€)' => round($allTotals['IVA (€)'], 2),
            'Total (€)' => round($allTotals['Total (€)'], 2)
        ];

        // Add country breakdown
        foreach ($countryTotals as $country => $amounts) {
            $breakdown[] = [
                'País de destino' => $country,
                'Base (€)' => round($amounts['Base (€)'], 2),
                'IVA (€)' => round($amounts['IVA (€)'], 2),
                'Total (€)' => round($amounts['Total (€)'], 2)
            ];
        }

        return $breakdown;
    }
}
