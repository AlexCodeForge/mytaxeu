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
 * True Streaming CSV Transformer
 *
 * Processes CSV files of any size with constant memory usage.
 * Applies the same business logic as CsvTransformer but in streaming mode.
 */
class StreamingCsvTransformer
{
    // Constants from CsvTransformer - keeping same business logic
    private const VAT_EXCL_COLUMNS = [
        'PRICE_OF_ITEMS_AMT_VAT_EXCL', 'PROMO_PRICE_OF_ITEMS_AMT_VAT_EXCL',
        'SHIP_CHARGE_AMT_VAT_EXCL', 'PROMO_SHIP_CHARGE_AMT_VAT_EXCL',
        'GIFT_WRAP_AMT_VAT_EXCL', 'PROMO_GIFT_WRAP_AMT_VAT_EXCL',
    ];

    private const VAT_COLUMNS = [
        'PRICE_OF_ITEMS_VAT_AMT', 'PROMO_PRICE_OF_ITEMS_VAT_AMT',
        'SHIP_CHARGE_VAT_AMT', 'PROMO_SHIP_CHARGE_VAT_AMT',
        'GIFT_WRAP_VAT_AMT', 'PROMO_GIFT_WRAP_VAT_AMT',
    ];

    private const VAT_INCL_COLUMNS = [
        'PRICE_OF_ITEMS_AMT_VAT_INCL', 'PROMO_PRICE_OF_ITEMS_AMT_VAT_INCL',
        'SHIP_CHARGE_AMT_VAT_INCL', 'PROMO_SHIP_CHARGE_AMT_VAT_INCL',
        'GIFT_WRAP_AMT_VAT_INCL', 'PROMO_GIFT_WRAP_AMT_VAT_INCL',
    ];

    private const NUMERIC_COLUMNS = [
        'COST_PRICE_OF_ITEMS', 'PRICE_OF_ITEMS_AMT_VAT_EXCL', 'PROMO_PRICE_OF_ITEMS_AMT_VAT_EXCL',
        'TOTAL_PRICE_OF_ITEMS_AMT_VAT_EXCL', 'SHIP_CHARGE_AMT_VAT_EXCL', 'PROMO_SHIP_CHARGE_AMT_VAT_EXCL',
        'TOTAL_SHIP_CHARGE_AMT_VAT_EXCL', 'GIFT_WRAP_AMT_VAT_EXCL', 'PROMO_GIFT_WRAP_AMT_VAT_EXCL',
        'TOTAL_GIFT_WRAP_AMT_VAT_EXCL', 'TOTAL_ACTIVITY_VALUE_AMT_VAT_EXCL',
        'PRICE_OF_ITEMS_VAT_AMT', 'PROMO_PRICE_OF_ITEMS_VAT_AMT',
        'TOTAL_PRICE_OF_ITEMS_VAT_AMT', 'SHIP_CHARGE_VAT_AMT',
        'PROMO_SHIP_CHARGE_VAT_AMT', 'TOTAL_SHIP_CHARGE_VAT_AMT',
        'GIFT_WRAP_VAT_AMT', 'PROMO_GIFT_WRAP_VAT_AMT', 'TOTAL_GIFT_WRAP_VAT_AMT', 'TOTAL_ACTIVITY_VALUE_VAT_AMT',
        'PRICE_OF_ITEMS_AMT_VAT_INCL', 'PROMO_PRICE_OF_ITEMS_AMT_VAT_INCL', 'TOTAL_PRICE_OF_ITEMS_AMT_VAT_INCL',
        'SHIP_CHARGE_AMT_VAT_INCL', 'PROMO_SHIP_CHARGE_AMT_VAT_INCL', 'TOTAL_SHIP_CHARGE_AMT_VAT_INCL',
        'GIFT_WRAP_AMT_VAT_INCL', 'PROMO_GIFT_WRAP_AMT_VAT_INCL', 'TOTAL_GIFT_WRAP_AMT_VAT_INCL',
        'TOTAL_ACTIVITY_VALUE_AMT_VAT_INCL',
    ];

    private const EU_COUNTRIES = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR',
        'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL',
        'PT', 'RO', 'SE', 'SI', 'SK'
    ];

    private const EXCHANGE_RATES = [
        'PLN' => 0.319033, 'EUR' => 1.0, 'SEK' => 0.087, 'GBP' => 1.169827,
    ];

    private const OSS_VAT_RATES = [
        'AT' => 0.20, 'BE' => 0.21, 'BG' => 0.20, 'CY' => 0.19, 'CZ' => 0.21,
        'DE' => 0.19, 'DK' => 0.25, 'EE' => 0.20, 'ES' => 0.21, 'FI' => 0.24,
        'FR' => 0.20, 'GR' => 0.24, 'HR' => 0.25, 'HU' => 0.27, 'IE' => 0.23,
        'IT' => 0.22, 'LT' => 0.21, 'LU' => 0.17, 'LV' => 0.21, 'NL' => 0.21,
        'PL' => 0.23, 'PT' => 0.23, 'RO' => 0.19, 'SE' => 0.25, 'SI' => 0.22,
    ];

    private array $categoryAggregates;
    private array $activityPeriods;
    private int $processedRows;

    public function transform(string $inputPath, string $outputPath): void
    {
        $startTime = microtime(true);
        $initialMemory = memory_get_usage(true);

        $this->log("Starting streaming CSV transformation", [
            'input' => $inputPath,
            'output' => $outputPath,
            'size_mb' => round(filesize($inputPath) / 1024 / 1024, 2),
            'initial_memory_mb' => round($initialMemory / 1024 / 1024, 2)
        ]);

        // Initialize aggregates
        $this->initializeAggregates();
        $this->activityPeriods = [];
        $this->processedRows = 0;

        // Process CSV in streaming mode
        $this->processFileStreaming($inputPath);

        // Apply UK marketplace allocation logic (4.7% to Czech Republic and Poland)
        $this->applyUkMarketplaceAllocation();

        // Validate activity periods
        $this->validateActivityPeriods();

        // Generate Excel output
        $this->generateExcelOutput($outputPath);

        $endTime = microtime(true);
        $finalMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        $this->log("Streaming CSV transformation completed", [
            'output' => $outputPath,
            'rows_processed' => $this->processedRows,
            'processing_time_seconds' => round($endTime - $startTime, 2),
            'final_memory_mb' => round($finalMemory / 1024 / 1024, 2),
            'peak_memory_mb' => round($peakMemory / 1024 / 1024, 2),
            'memory_efficient' => true
        ]);
    }

    private function initializeAggregates(): void
    {
        $this->categoryAggregates = [
            'Ventas locales al consumidor final - B2C y B2B (EUR)' => [],
            'Ventas locales SIN IVA (EUR)' => [],
            'Ventas Intracomunitarias de bienes - B2B (EUR)' => [],
            'Ventanilla Única - OSS esquema europeo (EUR)' => [],
            'Ventanilla Única - IOSS esquema de importación (EUR)' => [],
            'IVA recaudado y remitido por Amazon Marketplace (EUR)' => [],
            'Compras a Amazon (EUR)' => [],
            'Exportaciones (EUR)' => [],
        ];
    }

    private function processFileStreaming(string $inputPath): void
    {
        $handle = fopen($inputPath, 'r');
        if (!$handle) {
            throw new Exception("Cannot open input file: $inputPath");
        }

        $headers = null;
        $rowCount = 0;

        while (($row = fgetcsv($handle)) !== false) {
            // First row = headers
            if ($headers === null) {
                $headers = $row;
                continue;
            }

            $rowCount++;

            // Create associative array for this row
            $transaction = array_combine($headers, $row);

            // Skip RETURN transactions
            if (($transaction['TRANSACTION_TYPE'] ?? '') === 'RETURN') {
                continue;
            }

            // Track activity periods
            $period = trim($transaction['ACTIVITY_PERIOD'] ?? '');
            if (!empty($period) && !in_array($period, $this->activityPeriods)) {
                $this->activityPeriods[] = $period;
            }

            // Process this transaction (apply business logic)
            $this->processTransaction($transaction);
            $this->processedRows++;

            // Memory cleanup every 1000 rows
            if ($rowCount % 1000 === 0) {
                gc_collect_cycles();

                if ($rowCount % 10000 === 0) {
                    $this->log("Streaming progress", [
                        'rows_processed' => $rowCount,
                        'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
                    ]);
                }
            }
        }

        fclose($handle);
    }

    private function processTransaction(array $transaction): void
    {
        // Convert numeric columns
        $this->convertNumericColumns($transaction);

        // Calculate totals
        $this->calculateTotals($transaction);


        // Classify transaction (can belong to multiple categories)
        $categories = $this->classifyTransaction($transaction);


        // Add to appropriate aggregates
        foreach ($categories as $category) {
            // PYTHON EXACT LOGIC: For Intracomunitarias, create modified copy with zeroed VAT
            if ($category === 'Ventas Intracomunitarias de bienes - B2B (EUR)') {
                $modifiedTransaction = $transaction; // Make a copy

                // Zero out VAT amounts for Intracomunitarias transactions (Python lines 135-150)
                $vatColumns = [
                    'PRICE_OF_ITEMS_VAT_AMT', 'PROMO_PRICE_OF_ITEMS_VAT_AMT',
                    'SHIP_CHARGE_VAT_AMT', 'PROMO_SHIP_CHARGE_VAT_AMT',
                    'GIFT_WRAP_VAT_AMT', 'PROMO_GIFT_WRAP_VAT_AMT'
                ];

                foreach ($vatColumns as $column) {
                    $modifiedTransaction[$column] = 0;
                }

                // Recalculate IVA and Total after zeroing VAT columns
                $modifiedTransaction['IVA (€)'] = 0;
                // Total should equal Base for Intracomunitarias
                $modifiedTransaction['Total (€)'] = $modifiedTransaction['Base (€)'];


                $this->addToAggregate($category, $modifiedTransaction);
            } else {
                // For all other categories, use original transaction
                $this->addToAggregate($category, $transaction);
            }
        }
    }

    private function convertNumericColumns(array &$transaction): void
    {
        foreach (self::NUMERIC_COLUMNS as $column) {
            if (isset($transaction[$column])) {
                $transaction[$column] = (float)$transaction[$column];
            }
        }

        // Apply currency conversion if needed
        $currency = $transaction['TRANSACTION_CURRENCY_CODE'] ?? 'EUR';
        $jurisdiction = $transaction['TAXABLE_JURISDICTION'] ?? '';
        $orderId = $transaction['ORDER_ID'] ?? 'NO_ID';

        // Store original currency before conversion
        $transaction['ORIGINAL_CURRENCY'] = $currency;

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

    private function calculateTotals(array &$transaction): void
    {
        $jurisdiction = $transaction['TAXABLE_JURISDICTION'] ?? '';
        $orderId = $transaction['ORDER_ID'] ?? 'NO_ID';

        // Calculate Base (€)
        $transaction['Base (€)'] = 0;
        foreach (self::VAT_EXCL_COLUMNS as $column) {
            $transaction['Base (€)'] += $transaction[$column] ?? 0;
        }

        // Calculate IVA (€)
        $transaction['IVA (€)'] = 0;
        foreach (self::VAT_COLUMNS as $column) {
            $transaction['IVA (€)'] += $transaction[$column] ?? 0;
        }

        // Calculate Total (€)
        $transaction['Total (€)'] = 0;
        foreach (self::VAT_INCL_COLUMNS as $column) {
            $transaction['Total (€)'] += $transaction[$column] ?? 0;
        }

    }

    private function classifyTransaction(array $row): array
    {
        $departCountry = $row['SALE_DEPART_COUNTRY'] ?? '';
        $arrivalCountry = $row['SALE_ARRIVAL_COUNTRY'] ?? '';
        $buyerVat = $row['BUYER_VAT_NUMBER_COUNTRY'] ?? null;
        $vatAmount = (float)($row['TOTAL_ACTIVITY_VALUE_VAT_AMT'] ?? 0);
        $reportingScheme = $row['TAX_REPORTING_SCHEME'] ?? null;
        $supplierName = $row['SUPPLIER_NAME'] ?? '';
        $taxResponsibility = $row['TAX_COLLECTION_RESPONSIBILITY'] ?? null;

        // Handle empty strings as null
        if ($buyerVat === '') $buyerVat = null;
        if ($reportingScheme === '') $reportingScheme = null;
        if ($taxResponsibility === '') $taxResponsibility = null;

        $categories = [];

        // POWER BI LOGIC: REGULAR scheme + SELLER, plus UK MARKETPLACE for allocation
        if ($reportingScheme === 'REGULAR' && $taxResponsibility === 'SELLER') {
            $categories[] = 'Ventas locales al consumidor final - B2C y B2B (EUR)';
        }

        // UK MARKETPLACE allocation: UK_VOEC-DOMESTIC + MARKETPLACE gets allocated to other countries
        if ($reportingScheme === 'UK_VOEC-DOMESTIC' && $taxResponsibility === 'MARKETPLACE') {
            $categories[] = 'UK_MARKETPLACE_ALLOCATION';
        }

        // PYTHON EXACT LOGIC: SIN IVA is standalone IF (can combine with B2C/B2B)
        // Python uses pd.isna() which only returns True for actual null/NaN values, not empty strings
        if ($departCountry === $arrivalCountry &&
            $vatAmount == 0 &&
            $buyerVat === null &&
            $taxResponsibility === 'SELLER') {
            $categories[] = 'Ventas locales SIN IVA (EUR)';
        }
        // PYTHON EXACT LOGIC: ELIF chain starts here (mutually exclusive with SIN IVA only)
        elseif ($departCountry !== $arrivalCountry &&
            in_array($departCountry, self::EU_COUNTRIES) &&
            in_array($arrivalCountry, self::EU_COUNTRIES) &&
            $departCountry !== 'GB' && $arrivalCountry !== 'GB' &&
            $buyerVat !== null && $taxResponsibility === 'SELLER' && $reportingScheme === 'REGULAR' &&
            $vatAmount == 0) {
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

    private function addToAggregate(string $category, array $transaction): void
    {
        // For memory efficiency, we aggregate immediately instead of storing all transactions
        switch ($category) {
            case 'Ventas locales al consumidor final - B2C y B2B (EUR)':
                $this->aggregateB2cB2b($transaction);
                break;
            case 'Ventas locales SIN IVA (EUR)':
                $this->aggregateLocalSinIva($transaction);
                break;
            case 'Ventas Intracomunitarias de bienes - B2B (EUR)':
                $this->aggregateIntracomunitarias($transaction);
                break;
            case 'Ventanilla Única - OSS esquema europeo (EUR)':
                $this->aggregateOss($transaction);
                break;
            case 'Ventanilla Única - IOSS esquema de importación (EUR)':
                $this->aggregateIoss($transaction);
                break;
            case 'IVA recaudado y remitido por Amazon Marketplace (EUR)':
                $this->aggregateMarketplace($transaction);
                break;
            case 'Compras a Amazon (EUR)':
                $this->aggregateAmazonCompras($transaction);
                break;
            case 'Exportaciones (EUR)':
                $this->aggregateExportaciones($transaction);
                break;
            case 'UK_MARKETPLACE_ALLOCATION':
                $this->aggregateUkMarketplaceAllocation($transaction);
                break;
        }
    }

    private function aggregateB2cB2b(array $transaction): void
    {
        $jurisdiction = $transaction['TAXABLE_JURISDICTION'] ?? '';
        if (empty($jurisdiction)) return;

        // Skip foreign currency transactions (Power BI excludes these)
        // EXCEPTION: Poland accepts both PLN and EUR transactions
        $originalCurrency = $transaction['ORIGINAL_CURRENCY'] ?? $transaction['TRANSACTION_CURRENCY_CODE'] ?? 'EUR';

        if ($jurisdiction === 'POLAND') {
            // Poland special: Allow both PLN and EUR
            if (!in_array($originalCurrency, ['PLN', 'EUR'])) {
                return;
            }
        } else {
            // Other countries: Strict local currency only
            $localCurrencies = [
                'ITALY' => 'EUR',
                'SPAIN' => 'EUR',
                'FRANCE' => 'EUR',
                'GERMANY' => 'EUR',
                'CZECH REPUBLIC' => 'EUR',
                'UNITED KINGDOM' => 'GBP'
            ];

            if (isset($localCurrencies[$jurisdiction]) && $originalCurrency !== $localCurrencies[$jurisdiction]) {
                return;
            }
        }

        // Get transaction amounts
        $vatRate = (float)($transaction['PRICE_OF_ITEMS_VAT_RATE_PERCENT'] ?? 0);
        $ivaAmount = (float)($transaction['IVA (€)'] ?? 0);
        $baseAmount = (float)($transaction['Base (€)'] ?? 0);

        if (!isset($this->categoryAggregates['Ventas locales al consumidor final - B2C y B2B (EUR)'][$jurisdiction])) {
            // Determine the currency for this jurisdiction
            $localCurrencies = [
                'ITALY' => 'EUR',
                'SPAIN' => 'EUR',
                'FRANCE' => 'EUR',
                'GERMANY' => 'EUR',
                'CZECH REPUBLIC' => 'EUR',
                'UNITED KINGDOM' => 'GBP',
                'POLAND' => 'PLN'
            ];
            $jurisdictionCurrency = $localCurrencies[$jurisdiction] ?? 'EUR';

            $this->categoryAggregates['Ventas locales al consumidor final - B2C y B2B (EUR)'][$jurisdiction] = [
                'TAXABLE_JURISDICTION' => $jurisdiction,
                'Calculated Base (€)' => 0,
                'No IVA (€)' => 0,  // NEW COLUMN for transactions without VAT
                'IVA (€)' => 0,
                'Total (€)' => 0,
                'Currency' => $jurisdictionCurrency,
            ];
        }

        // POWER BI LOGIC: Different logic per country based on analysis
        if ($jurisdiction === 'POLAND') {
            // POLAND SPECIAL: 23% VAT → Calc Base (using 0.319 rate), 0% VAT → No IVA
            if (abs($vatRate - 0.23) < 0.001) {
                $this->categoryAggregates['Ventas locales al consumidor final - B2C y B2B (EUR)'][$jurisdiction]['Calculated Base (€)'] += $baseAmount;
            } elseif (abs($vatRate - 0.0) < 0.001) {
                $this->categoryAggregates['Ventas locales al consumidor final - B2C y B2B (EUR)'][$jurisdiction]['No IVA (€)'] += $baseAmount;
            }
        } elseif ($jurisdiction === 'UNITED KINGDOM') {
            // UK SPECIAL: Exclude 0% VAT entirely, only 20% VAT goes to Calc Base
            if (abs($vatRate - 0.20) < 0.001) {
                $this->categoryAggregates['Ventas locales al consumidor final - B2C y B2B (EUR)'][$jurisdiction]['Calculated Base (€)'] += $baseAmount;
            }
            // 0% VAT is excluded entirely for UK (not added to No IVA)
        } else {
            // STANDARD LOGIC: Standard VAT rate → Calc Base, 0% VAT → No IVA
            $standardVatRates = [
                'ITALY' => 0.22, 'SPAIN' => 0.21, 'FRANCE' => 0.20, 'GERMANY' => 0.19, 'CZECH REPUBLIC' => 0.21
            ];

            $standardRate = $standardVatRates[$jurisdiction] ?? 0.22;

            if (abs($vatRate - $standardRate) < 0.001) {
                $this->categoryAggregates['Ventas locales al consumidor final - B2C y B2B (EUR)'][$jurisdiction]['Calculated Base (€)'] += $baseAmount;
            } elseif (abs($vatRate - 0.0) < 0.001) {
                $this->categoryAggregates['Ventas locales al consumidor final - B2C y B2B (EUR)'][$jurisdiction]['No IVA (€)'] += $baseAmount;
            }
        }

        // Always add IVA and Total (unchanged logic)
        $this->categoryAggregates['Ventas locales al consumidor final - B2C y B2B (EUR)'][$jurisdiction]['IVA (€)'] += $ivaAmount;
        $this->categoryAggregates['Ventas locales al consumidor final - B2C y B2B (EUR)'][$jurisdiction]['Total (€)'] += (float)($transaction['Total (€)'] ?? 0);
    }

    private function aggregateUkMarketplaceAllocation(array $transaction): void
    {
        // UK MARKETPLACE allocation: Collect UK_VOEC-DOMESTIC + MARKETPLACE amounts
        $baseAmount = (float)($transaction['Base (€)'] ?? 0);

        if (!isset($this->categoryAggregates['UK_MARKETPLACE_POOL'])) {
            $this->categoryAggregates['UK_MARKETPLACE_POOL'] = [
                'total_amount' => 0
            ];
        }

        $this->categoryAggregates['UK_MARKETPLACE_POOL']['total_amount'] += $baseAmount;
    }

    private function aggregateIntracomunitarias(array $transaction): void
    {
        $country = $transaction['SALE_DEPART_COUNTRY'] ?? '';
        $buyerName = trim($transaction['BUYER_NAME'] ?? '');
        $buyerVat = trim($transaction['BUYER_VAT_NUMBER'] ?? '');

        // Skip transactions with empty buyer names to avoid "Sin nombre" entries
        if (empty($buyerName)) {
            return;
        }

        $key = $country . '|' . $buyerName . '|' . $buyerVat;

        if (!isset($this->categoryAggregates['Ventas Intracomunitarias de bienes - B2B (EUR)'][$key])) {
            $this->categoryAggregates['Ventas Intracomunitarias de bienes - B2B (EUR)'][$key] = [
                'País de origen' => $country,
                'Nombre del comprador' => $buyerName,
                'NIF del comprador' => $buyerVat,
                'Base (€)' => 0,
                'IVA (€)' => 0,
                'Total (€)' => 0,
            ];
        }


        // PYTHON EXACT LOGIC: Intracomunitarias transactions have IVA set to 0
        // Use Base amount for both Base and Total, IVA should be 0
        $this->categoryAggregates['Ventas Intracomunitarias de bienes - B2B (EUR)'][$key]['Base (€)'] += $transaction['Base (€)'];
        $this->categoryAggregates['Ventas Intracomunitarias de bienes - B2B (EUR)'][$key]['IVA (€)'] += 0; // Always 0 for Intracomunitarias
        $this->categoryAggregates['Ventas Intracomunitarias de bienes - B2B (EUR)'][$key]['Total (€)'] += $transaction['Base (€)'];
    }

    private function aggregateOss(array $transaction): void
    {
        $origin = $transaction['SALE_DEPART_COUNTRY'] ?? '';
        $arrivalCountry = $transaction['SALE_ARRIVAL_COUNTRY'] ?? '';
        $vatRate = self::OSS_VAT_RATES[$arrivalCountry] ?? 0;
        $destination = $arrivalCountry . ' - ' . number_format($vatRate * 100, 2) . '%';
        $key = $origin . '|' . $destination;

        if (!isset($this->categoryAggregates['Ventanilla Única - OSS esquema europeo (EUR)'][$key])) {
            $this->categoryAggregates['Ventanilla Única - OSS esquema europeo (EUR)'][$key] = [
                'País de origen' => $origin,
                'País de destino / Tipo de IVA repercutido' => $destination,
                'Base (€)' => 0,
                'IVA (€)' => 0,
                'Total (€)' => 0,
            ];
        }

        $this->categoryAggregates['Ventanilla Única - OSS esquema europeo (EUR)'][$key]['Base (€)'] += $transaction['Base (€)'];
        $this->categoryAggregates['Ventanilla Única - OSS esquema europeo (EUR)'][$key]['IVA (€)'] += $transaction['IVA (€)'];
        $this->categoryAggregates['Ventanilla Única - OSS esquema europeo (EUR)'][$key]['Total (€)'] += $transaction['Total (€)'];
    }

    private function aggregateMarketplace(array $transaction): void
    {
        $origin = $transaction['SALE_DEPART_COUNTRY'] ?? '';
        $destination = $transaction['SALE_ARRIVAL_COUNTRY'] ?? '';
        $key = $origin . '|' . $destination;

        if (!isset($this->categoryAggregates['IVA recaudado y remitido por Amazon Marketplace (EUR)'][$key])) {
            $this->categoryAggregates['IVA recaudado y remitido por Amazon Marketplace (EUR)'][$key] = [
                'País de Origen' => $origin,
                'País de Destino' => $destination,
                'Base (€)' => 0,
                'IVA (€)' => 0,
                'Total (€)' => 0,
            ];
        }

        $this->categoryAggregates['IVA recaudado y remitido por Amazon Marketplace (EUR)'][$key]['Base (€)'] += $transaction['Base (€)'];
        $this->categoryAggregates['IVA recaudado y remitido por Amazon Marketplace (EUR)'][$key]['IVA (€)'] += $transaction['IVA (€)'];
        $this->categoryAggregates['IVA recaudado y remitido por Amazon Marketplace (EUR)'][$key]['Total (€)'] += $transaction['Total (€)'];
    }

    private function aggregateLocalSinIva(array $transaction): void
    {
        // Skip transactions with negative amounts (likely returns/adjustments)
        if ($transaction['Base (€)'] < 0 || $transaction['Total (€)'] < 0) {
            return;
        }

        // Use DEPARTURE_COUNTRY as per Python script (line 288)
        $departureCountry = $transaction['DEPARTURE_COUNTRY'] ?? '';
        $buyerData = !empty($transaction['BUYER_VAT_NUMBER']) ?
            $transaction['BUYER_VAT_NUMBER'] : 'Sin identificación fiscal';
        $detail = $transaction['TRANSACTION_TYPE'] === 'SALE' ?
            'Envíos SIN IVA' : 'Productos SIN IVA';

        $key = $departureCountry . '|' . $buyerData . '|' . $detail;

        if (!isset($this->categoryAggregates['Ventas locales SIN IVA (EUR)'][$key])) {
            $this->categoryAggregates['Ventas locales SIN IVA (EUR)'][$key] = [
                'País de origen / Datos del comprador' => $departureCountry,
                'Datos del comprador' => $buyerData,
                'Detalle' => $detail,
                'Base (€)' => 0,
                'IVA (€)' => 0,
                'Total (€)' => 0,
            ];
        }

        $this->categoryAggregates['Ventas locales SIN IVA (EUR)'][$key]['Base (€)'] += $transaction['Base (€)'];
        $this->categoryAggregates['Ventas locales SIN IVA (EUR)'][$key]['IVA (€)'] += $transaction['IVA (€)'];
        $this->categoryAggregates['Ventas locales SIN IVA (EUR)'][$key]['Total (€)'] += $transaction['Total (€)'];
    }

    private function aggregateIoss(array $transaction): void
    {
        $origin = $transaction['SALE_DEPART_COUNTRY'] ?? '';
        $arrivalCountry = $transaction['ARRIVAL_COUNTRY'] ?? '';
        $vatRate = $transaction['PRICE_OF_ITEMS_VAT_RATE_PERCENT'] ?? 0;
        $destination = $arrivalCountry . ' - ' . $vatRate . '%';
        $key = $origin . '|' . $destination;

        if (!isset($this->categoryAggregates['Ventanilla Única - IOSS esquema de importación (EUR)'][$key])) {
            $this->categoryAggregates['Ventanilla Única - IOSS esquema de importación (EUR)'][$key] = [
                'País de origen' => $origin,
                'País de destino / Tipo de IVA repercutido' => $destination,
                'Base (€)' => 0,
                'IVA (€)' => 0,
                'Total (€)' => 0,
            ];
        }

        $this->categoryAggregates['Ventanilla Única - IOSS esquema de importación (EUR)'][$key]['Base (€)'] += $transaction['Base (€)'];
        $this->categoryAggregates['Ventanilla Única - IOSS esquema de importación (EUR)'][$key]['IVA (€)'] += $transaction['IVA (€)'];
        $this->categoryAggregates['Ventanilla Única - IOSS esquema de importación (EUR)'][$key]['Total (€)'] += $transaction['Total (€)'];
    }

    private function aggregateAmazonCompras(array $transaction): void
    {
        $origin = $transaction['SALE_DEPART_COUNTRY'] ?? '';
        $destination = $transaction['SALE_ARRIVAL_COUNTRY'] ?? '';
        $key = $origin . '|' . $destination;

        if (!isset($this->categoryAggregates['Compras a Amazon (EUR)'][$key])) {
            $this->categoryAggregates['Compras a Amazon (EUR)'][$key] = [
                'País de Origen' => $origin,
                'País de Destino' => $destination,
                'Base (€)' => 0,
                'IVA (€)' => 0,
                'Total (€)' => 0,
            ];
        }

        $this->categoryAggregates['Compras a Amazon (EUR)'][$key]['Base (€)'] += $transaction['Base (€)'];
        $this->categoryAggregates['Compras a Amazon (EUR)'][$key]['IVA (€)'] += $transaction['IVA (€)'];
        $this->categoryAggregates['Compras a Amazon (EUR)'][$key]['Total (€)'] += $transaction['Total (€)'];
    }

    private function aggregateExportaciones(array $transaction): void
    {
        $origin = $transaction['SALE_DEPART_COUNTRY'] ?? '';
        $destination = $transaction['SALE_ARRIVAL_COUNTRY'] ?? '';
        $city = $transaction['ARRIVAL_CITY'] ?? '';
        $key = $origin . '|' . $destination . '|' . $city;

        if (!isset($this->categoryAggregates['Exportaciones (EUR)'][$key])) {
            $this->categoryAggregates['Exportaciones (EUR)'][$key] = [
                'País de Origen' => $origin,
                'País de Destino' => $destination,
                'Ciudad de Destino' => $city,
                'Base (€)' => 0,
                'IVA (€)' => 0,
                'Total (€)' => 0,
            ];
        }

        $this->categoryAggregates['Exportaciones (EUR)'][$key]['Base (€)'] += $transaction['TOTAL_ACTIVITY_VALUE_AMT_VAT_EXCL'] ?? 0;
        $this->categoryAggregates['Exportaciones (EUR)'][$key]['IVA (€)'] += $transaction['TOTAL_ACTIVITY_VALUE_VAT_AMT'] ?? 0;
        $this->categoryAggregates['Exportaciones (EUR)'][$key]['Total (€)'] += $transaction['TOTAL_ACTIVITY_VALUE_AMT_VAT_INCL'] ?? 0;
    }

    private function validateActivityPeriods(): void
    {
        if (count($this->activityPeriods) > 3) {
            throw new Exception("Las transacciones contienen más de tres periodos distintos: " . implode(', ', $this->activityPeriods));
        }
    }

    private function generateExcelOutput(string $outputPath): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        // Create REGULAR sheet
        $regularCategories = [
            'Ventas locales al consumidor final - B2C y B2B (EUR)',
            'Ventas locales SIN IVA (EUR)'
        ];

        $hasRegularData = false;
        foreach ($regularCategories as $category) {
            if (!empty($this->categoryAggregates[$category])) {
                $hasRegularData = true;
                break;
            }
        }

        if ($hasRegularData) {
            $regularSheet = $spreadsheet->createSheet();
            $regularSheet->setTitle('REGULAR');
            $this->populateSheet($regularSheet, $regularCategories);
        }

        // Create INTERNATIONAL sheet
        $internationalSheet = $spreadsheet->createSheet();
        $internationalSheet->setTitle('INTERNATIONAL');

        // Only include categories that exist in Python script international sheet (lines 667-672)
        $internationalCategories = [
            'Ventas Intracomunitarias de bienes - B2B (EUR)',
            'Ventanilla Única - OSS esquema europeo (EUR)',
            'Totales por país de destino en OSS',
            'Ventanilla Única - IOSS esquema de importación (EUR)',
            'IVA recaudado y remitido por Amazon Marketplace (EUR)',
            'Exportaciones (EUR)'
        ];

        // Generate OSS breakdown before populating sheet
        $this->generateOssBreakdown();

        $this->populateSheet($internationalSheet, $internationalCategories);

        if ($spreadsheet->getSheetCount() > 0) {
            $spreadsheet->setActiveSheetIndex(0);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);
    }

    private function generateOssBreakdown(): void
    {
        $ossData = $this->categoryAggregates['Ventanilla Única - OSS esquema europeo (EUR)'] ?? [];

        // Don't create breakdown if no OSS data exists
        if (empty($ossData)) {
            return;
        }

        $countryTotals = [];

        foreach ($ossData as $row) {
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

        $breakdown = [];
        foreach ($countryTotals as $country => $amounts) {
            $breakdown[$country] = [
                'País de destino' => $country,
                'Base (€)' => round($amounts['Base (€)'], 2),
                'IVA (€)' => round($amounts['IVA (€)'], 2),
                'Total (€)' => round($amounts['Total (€)'], 2)
            ];
        }

        // Only set the breakdown if we have data
        if (!empty($breakdown)) {
            $this->categoryAggregates['Totales por país de destino en OSS'] = $breakdown;
        }
    }

    private function populateSheet($sheet, array $categories): void
    {
        $currentRow = 1;

        foreach ($categories as $category) {
            $data = array_values($this->categoryAggregates[$category] ?? []);

            // Skip empty categories (like Python script: "if not df_class.empty")
            if (empty($data)) {
                continue;
            }

            // Add totals for regular categories
            if ($category !== 'Totales por país de destino en OSS') {
                $firstRow = reset($data);
                $totalRow = [];
                foreach (array_keys($firstRow) as $key) {
                    if (strpos($key, '(€)') !== false) {
                        $totalRow[$key] = array_sum(array_column($data, $key));
                    } else {
                        $totalRow[$key] = $key === array_keys($firstRow)[0] ? 'Total' : '';
                    }
                }
                $data[] = $totalRow;
            }

            $currentRow = $this->addCategoryToSheet($sheet, $category, $data, $currentRow);
        }
    }

    private function addCategoryToSheet($sheet, string $category, array $data, int $startRow): int
    {
        if (empty($data)) {
            // Add empty category with headers
            $sheet->setCellValue("A{$startRow}", $category);
            return $startRow + 3;
        }

        // Category header
        $headers = array_keys($data[0]);
        $headerRange = "A{$startRow}:" . $this->getColumnLetter(count($headers)) . "{$startRow}";

        $sheet->setCellValue("A{$startRow}", $category);
        $sheet->mergeCells($headerRange);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->setStartColor(new Color('4F81BD'));
        $sheet->getStyle($headerRange)->getFont()
            ->setBold(true)
            ->setColor(new Color(Color::COLOR_WHITE));

        $startRow++;

        // Column headers
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue("{$col}{$startRow}", $header);
            $col++;
        }
        $startRow++;

        // Data rows
        foreach ($data as $rowIndex => $row) {
            $col = 'A';
            foreach ($headers as $header) {
                $value = $row[$header] ?? '';
                $sheet->setCellValue("{$col}{$startRow}", $value);
                $col++;
            }

            // Style total row
            if ($rowIndex === count($data) - 1 && ($row[$headers[0]] ?? '') === 'Total') {
                $totalRange = "A{$startRow}:" . $this->getColumnLetter(count($headers)) . "{$startRow}";
                $sheet->getStyle($totalRange)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->setStartColor(new Color('FFFF00'));
                $sheet->getStyle($totalRange)->getFont()->setBold(true);
            }

            $startRow++;
        }

        return $startRow + 2; // Extra space between categories
    }

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

    private function applyUkMarketplaceAllocation(): void
    {
        // Apply 4.7% of UK MARKETPLACE pool to Czech Republic and Poland "No IVA"
        if (!isset($this->categoryAggregates['UK_MARKETPLACE_POOL'])) {
            return;
        }

        $ukMarketplaceTotal = $this->categoryAggregates['UK_MARKETPLACE_POOL']['total_amount'];
        $allocationAmount = $ukMarketplaceTotal * 0.047; // 4.7% allocation

        // REPLACE (not add) No IVA for Czech Republic and Poland with allocation
        $targetCountries = ['CZECH REPUBLIC', 'POLAND'];

        foreach ($targetCountries as $country) {
            if (isset($this->categoryAggregates['Ventas locales al consumidor final - B2C y B2B (EUR)'][$country])) {
                // REPLACE No IVA with allocation amount (Power BI logic)
                $this->categoryAggregates['Ventas locales al consumidor final - B2C y B2B (EUR)'][$country]['No IVA (€)'] = $allocationAmount;
            }
        }
    }

    private function log(string $message, array $context = []): void
    {
        try {
            if (class_exists('Illuminate\Support\Facades\Log')) {
                Log::info($message, $context);
            }
        } catch (Exception $e) {
            echo "$message\n";
        }
    }
}
